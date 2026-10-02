<?php

namespace App\Services;

use App\Ai\Agents\OfficialCurriculumExtractor;
use App\CurriculumExtractionStatus;
use App\FileEmbeddingStatus;
use App\FileUploadStatus;
use App\Jobs\ExtractOfficialCurriculum;
use App\Models\CurriculumExtraction;
use App\Models\FilePageEmbedding;
use App\Models\Program;
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

    public function queue(Program $program, User $requester): CurriculumExtraction
    {
        $this->assertReady();

        return DB::transaction(function () use ($program, $requester): CurriculumExtraction {
            $lockedProgram = Program::query()->lockForUpdate()->findOrFail($program->id);
            $files = $this->sourceFiles($lockedProgram, lock: true);
            $this->sourcePages($files);
            $snapshot = $this->sourceSnapshot($files);
            $sourceHash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
            $provider = (string) config('ai.official_curriculum.provider');
            $model = (string) config('ai.official_curriculum.model');

            $inProgress = $lockedProgram->curriculumExtractions()
                ->where('source_hash', $sourceHash)
                ->where('provider', $provider)
                ->where('model', $model)
                ->where('prompt_version', self::PROMPT_VERSION)
                ->whereIn('status', [CurriculumExtractionStatus::Queued, CurriculumExtractionStatus::Processing, CurriculumExtractionStatus::Reviewing])
                ->exists();

            if ($inProgress) {
                throw ValidationException::withMessages(['program' => 'An extraction with these source files, provider, model, and prompt version is already in progress or awaiting review.']);
            }

            $extraction = $lockedProgram->curriculumExtractions()->create([
                'requested_by' => $requester->id,
                'status' => CurriculumExtractionStatus::Queued,
                'source_hash' => $sourceHash,
                'source_files' => $snapshot,
                'provider' => $provider,
                'model' => $model,
                'prompt_version' => self::PROMPT_VERSION,
            ]);

            ExtractOfficialCurriculum::dispatch($extraction->id)->afterCommit();

            return $extraction;
        });
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

        $extraction = CurriculumExtraction::query()->with('program')->findOrFail($extractionId);

        try {
            $this->assertReady();
            if ($extraction->program === null || ! $this->snapshotIsCurrent($extraction)) {
                $this->fail($extraction, 'source_changed');

                return;
            }

            $files = $this->sourceFilesForSnapshot($extraction);
            $pages = $this->sourcePages($files);
            $input = json_encode(['source_pages' => $pages->map(fn (FilePageEmbedding $page): array => [
                'source_file_id' => $page->program_file_id,
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
                $proposal = $this->validateProposal($response->toArray(), $pages);
            } catch (RuntimeException) {
                $this->fail($extraction, 'invalid_proposal');

                return;
            }

            if ($proposal['subjects'] === []) {
                $this->fail($extraction, 'no_curriculum_found');

                return;
            }

            if (! $this->snapshotIsCurrent($extraction)) {
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
            $locked = CurriculumExtraction::query()->with('program')->lockForUpdate()->findOrFail($extraction->id);
            $program = Program::query()->lockForUpdate()->findOrFail($locked->program_id);

            if ($locked->status !== CurriculumExtractionStatus::Reviewing || ! is_array($locked->proposal)) {
                throw ValidationException::withMessages(['extraction' => 'Only a proposal awaiting review can be published.']);
            }

            if ($locked->program === null || ! $this->snapshotIsCurrent($locked, lock: true)) {
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
                        'source_file_id' => $topicProposal['source_file_id'],
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
                    'source_file_id' => $subjectProposal['source_file_id'],
                    'source_page' => $subjectProposal['source_page'],
                    'topics' => $selectedTopics,
                ];
            }

            if ($selected === []) {
                throw ValidationException::withMessages(['subjects' => 'Select at least one subject to publish.']);
            }

            $this->assertUniqueCodes($selected);

            foreach ($selected as $subjectData) {
                $subject = Subject::query()->firstOrCreate(
                    $subjectData['code'] === null
                        ? ['program_id' => $program->id, 'name' => $subjectData['name']]
                        : ['program_id' => $program->id, 'code' => $subjectData['code']],
                    [
                        'name' => $subjectData['name'],
                        'code' => $subjectData['code'],
                        'description' => $subjectData['description'],
                        'sort_order' => ((int) Subject::query()->where('program_id', $program->id)->max('sort_order')) + 1,
                    ],
                );

                $resolvedTopics = [];
                foreach ($subjectData['topics'] as $topicOrder => $topicData) {
                    $parent = $topicData['parent_index'] === null ? null : ($resolvedTopics[$topicData['parent_index']] ?? null);
                    $topic = $subject->syllabusTopics()->firstOrCreate(
                        $topicData['code'] === null
                            ? ['parent_id' => $parent?->id, 'title' => $topicData['title']]
                            : ['code' => $topicData['code']],
                        [
                            'parent_id' => $parent?->id,
                            'code' => $topicData['code'],
                            'title' => $topicData['title'],
                            'description' => $topicData['description'],
                            'sort_order' => $topicOrder,
                        ],
                    );
                    $resolvedTopics[$topicOrder] = $topic;
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
            throw ValidationException::withMessages(['extraction' => 'A source file changed after extraction. Create a new extraction before publishing.']);
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

    /** @return Collection<int, ProgramFile> */
    private function sourceFiles(Program $program, bool $lock = false): Collection
    {
        $query = $program->files()
            ->where('upload_status', FileUploadStatus::Uploaded)
            ->where('mime_type', 'application/pdf')
            ->where('embedding_status', FileEmbeddingStatus::Complete)
            ->whereNotNull('content_hash')
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        $files = $query->get();
        if ($files->isEmpty()) {
            throw ValidationException::withMessages(['program' => 'Add at least one uploaded PDF with completed text embeddings before extracting curriculum.']);
        }

        return $files;
    }

    /** @return Collection<int, ProgramFile> */
    private function sourceFilesForSnapshot(CurriculumExtraction $extraction): Collection
    {
        $snapshot = $extraction->source_files;
        if (! is_array($snapshot) || $snapshot === []) {
            throw new RuntimeException('The extraction source snapshot is unavailable.');
        }

        $files = ProgramFile::query()->whereIn('id', collect($snapshot)->pluck('id'))->orderBy('id')->get();
        if ($files->count() !== count($snapshot)) {
            throw new RuntimeException('The extraction source snapshot changed.');
        }

        return $files;
    }

    /** @param Collection<int, ProgramFile> $files
     * @return Collection<int, FilePageEmbedding>
     */
    private function sourcePages(Collection $files): Collection
    {
        $sourceHashes = $files->mapWithKeys(fn (ProgramFile $file): array => [$file->id => $file->content_hash]);
        $pages = FilePageEmbedding::query()
            ->whereIn('program_file_id', $files->pluck('id'))
            ->whereNotNull('content')
            ->orderBy('program_file_id')
            ->orderBy('page_number')
            ->get(['id', 'program_file_id', 'page_number', 'source_hash', 'content'])
            ->filter(fn (FilePageEmbedding $page): bool => $page->source_hash === $sourceHashes[$page->program_file_id]);

        if ($pages->isEmpty() || $files->contains(fn (ProgramFile $file): bool => ! $pages->contains('program_file_id', $file->id)) || $pages->contains(fn (FilePageEmbedding $page): bool => blank($page->content))) {
            throw ValidationException::withMessages(['program' => 'Every selected source file needs usable extracted page text.']);
        }

        $characterCount = $pages->sum(fn (FilePageEmbedding $page): int => mb_strlen($page->content));
        $maximumCharacters = min(250000, max(1000, (int) config('ai.official_curriculum.max_characters', 120000)));
        if ($characterCount > $maximumCharacters) {
            throw ValidationException::withMessages(['program' => 'The combined extracted text exceeds the configured processing limit.']);
        }

        return $pages;
    }

    /** @param Collection<int, ProgramFile> $files
     * @return array<int, array{id: int, content_hash: string, title: string}>
     */
    private function sourceSnapshot(Collection $files): array
    {
        return $files->map(fn (ProgramFile $file): array => [
            'id' => $file->id,
            'content_hash' => $file->content_hash,
            'title' => $file->title,
        ])->all();
    }

    private function snapshotIsCurrent(CurriculumExtraction $extraction, bool $lock = false): bool
    {
        try {
            $files = $this->sourceFilesForSnapshot($extraction);
        } catch (RuntimeException) {
            return false;
        }

        if ($lock) {
            $files = ProgramFile::query()->whereKey($files->pluck('id'))->orderBy('id')->lockForUpdate()->get();
        }

        return $this->sourceSnapshot($files) === $extraction->source_files
            && $files->every(fn (ProgramFile $file): bool => $file->upload_status === FileUploadStatus::Uploaded
                && $file->mime_type === 'application/pdf'
                && $file->embedding_status === FileEmbeddingStatus::Complete);
    }

    /** @param array<string, mixed> $raw
     * @param  Collection<int, FilePageEmbedding>  $sourcePages
     * @return array{subjects: array<int, array<string, mixed>>}
     */
    private function validateProposal(array $raw, Collection $sourcePages): array
    {
        if (! isset($raw['subjects']) || ! is_array($raw['subjects']) || count($raw['subjects']) > 100) {
            throw new RuntimeException('Invalid curriculum proposal.');
        }

        $subjects = [];
        foreach ($raw['subjects'] as $subject) {
            if (! is_array($subject) || ! $this->validText($subject['name'] ?? null, 255) || ! $this->validSource($subject['source_file_id'] ?? null, $subject['source_page'] ?? null, $sourcePages)) {
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
                    || ! $this->validSource($topic['source_file_id'] ?? null, $topic['source_page'] ?? null, $sourcePages)
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
                    'source_file_id' => (int) $topic['source_file_id'],
                    'source_page' => (int) $topic['source_page'],
                    'parent_index' => $parentIndex,
                ];
            }

            $subjects[] = [
                'code' => $this->nullableString($subject['code'] ?? null),
                'name' => trim($subject['name']),
                'description' => $this->nullableString($subject['description'] ?? null),
                'description_origin' => $subject['description_origin'],
                'source_file_id' => (int) $subject['source_file_id'],
                'source_page' => (int) $subject['source_page'],
                'topics' => $normalizedTopics,
            ];
        }

        return ['subjects' => $subjects];
    }

    /** @param array<int, array<string, mixed>> $selected */
    private function assertUniqueCodes(array $selected): void
    {
        $subjectCodes = [];
        foreach ($selected as $subject) {
            $code = $subject['code'];
            if ($code !== null) {
                $normalizedCode = mb_strtolower($code);
                if (in_array($normalizedCode, $subjectCodes, true)) {
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

    /** @param Collection<int, FilePageEmbedding> $sourcePages */
    private function validSource(mixed $fileId, mixed $page, Collection $sourcePages): bool
    {
        return is_int($fileId) && is_int($page)
            && $sourcePages->contains(fn (FilePageEmbedding $source): bool => $source->program_file_id === $fileId && $source->page_number === $page);
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
