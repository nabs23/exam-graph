<?php

namespace App\Services;

use App\Ai\Agents\OfficialCurriculumExtractor;
use App\CurriculumExtractionError;
use App\CurriculumExtractionStatus;
use App\Exceptions\CurriculumExtractionException;
use App\FileEmbeddingStatus;
use App\FileUploadStatus;
use App\Jobs\ExtractOfficialCurriculum;
use App\Models\Concept;
use App\Models\CurriculumExtraction;
use App\Models\FilePageEmbedding;
use App\Models\Program;
use App\Models\ProgramFile;
use App\Models\Subject;
use App\Models\SubjectFile;
use App\Models\SyllabusTopic;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    /** @return list<string> */
    public function availableModels(): array
    {
        return array_values(array_unique(array_filter([
            ...config('ai.official_curriculum.models', []),
            config('ai.official_curriculum.model'),
        ], fn (mixed $model): bool => is_string($model) && $model !== '')));
    }

    public function queue(Program $program, User $requester, ?string $model = null): CurriculumExtraction
    {
        $this->assertReady();

        if ($this->timeout() === 0 && ! $this->usesOpenAi((string) config('ai.official_curriculum.provider'))) {
            throw ValidationException::withMessages(['program' => 'Waiting without a time limit is currently supported for OpenAI providers only.']);
        }

        $model ??= (string) config('ai.official_curriculum.model');

        return DB::transaction(function () use ($program, $requester, $model): CurriculumExtraction {
            $lockedProgram = Program::query()->lockForUpdate()->findOrFail($program->id);
            $files = $this->sourceFiles($lockedProgram, lock: true);
            $this->sourcePages($files);
            $snapshot = $this->sourceSnapshot($files);
            $sourceHash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
            $provider = (string) config('ai.official_curriculum.provider');

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
        $pending = CurriculumExtraction::query()->findOrFail($extractionId);
        if ($pending->provider_response_id !== null && $pending->status === CurriculumExtractionStatus::Processing) {
            $this->poll($pending);

            return;
        }

        $claimableStatuses = $isRetry
            ? [CurriculumExtractionStatus::Queued->value, CurriculumExtractionStatus::Processing->value]
            : [CurriculumExtractionStatus::Queued->value];

        $claimed = CurriculumExtraction::query()
            ->whereKey($extractionId)
            ->whereIn('status', $claimableStatuses)
            ->update(['status' => CurriculumExtractionStatus::Processing->value, 'error_code' => null, 'provider_started_at' => now(), 'updated_at' => now()]);

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
                $background = $this->usesOpenAi($extraction->provider);
                $response = (new OfficialCurriculumExtractor(extractionModel: $extraction->model, background: $background))->prompt(
                    $input,
                    provider: $extraction->provider,
                    model: $extraction->model,
                    timeout: $background ? 30 : $this->timeout(),
                );
            } catch (Throwable $exception) {
                throw new CurriculumExtractionException(CurriculumExtractionError::fromException($exception), $exception);
            }

            if ($background && in_array($response->raw?->json('status'), ['queued', 'in_progress'], true)) {
                $responseId = $response->raw->json('id');
                if (! is_string($responseId) || $responseId === '') {
                    throw new CurriculumExtractionException(CurriculumExtractionError::ProviderFailed);
                }
                $extraction->forceFill(['provider_response_id' => $responseId])->save();
                ExtractOfficialCurriculum::dispatch($extraction->id)->delay(now()->addSeconds(5))->afterCommit();

                return;
            }

            if (! $response instanceof StructuredAgentResponse) {
                $this->fail($extraction, 'invalid_proposal');

                return;
            }

            $this->complete($extraction, $pages, $response->toArray());
        } catch (Throwable $exception) {
            if ($exception instanceof CurriculumExtractionException) {
                throw $exception;
            }

            report($exception);
            $this->fail($extraction, 'extraction_failed');
        }
    }

    private function timeout(): int
    {
        return max(0, (int) config('ai.official_curriculum.timeout', 240));
    }

    private function usesOpenAi(string $provider): bool
    {
        return config("ai.providers.$provider.driver") === 'openai';
    }

    private function poll(CurriculumExtraction $extraction): void
    {
        if (! $this->snapshotIsCurrent($extraction)) {
            $this->fail($extraction, 'source_changed');

            return;
        }

        $url = rtrim(config("ai.providers.{$extraction->provider}.url") ?: 'https://api.openai.com/v1', '/').'/responses/'.rawurlencode($extraction->provider_response_id);
        $client = Http::withToken((string) config("ai.providers.{$extraction->provider}.key"))
            ->withHeaders(config("ai.providers.{$extraction->provider}.headers", []))
            ->timeout(30);

        try {
            $data = $client->get($url)->throw()->json();
            if (in_array($data['status'] ?? null, ['queued', 'in_progress'], true)
                && $this->timeout() > 0 && $extraction->provider_started_at->diffInSeconds(now()) >= $this->timeout()) {
                $client->post($url.'/cancel')->throw();
                $this->fail($extraction, 'provider_timeout');

                return;
            }

        } catch (Throwable $exception) {
            throw new CurriculumExtractionException(CurriculumExtractionError::fromException($exception), $exception);
        }

        if (in_array($data['status'] ?? null, ['queued', 'in_progress'], true)) {
            ExtractOfficialCurriculum::dispatch($extraction->id)->delay(now()->addSeconds(5))->afterCommit();

            return;
        }

        if (($data['status'] ?? null) !== 'completed') {
            $failure = match (true) {
                ($data['status'] ?? null) === 'cancelled' => CurriculumExtractionError::ProviderCancelled,
                ($data['incomplete_details']['reason'] ?? null) === 'max_output_tokens' => CurriculumExtractionError::ProviderOutputLimit,
                ($data['incomplete_details']['reason'] ?? null) === 'content_filter' => CurriculumExtractionError::ProviderRefused,
                default => CurriculumExtractionError::fromProviderError(0, $data['error']['code'] ?? $data['error']['type'] ?? null),
            };
            $this->fail($extraction, $failure->value);

            return;
        }

        $content = collect($data['output'] ?? [])->where('type', 'message')->flatMap(fn (array $message): array => $message['content'] ?? []);
        if ($content->contains('type', 'refusal')) {
            $this->fail($extraction, 'provider_refused');

            return;
        }
        $text = $content->where('type', 'output_text')->pluck('text')->implode('');
        $proposal = json_decode($text, true);
        if (! is_array($proposal)) {
            $this->fail($extraction, 'invalid_proposal');

            return;
        }

        try {
            $pages = $this->sourcePages($this->sourceFilesForSnapshot($extraction));
            $this->complete($extraction, $pages, $proposal);
        } catch (Throwable $exception) {
            report($exception);
            $this->fail($extraction, 'extraction_failed');
        }
    }

    /** @param Collection<int, FilePageEmbedding> $pages
     * @param  array<string, mixed>  $raw
     */
    private function complete(CurriculumExtraction $extraction, Collection $pages, array $raw): void
    {
        try {
            $proposal = $this->validateProposal($raw, $pages);
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

        $extraction->forceFill(['proposal' => $proposal, 'status' => CurriculumExtractionStatus::Reviewing, 'error_code' => null])->save();
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
                    'merge_target_id' => isset($selection['merge_target_id']) ? (int) $selection['merge_target_id'] : null,
                    'merge_source_ids' => array_map('intval', $selection['merge_source_ids'] ?? []),
                ];
            }

            if ($selected === []) {
                throw ValidationException::withMessages(['subjects' => 'Select at least one subject to publish.']);
            }

            $this->assertUniqueCodes($selected);

            $subjects = $program->subjects()->lockForUpdate()->get();
            foreach ($selected as $subjectIndex => $subjectData) {
                $mergeSourceIds = array_map('intval', $subjectData['merge_source_ids'] ?? []);
                $subject = null;
                $mergeTargetId = (int) ($subjectData['merge_target_id'] ?? 0);
                if ($mergeTargetId > 0) {
                    $subject = $this->mergeSelectedSubjects($program, $mergeTargetId, $mergeSourceIds, $subjectData['name'], $subjectData['code']);
                    $subjects = $subjects->reject(fn (Subject $candidate): bool => in_array($candidate->id, $mergeSourceIds, true));
                    $subjects->push($subject);
                    $selected[$subjectIndex]['merged_subject_ids'] = $mergeSourceIds;
                } else {
                    $subject = $this->matchingCurriculumRecord($subjects, $subjectData['code'], 'name', $subjectData['name']);
                }
                if ($subject === null) {
                    $subject = $program->subjects()->create([
                        'name' => $subjectData['name'],
                        'code' => $subjectData['code'],
                        'description' => $subjectData['description'],
                        'sort_order' => ((int) Subject::query()->where('program_id', $program->id)->max('sort_order')) + 1,
                    ]);
                    $subjects->push($subject);
                } elseif ($subject->code === null && $subjectData['code'] !== null) {
                    $subject->update(['code' => $subjectData['code']]);
                }

                $resolvedTopics = [];
                $topics = $subject->syllabusTopics()->lockForUpdate()->get();
                foreach ($subjectData['topics'] as $topicOrder => $topicData) {
                    $parent = $topicData['parent_index'] === null ? null : ($resolvedTopics[$topicData['parent_index']] ?? null);
                    $topic = $this->matchingCurriculumRecord($topics, $topicData['code'], 'title', $topicData['title'], $parent?->id);
                    if ($topic === null) {
                        $topic = $subject->syllabusTopics()->create([
                            'parent_id' => $parent?->id,
                            'code' => $topicData['code'],
                            'title' => $topicData['title'],
                            'description' => $topicData['description'],
                            'sort_order' => $topicOrder,
                        ]);
                        $topics->push($topic);
                    } elseif ($topic->code === null && $topicData['code'] !== null) {
                        $topic->update(['code' => $topicData['code']]);
                    }
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

    /**
     * @param  list<int>  $sourceIds
     */
    private function mergeSelectedSubjects(Program $program, int $targetId, array $sourceIds, string $name, ?string $selectedCode): Subject
    {
        if ($targetId <= 0 || in_array($targetId, $sourceIds, true) || count($sourceIds) !== count(array_unique($sourceIds))) {
            throw ValidationException::withMessages(['subjects' => 'Choose one subject to keep and distinct subjects to merge into it.']);
        }

        $ids = [$targetId, ...$sourceIds];
        $records = $program->subjects()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
        if ($records->count() !== count($ids) || $records->contains(fn (Subject $record): bool => mb_strtolower(trim($record->name)) !== mb_strtolower(trim($name)))) {
            throw ValidationException::withMessages(['subjects' => 'The merge preview is stale. Refresh and review the matching subjects again.']);
        }

        $target = $records->firstWhere('id', $targetId);
        if ($sourceIds === []) {
            if ($selectedCode !== null && $target->code !== null && mb_strtolower(trim($selectedCode)) !== mb_strtolower(trim($target->code))) {
                throw ValidationException::withMessages(['subjects' => 'The selected subject has a different code. Update the proposed code or choose another subject.']);
            }

            if ($target->code === null && $selectedCode !== null) {
                $target->update(['code' => $selectedCode]);
            }

            return $target;
        }
        $codes = $records->pluck('code')->filter(fn (?string $code): bool => $code !== null)->map(fn (string $code): string => mb_strtolower(trim($code)))->unique();
        if ($codes->count() > 1 || ($selectedCode !== null && $codes->isNotEmpty() && ! $codes->contains(mb_strtolower(trim($selectedCode))))) {
            throw ValidationException::withMessages(['subjects' => 'The selected subjects have conflicting codes. Update the proposed code or choose compatible records.']);
        }

        $topics = SyllabusTopic::query()->whereIn('subject_id', $ids)->orderBy('id')->lockForUpdate()->get();
        $concepts = Concept::query()->whereIn('subject_id', $ids)->lockForUpdate()->get();
        $files = SubjectFile::query()->whereIn('subject_id', $ids)->lockForUpdate()->get();
        $conceptCodes = $concepts->pluck('code')->map(fn (string $code): string => mb_strtolower(trim($code)));
        if ($conceptCodes->count() !== $conceptCodes->unique()->count()) {
            throw ValidationException::withMessages(['subjects' => 'The selected subjects contain concepts with conflicting codes. Resolve those records before merging.']);
        }
        $backup = [
            'subjects' => $records->map->getAttributes(),
            'syllabus_topics' => $topics->map->getAttributes(),
            'concepts' => $concepts->map->getAttributes(),
            'subject_files' => $files->map->getAttributes(),
        ];

        $targetTopics = $topics->where('subject_id', $targetId)->values();
        $sourceTopics = $topics->whereIn('subject_id', $sourceIds)->keyBy('id');
        $resolvedTopicIds = [];
        $removedTopicIds = [];
        while ($sourceTopics->isNotEmpty()) {
            $progress = false;
            foreach ($sourceTopics as $topic) {
                if ($topic->parent_id !== null && ! array_key_exists($topic->parent_id, $resolvedTopicIds)) {
                    continue;
                }
                $parentId = $topic->parent_id === null ? null : $resolvedTopicIds[$topic->parent_id];
                $matches = $targetTopics->filter(function (SyllabusTopic $candidate) use ($topic, $parentId): bool {
                    $sameCode = $topic->code !== null && $candidate->code !== null && mb_strtolower(trim($candidate->code)) === mb_strtolower(trim($topic->code));

                    return $sameCode || ($candidate->parent_id === $parentId && mb_strtolower(trim($candidate->title)) === mb_strtolower(trim($topic->title)));
                });
                if ($matches->count() > 1) {
                    throw ValidationException::withMessages(['subjects' => "The selected merge has ambiguous topic matches for \"{$topic->title}\"."]);
                }
                $match = $matches->first();
                if ($match !== null) {
                    if ($match->parent_id !== $parentId || mb_strtolower(trim($match->title)) !== mb_strtolower(trim($topic->title)) || ($match->code !== null && $topic->code !== null && mb_strtolower(trim($match->code)) !== mb_strtolower(trim($topic->code)))) {
                        throw ValidationException::withMessages(['subjects' => "The selected merge has a topic code or hierarchy conflict for \"{$topic->title}\"."]);
                    }
                    $match->update(['code' => $match->code ?? $topic->code, 'description' => $match->description ?? $topic->description]);
                    $resolvedTopicIds[$topic->id] = $match->id;
                    $removedTopicIds[] = $topic->id;
                } else {
                    $topic->update(['subject_id' => $targetId, 'parent_id' => $parentId]);
                    $targetTopics->push($topic);
                    $resolvedTopicIds[$topic->id] = $topic->id;
                }
                $sourceTopics->forget($topic->id);
                $progress = true;
            }
            if (! $progress) {
                throw ValidationException::withMessages(['subjects' => 'The selected merge contains an invalid topic hierarchy.']);
            }
        }

        foreach ($concepts as $concept) {
            $concept->update([
                'subject_id' => $targetId,
                'syllabus_topic_id' => $resolvedTopicIds[$concept->syllabus_topic_id] ?? $concept->syllabus_topic_id,
            ]);
        }
        SubjectFile::query()->whereIn('subject_id', $sourceIds)->update(['subject_id' => $targetId]);
        SyllabusTopic::query()->whereKey($removedTopicIds)->delete();
        Subject::query()->whereKey($sourceIds)->delete();
        $target->update([
            'code' => $target->code ?? $selectedCode ?? $records->pluck('code')->filter()->first(),
            'description' => $target->description ?? $records->pluck('description')->filter()->first(),
        ]);

        $backupPath = 'curriculum-merges/'.Str::uuid().'.json';
        if (! Storage::disk('local')->put($backupPath, json_encode($backup, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))) {
            throw ValidationException::withMessages(['subjects' => 'The subject merge backup could not be saved. No changes were published.']);
        }

        return $target->fresh();
    }

    /**
     * @template T of Subject|SyllabusTopic
     *
     * @param  Collection<int, T>  $records
     * @return T|null
     */
    private function matchingCurriculumRecord(Collection $records, ?string $code, string $label, string $value, ?int $parentId = null): Subject|SyllabusTopic|null
    {
        $normalizedCode = $code === null ? null : mb_strtolower(trim($code));
        $normalizedValue = mb_strtolower(trim($value));
        $matches = [];
        foreach ($records as $record) {
            $sameCode = $normalizedCode !== null && $record->code !== null && mb_strtolower(trim($record->code)) === $normalizedCode;
            $sameName = mb_strtolower(trim($record->{$label})) === $normalizedValue;

            if ($sameCode || ($sameName && ($record instanceof Subject || $record->parent_id === $parentId))) {
                $matches[] = $record;
            }
        }

        if (count($matches) > 1) {
            throw ValidationException::withMessages(['subjects' => "Multiple existing records match \"{$value}\". Resolve the duplicates before publishing."]);
        }

        $match = $matches[0] ?? null;
        if ($match !== null && $normalizedCode !== null && $match->code !== null && mb_strtolower(trim($match->code)) !== $normalizedCode) {
            throw ValidationException::withMessages(['subjects' => "The code for \"{$value}\" conflicts with its existing code. Review the code before publishing."]);
        }
        if ($match instanceof SyllabusTopic && $match->parent_id !== $parentId) {
            throw ValidationException::withMessages(['subjects' => "The topic \"{$value}\" already exists under a different parent. Review its hierarchy before publishing."]);
        }

        return $match;
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
            if (! is_array($subject) || ! $this->validText($subject['name'] ?? null, 255)) {
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
                    || ! $this->validNullableText($topic['code'] ?? null, 50)
                    || ! $this->validNullableText($topic['description'] ?? null, 2000)
                    || ! $this->validDescriptionOrigin($topic['description_origin'] ?? null, $topic['description'] ?? null)
                    || ($parentIndex !== null && (! is_int($parentIndex) || $parentIndex < 0 || $parentIndex >= $index))) {
                    throw new RuntimeException('Invalid curriculum proposal.');
                }

                $hasSource = $this->validSource($topic['source_file_id'] ?? null, $topic['source_page'] ?? null, $sourcePages);
                $normalizedTopics[] = [
                    'code' => $this->nullableString($topic['code'] ?? null),
                    'title' => trim($topic['title']),
                    'description' => $this->nullableString($topic['description'] ?? null),
                    'description_origin' => $topic['description_origin'],
                    'source_file_id' => $hasSource ? $topic['source_file_id'] : null,
                    'source_page' => $hasSource ? $topic['source_page'] : null,
                    'parent_index' => $parentIndex,
                ];
            }

            $hasSource = $this->validSource($subject['source_file_id'] ?? null, $subject['source_page'] ?? null, $sourcePages);
            $subjects[] = [
                'code' => $this->nullableString($subject['code'] ?? null),
                'name' => trim($subject['name']),
                'description' => $this->nullableString($subject['description'] ?? null),
                'description_origin' => $subject['description_origin'],
                'source_file_id' => $hasSource ? $subject['source_file_id'] : null,
                'source_page' => $hasSource ? $subject['source_page'] : null,
                'topics' => $normalizedTopics,
            ];
        }

        return ['subjects' => $subjects];
    }

    /** @param array<int, array<string, mixed>> $selected */
    private function assertUniqueCodes(array $selected): void
    {
        $subjectCodes = [];
        $subjectNames = [];
        foreach ($selected as $subject) {
            $name = mb_strtolower(trim($subject['name']));
            if (in_array($name, $subjectNames, true)) {
                throw ValidationException::withMessages(['subjects' => 'Select each subject name only once, regardless of letter case.']);
            }
            $subjectNames[] = $name;
            $code = $subject['code'];
            if ($code !== null) {
                $normalizedCode = mb_strtolower($code);
                if (in_array($normalizedCode, $subjectCodes, true)) {
                    throw ValidationException::withMessages(['subjects' => 'Subject codes must be unique within the program.']);
                }
                $subjectCodes[] = $normalizedCode;
            }

            $topicCodes = [];
            $topicTitles = [];
            foreach ($subject['topics'] as $topic) {
                $title = [$topic['parent_index'], mb_strtolower(trim($topic['title']))];
                if (in_array($title, $topicTitles, true)) {
                    throw ValidationException::withMessages(['subjects' => 'Select each topic title only once under the same parent, regardless of letter case.']);
                }
                $topicTitles[] = $title;
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
