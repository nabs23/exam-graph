<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublishSubjectTopicExtractionRequest;
use App\Http\Requests\StoreSubjectTopicExtractionRequest;
use App\Models\FilePageEmbedding;
use App\Models\Program;
use App\Models\Subject;
use App\Models\SubjectTopicExtraction;
use App\Services\SubjectTopicExtractionService;
use App\SubjectTopicExtractionStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SubjectTopicExtractionController extends Controller
{
    public function index(Program $program, Subject $subject, SubjectTopicExtractionService $service): Response
    {
        $files = $service->filesForSubject($subject);
        $extractions = $subject->topicExtractions()
            ->with(['requester:id,name', 'reviewer:id,name'])
            ->latest()
            ->limit(30)
            ->get(['id', 'subject_id', 'status', 'source_hash', 'source_files', 'provider', 'model', 'prompt_version', 'error_code', 'requested_by', 'reviewed_by', 'reviewed_at', 'created_at'])
            ->map(fn (SubjectTopicExtraction $extraction): array => [
                'id' => $extraction->id,
                'status' => $extraction->status->value,
                'source_hash' => $extraction->source_hash,
                'source_files' => $extraction->source_files,
                'provider' => $extraction->provider,
                'model' => $extraction->model,
                'prompt_version' => $extraction->prompt_version,
                'error_code' => $extraction->error_code,
                'error_message' => $extraction->error_message,
                'created_at' => $extraction->created_at,
                'reviewed_at' => $extraction->reviewed_at,
                'requester' => $extraction->requester?->only(['name']),
                'reviewer' => $extraction->reviewer?->only(['name']),
            ]);

        $unavailableReason = $service->unavailableReason();

        return Inertia::render('admin/subject-topic-extractions/index', [
            'program' => $program->only(['id', 'name', 'code']),
            'subject' => [...$subject->only(['id', 'name', 'code']), 'route_parameters' => $subject->curriculumRouteParameters()],
            'files' => $files->map(fn ($file): array => [
                'id' => $file->id,
                'title' => $file->title,
                'file_type' => $file->file_type?->value,
                'mime_type' => $file->mime_type,
                'upload_status' => $file->upload_status?->value,
                'embedding_status' => $file->embedding_status?->value,
                'usable_pages_count' => $file->usable_pages_count,
                'subject_id' => $file->subject_id,
                'subject_name' => $file->subject?->name,
                'eligible' => $file->upload_status?->value === 'uploaded'
                    && $file->mime_type === 'application/pdf'
                    && $file->embedding_status?->value === 'complete'
                    && $file->usable_pages_count > 0,
            ]),
            'extractions' => $extractions,
            'topicExtractionEnabled' => $unavailableReason === null,
            'topicExtractionUnavailableReason' => $unavailableReason,
            'topicExtractionModels' => $service->availableModels(),
            'topicExtractionDefaultModel' => config('ai.subject_topic_extraction.model'),
        ]);
    }

    public function store(
        StoreSubjectTopicExtractionRequest $request,
        Program $program,
        Subject $subject,
        SubjectTopicExtractionService $service,
    ): RedirectResponse {
        $data = $request->validated();
        $extraction = $service->queue($subject, $data['source_file_ids'], $request->user(), $data['model'] ?? null);

        return to_route('programs.subjects.topics-extraction.show', [$program, $subject, $extraction]);
    }

    public function show(
        Program $program,
        Subject $subject,
        SubjectTopicExtraction $topicExtraction,
        SubjectTopicExtractionService $service,
    ): Response {
        $topicExtraction->load(['requester:id,name', 'reviewer:id,name']);
        $sourcePages = collect();
        if (in_array($topicExtraction->status, [SubjectTopicExtractionStatus::Reviewing, SubjectTopicExtractionStatus::Published], true)) {
            $snapshot = collect($topicExtraction->source_files)->keyBy('id');
            $programFileIds = $snapshot
                ->filter(fn (array $file): bool => ($file['source_type'] ?? null) === 'program_file')
                ->keys();
            $legacyFileIds = $snapshot
                ->reject(fn (array $file): bool => ($file['source_type'] ?? null) === 'program_file')
                ->keys();
            $legacyMap = DB::table('legacy_program_file_sources')
                ->where('legacy_type', 'subject_file')
                ->whereIn('legacy_id', $legacyFileIds)
                ->pluck('program_file_id', 'legacy_id');

            $sourcePages = FilePageEmbedding::query()
                ->where(function ($query) use ($programFileIds, $legacyFileIds, $legacyMap): void {
                    if ($legacyFileIds->isNotEmpty()) {
                        $query->whereIn('subject_file_id', $legacyFileIds);
                    }
                    $canonicalProgramFileIds = $programFileIds->merge($legacyMap->values())->unique();
                    if ($canonicalProgramFileIds->isNotEmpty()) {
                        if ($legacyFileIds->isNotEmpty()) {
                            $query->orWhereIn('program_file_id', $canonicalProgramFileIds);
                        } else {
                            $query->whereIn('program_file_id', $canonicalProgramFileIds);
                        }
                    }
                    if ($legacyFileIds->isEmpty() && $canonicalProgramFileIds->isEmpty()) {
                        $query->whereRaw('1 = 0');
                    }
                })
                ->whereNotNull('content')
                ->orderBy('page_number')
                ->get(['id', 'subject_file_id', 'program_file_id', 'page_number', 'source_hash', 'content'])
                ->flatMap(function (FilePageEmbedding $page) use ($legacyMap, $programFileIds): Collection {
                    if ($page->subject_file_id !== null) {
                        return collect([[
                            'source_file_id' => $page->subject_file_id,
                            'page_number' => $page->page_number,
                            'source_hash' => $page->source_hash,
                            'content' => $page->content,
                        ]]);
                    }

                    $legacySourceIds = $legacyMap
                        ->filter(fn (mixed $programFileId): bool => (int) $programFileId === (int) $page->program_file_id)
                        ->keys()
                        ->map(fn (int|string $legacyId): int => (int) $legacyId);
                    $programSourceIds = $programFileIds->contains((int) $page->program_file_id)
                        ? collect([(int) $page->program_file_id])
                        : collect();

                    return $legacySourceIds->merge($programSourceIds)->unique()->map(fn (int $sourceId): array => [
                        'source_file_id' => $sourceId,
                        'page_number' => $page->page_number,
                        'source_hash' => $page->source_hash,
                        'content' => $page->content,
                    ]);
                })
                ->filter(fn (array $page): bool => $page['source_file_id'] !== null
                    && $snapshot->has($page['source_file_id'])
                    && $snapshot->get($page['source_file_id'])['content_hash'] === $page['source_hash'])
                ->unique(fn (array $page): string => $page['source_file_id'].':'.$page['page_number'])
                ->map(fn (array $page): array => [
                    'source_file_id' => $page['source_file_id'],
                    'page_number' => $page['page_number'],
                    'text' => str($page['content'])->squish()->limit(1400)->toString(),
                ])->values();
        }

        return Inertia::render('admin/subject-topic-extractions/show', [
            'program' => $program->only(['id', 'name', 'code']),
            'subject' => [...$subject->only(['id', 'name', 'code']), 'route_parameters' => $subject->curriculumRouteParameters()],
            'extraction' => [
                'id' => $topicExtraction->id,
                'status' => $topicExtraction->status->value,
                'source_hash' => $topicExtraction->source_hash,
                'source_files' => $topicExtraction->source_files,
                'provider' => $topicExtraction->provider,
                'model' => $topicExtraction->model,
                'prompt_version' => $topicExtraction->prompt_version,
                'error_code' => $topicExtraction->error_code,
                'error_message' => $topicExtraction->error_message,
                'proposal' => $topicExtraction->proposal,
                'reviewed_proposal' => $topicExtraction->reviewed_proposal,
                'requester' => $topicExtraction->requester?->only(['name']),
                'reviewer' => $topicExtraction->reviewer?->only(['name']),
                'created_at' => $topicExtraction->created_at,
                'reviewed_at' => $topicExtraction->reviewed_at,
            ],
            'sourcePages' => $sourcePages,
            'canReview' => $topicExtraction->status === SubjectTopicExtractionStatus::Reviewing,
            'sourceSnapshotCurrent' => $service->isSnapshotCurrent($topicExtraction),
        ]);
    }

    public function publish(
        PublishSubjectTopicExtractionRequest $request,
        Program $program,
        Subject $subject,
        SubjectTopicExtraction $topicExtraction,
        SubjectTopicExtractionService $service,
    ): RedirectResponse {
        $service->publish($topicExtraction, $request->validated('topics'), $request->user());

        return to_route('programs.subjects.topics-extraction.show', [$program, $subject, $topicExtraction]);
    }

    public function reject(Request $request, Program $program, Subject $subject, SubjectTopicExtraction $topicExtraction, SubjectTopicExtractionService $service): RedirectResponse
    {
        $service->reject($topicExtraction, $request->user());

        return to_route('programs.subjects.topics-extraction.show', [$program, $subject, $topicExtraction]);
    }

    public function retry(Program $program, Subject $subject, SubjectTopicExtraction $topicExtraction, SubjectTopicExtractionService $service): RedirectResponse
    {
        $service->retry($topicExtraction);

        return to_route('programs.subjects.topics-extraction.show', [$program, $subject, $topicExtraction]);
    }

    public function destroy(Program $program, Subject $subject, SubjectTopicExtraction $topicExtraction): RedirectResponse
    {
        abort_unless(in_array($topicExtraction->status, [SubjectTopicExtractionStatus::Failed, SubjectTopicExtractionStatus::Rejected], true), 422);
        $topicExtraction->delete();

        return to_route('programs.subjects.topics-extraction.index', [$program, $subject]);
    }
}
