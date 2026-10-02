<?php

use App\Models\Concept;
use App\Models\Program;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Subject;
use App\Models\SyllabusTopic;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('a public learner can submit a quiz and receive persisted progress', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
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

    $attempt = QuizAttempt::query()->firstOrFail();
    $this->actingAs(User::factory()->create())
        ->get(route('attempts.result', $attempt))
        ->assertForbidden();
});

test('a quiz submission requires an answer for every question', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
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
    $this->actingAs(User::factory()->admin()->create());

    $program = Program::query()->create(['name' => 'CPALE', 'code' => 'CPALE']);
    $subject = Subject::query()->create(['program_id' => $program->id, 'name' => 'FAR', 'code' => 'FAR']);
    $concept = Concept::query()->create(['subject_id' => $subject->id, 'code' => 'PPE-1', 'title' => 'Initial cost']);

    $this->post($concept->curriculumRoute('concepts.prerequisites.store'), ['prerequisite_concept_id' => $concept->id])
        ->assertStatus(422);

    $this->post($concept->curriculumRoute('concepts.questions.store'), [
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
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('programs.store'), ['name' => 'CPALE', 'code' => 'CPALE'])
        ->assertRedirect();

    $this->from(route('programs.create'))
        ->post(route('programs.store'), ['name' => 'Another Program', 'code' => 'CPALE'])
        ->assertRedirect(route('programs.create'))
        ->assertSessionHasErrors('code');
});

test('content CRUD creates lessons and objectives under a concept', function () {
    $this->actingAs(User::factory()->admin()->create());

    $program = Program::query()->create(['name' => 'CPALE', 'code' => 'CPALE']);
    $subject = Subject::query()->create(['program_id' => $program->id, 'name' => 'FAR', 'code' => 'FAR']);
    $concept = Concept::query()->create(['subject_id' => $subject->id, 'code' => 'PPE-1', 'title' => 'Initial cost']);

    $this->post($concept->curriculumRoute('concepts.lessons.store'), [
        'title' => 'Cost lesson',
        'summary' => 'Summary',
        'content' => 'Content',
    ])->assertRedirect($concept->curriculumRoute('concepts.show'));

    $this->post($concept->curriculumRoute('concepts.objectives.store'), [
        'description' => 'Identify directly attributable costs.',
    ])->assertRedirect($concept->curriculumRoute('concepts.show'));

    $this->assertDatabaseHas('lessons', ['concept_id' => $concept->id, 'title' => 'Cost lesson']);
    $this->assertDatabaseHas('learning_objectives', ['concept_id' => $concept->id, 'description' => 'Identify directly attributable costs.']);
});

test('a quiz cannot include a question from another concept', function () {
    $this->actingAs(User::factory()->admin()->create());

    $program = Program::query()->create(['name' => 'CPALE', 'code' => 'CPALE']);
    $subject = Subject::query()->create(['program_id' => $program->id, 'name' => 'FAR', 'code' => 'FAR']);
    $firstConcept = Concept::query()->create(['subject_id' => $subject->id, 'code' => 'PPE-1', 'title' => 'Initial cost']);
    $secondConcept = Concept::query()->create(['subject_id' => $subject->id, 'code' => 'PPE-2', 'title' => 'Depreciation']);
    $question = $secondConcept->questions()->create(['prompt' => 'Question', 'difficulty' => 'easy']);
    $question->choices()->create(['content' => 'Correct', 'is_correct' => true]);
    $question->choices()->create(['content' => 'Wrong', 'is_correct' => false]);

    $this->post($firstConcept->curriculumRoute('concepts.quizzes.store'), [
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
    $this->actingAs(User::factory()->create());

    $this->get(route('study.index'))->assertOk();

    $this->get(route('progress.index'))->assertOk();
});

test('topic routes resolve the requested topic for viewing editing updating and deleting', function () {
    $subject = Subject::factory()->create();
    $topic = SyllabusTopic::factory()->for($subject)->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->get($topic->curriculumRoute('topics.show'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/topics/show')
            ->where('topic.id', $topic->id)
            ->where('topic.subject.id', $subject->id));
    $this->get($topic->curriculumRoute('topics.edit'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/topics/edit')
            ->where('topic.id', $topic->id));
    $this->put($topic->curriculumRoute('topics.update'), ['title' => 'Updated topic', 'code' => null])
        ->assertRedirect($subject->curriculumRoute('subjects.show'));
    $this->assertDatabaseHas('syllabus_topics', ['id' => $topic->id, 'title' => 'Updated topic']);
    $this->delete($topic->curriculumRoute('topics.destroy'))
        ->assertRedirect($subject->curriculumRoute('subjects.show'));
    $this->assertModelMissing($topic);
    $this->get($topic->curriculumRoute('topics.show'))->assertNotFound();
});

test('content administrators can create a child topic', function () {
    $subject = Subject::factory()->create();
    $parent = SyllabusTopic::factory()->for($subject)->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('topics.store', $subject->curriculumRouteParameters()), [
        'parent_id' => $parent->id,
        'code' => 'FAR-1.1',
        'title' => 'Initial measurement',
        'description' => 'Measure the asset when it is first recognized.',
    ])->assertRedirect($subject->curriculumRoute('subjects.show'));

    $this->assertDatabaseHas('syllabus_topics', [
        'subject_id' => $subject->id,
        'parent_id' => $parent->id,
        'code' => 'FAR-1.1',
        'title' => 'Initial measurement',
    ]);
});

test('the subject outline includes nested topics and concept assignments', function () {
    $subject = Subject::factory()->create();
    $parent = SyllabusTopic::factory()->for($subject)->create(['sort_order' => 0]);
    $child = SyllabusTopic::factory()->for($subject)->create(['parent_id' => $parent->id, 'sort_order' => 1]);
    $linked = Concept::factory()->for($subject)->create(['syllabus_topic_id' => $child->id, 'sort_order' => 0]);
    $unassigned = Concept::factory()->for($subject)->create(['sort_order' => 1]);

    $this->actingAs(User::factory()->admin()->create())
        ->get($subject->curriculumRoute('subjects.show'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/subjects/show')
            ->has('subject.syllabus_topics', 2)
            ->where('subject.syllabus_topics.0.id', $parent->id)
            ->where('subject.syllabus_topics.1.parent_id', $parent->id)
            ->has('subject.concepts', 2)
            ->where('subject.concepts.0.id', $linked->id)
            ->where('subject.concepts.0.syllabus_topic_id', $child->id)
            ->where('subject.concepts.1.id', $unassigned->id)
            ->where('subject.concepts.1.syllabus_topic_id', null));
});
