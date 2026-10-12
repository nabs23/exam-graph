<?php

namespace App\Services;

use App\Ai\Agents\SubjectTopicExtractor;
use App\FileEmbeddingStatus;
use App\FileUploadStatus;
use App\Jobs\ExtractSubjectTopics;
use App\Models\FilePageEmbedding;
use App\Models\ProgramFile;
use App\Models\Subject;
use App\Models\SubjectFile;
use App\Models\SubjectTopicExtraction;
use App\Models\SyllabusTopic;
use App\Models\User;
use App\SubjectTopicExtractionError;
use App\SubjectTopicExtractionStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SubjectTopicExtractionService
{
    private const PROMPT_VERSION = 'subject-topics-v1';

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
                'Subject topic extraction is disabled.' => 'Subject topic extraction is disabled in application configuration.',
                'Subject topic extraction requires PostgreSQL, pgvector, and completed migrations.' => 'PostgreSQL, pgvector, and the subject topic extraction migrations are required.',
                'The pgvector extension is not enabled.' => 'The pgvector extension is not enabled for this database.',
                'Configure a subject topic extraction provider, model, and API key.' => 'Configure the topic extraction provider, model, and API key.',
                default => 'Topic extraction requirements are unavailable. Check the database and provider configuration.',
            };
        }
    }

    /** @return list<string> */
    public function availableModels(): array
    {
        return array_values(array_unique(array_filter([
            ...config('ai.subject_topic_extraction.models', []),
            config('ai.subject_topic_extraction.model'),
        ], fn (mixed $model): bool => is_string($model) && $model !== '')));
    }

    /** @param array<int, int> $sourceFileIds */
    public function queue(Subject $subject, array $sourceFileIds, User $requester, ?string $model = null): SubjectTopicExtraction
    {
        $this->assertReady();
        $model ??= (string) config('ai.subject_topic_extraction.model');

        if (! in_array($model, $this->availableModels(), true)) {
            throw ValidationException::withMessages(['model' => 'Choose an available extraction model.']);
        }

        return DB::transaction(function () use ($subject, $sourceFileIds, $requester, $model): SubjectTopicExtraction {
            $lockedSubject = Subject::query()->lockForUpdate()->findOrFail($subject->id);
            $fileIds = array_values(array_unique(array_map('intval', $sourceFileIds)));
            $files = ProgramFile::query()
                ->where('program_id', $lockedSubject->program_id)
                ->whereIn('id', $fileIds)
                ->where(function ($query) use ($lockedSubject): void {
                    $query->whereNull('subject_id')->orWhere('subject_id', $lockedSubject->id);
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($files->count() !== count($fileIds)) {
                throw ValidationException::withMessages(['source_file_ids' => 'Select only shared program files or files associated with this subject.']);
            }

            $this->assertEligibleFiles($files);
            $pages = $this->sourcePages($files);
            $this->assertInputBounds($pages);
            $snapshot = $this->sourceSnapshot($files);
            $sourceHash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
            $provider = (string) config('ai.subject_topic_extraction.provider');

            $inProgress = $lockedSubject->topicExtractions()
                ->where('source_hash', $sourceHash)
                ->where('provider', $provider)
                ->where('model', $model)
                ->where('prompt_version', self::PROMPT_VERSION)
                ->whereIn('status', [SubjectTopicExtractionStatus::Queued, SubjectTopicExtractionStatus::Processing, SubjectTopicExtractionStatus::Reviewing])
                ->exists();

            if ($inProgress) {
                throw ValidationException::withMessages(['source_file_ids' => 'An extraction for these files and settings is already in progress or awaiting review.']);
            }

            $extraction = $lockedSubject->topicExtractions()->create([
                'requested_by' => $requester->id,
                'status' => SubjectTopicExtractionStatus::Queued,
                'source_hash' => $sourceHash,
                'source_files' => $snapshot,
                'provider' => $provider,
                'model' => $model,
                'prompt_version' => self::PROMPT_VERSION,
                'correlation_id' => (string) Str::uuid(),
            ]);

            ExtractSubjectTopics::dispatch($extraction->id)->afterCommit();

            return $extraction;
        });
    }

    public function extract(int $extractionId): void
    {
        $this->assertReady();
        $claimed = SubjectTopicExtraction::query()
            ->whereKey($extractionId)
            ->where('status', SubjectTopicExtractionStatus::Queued)
            ->update(['status' => SubjectTopicExtractionStatus::Processing, 'error_code' => null, 'updated_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $extraction = SubjectTopicExtraction::query()->with('subject')->findOrFail($extractionId);

        if (! $this->snapshotIsCurrent($extraction)) {
            $this->fail($extraction, SubjectTopicExtractionError::SourceChanged);

            return;
        }

        try {
            $files = $this->filesForSnapshot($extraction);
            $this->assertEligibleFiles($files);
            $pages = $this->sourcePages($files);
            $this->assertInputBounds($pages);
            $input = json_encode([
                'subject' => ['name' => $extraction->subject->name, 'code' => $extraction->subject->code],
                'source_pages' => $pages->map(fn (FilePageEmbedding $page): array => [
                    'source_file_id' => $page->subject_file_id,
                    'page_number' => $page->page_number,
                    'text' => $page->content,
                ])->values()->all(),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

            $response = (new SubjectTopicExtractor($extraction->model))->prompt(
                $input,
                provider: $extraction->provider,
                model: $extraction->model,
                timeout: (int) config('ai.subject_topic_extraction.timeout', 240),
            );

            $responseData = $response->toArray();
            $validation = ['status' => 'passed', 'notes' => []];

            try {
                $proposal = $this->validateProposal($responseData, $pages);
            } catch (RuntimeException $exception) {
                $proposal = $this->proposalForReview($responseData);
                $validation = [
                    'status' => 'needs_review',
                    'notes' => [$this->validationNote($exception->getMessage())],
                ];
            }

            $proposal['validation'] = $validation;
            if ($proposal['topics'] === []) {
                $this->fail($extraction, SubjectTopicExtractionError::NoTopicsFound);

                return;
            }

            if (! $this->snapshotIsCurrent($extraction)) {
                $this->fail($extraction, SubjectTopicExtractionError::SourceChanged);

                return;
            }

            $extraction->forceFill([
                'proposal' => $proposal,
                'status' => SubjectTopicExtractionStatus::Reviewing,
                'error_code' => null,
            ])->save();
        } catch (RuntimeException $exception) {
            $error = match (true) {
                str_contains($exception->getMessage(), 'source snapshot changed') => SubjectTopicExtractionError::SourceChanged,
                str_contains($exception->getMessage(), 'source snapshot is unavailable') => SubjectTopicExtractionError::SourceUnavailable,
                default => SubjectTopicExtractionError::InvalidProposal,
            };
            $this->fail($extraction, $error);
        } catch (Throwable $exception) {
            Log::error('Subject topic extraction failed.', [
                'subject_topic_extraction_id' => $extraction->id,
                'correlation_id' => $extraction->correlation_id,
                'exception_class' => $exception::class,
            ]);
            $this->fail($extraction, SubjectTopicExtractionError::ProviderFailed);
        }
    }

    /** @param array<int, array<string, mixed>> $selections */
    public function publish(SubjectTopicExtraction $extraction, array $selections, User $reviewer): void
    {
        $sourceChanged = false;

        DB::transaction(function () use ($extraction, $selections, $reviewer, &$sourceChanged): void {
            $locked = SubjectTopicExtraction::query()->lockForUpdate()->findOrFail($extraction->id);
            $subject = Subject::query()->lockForUpdate()->findOrFail($locked->subject_id);
            if ($locked->status !== SubjectTopicExtractionStatus::Reviewing || ! is_array($locked->proposal)) {
                throw ValidationException::withMessages(['extraction' => 'Only a proposal awaiting review can be published.']);
            }

            if (! $this->snapshotIsCurrent($locked, lock: true)) {
                $locked->forceFill(['status' => SubjectTopicExtractionStatus::Failed, 'error_code' => SubjectTopicExtractionError::SourceChanged])->save();
                $sourceChanged = true;

                return;
            }

            $sourceFiles = $this->filesForSnapshot($locked, lock: true);
            $sourcePages = $this->sourcePages($sourceFiles);
            $availableSourcePages = $sourcePages->mapWithKeys(fn (FilePageEmbedding $page): array => [
                $page->subject_file_id.':'.$page->page_number => true,
            ]);

            $proposedTopics = $locked->proposal['topics'] ?? [];
            if (count($selections) !== count($proposedTopics)) {
                throw ValidationException::withMessages(['topics' => 'The review form does not match the saved topic proposal.']);
            }

            $selected = [];
            foreach ($proposedTopics as $index => $proposal) {
                $selection = $selections[$index] ?? [];
                if (! filter_var($selection['include'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    continue;
                }

                $sourceCitations = is_array($proposal['source_pages'] ?? null)
                    ? array_values($proposal['source_pages'])
                    : [];
                $matchedSourceCitations = array_values(array_filter($sourceCitations, fn (mixed $citation): bool => is_array($citation)
                    && is_int($citation['source_file_id'] ?? null)
                    && is_int($citation['page_number'] ?? null)
                    && isset($availableSourcePages[$citation['source_file_id'].':'.$citation['page_number']])));
                $verifiedSourceCitations = collect($matchedSourceCitations)
                    ->unique(fn (array $citation): string => $citation['source_file_id'].':'.$citation['page_number'])
                    ->values()
                    ->all();
                $citationStatus = $sourceCitations === []
                    ? 'unverified'
                    : (count($matchedSourceCitations) === count($sourceCitations) ? 'valid' : 'invalid');

                $title = trim((string) ($selection['title'] ?? ''));
                if ($title === '') {
                    throw ValidationException::withMessages(["topics.$index.title" => 'Selected topics need a title.']);
                }

                $parentReference = $this->reviewParentReference($selection);
                $matchExistingTopicId = ($selection['match_existing_topic_id'] ?? '') === ''
                    ? null
                    : (int) $selection['match_existing_topic_id'];
                $selected[$index] = [
                    'code' => $this->nullableString($selection['code'] ?? null),
                    'title' => $title,
                    'description' => $this->nullableString($selection['description'] ?? null),
                    ...$parentReference,
                    'match_existing_topic_id' => $matchExistingTopicId,
                    'sort_order' => (int) ($selection['sort_order'] ?? $index),
                    'source_pages' => $sourceCitations,
                    'verified_source_pages' => $verifiedSourceCitations,
                    'citation_status' => $citationStatus,
                ];
            }

            if ($selected === []) {
                throw ValidationException::withMessages(['topics' => 'Select at least one topic to publish.']);
            }

            $existingTopics = $subject->syllabusTopics()->lockForUpdate()->get()->keyBy('id');
            $this->assertReviewHierarchy($selected, $existingTopics);
            [$publishable, $matchedExisting, $skippedTopics] = $this->excludeConflictingTopics($selected, $existingTopics);
            $filesById = collect($locked->source_files)->keyBy('id');

            $ordered = $publishable;
            uasort($ordered, fn (array $left, array $right): int => $left['sort_order'] <=> $right['sort_order']);
            $publishedTopicIds = collect($matchedExisting)
                ->mapWithKeys(fn (array $match, int $index): array => [$index => $match['topic']->id])
                ->all();
            foreach ($ordered as $proposalIndex => $topicData) {
                $topic = $subject->syllabusTopics()->create([
                    'parent_id' => null,
                    'code' => $topicData['code'],
                    'title' => $topicData['title'],
                    'description' => $topicData['description'],
                    'sort_order' => $topicData['sort_order'],
                ]);
                $publishedTopicIds[$proposalIndex] = $topic->id;
            }

            foreach ($publishable as $proposalIndex => $topicData) {
                $parentTopicId = $topicData['parent_topic_id']
                    ?? ($topicData['parent_index'] === null ? null : ($publishedTopicIds[$topicData['parent_index']] ?? null));
                if ($parentTopicId !== null) {
                    SyllabusTopic::query()->whereKey($publishedTopicIds[$proposalIndex])->update(['parent_id' => $parentTopicId]);
                }
            }

            $accepted = $publishable + collect($matchedExisting)
                ->map(fn (array $match): array => $match['data'])
                ->all();
            ksort($accepted);
            foreach ($accepted as $proposalIndex => $topicData) {
                $topic = $matchedExisting[$proposalIndex]['topic'] ?? SyllabusTopic::query()->findOrFail($publishedTopicIds[$proposalIndex]);
                foreach ($topicData['verified_source_pages'] as $sourcePage) {
                    $sourceSnapshot = $filesById->get($sourcePage['source_file_id']);
                    $isProgramFile = ($sourceSnapshot['source_type'] ?? null) === 'program_file';
                    $programFileId = $isProgramFile
                        ? $sourcePage['source_file_id']
                        : DB::table('legacy_program_file_sources')
                            ->where('legacy_type', 'subject_file')
                            ->where('legacy_id', $sourcePage['source_file_id'])
                            ->value('program_file_id');

                    $topic->sources()->firstOrCreate(
                        [
                            'source_file_id_snapshot' => $sourcePage['source_file_id'],
                            'page_number' => $sourcePage['page_number'],
                        ],
                        [
                            'subject_file_id' => $isProgramFile ? null : $sourcePage['source_file_id'],
                            'program_file_id' => $programFileId,
                            'source_title' => $sourceSnapshot['title'],
                            'source_hash' => $sourceSnapshot['content_hash'],
                        ],
                    );
                }
            }

            $reviewedTopics = [];
            foreach ($accepted as $proposalIndex => $topicData) {
                $reviewedTopics[] = [
                    ...$topicData,
                    'parent_index' => $topicData['parent_index'],
                    'parent_topic_id' => $topicData['parent_topic_id'],
                    'matched_existing_topic' => isset($matchedExisting[$proposalIndex]),
                    'published_topic_id' => $publishedTopicIds[$proposalIndex],
                ];
            }

            $locked->forceFill([
                'status' => SubjectTopicExtractionStatus::Published,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'reviewed_proposal' => [
                    'topics' => $reviewedTopics,
                    'skipped_topics' => $skippedTopics,
                ],
            ])->save();
        });

        if ($sourceChanged) {
            throw ValidationException::withMessages(['extraction' => 'The selected source files changed; start a new extraction.']);
        }
    }

    public function reject(SubjectTopicExtraction $extraction, User $reviewer): void
    {
        if ($extraction->status !== SubjectTopicExtractionStatus::Reviewing) {
            throw ValidationException::withMessages(['extraction' => 'Only a proposal awaiting review can be rejected.']);
        }

        $extraction->forceFill([
            'status' => SubjectTopicExtractionStatus::Rejected,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'reviewed_proposal' => null,
        ])->save();
    }

    public function retry(SubjectTopicExtraction $extraction): void
    {
        $this->assertReady();
        if ($extraction->status !== SubjectTopicExtractionStatus::Failed || ! $this->snapshotIsCurrent($extraction)) {
            throw ValidationException::withMessages(['extraction' => 'Only a failed extraction with unchanged source files can be retried.']);
        }

        $extraction->forceFill(['status' => SubjectTopicExtractionStatus::Queued, 'error_code' => null])->save();
        ExtractSubjectTopics::dispatch($extraction->id)->afterCommit();
    }

    /** @return Collection<int, ProgramFile> */
    public function filesForSubject(Subject $subject): Collection
    {
        $files = ProgramFile::query()
            ->where('program_id', $subject->program_id)
            ->where(function ($query) use ($subject): void {
                $query->whereNull('subject_id')->orWhere('subject_id', $subject->id);
            })
            ->with('subject:id,name')
            ->latest()
            ->get();
        if ($files->isEmpty()) {
            return $files;
        }

        $usablePageCounts = FilePageEmbedding::query()
            ->join('program_files', 'program_files.id', '=', 'file_page_embeddings.program_file_id')
            ->whereIn('file_page_embeddings.program_file_id', $files->modelKeys())
            ->whereNotNull('file_page_embeddings.content')
            ->whereColumn('file_page_embeddings.source_hash', 'program_files.content_hash')
            ->selectRaw('file_page_embeddings.program_file_id, count(distinct file_page_embeddings.page_number) as usable_pages_count')
            ->groupBy('file_page_embeddings.program_file_id')
            ->pluck('usable_pages_count', 'program_file_id');

        foreach ($files as $file) {
            $file->usable_pages_count = (int) $usablePageCounts->get($file->id, 0);
        }

        return $files;
    }

    private function assertReady(): void
    {
        if (! (bool) config('ai.subject_topic_extraction.enabled', false)) {
            throw new RuntimeException('Subject topic extraction is disabled.');
        }

        if (DB::connection()->getDriverName() !== 'pgsql'
            || ! Schema::hasTable('subject_topic_extractions')
            || ! Schema::hasTable('file_page_embeddings')) {
            throw new RuntimeException('Subject topic extraction requires PostgreSQL, pgvector, and completed migrations.');
        }

        if (! DB::table('pg_extension')->where('extname', 'vector')->exists()) {
            throw new RuntimeException('The pgvector extension is not enabled.');
        }

        $provider = (string) config('ai.subject_topic_extraction.provider');
        if (blank($provider) || blank(config('ai.subject_topic_extraction.model')) || blank(config("ai.providers.$provider.key"))) {
            throw new RuntimeException('Configure a subject topic extraction provider, model, and API key.');
        }
    }

    /** @param Collection<int, ProgramFile|SubjectFile> $files */
    private function assertEligibleFiles(Collection $files): void
    {
        if ($files->isEmpty() || $files->contains(fn (ProgramFile|SubjectFile $file): bool => $file->upload_status !== FileUploadStatus::Uploaded
            || $file->mime_type !== 'application/pdf'
            || $file->embedding_status !== FileEmbeddingStatus::Complete)) {
            throw ValidationException::withMessages(['source_file_ids' => 'Select only uploaded PDFs with completed page text.']);
        }
    }

    /** @param Collection<int, ProgramFile|SubjectFile> $files
     * @return SupportCollection<int, FilePageEmbedding>
     */
    private function sourcePages(Collection $files): SupportCollection
    {
        $programFiles = $files->filter(fn (ProgramFile|SubjectFile $file): bool => $file instanceof ProgramFile)->keyBy('id');
        $subjectFiles = $files->filter(fn (ProgramFile|SubjectFile $file): bool => $file instanceof SubjectFile)->keyBy('id');
        $sourceHashes = $files->mapWithKeys(fn (ProgramFile|SubjectFile $file): array => [$file->id => $file->content_hash]);
        $legacyMap = DB::table('legacy_program_file_sources')
            ->where('legacy_type', 'subject_file')
            ->whereIn('legacy_id', $subjectFiles->keys())
            ->pluck('program_file_id', 'legacy_id');

        $pages = FilePageEmbedding::query()
            ->where(function ($query) use ($programFiles, $subjectFiles, $legacyMap): void {
                if ($subjectFiles->isNotEmpty()) {
                    $query->whereIn('subject_file_id', $subjectFiles->keys());
                }
                $programFileIds = $programFiles->keys()->merge($legacyMap->values())->unique();
                if ($programFileIds->isNotEmpty()) {
                    if ($subjectFiles->isNotEmpty()) {
                        $query->orWhereIn('program_file_id', $programFileIds);
                    } else {
                        $query->whereIn('program_file_id', $programFileIds);
                    }
                }
                if ($subjectFiles->isEmpty() && $programFiles->isEmpty()) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->whereNotNull('content')
            ->orderBy('page_number')
            ->get(['id', 'subject_file_id', 'program_file_id', 'page_number', 'source_hash', 'content'])
            ->flatMap(function (FilePageEmbedding $page) use ($programFiles, $subjectFiles, $legacyMap) {
                $sourceIds = collect();
                if ($page->subject_file_id !== null && $subjectFiles->has($page->subject_file_id)) {
                    $sourceIds->push($page->subject_file_id);
                }
                if ($page->program_file_id !== null && $programFiles->has($page->program_file_id)) {
                    $sourceIds->push($page->program_file_id);
                }
                $sourceIds = $sourceIds->merge($legacyMap
                    ->filter(fn (mixed $programFileId): bool => (int) $programFileId === (int) $page->program_file_id)
                    ->keys()
                    ->filter(fn (int|string $legacyId): bool => $subjectFiles->has((int) $legacyId)));

                return $sourceIds->unique()->map(function (int|string $sourceId) use ($page): FilePageEmbedding {
                    $mappedPage = clone $page;
                    $mappedPage->subject_file_id = (int) $sourceId;

                    return $mappedPage;
                });
            })
            ->filter(fn (FilePageEmbedding $page): bool => $page->subject_file_id !== null
                && $sourceHashes->has($page->subject_file_id)
                && $page->source_hash === $sourceHashes[$page->subject_file_id]
                && filled($page->content))
            ->unique(fn (FilePageEmbedding $page): string => $page->subject_file_id.':'.$page->page_number)
            ->values();

        foreach ($files as $file) {
            if (! $pages->contains('subject_file_id', $file->id)) {
                throw ValidationException::withMessages(['source_file_ids' => "The file '{$file->title}' has no current extracted page text."]);
            }
        }

        return $pages;
    }

    /** @param SupportCollection<int, FilePageEmbedding> $pages */
    private function assertInputBounds(SupportCollection $pages): void
    {
        $maximumPages = max(1, (int) config('ai.subject_topic_extraction.max_pages', 300));
        $maximumCharacters = min(250000, max(1000, (int) config('ai.subject_topic_extraction.max_characters', 250000)));

        if ($pages->count() > $maximumPages) {
            throw ValidationException::withMessages(['source_file_ids' => "The selected files exceed the {$maximumPages}-page extraction limit."]);
        }

        if ($pages->sum(fn (FilePageEmbedding $page): int => mb_strlen($page->content)) > $maximumCharacters) {
            throw ValidationException::withMessages(['source_file_ids' => 'The selected files exceed the configured text limit. Select fewer files.']);
        }
    }

    /** @param Collection<int, ProgramFile|SubjectFile> $files
     * @return array<int, array{id: int, content_hash: string, title: string, mime_type: string, source_type?: string, file_type?: string, subject_id?: int|null}>
     */
    private function sourceSnapshot(Collection $files): array
    {
        return $files->map(function (ProgramFile|SubjectFile $file): array {
            $snapshot = [
                'id' => $file->id,
                'content_hash' => $file->content_hash,
                'title' => $file->title,
                'mime_type' => $file->mime_type,
            ];

            if ($file instanceof ProgramFile) {
                return [
                    ...$snapshot,
                    'source_type' => 'program_file',
                    'file_type' => $file->file_type?->value,
                    'subject_id' => $file->subject_id,
                ];
            }

            return $snapshot;
        })->all();
    }

    /** @return Collection<int, ProgramFile|SubjectFile> */
    private function filesForSnapshot(SubjectTopicExtraction $extraction, bool $lock = false): Collection
    {
        $snapshot = $extraction->source_files;
        if (! is_array($snapshot) || $snapshot === []) {
            throw new RuntimeException('The extraction source snapshot is unavailable.');
        }

        $programFileSnapshot = collect($snapshot)->every(fn (array $file): bool => ($file['source_type'] ?? null) === 'program_file');
        $subject = Subject::query()->findOrFail($extraction->subject_id);
        $query = $programFileSnapshot
            ? ProgramFile::query()->where('program_id', $subject->program_id)->where(function ($query) use ($subject): void {
                $query->whereNull('subject_id')->orWhere('subject_id', $subject->id);
            })
            : SubjectFile::query()->where('subject_id', $subject->id);
        $query->whereIn('id', collect($snapshot)->pluck('id'))->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $files = $query->get();
        if ($files->count() !== count($snapshot)) {
            throw new RuntimeException('The extraction source snapshot changed.');
        }

        return $files;
    }

    public function isSnapshotCurrent(SubjectTopicExtraction $extraction): bool
    {
        return $this->snapshotIsCurrent($extraction);
    }

    private function snapshotIsCurrent(SubjectTopicExtraction $extraction, bool $lock = false): bool
    {
        try {
            $files = $this->filesForSnapshot($extraction, $lock);
        } catch (RuntimeException) {
            return false;
        }

        return $this->sourceSnapshot($files) === $extraction->source_files
            && $files->every(fn (ProgramFile|SubjectFile $file): bool => $file->upload_status === FileUploadStatus::Uploaded
                && $file->mime_type === 'application/pdf'
                && $file->embedding_status === FileEmbeddingStatus::Complete);
    }

    /** @param array<string, mixed> $raw
     * @param  SupportCollection<int, FilePageEmbedding>  $pages
     * @return array{topics: array<int, array<string, mixed>>}
     */
    private function validateProposal(array $raw, SupportCollection $pages): array
    {
        $topics = $raw['topics'] ?? null;
        if (! is_array($topics) || count($topics) > 1000) {
            throw new RuntimeException('Invalid topic proposal.');
        }

        $availablePages = $pages->mapWithKeys(fn (FilePageEmbedding $page): array => [$page->subject_file_id.':'.$page->page_number => true]);
        $normalized = [];
        $codes = [];
        foreach ($topics as $index => $topic) {
            $parentIndex = $topic['parent_index'] ?? null;
            if (! is_array($topic)
                || ! $this->validText($topic['title'] ?? null, 255)
                || ! $this->validNullableText($topic['code'] ?? null, 100)
                || ! $this->validNullableText($topic['description'] ?? null, 2000)
                || ($parentIndex !== null && (! is_int($parentIndex) || $parentIndex < 0 || $parentIndex >= $index))) {
                throw new RuntimeException('Invalid topic proposal.');
            }

            $sourcePages = $topic['source_pages'] ?? null;
            if (! is_array($sourcePages) || $sourcePages === [] || count($sourcePages) > 20) {
                throw new RuntimeException('Invalid topic source citations.');
            }

            $citations = [];
            foreach ($sourcePages as $source) {
                if (! is_array($source)
                    || ! is_int($source['source_file_id'] ?? null)
                    || ! is_int($source['page_number'] ?? null)
                    || ! isset($availablePages[$source['source_file_id'].':'.$source['page_number']])) {
                    throw new RuntimeException('Invalid topic source citations.');
                }

                $citations[] = ['source_file_id' => $source['source_file_id'], 'page_number' => $source['page_number']];
            }

            $code = $this->nullableString($topic['code'] ?? null);
            if ($code !== null && in_array(mb_strtolower($code), $codes, true)) {
                throw new RuntimeException('Topic codes must be unique within the proposal.');
            }
            if ($code !== null) {
                $codes[] = mb_strtolower($code);
            }

            $normalized[] = [
                'code' => $code,
                'title' => trim($topic['title']),
                'description' => $this->nullableString($topic['description'] ?? null),
                'parent_index' => $parentIndex,
                'source_pages' => array_values(array_unique($citations, SORT_REGULAR)),
            ];
        }

        $this->assertNumberedTopicHierarchy($normalized);

        return ['topics' => $normalized];
    }

    /** @param array<int, array<string, mixed>> $topics */
    private function assertNumberedTopicHierarchy(array $topics): void
    {
        $indicesByCode = [];
        foreach ($topics as $index => $topic) {
            if (is_string($topic['code']) && preg_match('/^\d+(?:\.\d+)+$/', $topic['code']) === 1) {
                $indicesByCode[$topic['code']] = $index;
            }
        }

        $siblingsByParent = [];
        foreach ($topics as $index => $topic) {
            $code = $topic['code'];
            if (! is_string($code) || preg_match('/^\d+(?:\.\d+)+$/', $code) !== 1) {
                continue;
            }

            $segments = explode('.', $code);
            $expectedParentCode = null;
            if (count($segments) === 2 && $segments[1] !== '0') {
                $expectedParentCode = $segments[0].'.0';
            } elseif (count($segments) > 2) {
                $expectedParentCode = implode('.', array_slice($segments, 0, -1));
            }

            if ($expectedParentCode !== null && isset($indicesByCode[$expectedParentCode])
                && $topic['parent_index'] !== $indicesByCode[$expectedParentCode]) {
                throw new RuntimeException('Topic parentage does not match its numbered code hierarchy.');
            }

            if (count($segments) === 2 && $segments[1] === '0' && $topic['parent_index'] !== null) {
                throw new RuntimeException('Topic parentage does not match its numbered code hierarchy.');
            }

            $parentKey = $topic['parent_index'] === null ? 'root' : (string) $topic['parent_index'];
            $siblingsByParent[$parentKey][] = $code;
        }

        foreach ($siblingsByParent as $siblingCodes) {
            $orderedCodes = $siblingCodes;
            usort($orderedCodes, version_compare(...));

            if ($siblingCodes !== $orderedCodes) {
                throw new RuntimeException('Numbered topic siblings are not in code order.');
            }
        }
    }

    /** @param array<string, mixed> $raw
     * @return array{topics: array<int, array<string, mixed>>}
     */
    private function proposalForReview(array $raw): array
    {
        $topics = $raw['topics'] ?? [];
        if (! is_array($topics)) {
            return ['topics' => []];
        }

        return ['topics' => array_values(array_map(function (mixed $topic): array {
            if (! is_array($topic)) {
                return [
                    'code' => null,
                    'title' => 'Unverified topic',
                    'description' => null,
                    'parent_index' => null,
                    'source_pages' => [],
                ];
            }

            $citations = is_array($topic['source_pages'] ?? null) ? $topic['source_pages'] : [];

            return [
                'code' => is_string($topic['code'] ?? null) ? $topic['code'] : null,
                'title' => is_string($topic['title'] ?? null) ? $topic['title'] : 'Unverified topic',
                'description' => is_string($topic['description'] ?? null) ? $topic['description'] : null,
                'parent_index' => is_int($topic['parent_index'] ?? null) ? $topic['parent_index'] : null,
                'source_pages' => array_values(array_map(fn (mixed $citation): array => [
                    'source_file_id' => is_array($citation) && is_int($citation['source_file_id'] ?? null) ? $citation['source_file_id'] : 0,
                    'page_number' => is_array($citation) && is_int($citation['page_number'] ?? null) ? $citation['page_number'] : 0,
                ], $citations)),
            ];
        }, $topics))];
    }

    private function validationNote(string $message): string
    {
        return match ($message) {
            'Invalid topic source citations.' => 'One or more source citations do not match a page in the selected files. Verify each citation before publishing.',
            'Topic parentage does not match its numbered code hierarchy.' => 'One or more numbered topics has a parent that does not match its official code hierarchy. Correct the parent assignments before publishing.',
            'Numbered topic siblings are not in code order.' => 'Numbered sibling topics are not in code order. Reorder them before publishing.',
            'Topic codes must be unique within the proposal.' => 'Topic codes are duplicated. Review and correct the codes before publishing.',
            default => 'The generated topic fields or parent hierarchy did not pass automatic checks. Review and correct the proposal before publishing.',
        };
    }

    /** @param array<string, mixed> $selection
     * @return array{parent_index: ?int, parent_topic_id: ?int}
     */
    private function reviewParentReference(array $selection): array
    {
        $reference = $selection['parent_reference'] ?? null;
        if (is_string($reference) && preg_match('/^(proposal|existing):(\d+)$/', $reference, $matches) === 1) {
            return $matches[1] === 'proposal'
                ? ['parent_index' => (int) $matches[2], 'parent_topic_id' => null]
                : ['parent_index' => null, 'parent_topic_id' => (int) $matches[2]];
        }

        return [
            'parent_index' => ($selection['parent_index'] ?? '') === '' ? null : (int) $selection['parent_index'],
            'parent_topic_id' => null,
        ];
    }

    /** @param array<int, array<string, mixed>> $selected
     * @param  Collection<int, SyllabusTopic>  $existingTopics
     */
    private function assertReviewHierarchy(array $selected, Collection $existingTopics): void
    {
        $positions = [];
        $matchedExistingIds = [];
        foreach ($selected as $index => $topic) {
            if (isset($positions[$topic['sort_order']])) {
                throw ValidationException::withMessages(['topics' => 'Each selected topic must have a unique order.']);
            }
            $positions[$topic['sort_order']] = true;

            $parentIndex = $topic['parent_index'];
            if ($parentIndex !== null && (! isset($selected[$parentIndex]) || $parentIndex === $index)) {
                throw ValidationException::withMessages(["topics.$index.parent_index" => 'A selected topic must have a selected parent.']);
            }

            $parentTopicId = $topic['parent_topic_id'];
            if ($parentTopicId !== null && (! $existingTopics->has($parentTopicId)
                || $parentTopicId === $topic['match_existing_topic_id'])) {
                throw ValidationException::withMessages(["topics.$index.parent_reference" => 'Choose a parent topic in this subject.']);
            }

            $matchedExistingTopicId = $topic['match_existing_topic_id'];
            if ($matchedExistingTopicId === null) {
                continue;
            }

            $existingTopic = $existingTopics->get($matchedExistingTopicId);
            if (! $existingTopic instanceof SyllabusTopic
                || mb_strtolower(trim((string) $topic['code'])) !== mb_strtolower(trim((string) $existingTopic->code))
                || mb_strtolower(trim($topic['title'])) !== mb_strtolower(trim($existingTopic->title))) {
                throw ValidationException::withMessages(["topics.$index.match_existing_topic_id" => 'Only an existing topic with the same code and title can be matched.']);
            }

            if (isset($matchedExistingIds[$matchedExistingTopicId])) {
                throw ValidationException::withMessages(['topics' => 'An existing topic can only be matched once in this review.']);
            }

            $matchedExistingIds[$matchedExistingTopicId] = true;

            $resolvedParentTopicId = $parentTopicId;
            if ($parentIndex !== null) {
                $resolvedParentTopicId = $selected[$parentIndex]['match_existing_topic_id'];
            }

            if ($resolvedParentTopicId !== $existingTopic->parent_id) {
                throw ValidationException::withMessages(["topics.$index.parent_reference" => 'The matched topic must retain its existing parent.']);
            }
        }

        foreach (array_keys($selected) as $index) {
            $seen = [];
            $cursor = $index;
            while ($cursor !== null) {
                if (isset($seen[$cursor])) {
                    throw ValidationException::withMessages(['topics' => 'The selected topic hierarchy cannot contain a cycle.']);
                }
                $seen[$cursor] = true;
                $cursor = $selected[$cursor]['parent_index'];
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $selected
     * @param  Collection<int, SyllabusTopic>  $existingTopics
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array{topic: SyllabusTopic, data: array<string, mixed>}>, 2: array<int, array{code: ?string, title: string, reasons: array<int, string>}>}
     */
    private function excludeConflictingTopics(array $selected, Collection $existingTopics): array
    {
        $existingCodes = $existingTopics
            ->pluck('code')
            ->filter()
            ->mapWithKeys(fn (string $code): array => [mb_strtolower($code) => true])
            ->all();
        $existingTitlesByParent = $existingTopics
            ->groupBy(fn (SyllabusTopic $topic): string => $topic->parent_id === null ? 'root' : 'existing:'.$topic->parent_id)
            ->map(fn (Collection $topics): array => $topics
                ->mapWithKeys(fn (SyllabusTopic $topic): array => [mb_strtolower($topic->title) => true])
                ->all())
            ->all();

        $pending = $selected;
        uasort($pending, fn (array $left, array $right): int => $left['sort_order'] <=> $right['sort_order']);

        $publishable = [];
        $matchedExisting = [];
        $skipped = [];
        $seenCodes = $existingCodes;
        $seenTitles = [];

        while ($pending !== []) {
            $madeProgress = false;

            foreach ($pending as $index => $topic) {
                $parentIndex = $topic['parent_index'];

                if ($parentIndex !== null && isset($skipped[$parentIndex])) {
                    $skipped[$index] = [
                        'code' => $topic['code'],
                        'title' => $topic['title'],
                        'reasons' => ['Its parent topic was skipped, so this topic could not be added without breaking the hierarchy.'],
                    ];
                    unset($pending[$index]);
                    $madeProgress = true;

                    continue;
                }

                if ($parentIndex !== null
                    && ! array_key_exists($parentIndex, $publishable)
                    && ! array_key_exists($parentIndex, $matchedExisting)) {
                    continue;
                }

                if ($topic['match_existing_topic_id'] !== null) {
                    $matchedExisting[$index] = [
                        'topic' => $existingTopics->get($topic['match_existing_topic_id']),
                        'data' => $topic,
                    ];
                    unset($pending[$index]);
                    $madeProgress = true;

                    continue;
                }

                $reasons = [];
                $normalizedCode = $topic['code'] === null ? null : mb_strtolower($topic['code']);
                $normalizedTitle = mb_strtolower($topic['title']);
                $parentTopicId = $topic['parent_topic_id']
                    ?? ($parentIndex === null ? null : ($matchedExisting[$parentIndex]['topic']->id ?? null));
                $parentKey = $parentTopicId !== null
                    ? 'existing:'.$parentTopicId
                    : ($parentIndex === null ? 'root' : 'proposal:'.$parentIndex);
                $titleKey = $parentKey.':'.$normalizedTitle;

                if ($normalizedCode !== null && isset($seenCodes[$normalizedCode])) {
                    $reasons[] = 'The topic code already exists in this subject or an earlier selected topic.';
                }

                if (isset($seenTitles[$titleKey]) || isset($existingTitlesByParent[$parentKey][$normalizedTitle])) {
                    $reasons[] = 'The topic title already exists at this level in the subject or proposal.';
                }

                if ($reasons !== []) {
                    $skipped[$index] = [
                        'code' => $topic['code'],
                        'title' => $topic['title'],
                        'reasons' => $reasons,
                    ];
                } else {
                    $publishable[$index] = $topic;
                    $seenTitles[$titleKey] = true;

                    if ($normalizedCode !== null) {
                        $seenCodes[$normalizedCode] = true;
                    }
                }

                unset($pending[$index]);
                $madeProgress = true;
            }

            if (! $madeProgress) {
                foreach ($pending as $index => $topic) {
                    $skipped[$index] = [
                        'code' => $topic['code'],
                        'title' => $topic['title'],
                        'reasons' => ['Its parent relationship could not be resolved.'],
                    ];
                }

                break;
            }
        }

        ksort($publishable);
        ksort($matchedExisting);
        ksort($skipped);

        return [$publishable, $matchedExisting, array_values($skipped)];
    }

    private function validText(mixed $value, int $maxLength): bool
    {
        return is_string($value) && trim($value) !== '' && mb_strlen($value) <= $maxLength;
    }

    private function validNullableText(mixed $value, int $maxLength): bool
    {
        return $value === null || (is_string($value) && mb_strlen($value) <= $maxLength);
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function fail(SubjectTopicExtraction $extraction, SubjectTopicExtractionError $error): void
    {
        $extraction->forceFill([
            'status' => SubjectTopicExtractionStatus::Failed,
            'error_code' => $error,
            'proposal' => null,
        ])->save();
    }
}
