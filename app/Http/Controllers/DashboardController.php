<?php

namespace App\Http\Controllers;

use App\CurriculumExtractionStatus;
use App\CurriculumGenerationStatus;
use App\Models\CurriculumExtraction;
use App\Models\CurriculumGeneration;
use App\Models\Program;
use App\Models\Subject;
use App\Models\SubjectTopicExtraction;
use App\Models\User;
use App\SubjectTopicExtractionStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $counts = [
            'programs' => Program::query()->count(),
            'subjects' => Subject::query()->count(),
            'studyHistory' => $user->quizAttempts()->count(),
            'reviewWork' => 0,
        ];

        if ($user->is_admin) {
            $counts['reviewWork'] = CurriculumGeneration::query()->where('status', CurriculumGenerationStatus::Reviewing)->count()
                + CurriculumExtraction::query()->where('status', CurriculumExtractionStatus::Reviewing)->count()
                + SubjectTopicExtraction::query()->where('status', SubjectTopicExtractionStatus::Reviewing)->count();
        }

        return Inertia::render('dashboard', [
            'isAdmin' => $user->is_admin,
            'counts' => $counts,
        ]);
    }
}
