<?php

use App\CurriculumExtractionStatus;
use App\CurriculumGenerationStatus;
use App\Models\CurriculumExtraction;
use App\Models\CurriculumGeneration;
use App\Models\Program;
use App\Models\QuizAttempt;
use App\Models\Subject;
use App\Models\SubjectTopicExtraction;
use App\Models\User;
use App\SubjectTopicExtractionStatus;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('isAdmin', false)
        ->where('counts.programs', 0)
        ->where('counts.subjects', 0)
        ->where('counts.studyHistory', 0));
});

test('learners see available subjects and their study history counts', function () {
    $user = User::factory()->create();
    $program = Program::factory()->create();
    Subject::factory()->for($program)->create();
    QuizAttempt::factory()->for($user)->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('isAdmin', false)
            ->has('counts.programs')
            ->has('counts.subjects')
            ->where('counts.studyHistory', 1)
            ->where('counts.reviewWork', 0));
});

test('administrators see all items waiting for review', function () {
    $admin = User::factory()->admin()->create();
    CurriculumGeneration::factory()->create(['status' => CurriculumGenerationStatus::Reviewing]);
    CurriculumExtraction::factory()->create(['status' => CurriculumExtractionStatus::Reviewing]);
    SubjectTopicExtraction::factory()->create(['status' => SubjectTopicExtractionStatus::Reviewing]);

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('isAdmin', true)
            ->where('counts.reviewWork', 3));
});
