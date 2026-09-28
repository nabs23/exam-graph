<?php

namespace App\Http\Controllers;

use App\Actions\SubmitQuizAttempt;
use App\Http\Requests\QuizAttemptRequest;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class QuizAttemptController extends Controller
{
    public function show(Quiz $quiz): Response
    {
        return Inertia::render('study/quiz', ['quiz' => $quiz->load(['concept.subject', 'questions.choices'])]);
    }

    public function store(QuizAttemptRequest $request, Quiz $quiz, SubmitQuizAttempt $submitQuizAttempt): RedirectResponse
    {
        $attempt = $submitQuizAttempt->execute($request->user(), $quiz, $request->validated('answers'));

        return to_route('attempts.result', $attempt);
    }

    public function result(QuizAttempt $attempt): Response
    {
        abort_unless($attempt->user_id === auth()->id(), 403);

        return Inertia::render('study/result', ['attempt' => $attempt->load(['quiz.concept', 'answers.question.choices', 'answers.questionChoice'])]);
    }
}
