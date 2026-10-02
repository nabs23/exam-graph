<?php

namespace App\Services;

use App\Ai\Agents\OfficialCurriculumExtractor;
use App\CurriculumExtractionStatus;
use App\FileEmbeddingStatus;
use App\FileUploadStatus;
use App\Jobs\ExtractOfficialCurriculum;
use App\Models\CurriculumExtraction;
use App\Models\FilePageEmbedding;
use App\Models\ProgramFile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Responses\StructuredAgentResponse;
use LogicException;
use RuntimeException;
use Throwable;

class OfficialCurriculumExtractionService
{
    private const PROMPT_VERSION = 'official-curriculum-v1';

    public function isAvailable(): bool
    {
        return $this->unavailableReason() === null;
    }

    public function unavailableReason(): ?string
    {
        try {
            $this->assertReady();

            return null;
        } catch (Throwable $exception) {
            return match ($exception->getMessage()) {
                'Official curriculum extraction is disabled.' => 'Official curriculum extraction is disabled in the application configuration.',
                'Official curriculum extraction requires PostgreSQL and completed AI migrations.' => 'PostgreSQL and the curriculum extraction migrations are required.',
                'The pgvector extension is not enabled.' => 'The pgvector extension is not enabled for this database.',
                'Configure an official curriculum extraction provider, model, and API key.' => 'Configure the curriculum extraction provider, model, and API key.',
                default => 'Curriculum extraction requirements are unavailable. Check the database and provider configuration.',
            };
        }
    }

    public function queue(ProgramFile $file, User $requester): CurriculumExtraction
    {
        $this->assertReady();

        $pages = $this->sourcePages($file);
        $sourceHash = (string) $file->content_hash;

        $extraction = DB::transaction(function () use ($file, $requester, $sourceHash): CurriculumExtraction {
            $lockedFile = ProgramFile::query()->lockForUpdate()->findOrFail($file->id);

            if ($lockedFile->content_hash !== $sourceHash || $lockedFile->embedding_status !== FileEmbeddingStatus::Complete) {
                throw ValidationException::withMessages(['file' => 'The source file changed. Recheck its completed text embeddings before extracting curriculum.']);
            }

            $inProgress = $lockedFile->curriculumExtractions()
                ->where('source_hash', $sourceHash)
                ->whereIn('status', [CurriculumExtractionStatus::Queued, CurriculumExtractionStatus::Processing, CurriculumExtractionStatus::Reviewing])
                ->exists();

            if ($inProgress) {
                throw ValidationException::withMessages(['file' => 'An extraction for this file version is already in progress or awaiting review.']);
            }

            $extraction = $lockedFile->curriculumExtractions()->create([
                'requested_by' => $requester->id,
                'status' => CurriculumExtractionStatus::Queued,
                'source_hash' => $sourceHash,
                'provider' => (string) config('ai.official_curriculum.provider'),
                'model' => (string) config('ai.official_curriculum.model'),
                'prompt_version' => self::PROMPT_VERSION,
            ]);

            ExtractOfficialCurriculum::dispatch($extraction->id)->afterCommit();

            return $extraction;
        });

        return $extraction;
    }

