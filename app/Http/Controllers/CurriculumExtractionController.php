<?php

namespace App\Http\Controllers;

use App\CurriculumExtractionStatus;
use App\Http\Requests\PublishCurriculumExtractionRequest;
use App\Http\Requests\StoreCurriculumExtractionRequest;
use App\Models\CurriculumExtraction;
use App\Models\FilePageEmbedding;
use App\Models\Program;
use App\Services\OfficialCurriculumExtractionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CurriculumExtractionController extends Controller
{
    public function index(Program $program, OfficialCurriculumExtractionService $service): Response
    {
        $unavailableReason = $service->unavailableReason();

        return Inertia::render('admin/curriculum-extractions/index', [
            'program' => $program->only(['id', 'name', 'code']),
            'curriculumExtractionEnabled' => $unavailableReason === null,
            'curriculumExtractionUnavailableReason' => $unavailableReason,
            'curriculumExtractionModels' => $service->availableModels(),
            'curriculumExtractionDefaultModel' => config('ai.official_curriculum.model'),
            'extractions' => $program->curriculumExtractions()
                ->with(['requester:id,name', 'reviewer:id,name'])
                ->latest()
                ->get([
                    'id', 'program_id', 'status', 'source_hash', 'source_files', 'provider', 'model', 'prompt_version',
                    'error_code', 'requested_by', 'reviewed_by', 'reviewed_at', 'reviewed_proposal', 'created_at', 'updated_at',
                ])
                ->map(fn (CurriculumExtraction $extraction): array => [
                    'id' => $extraction->id,
                    'status' => $extraction->status,
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
                    'can_delete' => $extraction->status === CurriculumExtractionStatus::Failed || $extraction->reviewed_proposal === null,
                ]),
        ]);
    }

    public function store(
        StoreCurriculumExtractionRequest $request,
        Program $program,
        OfficialCurriculumExtractionService $service,
    ): RedirectResponse {
        abort_unless($service->isAvailable(), 404);
        $model = $request->validated('model');
        $extraction = $model === null
            ? $service->queue($program, $request->user())
            : $service->queue($program, $request->user(), $model);

        return to_route('curriculum-extractions.show', $extraction);
    }

    public function show(CurriculumExtraction $curriculumExtraction, OfficialCurriculumExtractionService $service): Response
    {
        $curriculumExtraction->load('program:id,name,code', 'requester:id,name', 'reviewer:id,name');
        $pages = collect();

        if (in_array($curriculumExtraction->status, [CurriculumExtractionStatus::Reviewing, CurriculumExtractionStatus::Published], true)) {
            $sourceHashes = collect($curriculumExtraction->source_files)->pluck('content_hash', 'id');
            $pages = FilePageEmbedding::query()
                ->whereIn('program_file_id', $sourceHashes->keys())
                ->whereNotNull('content')
                ->orderBy('program_file_id')
                ->orderBy('page_number')
                ->get(['program_file_id', 'page_number', 'source_hash', 'content'])
                ->filter(fn (FilePageEmbedding $page): bool => $page->source_hash === $sourceHashes[$page->program_file_id])
                ->map(fn ($page): array => [
                    'source_file_id' => $page->program_file_id,
                    'page_number' => $page->page_number,
                    'text' => str($page->content)->squish()->limit(1200)->toString(),
                ]);
        }

        return Inertia::render('admin/curriculum-extractions/show', [
            'extraction' => $curriculumExtraction->makeHidden(['proposal']),
            'proposal' => $curriculumExtraction->proposal,
            'reviewedProposal' => $curriculumExtraction->reviewed_proposal,
            'sourcePages' => $pages,
            'existingSubjects' => $curriculumExtraction->program->subjects()
                ->with('syllabusTopics:id,subject_id,parent_id,code,title')
                ->withCount(['concepts', 'files'])
                ->orderBy('id')
                ->get(['id', 'program_id', 'name', 'code', 'description', 'sort_order'])
                ->map(fn ($subject): array => [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'code' => $subject->code,
                    'description' => $subject->description,
                    'concepts_count' => $subject->concepts_count,
                    'files_count' => $subject->files_count,
                    'topics' => $subject->syllabusTopics->map(fn ($topic): array => [
                        'id' => $topic->id,
                        'parent_id' => $topic->parent_id,
                        'code' => $topic->code,
                        'title' => $topic->title,
                        'description' => $topic->description,
                    ]),
                ]),
            'canReview' => $curriculumExtraction->status === CurriculumExtractionStatus::Reviewing,
        ]);
    }

    public function publish(
        PublishCurriculumExtractionRequest $request,
        CurriculumExtraction $curriculumExtraction,
        OfficialCurriculumExtractionService $service,
    ): RedirectResponse {
        $service->publish($curriculumExtraction, $request->validated('subjects'), $request->user());

        return back()->with('success', 'The selected official curriculum was published.');
    }

    public function reject(CurriculumExtraction $curriculumExtraction, OfficialCurriculumExtractionService $service): RedirectResponse
    {
        $service->reject($curriculumExtraction, request()->user());

        return back()->with('success', 'The curriculum proposal was rejected.');
    }

    public function destroy(Program $program, CurriculumExtraction $curriculumExtraction): RedirectResponse
    {
        abort_unless($curriculumExtraction->program_id === $program->id, 404);
        abort_unless(
            $curriculumExtraction->status === CurriculumExtractionStatus::Failed || $curriculumExtraction->reviewed_proposal === null,
            422,
            'Published curriculum extraction records cannot be deleted.',
        );

        $curriculumExtraction->delete();

        return back()->with('success', 'The curriculum extraction was deleted.');
    }
}
