<?php

use App\Models\Concept;
use App\Models\LearningObjective;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\SyllabusTopic;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('assigned lessons retain the entire curriculum hierarchy in their URLs and page data', function () {
    $subject = Subject::factory()->create();
    $topic = SyllabusTopic::factory()->for($subject)->create();
    $concept = Concept::factory()->for($subject)->create(['syllabus_topic_id' => $topic->id]);
    $lesson = Lesson::factory()->for($concept)->create();

    expect(parse_url($lesson->curriculumRoute('lessons.show'), PHP_URL_PATH))
        ->toBe("/programs/{$subject->program_id}/subjects/{$subject->id}/topics/{$topic->id}/concepts/{$concept->id}/lessons/{$lesson->id}");
    $this->actingAs(User::factory()->admin()->create())
        ->get($lesson->curriculumRoute('lessons.show'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/lessons/show')
            ->where('lesson.id', $lesson->id)
            ->where('lesson.route_parameters', $lesson->curriculumRouteParameters()));
    $this->put($lesson->curriculumRoute('lessons.update'), ['title' => 'Reviewed lesson', 'content' => 'Updated content'])
        ->assertRedirect($concept->curriculumRoute('concepts.show'));
    $this->assertDatabaseHas('lessons', ['id' => $lesson->id, 'title' => 'Reviewed lesson']);
});

test('nested curriculum routes reject records that do not belong to their URL parents', function () {
    $subject = Subject::factory()->create();
    $topic = SyllabusTopic::factory()->for($subject)->create();
    $concept = Concept::factory()->for($subject)->create(['syllabus_topic_id' => $topic->id]);
    $lesson = Lesson::factory()->for($concept)->create();
    $otherSubject = Subject::factory()->create();
    $otherProgram = Program::factory()->create();
    $otherTopic = SyllabusTopic::factory()->for($subject)->create();
    $otherConcept = Concept::factory()->for($subject)->create(['syllabus_topic_id' => $topic->id]);
    $otherLesson = Lesson::factory()->for($otherConcept)->create();
    $parameters = $lesson->curriculumRouteParameters();
    $this->actingAs(User::factory()->admin()->create());

    foreach ([['program' => $otherProgram->id], ['subject' => $otherSubject->id], ['topic' => $otherTopic->id], ['concept' => $otherConcept->id], ['lesson' => $otherLesson->id]] as $mismatch) {
        $url = route('lessons.show', array_replace($parameters, $mismatch));
        $this->get($url)->assertNotFound();
    }
    $this->put(route('lessons.update', array_replace($parameters, ['lesson' => $otherLesson->id])), ['title' => 'Wrong lesson', 'content' => 'Wrong content'])
        ->assertNotFound();
    $this->assertDatabaseMissing('lessons', ['id' => $otherLesson->id, 'title' => 'Wrong lesson']);
});

test('unassigned concepts and their lessons remain accessible beneath their subject', function () {
    $subject = Subject::factory()->create();
    $concept = Concept::factory()->for($subject)->create();
    $lesson = Lesson::factory()->for($concept)->create();

    expect(parse_url($lesson->curriculumRoute('lessons.show'), PHP_URL_PATH))
        ->toBe("/programs/{$subject->program_id}/subjects/{$subject->id}/concepts/{$concept->id}/lessons/{$lesson->id}");
    $this->actingAs(User::factory()->admin()->create())
        ->get($lesson->curriculumRoute('lessons.show'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/lessons/show')->where('lesson.id', $lesson->id));
});

test('assigned concepts cannot be opened through the unassigned subject route', function () {
    $subject = Subject::factory()->create();
    $topic = SyllabusTopic::factory()->for($subject)->create();
    $concept = Concept::factory()->for($subject)->create(['syllabus_topic_id' => $topic->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('unassigned.concepts.show', ['program' => $subject->program_id, 'subject' => $subject->id, 'concept' => $concept->id]))
        ->assertNotFound();
});

test('creating a concept from a topic keeps the route ownership and preselected context', function () {
    $subject = Subject::factory()->create();
    $topic = SyllabusTopic::factory()->for($subject)->create();
    $otherSubject = Subject::factory()->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->get($topic->curriculumRoute('topics.concepts.create'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/concepts/create')
            ->where('contextSubject.id', $subject->id)->where('contextTopic.id', $topic->id)
            ->has('subjects', 1)->has('topics', 1));
    $this->post($topic->curriculumRoute('topics.concepts.store'), [
        'subject_id' => $otherSubject->id, 'syllabus_topic_id' => null, 'code' => 'CELL-1', 'title' => 'Cell membranes',
    ])->assertRedirect();
    $this->assertDatabaseHas('concepts', ['subject_id' => $subject->id, 'syllabus_topic_id' => $topic->id, 'code' => 'CELL-1']);
});

test('moving a concept redirects to its new subject hierarchy', function () {
    $concept = Concept::factory()->create();
    $destination = Subject::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put($concept->curriculumRoute('concepts.update'), [
            'subject_id' => $destination->id, 'syllabus_topic_id' => null,
            'code' => $concept->code, 'title' => $concept->title,
        ])->assertRedirect(route('unassigned.concepts.show', [
            'program' => $destination->program_id, 'subject' => $destination->id, 'concept' => $concept->id,
        ]));
    $this->assertDatabaseHas('concepts', ['id' => $concept->id, 'subject_id' => $destination->id]);
});

test('all concept resources resolve beneath their owning assigned concept', function () {
    $subject = Subject::factory()->create();
    $topic = SyllabusTopic::factory()->for($subject)->create();
    $concept = Concept::factory()->for($subject)->create(['syllabus_topic_id' => $topic->id]);
    $this->actingAs(User::factory()->admin()->create());

    foreach ([
        'lessons' => Lesson::factory()->for($concept)->create(),
        'questions' => Question::factory()->for($concept)->create(),
        'quizzes' => Quiz::factory()->for($concept)->create(),
        'objectives' => LearningObjective::factory()->for($concept)->create(),
    ] as $resource => $record) {
        $this->get($record->curriculumRoute($resource.'.show'))->assertOk();
        $this->get($record->curriculumRoute($resource.'.edit'))->assertOk();
    }
});
