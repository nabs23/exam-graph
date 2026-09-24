<?php

namespace App\Http\Controllers;

use App\Models\ConceptProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QuizAttemptController extends Controller
{
    public function show(Quiz $quiz): Response
    {
        return Inertia::render('study/quiz', ['quiz' => $quiz->load(['concept.subject', 'questions.choices'])]);
    }

    public function store(Request $request, Quiz $quiz): RedirectResponse
    {
        $quiz->load('questions.choices');
        abort_if($quiz->questions->isEmpty(), 422, 'This quiz has no questions.');
        $answers = $request->validate(['answers' => ['required', 'array', 'size:'.$quiz->questions->count()], 'answers.*' => ['required', 'integer']])['answers'];

        $attempt = DB::transaction(function () use ($answers, $quiz): QuizAttempt {
            $user = User::query()->firstOrFail();
            $attempt = QuizAttempt::create(['user_id' => $user->id, 'quiz_id' => $quiz->id, 'started_at' => now(), 'submitted_at' => now(), 'total_questions' => $quiz->questions->count()]);
            $correctAnswers = 0;
            foreach ($quiz->questions as $question) {
                $choiceId = $answers[$question->id] ?? null;
                $choice = $question->choices->firstWhere('id', $choiceId);
                abort_if($choice === null, 422, 'Every answer must belong to its quiz question.');
                $attempt->answers()->create(['question_id' => $question->id, 'question_choice_id' => $choice->id, 'is_correct' => $choice->is_correct]);
                $correctAnswers += $choice->is_correct ? 1 : 0;
            }
            $score = round(($correctAnswers / $quiz->questions->count()) * 100, 2);
            $attempt->update(['correct_answers' => $correctAnswers, 'score' => $score, 'passed' => $score >= (float) $quiz->passing_score]);
            $progress = ConceptProgress::query()->firstOrNew(['user_id' => $user->id, 'concept_id' => $quiz->concept_id]);
            $progress->fill(['last_score' => $score, 'best_score' => max((float) ($progress->best_score ?? 0), $score), 'attempts_count' => QuizAttempt::query()->where('user_id', $user->id)->whereHas('quiz', fn ($query) => $query->where('concept_id', $quiz->concept_id))->count(), 'last_attempted_at' => now(), 'is_completed' => $progress->is_completed || $score >= (float) $quiz->passing_score]);
            $progress->save();

            return $attempt;
        });

        return to_route('attempts.result', $attempt);
    }

    public function result(QuizAttempt $attempt): Response
    {
        return Inertia::render('study/result', ['attempt' => $attempt->load(['quiz.concept', 'answers.question.choices', 'answers.questionChoice'])]);
    }
}
