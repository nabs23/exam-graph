<?php

use App\FileEmbeddingStatus;
use App\FileUploadStatus;
use App\Jobs\DeleteProgramFile;
use App\Models\CurriculumExtraction;
use App\Models\CurriculumGeneration;
use App\Models\FilePageEmbedding;
use App\Models\Program;
use App\Models\ProgramFile;
use App\Models\Subject;
use App\Models\SubjectFile;
use App\Models\SubjectTopicExtraction;
use App\Models\SyllabusTopic;
use App\Models\User;
use App\Services\SourceFileStorage;
use App\Services\SubjectTopicExtractionService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;

test('program file deletion is queued and returns to the file list', function () {
    Storage::fake('s3');
    Queue::fake([DeleteProgramFile::class]);
    $file = ProgramFile::factory()->create();
    Storage::disk('s3')->put($file->storage_key, 'Private file');

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('program-files.show', $file))
        ->delete(route('program-files.destroy', $file))
        ->assertRedirect(route('programs.files.index', $file->program_id))
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'File deletion has started.');

    expect($file->fresh()->upload_status)->toBe(FileUploadStatus::DeletePending);
    Storage::disk('s3')->assertExists($file->storage_key);
    Queue::assertPushed(DeleteProgramFile::class, fn (DeleteProgramFile $job): bool => $job->fileId === $file->id);
});

test('program file deletion returns to the workspace files tab when started there', function () {
    Queue::fake([DeleteProgramFile::class]);
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('program-files.destroy', $file).'?tab=files')
        ->assertRedirect(route('programs.show', ['program' => $program, 'tab' => 'files']));

    expect($file->fresh()->upload_status)->toBe(FileUploadStatus::DeletePending);
});

test('program file deletion preserves the selected subject filter', function () {
    Queue::fake([DeleteProgramFile::class]);
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $file = ProgramFile::factory()->for($program)->for($subject)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('program-files.destroy', $file).'?subject='.$subject->id)
        ->assertRedirect(route('programs.files.index', [
            'program' => $program,
            'subject' => $subject->id,
        ]));

    expect($file->fresh()->upload_status)->toBe(FileUploadStatus::DeletePending);
});

