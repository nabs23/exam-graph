<?php

use App\Http\Controllers\ConceptController;
use App\Http\Controllers\CurriculumExtractionController;
use App\Http\Controllers\LearningObjectiveController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\ProgramFileController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\QuizAttemptController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\StudyController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SubjectFileController;
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
    Route::resource('subjects', SubjectController::class)->only(['index', 'create', 'store']);
    Route::get('programs/{program}/files', [ProgramFileController::class, 'index'])->name('programs.files.index');
    Route::post('programs/{program}/files/uploads', [ProgramFileController::class, 'uploadUrl'])->middleware('throttle:10,1')->name('programs.files.upload-url');
    Route::post('files/{programFile}', [ProgramFileController::class, 'store'])->middleware('throttle:10,1')->name('program-files.store');
    Route::get('files/{programFile}', [ProgramFileController::class, 'show'])->name('program-files.show');
    Route::patch('files/{programFile}', [ProgramFileController::class, 'update'])->name('program-files.update');
    Route::delete('files/{programFile}', [ProgramFileController::class, 'destroy'])->name('program-files.destroy');
    Route::post('files/{programFile}/download', [ProgramFileController::class, 'download'])->name('program-files.download');
    Route::post('files/{programFile}/embeddings', [ProgramFileController::class, 'embed'])->middleware('throttle:5,1')->name('program-files.embeddings.store');
    Route::post('files/{programFile}/curriculum-extractions', [CurriculumExtractionController::class, 'store'])->middleware('throttle:2,1')->name('program-files.curriculum-extractions.store');
    Route::get('curriculum-extractions/{curriculumExtraction}', [CurriculumExtractionController::class, 'show'])->name('curriculum-extractions.show');
    Route::post('curriculum-extractions/{curriculumExtraction}/publish', [CurriculumExtractionController::class, 'publish'])->name('curriculum-extractions.publish');
    Route::post('curriculum-extractions/{curriculumExtraction}/reject', [CurriculumExtractionController::class, 'reject'])->name('curriculum-extractions.reject');
    Route::get('subjects/{subject}/files', [SubjectFileController::class, 'index'])->name('subjects.files.index');
    Route::post('subjects/{subject}/files/uploads', [SubjectFileController::class, 'uploadUrl'])->middleware('throttle:10,1')->name('subjects.files.upload-url');
    Route::post('subject-files/{subjectFile}', [SubjectFileController::class, 'store'])->middleware('throttle:10,1')->name('subject-files.store');
    Route::get('subject-files/{subjectFile}', [SubjectFileController::class, 'show'])->name('subject-files.show');
    Route::patch('subject-files/{subjectFile}', [SubjectFileController::class, 'update'])->name('subject-files.update');
    Route::delete('subject-files/{subjectFile}', [SubjectFileController::class, 'destroy'])->name('subject-files.destroy');
    Route::post('subject-files/{subjectFile}/download', [SubjectFileController::class, 'download'])->name('subject-files.download');
    Route::post('subject-files/{subjectFile}/embeddings', [SubjectFileController::class, 'embed'])->middleware('throttle:5,1')->name('subject-files.embeddings.store');
    Route::resource('concepts', ConceptController::class)->only(['index', 'create', 'store']);
    Route::prefix('programs/{program}')->scopeBindings()->group(function (): void {
        Route::resource('subjects', SubjectController::class)->only(['index', 'create', 'store'])->names(['index' => 'programs.subjects.index', 'create' => 'programs.subjects.create', 'store' => 'programs.subjects.store']);
        Route::resource('subjects', SubjectController::class)->except(['index', 'create', 'store']);
        Route::prefix('subjects/{subject}')->group(function (): void {
            Route::resource('topics', SyllabusTopicController::class);

            $conceptRoutes = function (): void {
                Route::resource('concepts', ConceptController::class)->except(['index', 'create', 'store']);
                Route::resource('concepts.lessons', LessonController::class)->names(['show' => 'lessons.show', 'edit' => 'lessons.edit', 'update' => 'lessons.update', 'destroy' => 'lessons.destroy']);
                Route::resource('concepts.questions', QuestionController::class)->names(['show' => 'questions.show', 'edit' => 'questions.edit', 'update' => 'questions.update', 'destroy' => 'questions.destroy']);
                Route::resource('concepts.quizzes', QuizController::class)->names(['show' => 'quizzes.show', 'edit' => 'quizzes.edit', 'update' => 'quizzes.update', 'destroy' => 'quizzes.destroy']);
                Route::resource('concepts.objectives', LearningObjectiveController::class)->names(['show' => 'objectives.show', 'edit' => 'objectives.edit', 'update' => 'objectives.update', 'destroy' => 'objectives.destroy']);
                Route::post('concepts/{concept}/prerequisites', [ConceptController::class, 'addPrerequisite'])->name('concepts.prerequisites.store');
                Route::delete('concepts/{concept}/prerequisites/{prerequisite}', [ConceptController::class, 'removePrerequisite'])->name('concepts.prerequisites.destroy');
            };

            Route::prefix('topics/{topic}')->group(function () use ($conceptRoutes): void {
                Route::resource('concepts', ConceptController::class)->only(['index', 'create', 'store'])->names(['index' => 'topics.concepts.index', 'create' => 'topics.concepts.create', 'store' => 'topics.concepts.store']);
                $conceptRoutes();
            });
            Route::resource('concepts', ConceptController::class)->only(['index', 'create', 'store'])->names(['index' => 'subjects.concepts.index', 'create' => 'subjects.concepts.create', 'store' => 'subjects.concepts.store']);
            Route::name('unassigned.')->group($conceptRoutes);
        });
    });
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
