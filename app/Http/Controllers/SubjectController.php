<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubjectRequest;
use App\Models\Program;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('admin/subjects/index', ['subjects' => Subject::query()->with('program:id,name,code')->orderBy('sort_order')->get(), 'programs' => Program::query()->orderBy('name')->get(['id', 'name', 'code'])]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('admin/subjects/create', ['programs' => Program::query()->orderBy('name')->get(['id', 'name', 'code'])]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SubjectRequest $request): RedirectResponse
    {
        $subject = Subject::create($request->validated());

        return to_route('subjects.show', $subject);
    }

    /**
     * Display the specified resource.
     */
    public function show(Subject $subject): Response
    {
        return Inertia::render('admin/subjects/show', ['subject' => $subject->load(['program', 'syllabusTopics.children', 'concepts.syllabusTopic'])]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Subject $subject): Response
    {
        return Inertia::render('admin/subjects/edit', ['subject' => $subject, 'programs' => Program::query()->orderBy('name')->get(['id', 'name', 'code'])]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SubjectRequest $request, Subject $subject): RedirectResponse
    {
        $subject->update($request->validated());

        return to_route('subjects.show', $subject);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Subject $subject): RedirectResponse
    {
        abort_if($subject->concepts()->exists() || $subject->syllabusTopics()->exists(), 422, 'Remove the subject content before deleting it.');
        $subject->delete();

        return to_route('subjects.index');
    }
}