test('program files referenced by historical extraction snapshots cannot be deleted', function () {
    Queue::fake([DeleteProgramFile::class]);
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create();
    $extraction = CurriculumExtraction::factory()->for($program)->create([
        'program_file_id' => null,
        'source_files' => [[
            'id' => $file->id,
            'content_hash' => $file->content_hash,
            'title' => $file->title,
        ]],
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('program-files.destroy', $file))
        ->assertRedirect(route('programs.files.index', $program->id))
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', 'This source file is referenced by curriculum history and must remain available.');

    $this->assertModelExists($file);
    $this->assertModelExists($extraction);
    expect($file->fresh()->upload_status)->toBe(FileUploadStatus::Uploaded);
    Queue::assertNothingPushed();
});

test('program files selected for curriculum proposals cannot be deleted', function () {
    Queue::fake([DeleteProgramFile::class]);
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create();
    CurriculumGeneration::factory()->for($program)->create([
        'source_files' => [[
            'id' => $file->id,
            'content_hash' => $file->content_hash,
            'title' => $file->title,
        ]],
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('program-files.destroy', $file))
        ->assertRedirect(route('programs.files.index', $program->id))
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', 'This source file is referenced by curriculum history and must remain available.');

    $this->assertModelExists($file);
    Queue::assertNothingPushed();
});

test('program files linked directly to curriculum extractions cannot be deleted', function () {
    Queue::fake([DeleteProgramFile::class]);
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create();
    $extraction = CurriculumExtraction::factory()->for($program)->for($file)->create(['source_files' => []]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('program-files.destroy', $file))
        ->assertRedirect(route('programs.files.index', $program->id))
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', 'This source file is referenced by curriculum history and must remain available.');

    $this->assertModelExists($file);
    $this->assertModelExists($extraction);
    Queue::assertNothingPushed();
});

test('program files cited by published topics cannot be deleted', function () {
    Queue::fake([DeleteProgramFile::class]);
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $topic = SyllabusTopic::factory()->for($subject)->create();
    $file = ProgramFile::factory()->for($program)->create(['content_hash' => hash('sha256', 'cited source')]);
    DB::table('syllabus_topic_sources')->insert([
        'syllabus_topic_id' => $topic->id,
        'program_file_id' => $file->id,
        'source_file_id_snapshot' => $file->id,
        'source_title' => $file->title,
        'source_hash' => $file->content_hash,
        'page_number' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('program-files.destroy', $file))
        ->assertRedirect(route('programs.files.index', $program->id))
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', 'This source file is referenced by curriculum history and must remain available.');

    $this->assertModelExists($topic);
    $this->assertModelExists($file);
    Queue::assertNothingPushed();
});

test('program files mapped from legacy sources cannot be deleted while a historical extraction uses them', function () {
    Queue::fake([DeleteProgramFile::class]);
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $legacyFile = SubjectFile::factory()->for($subject)->create();
    $file = ProgramFile::factory()->for($program)->create(['subject_id' => $subject->id]);
    DB::table('legacy_program_file_sources')->insert([
        'legacy_type' => 'subject_file',
        'legacy_id' => $legacyFile->id,
        'program_file_id' => $file->id,
        'program_id' => $program->id,
        'subject_id' => $subject->id,
        'file_type' => 'other',
        'title' => $legacyFile->title,
        'original_filename' => $legacyFile->original_filename,
        'storage_disk' => $legacyFile->storage_disk,
        'storage_key' => $legacyFile->storage_key,
        'content_hash' => $legacyFile->content_hash,
        'created_program_file' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $extraction = SubjectTopicExtraction::factory()->for($subject)->create([
        'source_files' => [[
            'id' => $legacyFile->id,
            'content_hash' => $legacyFile->content_hash,
            'title' => $legacyFile->title,
            'mime_type' => $legacyFile->mime_type,
        ]],
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('program-files.destroy', $file))
        ->assertRedirect(route('programs.files.index', $program->id))
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', 'This source file is referenced by curriculum history and must remain available.');

    $this->assertModelExists($extraction);
    $this->assertModelExists($file);
    Queue::assertNothingPushed();
});

test('program file deletion removes its private object and page vectors and can run twice', function () {
    Storage::fake('s3');
    $file = ProgramFile::factory()->create(['upload_status' => FileUploadStatus::DeletePending]);
    Storage::disk('s3')->put($file->storage_key, 'Private file');
    $embedding = FilePageEmbedding::query()->create([
        'program_file_id' => $file->id,
        'source_key' => 'program_file:'.$file->id,
        'page_number' => 1,
        'source_hash' => str_repeat('a', 64),
        'provider' => 'voyageai',
        'model' => 'voyage-4',
        'dimensions' => 1024,
        'content' => 'Extracted page text',
        'embedding' => array_fill(0, 1024, 0.0),
        'embedded_at' => now(),
    ]);

    $job = new DeleteProgramFile($file->id);
    $job->handle();
    $job->handle();

    Storage::disk('s3')->assertMissing($file->storage_key);
    $this->assertModelMissing($file);
    $this->assertModelMissing($embedding);
});

test('ordinary users cannot delete program files', function () {
    Queue::fake([DeleteProgramFile::class]);
    $file = ProgramFile::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('program-files.destroy', $file))
        ->assertForbidden();

    expect($file->fresh()->upload_status)->toBe(FileUploadStatus::Uploaded);
    Queue::assertNothingPushed();
});

test('subjects with associated program files cannot be deleted', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    ProgramFile::factory()->for($program)->create(['subject_id' => $subject->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('subjects.destroy', [$program, $subject]))
        ->assertRedirect(route('subjects.edit', [$program, $subject]))
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', 'Remove or reassign this subject’s source files before deleting it.');

    $this->assertModelExists($subject);
});

test('subject page supplies its canonical subject-associated program files', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $associatedFile = ProgramFile::factory()->for($program)->create(['subject_id' => $subject->id]);
    ProgramFile::factory()->for($program)->create(['subject_id' => null]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('subjects.show', [$program, $subject]))
        ->assertInertia(fn ($page) => $page->component('admin/subjects/show')
            ->has('programFiles', 1)
            ->where('programFiles.0.id', $associatedFile->id));
});

test('subjects with associated program files cannot be moved to another program', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $destination = Program::factory()->create();
    ProgramFile::factory()->for($program)->create(['subject_id' => $subject->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('subjects.edit', [$program, $subject]))
        ->put(route('subjects.update', [$program, $subject]), [
            'program_id' => $destination->id,
            'name' => $subject->name,
            'code' => $subject->code,
            'description' => $subject->description,
            'sort_order' => $subject->sort_order,
        ])
        ->assertSessionHasErrors(['program_id' => 'Reassign or remove subject-associated program files before moving this subject.']);

    $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'program_id' => $program->id]);
});

test('source file libraries are restricted to content managers', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->create();

    $this->get(route('programs.files.index', $program))->assertRedirect(route('login'));
    $this->get('/subjects/'.$subject->id.'/files')->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get(route('programs.files.index', $program))
        ->assertForbidden();
});

test('content managers can inspect files only without receiving storage internals', function () {
    config(['ai.official_curriculum.enabled' => true, 'ai.file_embeddings.enabled' => false]);
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create(['storage_key' => 'program-files/'.$program->id.'/opaque.pdf']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('program-files.show', $file))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/program-files/show')
            ->where('file.id', $file->id)
            ->where('fileEmbeddingsEnabled', false)
            ->where('embeddingUnavailableReason', 'Embedding generation is disabled in the application configuration.')
            ->missing('file.storage_key')
            ->missing('file.storage_disk'));
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
            'title' => 'Exam specification',
            'file_type' => 'official_reference',
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
        'file_type' => 'official_reference',
        'subject_id' => null,
        'storage_key' => $key,
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'uploaded_by' => $user->id,
    ]);
});

