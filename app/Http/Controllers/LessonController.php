<?php

namespace App\Http\Controllers;

use App\Models\Concept;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LessonController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Concept $concept): Response
    {
        return Inertia::render('admin/lessons/index', ['concept' => $concept, 'lessons' => $concept->lessons()->get()]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Concept $concept): Response
    {
        return Inertia::render('admin/lessons/create', ['concept' => $concept]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Concept $concept): RedirectResponse
    {
        $concept->lessons()->create($request->validate(['title' => ['required', 'string', 'max:255'], 'summary' => ['nullable', 'string'], 'content' => ['required', 'string'], 'sort_order' => ['nullable', 'integer', 'min:0']]));

        return to_route('concepts.show', $concept);
    }

    /**
     * Display the specified resource.
     */
    public function show(Lesson $lesson): Response
    {
        return Inertia::render('admin/lessons/show', ['lesson' => $lesson->load('concept')]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Lesson $lesson): Response
    {
        return Inertia::render('admin/lessons/edit', ['lesson' => $lesson, 'concept' => $lesson->concept]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Lesson $lesson): RedirectResponse
    {
        $lesson->update($request->validate(['title' => ['required', 'string', 'max:255'], 'summary' => ['nullable', 'string'], 'content' => ['required', 'string'], 'sort_order' => ['nullable', 'integer', 'min:0']]));

        return to_route('concepts.show', $lesson->concept);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Lesson $lesson): RedirectResponse
    {
        $concept = $lesson->concept;
        $lesson->delete();

        return to_route('concepts.show', $concept);
    }
}
