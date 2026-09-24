<?php

use App\Models\Concept;
use App\Models\Program;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\SyllabusTopic;
use App\Models\User;

test('a public learner can submit a quiz and receive persisted progress', function () {
    User::factory()->create();
    $program = Program::query()->create(['name' => 'CPALE', 'code' => 'CPALE']);
    $subject = Subject::query()->create(['program_id' => $program->id, 'name' => 'FAR', 'code' => 'FAR']);
    $topic = SyllabusTopic::query()->create(['subject_id' => $subject->id, 'code' => 'PPE', 'title' => 'PPE']);
    $concept = Concept::query()->create(['subject_id' => $subject->id, 'syllabus_topic_id' => $topic->id, 'code' => 'PPE-1', 'title' => 'Initial cost']);
    $question = Question::query()->create(['concept_id' => $concept->id, 'prompt' => 'Capitalizable?', 'difficulty' => 'easy']);
    $correctChoice = $question->choices()->create(['content' => 'Delivery', 'is_correct' => true]);
    $question->choices()->create(['content' => 'Advertising', 'is_correct' => false]);
    $quiz = Quiz::query()->create(['concept_id' => $concept->id, 'title' => 'PPE check', 'passing_score' => 80]);
    $quiz->questions()->attach($question, ['sort_order' => 1]);

    $this->post(route('study.quizzes.attempts.store', $quiz), ['answers' => [$question->id => $correctChoice->id]])
        ->assertRedirect();

    $this->assertDatabaseHas('quiz_attempts', ['quiz_id' => $quiz->id, 'score' => 100, 'correct_answers' => 1, 'passed' => true]);
    $this->assertDatabaseHas('attempt_answers', ['question_id' => $question->id, 'question_choice_id' => $correctChoice->id, 'is_correct' => true]);
    $this->assertDatabaseHas('concept_progress', ['concept_id' => $concept->id, 'last_score' => 100, 'best_score' => 100, 'is_completed' => true]);
});

test('a quiz submission requires an answer for every question', function () {
    User::factory()->create();
    $program = Program::query()->create(['name' => 'CPALE', 'code' => 'CPALE']);
    $subject = Subject::query()->create(['program_id' => $program->id, 'name' => 'FAR', 'code' => 'FAR']);
    $concept = Concept::query()->create(['subject_id' => $subject->id, 'code' => 'PPE-1', 'title' => 'Initial cost']);
    $question = Question::query()->create(['concept_id' => $concept->id, 'prompt' => 'Capitalizable?', 'difficulty' => 'easy']);
    $question->choices()->create(['content' => 'Delivery', 'is_correct' => true]);
    $quiz = Quiz::query()->create(['concept_id' => $concept->id, 'title' => 'PPE check']);
    $quiz->questions()->attach($question, ['sort_order' => 1]);

    $this->post(route('study.quizzes.attempts.store', $quiz), ['answers' => []])
        ->assertSessionHasErrors('answers');
});

test('content creation rejects a self prerequisite and invalid question choices', function () {
    $program = Program::query()->create(['name' => 'CPALE', 'code' => 'CPALE']);
    $subject = Subject::query()->create(['program_id' => $program->id, 'name' => 'FAR', 'code' => 'FAR']);
    $concept = Concept::query()->create(['subject_id' => $subject->id, 'code' => 'PPE-1', 'title' => 'Initial cost']);

    $this->post(route('concepts.prerequisites.store', $concept), ['prerequisite_concept_id' => $concept->id])
        ->assertStatus(422);

    $this->post(route('concepts.questions.store', $concept), [
        'prompt' => 'Which cost is capitalized?',
        'difficulty' => 'easy',
        'choices' => [
            ['content' => 'Delivery', 'is_correct' => false],
            ['content' => 'Advertising', 'is_correct' => false],
        ],
    ])->assertStatus(422);

    expect($concept->questions()->count())->toBe(0);
});

test('program creation validates unique program codes', function () {
    $this->post(route('programs.store'), ['name' => 'CPALE', 'code' => 'CPALE'])
        ->assertRedirect();

    $this->from(route('programs.create'))
        ->post(route('programs.store'), ['name' => 'Another Program', 'code' => 'CPALE'])
        ->assertRedirect(route('programs.create'))
        ->assertSessionHasErrors('code');
});

test('content CRUD creates lessons and objectives under a concept', function () {
    $program = Program::query()->create(['name' => 'CPALE', 'code' => 'CPALE']);
    $subject = Subject::query()->create(['program_id' => $program->id, 'name' => 'FAR', 'code' => 'FAR']);
    $concept = Concept::query()->create(['subject_id' => $subject->id, 'code' => 'PPE-1', 'title' => 'Initial cost']);

    $this->post(route('concepts.lessons.store', $concept), [
        'title' => 'Cost lesson',
        'summary' => 'Summary',
        'content' => 'Content',
    ])->assertRedirect(route('concepts.show', $concept));

    $this->post(route('concepts.objectives.store', $concept), [
        'description' => 'Identify directly attributable costs.',
    ])->assertRedirect(route('concepts.show', $concept));

    $this->assertDatabaseHas('lessons', ['concept_id' => $concept->id, 'title' => 'Cost lesson']);
    $this->assertDatabaseHas('learning_objectives', ['concept_id' => $concept->id, 'description' => 'Identify directly attributable costs.']);
});

test('a quiz cannot include a question from another concept', function () {
    $program = Program::query()->create(['name' => 'CPALE', 'code' => 'CPALE']);
    $subject = Subject::query()->create(['program_id' => $program->id, 'name' => 'FAR', 'code' => 'FAR']);
    $firstConcept = Concept::query()->create(['subject_id' => $subject->id, 'code' => 'PPE-1', 'title' => 'Initial cost']);
    $secondConcept = Concept::query()->create(['subject_id' => $subject->id, 'code' => 'PPE-2', 'title' => 'Depreciation']);
    $question = $secondConcept->questions()->create(['prompt' => 'Question', 'difficulty' => 'easy']);
    $question->choices()->create(['content' => 'Correct', 'is_correct' => true]);
    $question->choices()->create(['content' => 'Wrong', 'is_correct' => false]);

    $this->post(route('concepts.quizzes.store', $firstConcept), [
        'title' => 'Invalid quiz',
        'passing_score' => 80,
        'question_ids' => [$question->id],
    ])->assertStatus(422);

    expect($firstConcept->quizzes()->count())->toBe(0);
});

test('domain factories create usable records', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $concept = Concept::factory()->for($subject)->create();
    $question = Question::factory()->for($concept)->create();

    expect($program->exists)->toBeTrue()
        ->and($subject->program->is($program))->toBeTrue()
        ->and($concept->subject->is($subject))->toBeTrue()
        ->and($question->concept->is($concept))->toBeTrue();
});

test('public study and progress entry points render for the local learner', function () {
    User::factory()->create();

    $this->get(route('study.index'))->assertOk();

    $this->get(route('progress.index'))->assertOk();
});