test('content managers can scope program file uploads to a subject in that program', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $user = User::factory()->admin()->create();
    $key = 'program-files/'.$program->id.'/subject.pdf';

    $this->mock(SourceFileStorage::class, function (MockInterface $mock) use ($key): void {
        $mock->shouldReceive('createKey')->once()->andReturn($key);
        $mock->shouldReceive('presign')->once()->andReturn(['url' => 'https://example.com/upload', 'headers' => []]);
    });

    $this->actingAs($user)->postJson(route('programs.files.upload-url', $program), [
        'title' => 'Subject reference', 'file_type' => 'reviewer_ebook', 'subject_id' => $subject->id,
        'original_filename' => 'reference.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'file_size' => 1024,
    ])->assertOk();

    $this->assertDatabaseHas('program_files', [
        'program_id' => $program->id, 'subject_id' => $subject->id, 'file_type' => 'reviewer_ebook', 'storage_key' => $key,
    ]);
});

test('program file subject scope rejects a subject from another program', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('programs.files.upload-url', $program), [
            'title' => 'Invalid scope', 'file_type' => 'other', 'subject_id' => $subject->id,
            'original_filename' => 'source.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'file_size' => 1024,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('subject_id');
});

test('program source library filters shared and subject scoped files', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $sharedFile = ProgramFile::factory()->for($program)->create(['subject_id' => null]);
    $subjectFile = ProgramFile::factory()->for($program)->create(['subject_id' => $subject->id]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('programs.files.index', ['program' => $program, 'subject' => 'shared']))
        ->assertInertia(fn ($page) => $page->where('subjectFilter', 'shared')->has('files', 1)->where('files.0.id', $sharedFile->id));

    $this->get(route('programs.files.index', ['program' => $program, 'subject' => $subject->id]))
        ->assertInertia(fn ($page) => $page->where('subjectFilter', (string) $subject->id)->has('files', 1)->where('files.0.id', $subjectFile->id));
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
        'embedding_status' => FileEmbeddingStatus::Supported->value,
    ]);
});

test('content managers can update a program file classification and subject scope', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $file = ProgramFile::factory()->for($program)->create(['subject_id' => null]);
    $storageKey = $file->storage_key;

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('program-files.show', $file))
        ->patch(route('program-files.update', $file), [
            'file_type' => 'reviewer_notes',
            'subject_id' => $subject->id,
            'title' => 'Subject reviewer notes',
        ])
        ->assertRedirect(route('program-files.show', $file));

    expect($file->fresh()->file_type->value)->toBe('reviewer_notes')
        ->and($file->fresh()->subject_id)->toBe($subject->id)
        ->and($file->fresh()->title)->toBe('Subject reviewer notes')
        ->and($file->fresh()->storage_key)->toBe($storageKey);
});

test('program file download returns a temporary URL for an uploaded source', function () {
    Storage::fake('s3');
    $file = ProgramFile::factory()->create();
    Storage::disk('s3')->put($file->storage_key, 'Private source');

    $response = $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('program-files.download', $file))
        ->assertOk()
        ->assertJsonStructure(['url']);

    expect($response->json('url'))->not->toBeEmpty();
});