    public function extract(int $extractionId, bool $isRetry = false): void
    {
        $claimableStatuses = $isRetry
            ? [CurriculumExtractionStatus::Queued->value, CurriculumExtractionStatus::Processing->value]
            : [CurriculumExtractionStatus::Queued->value];

        $claimed = CurriculumExtraction::query()
            ->whereKey($extractionId)
            ->whereIn('status', $claimableStatuses)
            ->update(['status' => CurriculumExtractionStatus::Processing->value, 'error_code' => null, 'updated_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $extraction = CurriculumExtraction::query()->with('programFile')->findOrFail($extractionId);

        try {
            $this->assertReady();
            $file = $extraction->programFile;

            if ($file->content_hash !== $extraction->source_hash || $file->embedding_status !== FileEmbeddingStatus::Complete) {
                $this->fail($extraction, 'source_changed');

                return;
            }

            $pages = $this->sourcePages($file);
            $input = json_encode(['source_pages' => $pages->map(fn (FilePageEmbedding $page): array => [
                'page_number' => $page->page_number,
                'text' => $page->content,
            ])->all()], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

            try {
                $response = (new OfficialCurriculumExtractor)->prompt(
                    $input,
                    provider: $extraction->provider,
                    model: $extraction->model,
                    timeout: min(300, max(1, (int) config('ai.official_curriculum.timeout', 60))),
                );
            } catch (Throwable) {
                throw new RuntimeException('The curriculum extraction provider request failed.');
            }

            if (! $response instanceof StructuredAgentResponse) {
                $this->fail($extraction, 'invalid_proposal');

                return;
            }

            try {
                $proposal = $this->validateProposal($response->toArray(), $pages->pluck('page_number')->map(fn ($number): int => (int) $number)->all());
            } catch (RuntimeException) {
                $this->fail($extraction, 'invalid_proposal');

                return;
            }

            if ($proposal['subjects'] === []) {
                $this->fail($extraction, 'no_curriculum_found');

                return;
            }

            $currentFile = ProgramFile::query()->findOrFail($file->id);
            if ($currentFile->content_hash !== $extraction->source_hash || $currentFile->embedding_status !== FileEmbeddingStatus::Complete) {
                $this->fail($extraction, 'source_changed');

                return;
            }

            $extraction->forceFill([
                'proposal' => $proposal,
                'status' => CurriculumExtractionStatus::Reviewing,
                'error_code' => null,
            ])->save();
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException && $exception->getMessage() === 'The curriculum extraction provider request failed.') {
                throw $exception;
            }

            $this->fail($extraction, 'extraction_failed');
        }
    }

    /** @param array<int, array<string, mixed>> $selections */
    public function publish(CurriculumExtraction $extraction, array $selections, User $reviewer): void
    {
        $sourceChanged = false;

        DB::transaction(function () use ($extraction, $selections, $reviewer, &$sourceChanged): void {
            $locked = CurriculumExtraction::query()->with('programFile')->lockForUpdate()->findOrFail($extraction->id);

            if ($locked->status !== CurriculumExtractionStatus::Reviewing || ! is_array($locked->proposal)) {
                throw ValidationException::withMessages(['extraction' => 'Only a proposal awaiting review can be published.']);
            }

            $sourceFile = ProgramFile::query()->lockForUpdate()->findOrFail($locked->program_file_id);
            if ($sourceFile->content_hash !== $locked->source_hash
                || $sourceFile->embedding_status !== FileEmbeddingStatus::Complete) {
                $locked->forceFill(['status' => CurriculumExtractionStatus::Failed, 'error_code' => 'source_changed'])->save();
                $sourceChanged = true;

                return;
            }

            $proposedSubjects = $locked->proposal['subjects'] ?? [];
            if (count($selections) !== count($proposedSubjects)) {
                throw ValidationException::withMessages(['subjects' => 'The review form does not match the saved proposal.']);
            }

            $selected = [];
            foreach ($proposedSubjects as $subjectIndex => $subjectProposal) {
                $selection = $selections[$subjectIndex] ?? [];
                if (! filter_var($selection['include'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    continue;
                }

                $name = trim((string) ($selection['name'] ?? ''));
                $code = trim((string) ($selection['code'] ?? ''));
                if ($name === '') {
                    throw ValidationException::withMessages(["subjects.$subjectIndex.name" => 'Selected subjects need a name.']);
                }

                $topics = $selection['topics'] ?? [];
                if (count($topics) !== count($subjectProposal['topics'] ?? [])) {
                    throw ValidationException::withMessages(["subjects.$subjectIndex.topics" => 'The review form does not match the saved topic proposal.']);
                }

                $selectedTopics = [];
                $selectedTopicPositions = [];
                foreach ($subjectProposal['topics'] ?? [] as $topicIndex => $topicProposal) {
                    $topicSelection = $topics[$topicIndex] ?? [];
                    if (! filter_var($topicSelection['include'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                        continue;
                    }

                    $title = trim((string) ($topicSelection['title'] ?? ''));
                    $topicCode = trim((string) ($topicSelection['code'] ?? ''));
                    if ($title === '') {
                        throw ValidationException::withMessages(["subjects.$subjectIndex.topics.$topicIndex.title" => 'Selected topics need a title.']);
                    }

                    $parentIndex = $topicProposal['parent_index'];
                    if ($parentIndex !== null && ! isset($selectedTopicPositions[$parentIndex])) {
                        throw ValidationException::withMessages(["subjects.$subjectIndex.topics.$topicIndex.include" => 'A topic parent must also be selected.']);
                    }

                    $description = $this->nullableString($topicSelection['description'] ?? null);
                    $selectedTopics[] = [
                        'code' => $topicCode === '' ? null : $topicCode,
                        'title' => $title,
                        'description' => $description,
                        'description_origin' => $this->reviewedDescriptionOrigin($description, $topicProposal),
                        'source_page' => $topicProposal['source_page'],
                        'parent_index' => $parentIndex === null ? null : $selectedTopicPositions[$parentIndex],
                    ];
                    $selectedTopicPositions[$topicIndex] = count($selectedTopics) - 1;
                }

                $description = $this->nullableString($selection['description'] ?? null);
                $selected[] = [
                    'name' => $name,
                    'code' => $code === '' ? null : $code,
                    'description' => $description,
                    'description_origin' => $this->reviewedDescriptionOrigin($description, $subjectProposal),
                    'source_page' => $subjectProposal['source_page'],
                    'topics' => $selectedTopics,
                ];
            }

            if ($selected === []) {
                throw ValidationException::withMessages(['subjects' => 'Select at least one subject to publish.']);
            }

            $this->assertUniqueCodes($selected, (int) $sourceFile->program_id);

            foreach ($selected as $subjectOrder => $subjectData) {
                $subject = Subject::query()->create([
                    'program_id' => $sourceFile->program_id,
                    'name' => $subjectData['name'],
                    'code' => $subjectData['code'],
                    'description' => $subjectData['description'],
                    'sort_order' => ((int) Subject::query()->where('program_id', $sourceFile->program_id)->max('sort_order')) + 1,
                ]);

                $createdTopics = [];
                foreach ($subjectData['topics'] as $topicOrder => $topicData) {
                    $parent = $topicData['parent_index'] === null ? null : ($createdTopics[$topicData['parent_index']] ?? null);
                    $topic = $subject->syllabusTopics()->create([
                        'parent_id' => $parent?->id,
                        'code' => $topicData['code'],
                        'title' => $topicData['title'],
                        'description' => $topicData['description'],
                        'sort_order' => $topicOrder,
                    ]);
                    $createdTopics[$topicOrder] = $topic;
                }
            }

            $locked->forceFill([
                'status' => CurriculumExtractionStatus::Published,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'reviewed_proposal' => ['subjects' => $selected],
                'error_code' => null,
            ])->save();
        });

        if ($sourceChanged) {
            throw ValidationException::withMessages(['extraction' => 'The source file changed after extraction. Create a new extraction before publishing.']);
        }
    }

    public function reject(CurriculumExtraction $extraction, User $reviewer): void
    {
        DB::transaction(function () use ($extraction, $reviewer): void {
            $locked = CurriculumExtraction::query()->lockForUpdate()->findOrFail($extraction->id);

            if ($locked->status !== CurriculumExtractionStatus::Reviewing) {
                throw ValidationException::withMessages(['extraction' => 'Only a proposal awaiting review can be rejected.']);
            }

            $locked->forceFill([
                'status' => CurriculumExtractionStatus::Rejected,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();
        });
    }

    /** @return Collection<int, FilePageEmbedding> */
    public function sourcePages(ProgramFile $file): Collection
    {
        if ($file->upload_status !== FileUploadStatus::Uploaded || $file->mime_type !== 'application/pdf'
            || $file->embedding_status !== FileEmbeddingStatus::Complete || blank($file->content_hash)) {
            throw ValidationException::withMessages(['file' => 'Only uploaded PDFs with completed text embeddings can be used.']);
        }

        $pages = $file->pageEmbeddings()
            ->where('source_hash', $file->content_hash)
            ->whereNotNull('content')
            ->orderBy('page_number')
            ->get(['id', 'program_file_id', 'page_number', 'source_hash', 'content']);

        if ($pages->isEmpty() || $pages->contains(fn (FilePageEmbedding $page): bool => blank($page->content))) {
            throw ValidationException::withMessages(['file' => 'The source has no usable extracted page text.']);
        }

        $characterCount = $pages->sum(fn (FilePageEmbedding $page): int => mb_strlen($page->content));
        $maximumCharacters = min(250000, max(1000, (int) config('ai.official_curriculum.max_characters', 120000)));
        if ($characterCount > $maximumCharacters) {
            throw ValidationException::withMessages(['file' => 'The extracted text exceeds the configured processing limit.']);
        }

        return $pages;
    }

    /** @param array<string, mixed> $raw
     * @param  array<int, int>  $sourcePageNumbers
     * @return array{subjects: array<int, array<string, mixed>>}
     */
    private function validateProposal(array $raw, array $sourcePageNumbers): array
    {
        if (! isset($raw['subjects']) || ! is_array($raw['subjects']) || count($raw['subjects']) > 100) {
            throw new RuntimeException('Invalid curriculum proposal.');
        }

        $subjects = [];
        foreach ($raw['subjects'] as $subject) {
            if (! is_array($subject) || ! $this->validText($subject['name'] ?? null, 255) || ! $this->validPage($subject['source_page'] ?? null, $sourcePageNumbers)) {
                throw new RuntimeException('Invalid curriculum proposal.');
            }

            if (! $this->validNullableText($subject['code'] ?? null, 50)
                || ! $this->validNullableText($subject['description'] ?? null, 2000)
                || ! $this->validDescriptionOrigin($subject['description_origin'] ?? null, $subject['description'] ?? null)) {
                throw new RuntimeException('Invalid curriculum proposal.');
            }

            $topics = $subject['topics'] ?? null;
            if (! is_array($topics) || count($topics) > 500) {
                throw new RuntimeException('Invalid curriculum proposal.');
            }

            $normalizedTopics = [];
            foreach ($topics as $index => $topic) {
                if (! is_array($topic)) {
                    throw new RuntimeException('Invalid curriculum proposal.');
                }

                $parentIndex = $topic['parent_index'] ?? null;
                if (! $this->validText($topic['title'] ?? null, 255)
                    || ! $this->validPage($topic['source_page'] ?? null, $sourcePageNumbers)
                    || ! $this->validNullableText($topic['code'] ?? null, 50)
                    || ! $this->validNullableText($topic['description'] ?? null, 2000)
                    || ! $this->validDescriptionOrigin($topic['description_origin'] ?? null, $topic['description'] ?? null)
                    || ($parentIndex !== null && (! is_int($parentIndex) || $parentIndex < 0 || $parentIndex >= $index))) {
                    throw new RuntimeException('Invalid curriculum proposal.');
                }

                $normalizedTopics[] = [
                    'code' => $this->nullableString($topic['code'] ?? null),
                    'title' => trim($topic['title']),
                    'description' => $this->nullableString($topic['description'] ?? null),
                    'description_origin' => $topic['description_origin'],
                    'source_page' => (int) $topic['source_page'],
                    'parent_index' => $parentIndex,
                ];
            }

            $subjects[] = [
                'code' => $this->nullableString($subject['code'] ?? null),
                'name' => trim($subject['name']),
                'description' => $this->nullableString($subject['description'] ?? null),
                'description_origin' => $subject['description_origin'],
                'source_page' => (int) $subject['source_page'],
                'topics' => $normalizedTopics,
            ];
        }

        return ['subjects' => $subjects];
    }

    /** @param array<int, array<string, mixed>> $selected */
    private function assertUniqueCodes(array $selected, int $programId): void
    {
        $subjectCodes = [];
        foreach ($selected as $subject) {
            $code = $subject['code'];
            if ($code !== null) {
                $normalizedCode = mb_strtolower($code);
                if (in_array($normalizedCode, $subjectCodes, true) || Subject::query()->where('program_id', $programId)->whereRaw('lower(code) = ?', [$normalizedCode])->exists()) {
                    throw ValidationException::withMessages(['subjects' => 'Subject codes must be unique within the program.']);
                }
                $subjectCodes[] = $normalizedCode;
            }

            $topicCodes = [];
            foreach ($subject['topics'] as $topic) {
                $topicCode = $topic['code'];
                if ($topicCode !== null) {
                    $normalizedTopicCode = mb_strtolower($topicCode);
                    if (in_array($normalizedTopicCode, $topicCodes, true)) {
                        throw ValidationException::withMessages(['subjects' => 'Topic codes must be unique within each subject.']);
                    }
                    $topicCodes[] = $normalizedTopicCode;
                }
            }
        }
    }

    private function assertReady(): void
    {
        if (! (bool) config('ai.official_curriculum.enabled', false)) {
            throw new LogicException('Official curriculum extraction is disabled.');
        }

        if (DB::connection()->getDriverName() !== 'pgsql' || ! Schema::hasTable('curriculum_extractions') || ! Schema::hasTable('file_page_embeddings')) {
            throw new RuntimeException('Official curriculum extraction requires PostgreSQL and completed AI migrations.');
        }

        if (! DB::table('pg_extension')->where('extname', 'vector')->exists()) {
            throw new RuntimeException('The pgvector extension is not enabled.');
        }

        $provider = (string) config('ai.official_curriculum.provider');
        if (blank($provider) || blank(config('ai.official_curriculum.model')) || blank(config("ai.providers.$provider.key"))) {
            throw new RuntimeException('Configure an official curriculum extraction provider, model, and API key.');
        }
    }

    /** @param array<int, int> $pages */
    private function validPage(mixed $page, array $pages): bool
    {
        return is_int($page) && in_array($page, $pages, true);
    }

    private function validText(mixed $value, int $maxLength): bool
    {
        return is_string($value) && trim($value) !== '' && mb_strlen($value) <= $maxLength;
    }

    private function validNullableText(mixed $value, int $maxLength): bool
    {
        return $value === null || (is_string($value) && mb_strlen($value) <= $maxLength);
    }

    private function validDescriptionOrigin(mixed $origin, mixed $description): bool
    {
        if ($origin === 'unavailable') {
            return $description === null;
        }

        return in_array($origin, ['source', 'ai_generated'], true)
            && $this->validText($description, 2000);
    }

    /** @param array<string, mixed> $proposal */
    private function reviewedDescriptionOrigin(?string $description, array $proposal): string
    {
        if ($description === null) {
            return 'unavailable';
        }

        if ($description !== $this->nullableString($proposal['description'] ?? null)) {
            return 'reviewer';
        }

        return in_array($proposal['description_origin'] ?? null, ['source', 'ai_generated', 'reviewer'], true)
            ? $proposal['description_origin']
            : 'source';
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function fail(CurriculumExtraction $extraction, string $errorCode): void
    {
        $extraction->forceFill([
            'status' => CurriculumExtractionStatus::Failed,
            'error_code' => $errorCode,
            'proposal' => null,
        ])->save();
    }
}
