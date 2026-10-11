<?php

namespace App\Services;

use App\Ai\Agents\CurriculumProposalGenerator;
use App\CurriculumGenerationKind;
use App\CurriculumGenerationStatus;
use App\Exceptions\CurriculumGenerationException;
use App\Jobs\GenerateCurriculumProposal;
use App\Models\Concept;
use App\Models\CurriculumGeneration;
use App\Models\Program;
use App\Models\ProgramFile;
use App\Models\Subject;
use App\Models\SyllabusTopic;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

class CurriculumGenerationService
{
    private const PROMPT_VERSION = 'curriculum-proposal-v1';

    private const SCHEMA_VERSION = '1';

    public function __construct(private PdfTextExtractor $pdfTextExtractor) {}

    public function unavailableReason(): ?string
    {
        if (! config('ai.curriculum_generation.enabled')) {
            return 'Author-reviewed curriculum generation is disabled.';
        }

        if (! filled(config('ai.curriculum_generation.provider')) || ! filled(config('ai.curriculum_generation.model'))) {
            return 'Configure a curriculum generation provider and model.';
        }

        return null;
    }

    /** @return list<string> */
    public function availableModels(): array
    {
        return array_values(array_unique(array_filter([
            ...config('ai.curriculum_generation.models', []),
            config('ai.curriculum_generation.model'),
        ], fn (mixed $model): bool => is_string($model) && $model !== '')));
    }

    /** @param list<int|string> $sourceFileIds */
    public function queue(
        Program $program,
        CurriculumGenerationKind $kind,
        array $sourceFileIds,
        User $requester,
        ?Subject $subject = null,
        ?SyllabusTopic $topic = null,
        ?string $requestContext = null,
        bool $ungrounded = false,
        ?string $model = null,
    ): CurriculumGeneration {
        $this->assertAvailable();

        if ($requestContext !== null && mb_strlen($requestContext) > 4000) {
            throw ValidationException::withMessages(['request_context' => 'Keep the author instructions to 4,000 characters or fewer.']);
        }

        $model ??= (string) config('ai.curriculum_generation.model');
        $sourceFileIds = array_map('intval', $sourceFileIds);
        $fileIds = array_values(array_unique($sourceFileIds));
        if ($fileIds === [] || count($fileIds) !== count($sourceFileIds)) {
            throw ValidationException::withMessages(['source_file_ids' => 'Select one or more distinct program files.']);
        }

        if (count($fileIds) > max(1, (int) config('ai.curriculum_generation.max_files', 10))) {
            throw ValidationException::withMessages(['source_file_ids' => 'Select no more than the allowed number of source files.']);
        }

        return DB::transaction(function () use ($program, $kind, $fileIds, $requester, $subject, $topic, $requestContext, $ungrounded, $model): CurriculumGeneration {
            $lockedProgram = Program::query()->lockForUpdate()->findOrFail($program->id);
            $this->assertScope($lockedProgram, $kind, $subject, $topic);

            $query = ProgramFile::query()
                ->where('program_id', $lockedProgram->id)
                ->whereIn('id', $fileIds)
                ->where('upload_status', 'uploaded');
            if ($subject !== null) {
                $query->where(fn ($scope) => $scope->whereNull('subject_id')->orWhere('subject_id', $subject->id));
            }
            $files = $query->orderBy('id')->lockForUpdate()->get();

            if ($files->count() !== count($fileIds)) {
                throw ValidationException::withMessages(['source_file_ids' => 'Select only uploaded shared files or files associated with the selected subject.']);
            }

            $sourceFiles = $this->sourceSnapshot($files);
            $sourceHash = hash('sha256', json_encode($sourceFiles, JSON_THROW_ON_ERROR));
            $provider = (string) config('ai.curriculum_generation.provider');
            $requestHash = $this->requestHash(
                $kind,
                $subject?->id,
                $topic?->id,
                $requestContext,
                $ungrounded,
                $sourceHash,
                $provider,
                $model,
            );

            if ($lockedProgram->curriculumGenerations()
                ->where('kind', $kind)
                ->where('request_hash', $requestHash)
                ->where('provider', $provider)
                ->where('model', $model)
                ->whereIn('status', [
                    CurriculumGenerationStatus::Queued,
                    CurriculumGenerationStatus::Generating,
                    CurriculumGenerationStatus::Reviewing,
                ])
                ->exists()) {
                throw ValidationException::withMessages(['source_file_ids' => 'A proposal for these sources and instructions is already queued or awaiting review.']);
            }

            $generation = $lockedProgram->curriculumGenerations()->create([
                'subject_id' => $subject?->id,
                'syllabus_topic_id' => $topic?->id,
                'requested_by' => $requester->id,
                'kind' => $kind,
                'status' => CurriculumGenerationStatus::Queued,
                'request_context' => $requestContext,
                'ungrounded_requested' => $ungrounded,
                'request_hash' => $requestHash,
                'source_hash' => $sourceHash,
                'source_files' => $sourceFiles,
                'provider' => $provider,
                'model' => $model,
                'prompt_version' => self::PROMPT_VERSION,
                'schema_version' => self::SCHEMA_VERSION,
                'correlation_id' => (string) Str::uuid(),
            ]);

            GenerateCurriculumProposal::dispatch($generation->id)->afterCommit();

            return $generation;
        });
    }