test('legacy subject file metadata endpoint is retired', function () {
    $subject = Subject::factory()->create();
    $file = SubjectFile::factory()->for($subject)->create();
    $key = $file->storage_key;

    $this->actingAs(User::factory()->admin()->create())
        ->patch('/subject-files/'.$file->id, [
            'file_type' => 'other', 'title' => 'Updated reference', 'metadata' => ['author' => 'Author'],
            'subject_id' => $subject->id + 1, 'storage_key' => 'attacker/key.pdf', 'storage_disk' => 'public',
            'file_size' => 999, 'upload_status' => 'failed',
        ])
        ->assertNotFound();

    expect($file->fresh()->storage_key)->toBe($key)
        ->and($file->fresh()->subject_id)->toBe($subject->id)
        ->and($file->fresh()->file_size)->toBe(1024)
        ->and($file->fresh()->title)->toBe($file->title);
});

test('all legacy subject file management URLs are retired', function () {
    $subject = Subject::factory()->create();
    $file = SubjectFile::factory()->for($subject)->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/subjects/'.$subject->id.'/files')->assertNotFound();
    $this->post('/subjects/'.$subject->id.'/files/uploads')->assertNotFound();
    $this->post('/subject-files/'.$file->id)->assertNotFound();
    $this->get('/subject-files/'.$file->id)->assertNotFound();
    $this->patch('/subject-files/'.$file->id)->assertNotFound();
    $this->delete('/subject-files/'.$file->id)->assertNotFound();
    $this->post('/subject-files/'.$file->id.'/download')->assertNotFound();
    $this->post('/subject-files/'.$file->id.'/embeddings')->assertNotFound();
});

