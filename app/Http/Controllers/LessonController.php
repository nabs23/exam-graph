<?php

namespace App\Http\Controllers;

use App\Http\Requests\LessonRequest;
use App\Models\Concept;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\Subject;
use App\Models\SyllabusTopic;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LessonController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Program $program, Subject $subject, ?SyllabusTopic $topic = null, ?Concept $concept = null): Response
    {

        $this->ensureConceptContext($topic, $concept);

        return Inertia::render('admin/lessons/index', ['concept' => $concept, 'lessons' => $concept->lessons()->get()]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Program $program, Subject $subject, ?SyllabusTopic $topic = null, ?Concept $concept = null): Response
    {

        $this->ensureConceptContext($topic, $concept);

        return Inertia::render('admin/lessons/create', ['concept' => $concept]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LessonRequest $request, Program $program, Subject $subject, ?SyllabusTopic $topic = null, ?Concept $concept = null): RedirectResponse
    {

        $this->ensureConceptContext($topic, $concept);
        $concept->lessons()->create($request->validated());

        return redirect()->to($concept->curriculumRoute('concepts.show'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Program $program, Subject $subject, ?SyllabusTopic $topic = null, ?Concept $concept = null, ?Lesson $lesson = null): Response
    {

        $this->ensureConceptContext($topic, $concept);

        return Inertia::render('admin/lessons/show', ['lesson' => $lesson->load('concept')]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Program $program, Subject $subject, ?SyllabusTopic $topic = null, ?Concept $concept = null, ?Lesson $lesson = null): Response
    {

        $this->ensureConceptContext($topic, $concept);

        return Inertia::render('admin/lessons/edit', ['lesson' => $lesson, 'concept' => $lesson->concept]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(LessonRequest $request, Program $program, Subject $subject, ?SyllabusTopic $topic = null, ?Concept $concept = null, ?Lesson $lesson = null): RedirectResponse
    {

        $this->ensureConceptContext($topic, $concept);
        $lesson->update($request->validated());

        return redirect()->to($lesson->concept->curriculumRoute('concepts.show'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Program $program, Subject $subject, ?SyllabusTopic $topic = null, ?Concept $concept = null, ?Lesson $lesson = null): RedirectResponse
    {

        $this->ensureConceptContext($topic, $concept);
        $concept = $lesson->concept;
        $lesson->delete();

        return redirect()->to($concept->curriculumRoute('concepts.show'));
    }
}