    public function generate(int $generationId): void
    {
        $claimed = CurriculumGeneration::query()
            ->whereKey($generationId)
            ->where('status', CurriculumGenerationStatus::Queued)
            ->update(['status' => CurriculumGenerationStatus::Generating, 'error_code' => null, 'updated_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $generation = CurriculumGeneration::query()->with(['program', 'subject', 'syllabusTopic'])->findOrFail($generationId);

        try {
            $this->assertAvailable();
            $files = $this->filesForSnapshot($generation);
            $sourcePages = $this->extractSourcePages($generation, $files, textEnabled: ! $generation->ungrounded_requested);
            if ($sourcePages === [] && ! $generation->ungrounded_requested) {
                throw new CurriculumGenerationException('source_text_unavailable');
            }

            $this->assertPageBounds($sourcePages);
            $sourceFiles = $this->sourceSnapshot($files, $sourcePages, $generation->source_files);
            $sourceHash = hash('sha256', json_encode($sourceFiles, JSON_THROW_ON_ERROR));
            $generation->forceFill([
                'source_files' => $sourceFiles,
                'source_hash' => $sourceHash,
                'request_hash' => $this->requestHash(
                    $generation->kind,
                    $generation->subject_id,
                    $generation->syllabus_topic_id,
                    $generation->request_context,
                    $generation->ungrounded_requested,
                    $sourceHash,
                    $generation->provider,
                    $generation->model,
                ),
            ])->save();

            $scope = match ($generation->kind) {
                CurriculumGenerationKind::Subjects => ['program' => ['name' => $generation->program->name, 'code' => $generation->program->code]],
                CurriculumGenerationKind::Topics => ['subject' => ['name' => $generation->subject->name, 'code' => $generation->subject->code]],
                CurriculumGenerationKind::Concepts => [
                    'subject' => ['name' => $generation->subject->name, 'code' => $generation->subject->code],
                    'topic' => $generation->syllabusTopic === null ? null : ['title' => $generation->syllabusTopic->title, 'code' => $generation->syllabusTopic->code],
                ],
            };
            $input = json_encode([
                'scope' => $scope,
                'request_context' => $generation->request_context,
                'selected_sources' => collect($sourceFiles)->map(fn (array $file): array => [
                    'id' => $file['id'],
                    'title' => $file['title'],
                    'file_type' => $file['file_type'],
                    'subject_id' => $file['subject_id'],
                ])->all(),
                'source_pages' => $sourcePages,
                'ungrounded' => $generation->ungrounded_requested,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

            $response = (new CurriculumProposalGenerator(
                $generation->kind,
                $generation->model,
                $generation->ungrounded_requested,
            ))->prompt(
                $input,
                provider: $generation->provider,
                model: $generation->model,
                timeout: max(1, (int) config('ai.curriculum_generation.timeout', 240)),
            );

            if (! $response instanceof StructuredAgentResponse) {
                throw new CurriculumGenerationException('invalid_proposal');
            }

            $proposal = $this->validateProposal($generation, $response->toArray(), $sourcePages);
            $generation->forceFill([
                'proposal' => $proposal,
                'status' => CurriculumGenerationStatus::Reviewing,
                'generated_at' => now(),
                'error_code' => null,
            ])->save();
        } catch (CurriculumGenerationException $exception) {
            $this->fail($generation, $exception->errorCode);
        } catch (Throwable $exception) {
            Log::warning('Curriculum proposal generation failed.', [
                'curriculum_generation_id' => $generation->id,
                'correlation_id' => $generation->correlation_id,
                'exception_class' => $exception::class,
            ]);
            CurriculumGeneration::query()
                ->whereKey($generation->id)
                ->where('status', CurriculumGenerationStatus::Generating)
                ->update([
                    'status' => CurriculumGenerationStatus::Queued,
                    'updated_at' => now(),
                ]);

            throw $exception;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $selections
     */
    public function review(CurriculumGeneration $generation, string $proposalHash, array $selections, User $reviewer): void
    {
        DB::transaction(function () use ($generation, $proposalHash, $selections, $reviewer): void {
            $locked = CurriculumGeneration::query()->lockForUpdate()->findOrFail($generation->id);
            if ($locked->status !== CurriculumGenerationStatus::Reviewing || ! is_array($locked->proposal)) {
                throw ValidationException::withMessages(['generation' => 'Only a proposal awaiting review can be approved.']);
            }

            $currentProposalHash = hash('sha256', json_encode($locked->proposal, JSON_THROW_ON_ERROR));
            if (! hash_equals($currentProposalHash, $proposalHash)) {
                throw ValidationException::withMessages(['proposal_hash' => 'This proposal changed. Reload it before reviewing.']);
            }

            $items = $locked->proposal['items'] ?? [];
            if (count($selections) !== count($items)) {
                throw ValidationException::withMessages(['items' => 'The review form does not match the saved proposal.']);
            }

            $subject = $locked->subject_id === null ? null : Subject::query()->lockForUpdate()->findOrFail($locked->subject_id);
            if ($locked->kind !== CurriculumGenerationKind::Subjects
                && ($subject === null || (int) $subject->program_id !== (int) $locked->program_id)) {
                throw ValidationException::withMessages(['generation' => 'The curriculum scope changed. Start a new proposal.']);
            }
            if ($locked->syllabus_topic_id !== null) {
                $topic = SyllabusTopic::query()->lockForUpdate()->findOrFail($locked->syllabus_topic_id);
                if ($subject === null || (int) $topic->subject_id !== (int) $subject->id) {
                    throw ValidationException::withMessages(['generation' => 'The topic scope changed. Start a new proposal.']);
                }
            }

            $proposedById = collect($items)->keyBy('draft_id');
            $selectionById = collect($selections)->keyBy('draft_id');
            if ($selectionById->count() !== count($items) || $selectionById->keys()->diff($proposedById->keys())->isNotEmpty()) {
                throw ValidationException::withMessages(['items' => 'Every proposed item must appear exactly once in the review form.']);
            }

            $previousItems = collect($locked->reviewed_proposal['items'] ?? [])->keyBy('draft_id');
            $reviewedItems = [];
            $pending = [];
            $approvedIds = [];

            foreach ($items as $index => $proposalItem) {
                $draftId = $proposalItem['draft_id'];
                $previous = $previousItems->get($draftId);
                if (in_array($previous['decision'] ?? null, ['approved', 'rejected'], true)) {
                    $reviewedItems[$draftId] = $previous;
                    if ($previous['decision'] === 'approved') {
                        $approvedIds[$draftId] = $previous['canonical_id'];
                    }

                    continue;
                }

                $selection = $selectionById->get($draftId);
                $titleField = $locked->kind === CurriculumGenerationKind::Subjects ? 'name' : 'title';
                $title = trim((string) ($selection[$titleField] ?? ''));
                $code = trim((string) ($selection['code'] ?? ''));
                $description = $selection['description'] ?? null;
                if (! $this->validText($title, 255) || ! $this->validText($code, 100) || ! $this->validNullableText($description, 2000)) {
                    throw ValidationException::withMessages(["items.$index" => 'Each item needs a title, a code, and a valid optional description.']);
                }

                $parentDraftId = $selection['parent_draft_id'] ?? null;
                if ($locked->kind === CurriculumGenerationKind::Topics) {
                    if ($parentDraftId !== null && (! is_string($parentDraftId) || ! $proposedById->has($parentDraftId))) {
                        throw ValidationException::withMessages(["items.$index.parent_draft_id" => 'Choose a parent from this proposal.']);
                    }
                } elseif ($parentDraftId !== null) {
                    throw ValidationException::withMessages(["items.$index.parent_draft_id" => 'Only topic proposals can have a proposed parent.']);
                }

                $include = filter_var($selection['include'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $reviewedItems[$draftId] = [
                    ...$proposalItem,
                    $titleField => $title,
                    'code' => $code,
                    'description' => $description === null ? null : trim((string) $description),
                    'parent_draft_id' => $parentDraftId,
                    'sort_order' => (int) ($selection['sort_order'] ?? $index),
                    'decision' => $include ? 'pending' : 'rejected',
                    'canonical_id' => null,
                    'review_notes' => [],
                ];

                if ($include) {
                    $pending[] = $draftId;
                }
            }

            $progress = true;
            while ($pending !== [] && $progress) {
                $progress = false;
                foreach ($pending as $pendingIndex => $draftId) {
                    $item = $reviewedItems[$draftId];
                    $parentDraftId = $item['parent_draft_id'];
                    if ($parentDraftId !== null) {
                        $parentDecision = $reviewedItems[$parentDraftId]['decision'] ?? null;
                        if ($parentDecision === 'rejected' || $parentDecision === 'conflict') {
                            $reviewedItems[$draftId]['decision'] = 'conflict';
                            $reviewedItems[$draftId]['review_notes'] = ['The selected parent was not approved.'];
                            unset($pending[$pendingIndex]);
                            $progress = true;

                            continue;
                        }
                        if (! isset($approvedIds[$parentDraftId])) {
                            continue;
                        }
                    }

                    $conflict = $this->canonicalConflict($locked, $item);
                    if ($conflict !== null) {
                        $reviewedItems[$draftId]['decision'] = 'conflict';
                        $reviewedItems[$draftId]['review_notes'] = [$conflict];
                        unset($pending[$pendingIndex]);
                        $progress = true;

                        continue;
                    }

                    if ($locked->kind === CurriculumGenerationKind::Topics) {
                        $item = $this->refreshTopicCitations($locked, $item);
                        $reviewedItems[$draftId] = $item;
                    }

                    try {
                        $canonicalId = DB::transaction(fn (): int => $this->createCanonicalItem($locked, $item, $approvedIds));
                    } catch (QueryException $exception) {
                        if (! in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                            throw $exception;
                        }
                        $reviewedItems[$draftId]['decision'] = 'conflict';
                        $reviewedItems[$draftId]['review_notes'] = ['Another curriculum item with this code was approved at the same time.'];
                        unset($pending[$pendingIndex]);
                        $progress = true;

                        continue;
                    }

                    $reviewedItems[$draftId]['decision'] = 'approved';
                    $reviewedItems[$draftId]['canonical_id'] = $canonicalId;
                    $approvedIds[$draftId] = $canonicalId;
                    unset($pending[$pendingIndex]);
                    $progress = true;
                }

                $pending = array_values($pending);
            }

            foreach ($pending as $draftId) {
                $reviewedItems[$draftId]['decision'] = 'conflict';
                $reviewedItems[$draftId]['review_notes'] = ['Resolve the topic hierarchy order before approving this item.'];
            }

            $orderedReview = collect($items)->map(fn (array $item): array => $reviewedItems[$item['draft_id']])->values()->all();
            $hasConflicts = collect($orderedReview)->contains(fn (array $item): bool => $item['decision'] === 'conflict');
            $approvedCount = collect($orderedReview)->where('decision', 'approved')->count();
            $status = $hasConflicts
                ? CurriculumGenerationStatus::Reviewing
                : ($approvedCount > 0 ? CurriculumGenerationStatus::Approved : CurriculumGenerationStatus::Rejected);

            $locked->forceFill([
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => $status === CurriculumGenerationStatus::Reviewing ? null : now(),
                'reviewed_proposal' => ['items' => $orderedReview],
                'status' => $status,
            ])->save();
        });
    }

    private function assertAvailable(): void
    {
        $reason = $this->unavailableReason();
        if ($reason !== null) {
            throw ValidationException::withMessages(['generation' => $reason]);
        }
    }

    private function requestHash(
        CurriculumGenerationKind $kind,
        ?int $subjectId,
        ?int $topicId,
        ?string $requestContext,
        bool $ungrounded,
        string $sourceHash,
        string $provider,
        string $model,
    ): string {
        return hash('sha256', json_encode([
            'kind' => $kind->value,
            'subject_id' => $subjectId,
            'syllabus_topic_id' => $topicId,
            'request_context' => $requestContext,
            'ungrounded' => $ungrounded,
            'source_hash' => $sourceHash,
            'provider' => $provider,
            'model' => $model,
            'prompt_version' => self::PROMPT_VERSION,
            'schema_version' => self::SCHEMA_VERSION,
        ], JSON_THROW_ON_ERROR));
    }

    private function assertScope(Program $program, CurriculumGenerationKind $kind, ?Subject $subject, ?SyllabusTopic $topic): void
    {
        if ($kind === CurriculumGenerationKind::Subjects && ($subject !== null || $topic !== null)) {
            throw ValidationException::withMessages(['kind' => 'Subject proposals are scoped to a program.']);
        }

        if ($kind === CurriculumGenerationKind::Subjects) {
            return;
        }

        if ($subject === null || (int) $subject->program_id !== (int) $program->id) {
            throw ValidationException::withMessages(['subject_id' => 'Choose a subject in this program.']);
        }

        if ($kind === CurriculumGenerationKind::Topics && $topic !== null) {
            throw ValidationException::withMessages(['syllabus_topic_id' => 'Topic proposals are scoped to a subject.']);
        }

        if ($topic !== null && (int) $topic->subject_id !== (int) $subject->id) {
            throw ValidationException::withMessages(['syllabus_topic_id' => 'Choose a topic in this subject.']);
        }
    }

    /** @return Collection<int, ProgramFile> */
    private function filesForSnapshot(CurriculumGeneration $generation): Collection
    {
        $snapshot = $generation->source_files;
        if (! is_array($snapshot) || $snapshot === []) {
            throw new CurriculumGenerationException('source_snapshot_unavailable');
        }

        $fileIds = collect($snapshot)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $query = ProgramFile::query()->where('program_id', $generation->program_id)->whereIn('id', $fileIds);
        if ($generation->subject_id !== null) {
            $query->where(fn ($scope) => $scope->whereNull('subject_id')->orWhere('subject_id', $generation->subject_id));
        }
        $files = $query->orderBy('id')->get();
        if ($files->count() !== count($snapshot)) {
            throw new CurriculumGenerationException('source_changed');
        }

        foreach ($files as $index => $file) {
            $source = $snapshot[$index] ?? [];
            if ($file->upload_status?->value !== 'uploaded'
                || $file->title !== ($source['title'] ?? null)
                || $file->mime_type !== ($source['mime_type'] ?? null)
                || $file->file_type?->value !== ($source['file_type'] ?? null)
                || $file->original_filename !== ($source['original_filename'] ?? null)
                || (int) $file->file_size !== (int) ($source['file_size'] ?? 0)
                || (int) ($file->subject_id ?? 0) !== (int) ($source['subject_id'] ?? 0)
                || (($source['content_hash'] ?? null) !== null && $file->content_hash !== $source['content_hash'])) {
                throw new CurriculumGenerationException('source_changed');
            }
        }

        return $files;
    }

    /**
     * @param  Collection<int, ProgramFile>  $files
     * @return list<array{source_file_id: int, page_number: int, text: string}>
     */
    private function extractSourcePages(CurriculumGeneration $generation, Collection $files, bool $textEnabled): array
    {
        $pages = [];
        $fileSnapshots = collect($generation->source_files)->keyBy('id');

        foreach ($files as $file) {
            $temporaryDirectory = sys_get_temp_dir().'/examgraph-generation-'.Str::uuid();
            File::ensureDirectoryExists($temporaryDirectory);
            $pdfPath = $temporaryDirectory.'/source.pdf';

            try {
                $extractText = $textEnabled && $file->mime_type === 'application/pdf';
                $sourceHash = $this->copySourceToLocalFile($file, $pdfPath, requirePdf: $extractText);
                $snapshot = $fileSnapshots->get($file->id);
                if (($snapshot['content_hash'] ?? null) !== null && $snapshot['content_hash'] !== $sourceHash) {
                    throw new CurriculumGenerationException('source_changed');
                }
                if ($file->content_hash !== null && $file->content_hash !== $sourceHash) {
                    throw new CurriculumGenerationException('source_changed');
                }

                if ($extractText) {
                    try {
                        foreach ($this->pdfTextExtractor->extract($pdfPath) as $page) {
                            $pages[] = ['source_file_id' => $file->id, 'page_number' => $page['page'], 'text' => $page['content']];
                        }
                    } catch (Throwable) {
                        throw new CurriculumGenerationException('source_unavailable');
                    }
                }

                if ($file->content_hash === null) {
                    $file->forceFill(['content_hash' => $sourceHash])->save();
                }
            } finally {
                File::deleteDirectory($temporaryDirectory);
            }
        }

        return $pages;
    }

    private function copySourceToLocalFile(ProgramFile $file, string $destination, bool $requirePdf): string
    {
        $expectedPrefix = 'program-files/'.$file->program_id.'/';
        $legacyPathIsMapped = DB::table('legacy_program_file_sources')
            ->where('legacy_type', 'subject_file')
            ->where('program_file_id', $file->id)
            ->where('storage_disk', $file->storage_disk)
            ->where('storage_key', $file->storage_key)
            ->exists();
        if (! str_starts_with($file->storage_key, $expectedPrefix) && ! $legacyPathIsMapped) {
            throw new CurriculumGenerationException('source_unavailable');
        }

        $source = Storage::disk($file->storage_disk)->readStream($file->storage_key);
        $target = fopen($destination, 'wb');
        if (! is_resource($source) || ! is_resource($target)) {
            if (is_resource($source)) {
                fclose($source);
            }
            if (is_resource($target)) {
                fclose($target);
            }
            throw new CurriculumGenerationException('source_unavailable');
        }

        try {
            if (stream_copy_to_stream($source, $target) === false) {
                throw new CurriculumGenerationException('source_unavailable');
            }
        } finally {
            fclose($source);
            fclose($target);
        }

        $fileSize = filesize($destination);
        if ($fileSize < 1 || $fileSize > 104857600
            || ($requirePdf && ($fileSize < 5 || file_get_contents($destination, false, null, 0, 5) !== '%PDF-'))
            || ($file->file_size !== null && $fileSize !== $file->file_size)) {
            throw new CurriculumGenerationException('source_unavailable');
        }

        $sourceHash = hash_file('sha256', $destination);
        if ($sourceHash === false) {
            throw new CurriculumGenerationException('source_unavailable');
        }

        return $sourceHash;
    }

    /** @param list<array{source_file_id: int, page_number: int, text: string}> $sourcePages */
    private function assertPageBounds(array $sourcePages): void
    {
        $pageLimit = max(1, (int) config('ai.curriculum_generation.max_pages', 300));
        $characterLimit = max(1, (int) config('ai.curriculum_generation.max_characters', 250000));
        $characters = array_sum(array_map(fn (array $page): int => mb_strlen($page['text']), $sourcePages));
        if (count($sourcePages) > $pageLimit || $characters > $characterLimit) {
            throw new CurriculumGenerationException('source_limit_exceeded');
        }
    }

    /** @param array<string, mixed> $item */
    private function canonicalConflict(CurriculumGeneration $generation, array $item): ?string
    {
        $titleField = $generation->kind === CurriculumGenerationKind::Subjects ? 'name' : 'title';
        $normalizedCode = mb_strtolower($item['code']);
        $normalizedTitle = mb_strtolower($item[$titleField]);

        $conflictExists = match ($generation->kind) {
            CurriculumGenerationKind::Subjects => Subject::query()
                ->where('program_id', $generation->program_id)
                ->where(fn ($query) => $query->whereRaw('lower(code) = ?', [$normalizedCode])->orWhereRaw('lower(name) = ?', [$normalizedTitle]))
                ->exists(),
            CurriculumGenerationKind::Topics => SyllabusTopic::query()
                ->where('subject_id', $generation->subject_id)
                ->where(fn ($query) => $query->whereRaw('lower(code) = ?', [$normalizedCode])->orWhereRaw('lower(title) = ?', [$normalizedTitle]))
                ->exists(),
            CurriculumGenerationKind::Concepts => Concept::query()
                ->where('subject_id', $generation->subject_id)
                ->where(fn ($query) => $query->whereRaw('lower(code) = ?', [$normalizedCode])->orWhereRaw('lower(title) = ?', [$normalizedTitle]))
                ->exists(),
        };

        return $conflictExists ? 'A curriculum item with this code or title already exists in the selected scope.' : null;
    }

    /** @param array<string, int> $approvedIds */
    private function createCanonicalItem(CurriculumGeneration $generation, array $item, array $approvedIds): int
    {
        $sortOrder = max(0, (int) $item['sort_order']);

        return match ($generation->kind) {
            CurriculumGenerationKind::Subjects => Subject::query()->create([
                'program_id' => $generation->program_id,
                'name' => $item['name'],
                'code' => $item['code'],
                'description' => $item['description'],
                'sort_order' => $sortOrder,
            ])->id,
            CurriculumGenerationKind::Topics => $this->createTopic($generation, $item, $approvedIds, $sortOrder),
            CurriculumGenerationKind::Concepts => Concept::query()->create([
                'subject_id' => $generation->subject_id,
                'syllabus_topic_id' => $generation->syllabus_topic_id,
                'code' => $item['code'],
                'title' => $item['title'],
                'description' => $item['description'],
                'sort_order' => $sortOrder,
            ])->id,
        };
    }

    /** @param array<string, mixed> $item
     * @param  array<string, int>  $approvedIds
     */
    private function createTopic(CurriculumGeneration $generation, array $item, array $approvedIds, int $sortOrder): int
    {
        $topic = SyllabusTopic::query()->create([
            'subject_id' => $generation->subject_id,
            'parent_id' => $item['parent_draft_id'] === null ? null : $approvedIds[$item['parent_draft_id']],
            'code' => $item['code'],
            'title' => $item['title'],
            'description' => $item['description'],
            'sort_order' => $sortOrder,
        ]);

        $sourceFiles = collect($generation->source_files)->keyBy('id');
        foreach (collect($item['citations'])
            ->where('verification', 'valid')
            ->unique(fn (array $citation): string => $citation['source_file_id'].':'.$citation['page_number']) as $citation) {
            $snapshot = $sourceFiles->get($citation['source_file_id']);
            $topic->sources()->create([
                'program_file_id' => $citation['source_file_id'],
                'source_file_id_snapshot' => $citation['source_file_id'],
                'source_title' => $snapshot['title'],
                'source_hash' => $snapshot['content_hash'],
                'page_number' => $citation['page_number'],
            ]);
        }

        return $topic->id;
    }

    /** @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function refreshTopicCitations(CurriculumGeneration $generation, array $item): array
    {
        $snapshots = collect($generation->source_files)->keyBy('id');
        $citations = collect($item['citations'] ?? [])->map(function (array $citation) use ($generation, $snapshots): array {
            $snapshot = $snapshots->get($citation['source_file_id']);
            if ($snapshot === null || ($citation['verification'] ?? null) !== 'valid') {
                return [...$citation, 'verification' => 'invalid'];
            }

            $file = ProgramFile::query()
                ->where('program_id', $generation->program_id)
                ->whereKey($citation['source_file_id'])
                ->lockForUpdate()
                ->first();
            $valid = $file !== null
                && $file->content_hash !== null
                && $file->content_hash === ($snapshot['content_hash'] ?? null);

            return [...$citation, 'verification' => $valid ? 'valid' : 'invalid'];
        })->values()->all();
        $status = $citations === []
            ? 'unverified'
            : (collect($citations)->every(fn (array $citation): bool => $citation['verification'] === 'valid') ? 'valid' : 'invalid');

        return [
            ...$item,
            'citations' => $citations,
            'citation_status' => $status,
            'review_notes' => $status === 'invalid' ? ['One or more citations no longer match a selected source file.'] : ($item['review_notes'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<array{source_file_id: int, page_number: int, text: string}>  $sourcePages
     * @return array{items: list<array<string, mixed>>}
     */
    private function validateProposal(CurriculumGeneration $generation, array $raw, array $sourcePages): array
    {
        $items = $raw['items'] ?? null;
        $itemLimit = max(1, min(1000, (int) config('ai.curriculum_generation.max_items', 200)));
        if (! is_array($items) || $items === [] || count($items) > $itemLimit) {
            throw new CurriculumGenerationException('invalid_proposal');
        }

        $availablePages = collect($sourcePages)->mapWithKeys(fn (array $page): array => [$page['source_file_id'].':'.$page['page_number'] => true]);
        $pageText = collect($sourcePages)->mapWithKeys(fn (array $page): array => [$page['source_file_id'].':'.$page['page_number'] => $page['text']]);
        $draftIds = [];
        $normalized = [];
        $characters = 0;

        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                throw new CurriculumGenerationException('invalid_proposal');
            }

            $draftId = $item['draft_id'] ?? null;
            $titleField = $generation->kind === CurriculumGenerationKind::Subjects ? 'name' : 'title';
            $title = $item[$titleField] ?? null;
            $code = $item['code'] ?? null;
            $description = $item['description'] ?? null;
            $parentDraftId = $item['parent_draft_id'] ?? null;
            if (! is_string($draftId) || preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/', $draftId) !== 1
                || in_array($draftId, $draftIds, true)
                || ! $this->validText($title, 255)
                || ! $this->validText($code, 100)
                || ! $this->validNullableText($description, 2000)) {
                throw new CurriculumGenerationException('invalid_proposal');
            }

            if ($generation->kind === CurriculumGenerationKind::Topics) {
                if ($parentDraftId !== null && (! is_string($parentDraftId) || ! in_array($parentDraftId, $draftIds, true))) {
                    throw new CurriculumGenerationException('invalid_proposal');
                }
            } elseif ($parentDraftId !== null) {
                throw new CurriculumGenerationException('invalid_proposal');
            }

            $citations = $item['citations'] ?? [];
            if (! is_array($citations) || count($citations) > 20) {
                throw new CurriculumGenerationException('invalid_proposal');
            }

            $normalizedCitations = [];
            $hasInvalidCitation = false;
            foreach ($citations as $citation) {
                if (! is_array($citation)
                    || ! is_int($citation['source_file_id'] ?? null)
                    || ! is_int($citation['page_number'] ?? null)) {
                    $hasInvalidCitation = true;

                    continue;
                }

                $isValid = isset($availablePages[$citation['source_file_id'].':'.$citation['page_number']]);
                $normalizedCitations[] = [
                    'source_file_id' => $citation['source_file_id'],
                    'page_number' => $citation['page_number'],
                    'verification' => $isValid ? 'valid' : 'invalid',
                    'excerpt' => $isValid ? mb_substr((string) $pageText->get($citation['source_file_id'].':'.$citation['page_number']), 0, 1800) : null,
                ];
                $hasInvalidCitation = $hasInvalidCitation || ! $isValid;
            }

            $characters += mb_strlen($title) + mb_strlen($code) + mb_strlen($description ?? '');
            $normalized[] = [
                'draft_id' => $draftId,
                $titleField => trim($title),
                'code' => trim($code),
                'description' => $description === null ? null : trim($description),
                'parent_draft_id' => $parentDraftId,
                'sort_order' => $index,
                'citations' => $normalizedCitations,
                'citation_status' => $citations === [] ? 'unverified' : ($hasInvalidCitation ? 'invalid' : 'valid'),
                'review_notes' => $hasInvalidCitation ? ['One or more citations do not match a selected source page.'] : [],
            ];
            $draftIds[] = $draftId;
        }

        if ($characters > max(1, (int) config('ai.curriculum_generation.max_characters', 250000))) {
            throw new CurriculumGenerationException('invalid_proposal');
        }

        return ['items' => $normalized];
    }

    private function validText(mixed $value, int $maximum): bool
    {
        return is_string($value) && trim($value) !== '' && mb_strlen($value) <= $maximum;
    }

    private function validNullableText(mixed $value, int $maximum): bool
    {
        return $value === null || (is_string($value) && mb_strlen($value) <= $maximum);
    }

    /**
     * @param  Collection<int, ProgramFile>  $files
     * @param  list<array{source_file_id: int, page_number: int, text: string}>  $pages
     * @param  array<int, array<string, mixed>>  $originalSnapshot
     * @return list<array<string, mixed>>
     */
    private function sourceSnapshot(Collection $files, array $pages = [], array $originalSnapshot = []): array
    {
        $pageCounts = collect($pages)->countBy('source_file_id');
        $originals = collect($originalSnapshot)->keyBy('id');

        return $files->map(fn (ProgramFile $file): array => [
            'id' => $file->id,
            'content_hash' => $file->content_hash ?? $originals->get($file->id)['content_hash'] ?? null,
            'title' => $file->title,
            'original_filename' => $file->original_filename,
            'mime_type' => $file->mime_type,
            'file_type' => $file->file_type?->value,
            'subject_id' => $file->subject_id,
            'file_size' => $file->file_size,
            'page_count' => $pageCounts->get($file->id, 0),
        ])->values()->all();
    }

    private function fail(CurriculumGeneration $generation, string $errorCode): void
    {
        $generation->forceFill([
            'status' => CurriculumGenerationStatus::Failed,
            'error_code' => $errorCode,
        ])->save();
    }
}
