<?php

use App\FileUploadStatus;
use App\Models\Program;
use App\Models\ProgramFile;
use App\Models\Subject;
use App\Models\SubjectFile;
use App\Models\User;
use App\Services\SourceFileStorage;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;

test('source file libraries are restricted to content managers', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->create();

    $this->get(route('programs.files.index', $program))->assertRedirect(route('login'));
    $this->get(route('subjects.files.index', $subject))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('programs.files.index', $program))
        ->assertForbidden();
});

test('content managers can inspect files only without receiving storage internals', function () {
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create(['storage_key' => 'program-files/'.$program->id.'/opaque.pdf']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('program-files.show', $file))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/program-files/show')->where('file.id', $file->id)->missing('file.storage_key')->missing('file.storage_disk'));
});

test('content managers can create a program file upload URL', function () {
    $program = Program::factory()->create();
    $user = User::factory()->admin()->create();
    $key = 'program-files/'.$program->id.'/source.pdf';

    $this->mock(SourceFileStorage::class, function (MockInterface $mock) use ($key): void {
        $mock->shouldReceive('createKey')->once()->andReturn($key);
        $mock->shouldReceive('presign')->once()->andReturn([
            'url' => 'https://example-bucket.s3.amazonaws.com/'.$key,
            'headers' => ['Content-Type' => 'application/pdf'],
        ]);
    });

    $this->actingAs($user)
        ->postJson(route('programs.files.upload-url', $program), [
            'file_type' => 'other',
            'title' => 'Exam specification',
            'original_filename' => 'source.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'file_size' => 1024,
        ])
        ->assertOk()
        ->assertJsonPath('upload_url', 'https://example-bucket.s3.amazonaws.com/'.$key)
        ->assertJsonPath('required_headers.Content-Type', 'application/pdf');

    $this->assertDatabaseHas('program_files', [
        'program_id' => $program->id,
        'storage_key' => $key,
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'uploaded_by' => $user->id,
    ]);
});

test('content managers can verify an uploaded program file', function () {
    Storage::fake('s3');

    $program = Program::factory()->create();
    $contents = "%PDF-1.4\n";
    $key = 'program-files/'.$program->id.'/source.pdf';
    $file = ProgramFile::factory()->for($program)->create([
        'storage_key' => $key,
        'file_size' => strlen($contents),
        'mime_type' => 'application/pdf',
        'upload_status' => FileUploadStatus::PendingUpload,
        'uploaded_at' => null,
    ]);
    Storage::disk('s3')->put($key, $contents);

    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('program-files.store', $file))
        ->assertOk()
        ->assertJsonPath('id', $file->id)
        ->assertJsonPath('upload_status', FileUploadStatus::Uploaded->value);

    $this->assertDatabaseHas('program_files', [
        'id' => $file->id,
        'upload_status' => FileUploadStatus::Uploaded->value,
    ]);
});

test('source file metadata updates cannot alter ownership or storage attributes', function () {
    $subject = Subject::factory()->create();
    $file = SubjectFile::factory()->for($subject)->create();
    $key = $file->storage_key;

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('subject-files.update', $file), [
            'file_type' => 'other', 'title' => 'Updated reference', 'metadata' => ['author' => 'Author'],
            'subject_id' => $subject->id + 1, 'storage_key' => 'attacker/key.pdf', 'storage_disk' => 'public',
            'file_size' => 999, 'upload_status' => 'failed',
        ])
        ->assertRedirect();

    expect($file->fresh()->storage_key)->toBe($key)
        ->and($file->fresh()->subject_id)->toBe($subject->id)
        ->and($file->fresh()->file_size)->toBe(1024)
        ->and($file->fresh()->title)->toBe('Updated reference');
});
