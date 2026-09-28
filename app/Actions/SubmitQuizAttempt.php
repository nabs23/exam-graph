<?php

namespace App\Actions;

use App\Models\ConceptProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmitQuizAttempt
{
    /**
     * @param  array<int|string, int>  $answers
     */
    public function execute(User $user, Quiz $quiz, array $answers): QuizAttempt
    {
        return DB::transaction(function () use ($answers, $quiz, $user): QuizAttempt {
            $questions = $quiz->questions()->with('choices')->get();
            abort_if($questions->isEmpty(), 422, 'This quiz has no questions.');
            abort_if(count($answers) !== $questions->count(), 422, 'Every question must be answered.');

            $attempt = $user->quizAttempts()->create([
                'quiz_id' => $quiz->id,
                'started_at' => now(),
                'submitted_at' => now(),
                'total_questions' => $questions->count(),
            ]);
            $correctAnswers = 0;

            foreach ($questions as $question) {
                $choice = $question->choices->firstWhere('id', $answers[$question->id] ?? null);
                abort_if($choice === null, 422, 'Every answer must belong to its quiz question.');

                $attempt->answers()->create([
                    'question_id' => $question->id,
                    'question_choice_id' => $choice->id,
                    'is_correct' => $choice->is_correct,
                ]);
                $correctAnswers += $choice->is_correct ? 1 : 0;
            }

            $score = round(($correctAnswers / $questions->count()) * 100, 2);
            $passed = $score >= (float) $quiz->passing_score;

            $attempt->update([
                'correct_answers' => $correctAnswers,
                'score' => $score,
                'passed' => $passed,
            ]);

            $progress = ConceptProgress::query()->firstOrNew([
                'user_id' => $user->id,
                'concept_id' => $quiz->concept_id,
            ]);
            $progress->fill([
                'last_score' => $score,
                'best_score' => max((float) ($progress->best_score ?? 0), $score),
                'attempts_count' => $user->quizAttempts()->whereHas('quiz', fn ($query) => $query->where('concept_id', $quiz->concept_id))->count(),
                'last_attempted_at' => now(),
                'is_completed' => $progress->is_completed || $passed,
            ]);
            $progress->save();

            return $attempt;
        });
    }
}
