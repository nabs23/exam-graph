<?php

namespace App\Http\Controllers;

use App\CurriculumExtractionStatus;
use App\CurriculumGenerationStatus;
use App\Http\Requests\ProgramRequest;
use App\Models\CurriculumExtraction;
use App\Models\Program;
use App\Services\OfficialCurriculumExtractionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgramController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('admin/programs/index', ['programs' => Program::query()->withCount('subjects')->orderBy('name')->get()]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('admin/programs/create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProgramRequest $request): RedirectResponse
    {
        $program = Program::create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'The program was created.']);

        return to_route('programs.show', $program);
    }

    /**
     * Display the specified resource.
     */
    public function show(Program $program, OfficialCurriculumExtractionService $curriculumExtractionService): Response
    {
        $program->loadCount([
            'curriculumGenerations',
            'curriculumGenerations as pending_curriculum_generations_count' => fn ($query) => $query
                ->where('status', CurriculumGenerationStatus::Reviewing->value),
            'curriculumGenerations as in_progress_curriculum_generations_count' => fn ($query) => $query
                ->whereIn('status', [CurriculumGenerationStatus::Queued->value, CurriculumGenerationStatus::Generating->value]),
            'curriculumGenerations as failed_curriculum_generations_count' => fn ($query) => $query
                ->where('status', CurriculumGenerationStatus::Failed->value),
        ]);
        $program->load([
            'subjects' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'files.uploader:id,name',
            'files.subject:id,name',
        ]);
        $hasCurriculumSources = $curriculumExtractionService->hasCompletedPdfSources($program);
        $unavailableReason = $curriculumExtractionService->unavailableReason();

        return Inertia::render('admin/programs/show', [
            'program' => $program,
            'curriculumExtractionEnabled' => $unavailableReason === null,
            'curriculumExtractionHasSources' => $hasCurriculumSources,
            'curriculumExtractionUnavailableReason' => $unavailableReason,
            'curriculumExtractionModels' => $curriculumExtractionService->availableModels(),
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

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Program $program): Response
    {
        return Inertia::render('admin/programs/edit', [
            'program' => $program,
            'deleteBlockedReason' => $this->deleteBlockedReason($program),
            'returnTab' => $this->workspaceTab($request->query('tab')),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProgramRequest $request, Program $program): RedirectResponse
    {
        $program->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Program changes were saved.']);

        $returnTab = $this->workspaceTab($request->query('tab'));

        return to_route('programs.show', array_filter([
            'program' => $program,
            'tab' => $returnTab,
        ]));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Program $program): RedirectResponse
    {
        $blockedReason = $this->deleteBlockedReason($program);

        if ($blockedReason !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $blockedReason]);

            return to_route('programs.edit', array_filter([
                'program' => $program,
                'tab' => $this->workspaceTab($request->query('tab')),
            ]));
        }

        $program->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'The program was deleted.']);

        return to_route('programs.index');
    }

    private function deleteBlockedReason(Program $program): ?string
    {
        if ($program->subjects()->exists()) {
            return 'Remove this program’s subjects before deleting the program.';
        }

        if ($program->files()->exists()) {
            return 'Remove this program’s source files before deleting the program.';
        }

        if ($program->curriculumGenerations()->exists()) {
            return 'This program has retained curriculum proposal history and cannot be deleted.';
        }

        return null;
    }

    private function workspaceTab(mixed $tab): ?string
    {
        return is_string($tab) && in_array($tab, ['subjects', 'files', 'extractions'], true)
            ? $tab
            : null;
    }
}
