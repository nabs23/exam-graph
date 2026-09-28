<?php

namespace App\Http\Controllers;

use App\Http\Requests\LearningObjectiveRequest;
use App\Models\Concept;
use App\Models\LearningObjective;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LearningObjectiveController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Concept $concept): Response
    {
        return Inertia::render('admin/objectives/index', ['concept' => $concept, 'objectives' => $concept->learningObjectives()->get()]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Concept $concept): Response
    {
        return Inertia::render('admin/objectives/create', ['concept' => $concept]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LearningObjectiveRequest $request, Concept $concept): RedirectResponse
    {
        $concept->learningObjectives()->create($request->validated());

        return to_route('concepts.show', $concept);
    }

    /**
     * Display the specified resource.
     */
    public function show(LearningObjective $objective): Response
    {
        return Inertia::render('admin/objectives/show', ['objective' => $objective->load('concept')]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(LearningObjective $objective): Response
    {
        return Inertia::render('admin/objectives/edit', ['objective' => $objective, 'concept' => $objective->concept]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(LearningObjectiveRequest $request, LearningObjective $objective): RedirectResponse
    {
        $objective->update($request->validated());

        return to_route('concepts.show', $objective->concept);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LearningObjective $objective): RedirectResponse
    {
        $concept = $objective->concept;
        $objective->delete();

        return to_route('concepts.show', $concept);
    }
}
