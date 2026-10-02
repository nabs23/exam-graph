<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubjectRequest;
use App\Models\Program;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(?Program $program = null): Response
    {
        return Inertia::render('admin/subjects/index', ['subjects' => Subject::query()->when($program, fn (Builder $query): Builder => $query->where('program_id', $program->id))->with('program:id,name,code')->orderBy('sort_order')->get(), 'contextProgram' => $program, 'programs' => $program ? collect([$program]) : Program::query()->orderBy('name')->get(['id', 'name', 'code'])]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(?Program $program = null): Response
    {
        return Inertia::render('admin/subjects/create', ['contextProgram' => $program, 'programs' => $program ? collect([$program]) : Program::query()->orderBy('name')->get(['id', 'name', 'code'])]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SubjectRequest $request, ?Program $program = null): RedirectResponse
    {
        $data = $request->validated();
        if ($program !== null) {
            $data['program_id'] = $program->id;
        }
        $subject = Subject::create($data);

        return redirect()->to($subject->curriculumRoute('subjects.show'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Program $program, Subject $subject): Response
    {
        return Inertia::render('admin/subjects/show', ['subject' => $subject->load(['program', 'syllabusTopics' => fn (HasMany $query): HasMany => $query->orderBy('sort_order')->orderBy('id'), 'concepts' => fn (HasMany $query): HasMany => $query->orderBy('sort_order')->orderBy('id'), 'files.uploader:id,name'])]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Program $program, Subject $subject): Response
    {
        return Inertia::render('admin/subjects/edit', ['subject' => $subject, 'programs' => Program::query()->orderBy('name')->get(['id', 'name', 'code'])]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SubjectRequest $request, Program $program, Subject $subject): RedirectResponse
    {
        $subject->update($request->validated());

        return redirect()->to($subject->curriculumRoute('subjects.show'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Program $program, Subject $subject): RedirectResponse
    {
        abort_if($subject->concepts()->exists() || $subject->syllabusTopics()->exists() || $subject->files()->exists(), 422, 'Remove the subject content and source files before deleting it.');
        $subject->delete();

        return to_route('subjects.index');
    }
}
