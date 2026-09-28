<?php

use App\Http\Controllers\ConceptController;
use App\Http\Controllers\LearningObjectiveController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\QuizAttemptController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\StudyController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SyllabusTopicController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('study', [StudyController::class, 'index'])->name('study.index');
    Route::get('study/subjects/{subject}', [StudyController::class, 'subject'])->name('study.subjects.show');
    Route::get('study/concepts/{concept}', [StudyController::class, 'concept'])->name('study.concepts.show');
    Route::get('study/lessons/{lesson}', [StudyController::class, 'lesson'])->name('study.lessons.show');
    Route::get('study/quizzes/{quiz}', [QuizAttemptController::class, 'show'])->name('study.quizzes.show');
    Route::post('study/quizzes/{quiz}/attempts', [QuizAttemptController::class, 'store'])->name('study.quizzes.attempts.store');
    Route::get('study/attempts/{attempt}', [QuizAttemptController::class, 'result'])->name('attempts.result');
    Route::get('progress', [ProgressController::class, 'index'])->name('progress.index');
});

Route::middleware(['auth', 'verified', 'can:manage-content'])->group(function (): void {
    Route::resource('programs', ProgramController::class);
    Route::resource('subjects', SubjectController::class);
    Route::resource('concepts', ConceptController::class);
    Route::resource('subjects.topics', SyllabusTopicController::class)->shallow();
    Route::resource('concepts.questions', QuestionController::class)->shallow();
    Route::resource('concepts.quizzes', QuizController::class)->shallow();
    Route::resource('concepts.lessons', LessonController::class)->shallow();
    Route::resource('concepts.objectives', LearningObjectiveController::class)->shallow();
    Route::post('concepts/{concept}/prerequisites', [ConceptController::class, 'addPrerequisite'])->name('concepts.prerequisites.store');
    Route::delete('concepts/{concept}/prerequisites/{prerequisite}', [ConceptController::class, 'removePrerequisite'])->name('concepts.prerequisites.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
