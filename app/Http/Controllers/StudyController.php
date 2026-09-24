<?php

namespace App\Http\Controllers;

use App\Models\Concept;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\Subject;
use Inertia\Inertia;
use Inertia\Response;

class StudyController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('study/index', ['programs' => Program::query()->with('subjects:id,program_id,name,code')->orderBy('name')->get()]);
    }

    public function subject(Subject $subject): Response
    {
        return Inertia::render('study/subject', ['subject' => $subject->load(['program', 'syllabusTopics.children', 'concepts.syllabusTopic', 'concepts.quizzes'])]);
    }

    public function concept(Concept $concept): Response
    {
        return Inertia::render('study/concept', ['concept' => $concept->load(['subject.program', 'syllabusTopic', 'prerequisites', 'lessons', 'learningObjectives', 'quizzes.questions'])]);
    }

    public function lesson(Lesson $lesson): Response
    {
        return Inertia::render('study/lesson', ['lesson' => $lesson->load(['concept.subject'])]);
    }
}
