<?php

use App\Models\Concept;
use App\Models\Program;
use App\Models\Subject;
use App\Models\SubjectFile;
use App\Models\SyllabusTopic;
use Illuminate\Support\Facades\Storage;

test('merging subjects preserves topic hierarchy concepts and files with a backup', function () {
    Storage::fake('local');
    $program = Program::factory()->create();
    $target = Subject::factory()->for($program)->create(['name' => 'Biology', 'code' => null]);
    $source = Subject::factory()->for($program)->create(['name' => 'BIOLOGY', 'code' => 'BIO']);
    $parent = SyllabusTopic::factory()->for($target)->create(['title' => 'Cells', 'code' => 'CELL', 'parent_id' => null]);
    $duplicate = SyllabusTopic::factory()->for($source)->create(['title' => 'CELLS', 'code' => 'cell', 'parent_id' => null]);
    $child = SyllabusTopic::factory()->for($source)->create(['title' => 'Structure', 'code' => null, 'parent_id' => $duplicate->id]);
    $concept = Concept::factory()->for($source)->create(['syllabus_topic_id' => $duplicate->id]);
    $file = SubjectFile::factory()->for($source)->create();

    $this->artisan('app:merge-curriculum-subjects', ['target' => $target->id, 'sources' => [$source->id], '--apply' => true])->assertSuccessful();

    $this->assertModelMissing($source);
    $this->assertModelMissing($duplicate);
    $this->assertDatabaseHas('subjects', ['id' => $target->id, 'code' => 'BIO', 'name' => 'Biology']);
    $this->assertDatabaseHas('syllabus_topics', ['id' => $child->id, 'parent_id' => $parent->id, 'subject_id' => $target->id]);
    $this->assertDatabaseHas('concepts', ['id' => $concept->id, 'subject_id' => $target->id, 'syllabus_topic_id' => $parent->id]);
    $this->assertDatabaseHas('subject_files', ['id' => $file->id, 'subject_id' => $target->id, 'storage_key' => $file->storage_key]);
    $backups = Storage::disk('local')->files('curriculum-merges');
    expect($backups)->toHaveCount(1);
    $backup = json_decode(Storage::disk('local')->get($backups[0]), true, flags: JSON_THROW_ON_ERROR);
    expect($backup['subjects'])->toHaveCount(2);
    expect($backup['syllabus_topics'])->toHaveCount(3);
    expect($backup['concepts'][0]['subject_id'])->toBe($source->id);
});

test('a merge preview does not change records or create a backup', function () {
    Storage::fake('local');
    $program = Program::factory()->create();
    $target = Subject::factory()->for($program)->create(['name' => 'Biology', 'code' => null]);
    $source = Subject::factory()->for($program)->create(['name' => 'BIOLOGY', 'code' => null]);
    $topic = SyllabusTopic::factory()->for($source)->create(['parent_id' => null]);

    $this->artisan('app:merge-curriculum-subjects', ['target' => $target->id, 'sources' => [$source->id]])->assertSuccessful();

    $this->assertModelExists($source);
    $this->assertDatabaseHas('syllabus_topics', ['id' => $topic->id, 'subject_id' => $source->id]);
    expect(Storage::disk('local')->files('curriculum-merges'))->toBeEmpty();
});

test('a conflicting topic rolls back the whole subject merge', function () {
    Storage::fake('local');
    $program = Program::factory()->create();
    $target = Subject::factory()->for($program)->create(['name' => 'Biology', 'code' => null]);
    $source = Subject::factory()->for($program)->create(['name' => 'BIOLOGY', 'code' => null]);
    $firstTopic = SyllabusTopic::factory()->for($source)->create(['title' => 'New topic', 'code' => 'NEW', 'parent_id' => null]);
    SyllabusTopic::factory()->for($target)->create(['title' => 'Cells', 'code' => 'CELL', 'parent_id' => null]);
    SyllabusTopic::factory()->for($source)->create(['title' => 'Different meaning', 'code' => 'CELL', 'parent_id' => null]);

    $this->artisan('app:merge-curriculum-subjects', ['target' => $target->id, 'sources' => [$source->id], '--apply' => true])->assertFailed();

    $this->assertModelExists($source);
    $this->assertDatabaseHas('syllabus_topics', ['id' => $firstTopic->id, 'subject_id' => $source->id]);
    expect(Storage::disk('local')->files('curriculum-merges'))->toBeEmpty();
});

test('merging subjects rejects conflicting subject codes', function () {
    $program = Program::factory()->create();
    $target = Subject::factory()->for($program)->create(['name' => 'Biology', 'code' => 'BIO']);
    $source = Subject::factory()->for($program)->create(['name' => 'BIOLOGY', 'code' => 'BIO2']);

    $this->artisan('app:merge-curriculum-subjects', ['target' => $target->id, 'sources' => [$source->id], '--apply' => true])->assertFailed();

    $this->assertModelExists($source);
    $this->assertModelExists($target);
});

test('merging subjects rejects concept code collisions', function () {
    $program = Program::factory()->create();
    $target = Subject::factory()->for($program)->create(['name' => 'Biology', 'code' => null]);
    $source = Subject::factory()->for($program)->create(['name' => 'BIOLOGY', 'code' => null]);
    Concept::factory()->for($target)->create(['code' => 'CELL']);
    Concept::factory()->for($source)->create(['code' => 'cell']);

    $this->artisan('app:merge-curriculum-subjects', ['target' => $target->id, 'sources' => [$source->id], '--apply' => true])->assertFailed();

    $this->assertModelExists($source);
    $this->assertDatabaseCount('concepts', 2);
});
