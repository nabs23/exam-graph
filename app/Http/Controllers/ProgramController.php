<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProgramRequest;
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
            'latestCurriculumExtraction' => $program->curriculumExtractions()->latest()->first(['id', 'program_id', 'status']),
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