test('legacy subject file source mapping resolves page text and vectors through canonical program file', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $programFile = ProgramFile::factory()->for($program)->create([
        'subject_id' => null,
        'file_type' => 'official_reference',
        'content_hash' => hash('sha256', 'shared-content'),
    ]);
    $subjectFile = SubjectFile::factory()->for($subject)->create([
        'content_hash' => $programFile->content_hash,
    ]);

    // Insert legacy mapping
    DB::table('legacy_program_file_sources')->insert([
        'legacy_type' => 'subject_file',
        'legacy_id' => $subjectFile->id,
        'program_file_id' => $programFile->id,
        'program_id' => $program->id,
        'subject_id' => $subject->id,
        'file_type' => 'other',
        'title' => $subjectFile->title,
        'original_filename' => $subjectFile->original_filename,
        'storage_disk' => $subjectFile->storage_disk,
        'storage_key' => $subjectFile->storage_key,
        'content_hash' => $subjectFile->content_hash,
        'created_program_file' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Insert canonical page embedding on program file
    DB::table('file_page_embeddings')->insert([
        'program_file_id' => $programFile->id,
        'subject_file_id' => null,
        'source_key' => 'program_file:'.$programFile->id,
        'page_number' => 1,
        'source_hash' => $programFile->content_hash,
        'provider' => 'voyageai',
        'model' => 'voyage-4',
        'dimensions' => 1024,
        'content' => 'Sample extracted syllabus page.',
        'embedding' => DB::raw('array_fill(0, ARRAY[1024])::vector'),
        'embedded_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(SubjectTopicExtractionService::class);
    $files = $service->filesForSubject($subject);

    expect($files)->toHaveCount(1)
        ->and($files->first()->id)->toBe($programFile->id)
        ->and($files->first()->usable_pages_count)->toBe(1);
});

test('legacy source reconciliation is repeatable without duplicating files or resetting migrated types', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $matchingHash = hash('sha256', 'canonical source');
    $canonicalFile = ProgramFile::factory()->for($program)->create([
        'file_type' => 'official_reference',
        'content_hash' => $matchingHash,
    ]);
    $matchingSubjectFile = SubjectFile::factory()->for($subject)->create([
        'file_type' => 'other',
        'content_hash' => $matchingHash,
    ]);
    $subjectOnlyFile = SubjectFile::factory()->for($subject)->create([
        'file_type' => 'reviewer_ebook',
        'content_hash' => null,
    ]);
    $migration = require database_path('migrations/2026_10_11_054054_unify_subject_files_into_program_files.php');

    $migration->reconcileLegacySources();
    $migration->reconcileLegacySources();

    $matchingMapping = DB::table('legacy_program_file_sources')
        ->where('legacy_type', 'subject_file')->where('legacy_id', $matchingSubjectFile->id)->first();
    $subjectOnlyMapping = DB::table('legacy_program_file_sources')
        ->where('legacy_type', 'subject_file')->where('legacy_id', $subjectOnlyFile->id)->first();

    expect(ProgramFile::query()->where('program_id', $program->id)->count())->toBe(2)
        ->and(DB::table('legacy_program_file_sources')->where('program_id', $program->id)->count())->toBe(2)
        ->and($matchingMapping->program_file_id)->toBe($canonicalFile->id)
        ->and((bool) $matchingMapping->created_program_file)->toBeFalse()
        ->and((bool) $subjectOnlyMapping->created_program_file)->toBeTrue()
        ->and(ProgramFile::query()->findOrFail($subjectOnlyMapping->program_file_id)->file_type->value)->toBe('reviewer_ebook');
});

test('existing program files are classified official only when their verified syllabus hash matches', function () {
    $program = Program::factory()->create();
    $verifiedSyllabus = ProgramFile::factory()->for($program)->create([
        'file_type' => 'other',
        'content_hash' => 'd11428585fc47e456fe5b63ab089f9799719735ff1f4aac2b243a50dac42048b',
    ]);
    $unverifiedFile = ProgramFile::factory()->for($program)->create([
        'file_type' => 'other',
        'content_hash' => hash('sha256', 'unverified program file'),
    ]);
    $migration = require database_path('migrations/2026_10_11_054054_unify_subject_files_into_program_files.php');

    $migration->backfillVerifiedProgramFileTypes();

    expect($verifiedSyllabus->fresh()->file_type->value)->toBe('official_reference')
        ->and($unverifiedFile->fresh()->file_type->value)->toBe('other');
});

test('topic citation publication sets program_file_id when mapped to legacy source', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $programFile = ProgramFile::factory()->for($program)->create([
        'content_hash' => hash('sha256', 'hash-1'),
    ]);
    $subjectFile = SubjectFile::factory()->for($subject)->create([
        'title' => 'Curriculum source',
        'content_hash' => $programFile->content_hash,
        'mime_type' => 'application/pdf',
        'upload_status' => FileUploadStatus::Uploaded,
        'embedding_status' => FileEmbeddingStatus::Complete,
    ]);
    FilePageEmbedding::query()->create([
        'subject_file_id' => $subjectFile->id,
        'source_key' => 'subject_file:'.$subjectFile->id,
        'page_number' => 1,
        'source_hash' => $subjectFile->content_hash,
        'provider' => 'voyageai',
        'model' => 'voyage-4',
        'dimensions' => 1024,
        'content' => 'Topic page content.',
        'embedding' => array_fill(0, 1024, 0.0),
        'embedded_at' => now(),
    ]);

    DB::table('legacy_program_file_sources')->insert([
        'legacy_type' => 'subject_file',
        'legacy_id' => $subjectFile->id,
        'program_file_id' => $programFile->id,
        'program_id' => $program->id,
        'subject_id' => $subject->id,
        'file_type' => 'official_reference',
        'title' => $subjectFile->title,
        'original_filename' => $subjectFile->original_filename,
        'storage_disk' => $subjectFile->storage_disk,
        'storage_key' => $subjectFile->storage_key,
        'content_hash' => $subjectFile->content_hash,
        'created_program_file' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $snapshot = [[
        'id' => $subjectFile->id,
        'content_hash' => $subjectFile->content_hash,
        'title' => $subjectFile->title,
        'mime_type' => $subjectFile->mime_type,
    ]];

    $extraction = SubjectTopicExtraction::factory()->for($subject)->create([
        'source_files' => $snapshot,
        'source_hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
        'proposal' => ['topics' => [[
            'code' => '1.0',
            'title' => 'General concepts',
            'description' => null,
            'parent_index' => null,
            'source_pages' => [['source_file_id' => $subjectFile->id, 'page_number' => 1]],
        ]]],
    ]);

    app(SubjectTopicExtractionService::class)->publish($extraction, [
        ['include' => '1', 'code' => '1.0', 'title' => 'General concepts', 'description' => '', 'parent_index' => '', 'sort_order' => '0'],
    ], User::factory()->admin()->create());

    $topic = SyllabusTopic::query()->where('subject_id', $subject->id)->firstOrFail();
    $source = $topic->sources()->sole();

    expect($source->program_file_id)->toBe($programFile->id)
        ->and($source->subject_file_id)->toBe($subjectFile->id)
        ->and($source->programFile->id)->toBe($programFile->id);
});
