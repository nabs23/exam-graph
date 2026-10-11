<?php

namespace App\Http\Controllers;

use App\CurriculumExtractionStatus;
use App\Http\Requests\ProgramRequest;
use App\Models\CurriculumExtraction;
use App\Models\Program;
use App\Services\OfficialCurriculumExtractionService;
use Illuminate\Http\RedirectResponse;
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

        return to_route('programs.show', $program);
    }

    /**
     * Display the specified resource.
     */
    public function show(Program $program, OfficialCurriculumExtractionService $curriculumExtractionService): Response
    {
        $program->load(['subjects' => fn ($query) => $query->orderBy('sort_order'), 'files.uploader:id,name']);
        $unavailableReason = $curriculumExtractionService->unavailableReason();

        return Inertia::render('admin/programs/show', [
            'program' => $program,
            'curriculumExtractionEnabled' => $unavailableReason === null,
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
    public function edit(Program $program): Response
    {
        return Inertia::render('admin/programs/edit', ['program' => $program]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProgramRequest $request, Program $program): RedirectResponse
    {
        $program->update($request->validated());

        return to_route('programs.show', $program);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Program $program): RedirectResponse
    {
        abort_if($program->subjects()->exists() || $program->files()->exists(), 422, 'Remove subjects and source files before deleting this program.');
        $program->delete();

        return to_route('programs.index');
    }
}
