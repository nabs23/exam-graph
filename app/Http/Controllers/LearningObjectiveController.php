<?php

namespace App\Http\Controllers;

use App\Http\Requests\LearningObjectiveRequest;
use App\Models\Concept;
use App\Models\LearningObjective;
use App\Models\Program;
use App\Models\Subject;
use App\Models\SyllabusTopic;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LearningObjectiveController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Program $program, Subject $subject, ?SyllabusTopic $topic = null, ?Concept $concept = null): Response
    {

        $this->ensureConceptContext($topic, $concept);

        return Inertia::render('admin/objectives/index', ['concept' => $concept, 'objectives' => $concept->learningObjectives()->get()]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Program $program, Subject $subject, ?SyllabusTopic $topic = null, ?Concept $concept = null): Response
    {

        $this->ensureConceptContext($topic, $concept);

        return Inertia::render('admin/objectives/create', ['concept' => $concept]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LearningObjectiveRequest $request, Program $program, Subject $subject, ?SyllabusTopic $topic = null, ?Concept $concept = null): RedirectResponse
    {

        $this->ensureConceptContext($topic, $concept);
        $concept->learningObjectives()->create($request->validated());

        return redirect()->to($concept->curriculumRoute('concepts.show'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Program $program, Subject $subject, ?SyllabusTopic $topic = null, ?Concept $concept = null, ?LearningObjective $objective = null): Response
    {

        $this->ensureConceptContext($topic, $concept);

        return Inertia::render('admin/objectives/show', ['objective' => $objective->load('concept')]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Program $program, Subject $subject, ?SyllabusTopic $topic = null, ?Concept $concept = null, ?LearningObjective $objective = null): Response
    {

        $this->ensureConceptContext($topic, $concept);

        return Inertia::render('admin/objectives/edit', ['objective' => $objective, 'concept' => $objective->concept]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(LearningObjectiveRequest $request, Program $program, Subject $subject, ?SyllabusTopic $topic = null, ?Concept $concept = null, ?LearningObjective $objective = null): RedirectResponse
    {

        $this->ensureConceptContext($topic, $concept);
        $objective->update($request->validated());

        return redirect()->to($objective->concept->curriculumRoute('concepts.show'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Program $program, Subject $subject, ?SyllabusTopic $topic = null, ?Concept $concept = null, ?LearningObjective $objective = null): RedirectResponse
    {

        $this->ensureConceptContext($topic, $concept);
        $concept = $objective->concept;
        $objective->delete();

        return redirect()->to($concept->curriculumRoute('concepts.show'));
    }
}
