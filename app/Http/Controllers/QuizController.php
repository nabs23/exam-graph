<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuizRequest;
use App\Models\Concept;
use App\Models\Quiz;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class QuizController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Concept $concept): Response
    {
        return Inertia::render('admin/quizzes/index', ['concept' => $concept, 'quizzes' => $concept->quizzes()->withCount('questions')->get()]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Concept $concept): Response
    {
        return Inertia::render('admin/quizzes/create', ['concept' => $concept, 'questions' => $concept->questions()->with('choices')->get()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(QuizRequest $request, Concept $concept): RedirectResponse
    {
        $data = $request->validated();
        $this->ensureQuestionsBelongToConcept($data['question_ids'], $concept);
        $quiz = $concept->quizzes()->create(collect($data)->except('question_ids')->all());
        $quiz->questions()->sync(collect($data['question_ids'])->values()->mapWithKeys(fn (int $id, int $index): array => [$id => ['sort_order' => $index]])->all());

        return to_route('concepts.show', $concept);
    }

    /**
     * Display the specified resource.
     */
    public function show(Quiz $quiz): Response
    {
        return Inertia::render('admin/quizzes/show', ['quiz' => $quiz->load(['concept', 'questions.choices'])]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Quiz $quiz): Response
    {
        return Inertia::render('admin/quizzes/edit', ['quiz' => $quiz->load('questions'), 'concept' => $quiz->concept, 'questions' => $quiz->concept->questions()->with('choices')->get()]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(QuizRequest $request, Quiz $quiz): RedirectResponse
    {
        $data = $request->validated();
        $this->ensureQuestionsBelongToConcept($data['question_ids'], $quiz->concept);
        $quiz->update(collect($data)->except('question_ids')->all());
        $quiz->questions()->sync(collect($data['question_ids'])->values()->mapWithKeys(fn (int $id, int $index): array => [$id => ['sort_order' => $index]])->all());

        return to_route('concepts.show', $quiz->concept);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Quiz $quiz): RedirectResponse
    {
        abort_if($quiz->attempts()->exists(), 422, 'A quiz with attempts cannot be deleted.');
        $concept = $quiz->concept;
        $quiz->delete();

        return to_route('concepts.show', $concept);
    }

    private function ensureQuestionsBelongToConcept(array $questionIds, Concept $concept): void
    {
        $matchingQuestions = $concept->questions()->whereIn('questions.id', $questionIds)->count();

        abort_if($matchingQuestions !== count(array_unique($questionIds)), 422, 'Every quiz question must belong to the selected concept.');
    }
}
