<?php

use App\Models\Concept;
use App\Models\Subject;
use App\Models\SyllabusTopic;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('renders all topics from the root topics index', function () {
    $firstTopic = SyllabusTopic::factory()->create(['sort_order' => 1]);
    $secondTopic = SyllabusTopic::factory()->create(['sort_order' => 2]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('topics.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/topics/index')
            ->where('subject', null)
            ->has('topics', 2)
            ->where('topics.0.id', $firstTopic->id)
            ->where('topics.1.id', $secondTopic->id));
});

test('creating a syllabus topic shows a success toast', function () {
    $subject = Subject::factory()->create();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route('topics.store', $subject->curriculumRouteParameters()), [
            'code' => 'FND-1',
            'title' => 'Foundations',
            'sort_order' => 1,
        ]);

    $response
        ->assertRedirect($subject->curriculumRoute('subjects.show'))
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'The syllabus topic was created.');

    $this->assertDatabaseHas('syllabus_topics', [
        'subject_id' => $subject->id,
        'code' => 'FND-1',
        'title' => 'Foundations',
        'sort_order' => 1,
    ]);
});

test('updating a syllabus topic confirms the saved changes', function () {
    $subject = Subject::factory()->create();
    $topic = SyllabusTopic::factory()->for($subject)->create([
        'title' => 'Original title',
    ]);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->put($topic->curriculumRoute('topics.update'), [
            'code' => $topic->code,
            'title' => 'Updated title',
            'description' => $topic->description,
            'parent_id' => $topic->parent_id,
            'sort_order' => $topic->sort_order,
        ]);

    $response
        ->assertRedirect($subject->curriculumRoute('subjects.show'))
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'Syllabus topic changes were saved.');

    $this->assertDatabaseHas('syllabus_topics', [
        'id' => $topic->id,
        'title' => 'Updated title',
    ]);
});

test('deleting an unused syllabus topic confirms its removal', function () {
    $subject = Subject::factory()->create();
    $topic = SyllabusTopic::factory()->for($subject)->create();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->delete($topic->curriculumRoute('topics.destroy'));

    $response
        ->assertRedirect($subject->curriculumRoute('subjects.show'))
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'The syllabus topic was deleted.');

    $this->assertModelMissing($topic);
});

test('deleting a topic with child topics returns a recovery message', function () {
    $subject = Subject::factory()->create();
    $topic = SyllabusTopic::factory()->for($subject)->create();
    SyllabusTopic::factory()->for($subject)->create(['parent_id' => $topic->id]);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->delete($topic->curriculumRoute('topics.destroy'));

    $response
        ->assertRedirect($topic->curriculumRoute('topics.show'))
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash(
            'toast.message',
            'Remove child topics before deleting this topic.',
        );

    $this->assertModelExists($topic);
});

test('deleting a topic with linked concepts returns a recovery message', function () {
    $subject = Subject::factory()->create();
    $topic = SyllabusTopic::factory()->for($subject)->create();
    Concept::factory()->for($subject)->create([
        'syllabus_topic_id' => $topic->id,
    ]);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->delete($topic->curriculumRoute('topics.destroy'));

    $response
        ->assertRedirect($topic->curriculumRoute('topics.show'))
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash(
            'toast.message',
            'Remove linked concepts before deleting this topic.',
        );

    $this->assertModelExists($topic);
});

test('topic detail explains which dependencies block deletion', function () {
    $subject = Subject::factory()->create();
    $topic = SyllabusTopic::factory()->for($subject)->create();
    SyllabusTopic::factory()->for($subject)->create(['parent_id' => $topic->id]);
    Concept::factory()->for($subject)->create([
        'syllabus_topic_id' => $topic->id,
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get($topic->curriculumRoute('topics.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/topics/show')
            ->where(
                'deleteBlockedReason',
                'Remove child topics and linked concepts before deleting this topic.',
            ));
});
