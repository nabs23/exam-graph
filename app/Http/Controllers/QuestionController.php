<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuestionRequest;
use App\Models\Concept;
use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QuestionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Concept $concept): Response
    {
        return Inertia::render('admin/questions/index', ['concept' => $concept, 'questions' => $concept->questions()->with('choices')->get()]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Concept $concept): Response
    {
        return Inertia::render('admin/questions/create', ['concept' => $concept]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(QuestionRequest $request, Concept $concept): RedirectResponse
    {
        $data = $request->validated();
        abort_unless(collect($data['choices'])->where('is_correct', true)->count() === 1, 422, 'A question needs exactly one correct choice.');
        DB::transaction(function () use ($concept, $data): void {
            $question = $concept->questions()->create(collect($data)->except('choices')->all());
            foreach ($data['choices'] as $index => $choice) {
                $question->choices()->create($choice + ['sort_order' => $index]);
            }
        });

        return to_route('concepts.show', $concept);
    }

    /**
     * Display the specified resource.
     */
    public function show(Question $question): Response
    {
        return Inertia::render('admin/questions/show', ['question' => $question->load(['concept', 'choices', 'quizzes'])]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Question $question): Response
    {
        return Inertia::render('admin/questions/edit', ['question' => $question->load('choices'), 'concept' => $question->concept]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(QuestionRequest $request, Question $question): RedirectResponse
    {
        $data = $request->validated();
        abort_unless(collect($data['choices'])->where('is_correct', true)->count() === 1, 422, 'A question needs exactly one correct choice.');
        DB::transaction(function () use ($question, $data): void {
            $question->update(collect($data)->except('choices')->all());
            $question->choices()->delete();
            foreach ($data['choices'] as $index => $choice) {
                $question->choices()->create(collect($choice)->except('id')->put('sort_order', $index)->all());
            }
        });

        return to_route('concepts.show', $question->concept);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Question $question): RedirectResponse
    {
        abort_if($question->quizzes()->exists(), 422, 'Remove this question from quizzes before deleting it.');
        $concept = $question->concept;
        $question->delete();

        return to_route('concepts.show', $concept);
    }
}
