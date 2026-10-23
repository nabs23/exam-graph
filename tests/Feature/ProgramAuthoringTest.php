<?php

use App\CurriculumGenerationStatus;
use App\Models\Concept;
use App\Models\CurriculumExtraction;
use App\Models\CurriculumGeneration;
use App\Models\Program;
use App\Models\ProgramFile;
use App\Models\Subject;
use App\Models\SubjectTopicExtraction;
use App\Models\SyllabusTopic;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('program workspace exposes counts for the authoring setup steps', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    ProgramFile::factory()->for($program)->for($subject)->create();
    CurriculumGeneration::factory()->for($program)->create(['status' => CurriculumGenerationStatus::Reviewing]);
    CurriculumGeneration::factory()->for($program)->create(['status' => CurriculumGenerationStatus::Queued]);
    CurriculumGeneration::factory()->for($program)->create(['status' => CurriculumGenerationStatus::Failed]);
    CurriculumGeneration::factory()->for($program)->create(['status' => CurriculumGenerationStatus::Approved]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('programs.show', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/programs/show')
            ->where('program.id', $program->id)
            ->has('program.subjects', 1)
            ->has('program.files', 1)
            ->where('program.files.0.subject.id', $subject->id)
            ->where('program.files.0.subject.name', $subject->name)
            ->where('program.curriculum_generations_count', 4)
            ->where('program.pending_curriculum_generations_count', 1)
            ->where('program.in_progress_curriculum_generations_count', 1)
            ->where('program.failed_curriculum_generations_count', 1)
            ->where('curriculumExtractionHasSources', false)
            ->has('extractions', 0));
});

test('creating a program confirms the save in the success toast', function () {
    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route('programs.store'), [
            'name' => 'Accounting Review',
            'code' => 'ACCT-REV',
            'description' => 'A review program for accounting learners.',
        ]);

    $program = Program::where('code', 'ACCT-REV')->firstOrFail();

    $response
        ->assertRedirect(route('programs.show', $program))
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'The program was created.');

    $this->assertDatabaseHas('programs', ['code' => 'ACCT-REV', 'name' => 'Accounting Review']);
});

test('updating a program confirms the saved changes in the success toast', function () {
    $program = Program::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('programs.update', $program), [
            'name' => 'Updated Review Program',
            'code' => $program->code,
            'description' => 'Updated program details.',
        ])
        ->assertRedirect(route('programs.show', $program))
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'Program changes were saved.');

    expect($program->fresh()->name)->toBe('Updated Review Program');
});

test('updating a program returns to the workspace section it came from', function () {
    $program = Program::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('programs.update', $program).'?tab=files', [
            'name' => 'Updated Review Program',
            'code' => $program->code,
            'description' => $program->description,
        ])
        ->assertRedirect(route('programs.show', $program).'?tab=files');

    expect($program->fresh()->name)->toBe('Updated Review Program');
});

test('editing a program retains the requested workspace section', function () {
    $program = Program::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('programs.edit', $program).'?tab=extractions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/programs/edit')
            ->where('returnTab', 'extractions'));
});

test('updating a program ignores an unknown workspace section', function () {
    $program = Program::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('programs.update', $program).'?tab=unknown', [
            'name' => 'Updated Review Program',
            'code' => $program->code,
            'description' => $program->description,
        ])
        ->assertRedirect(route('programs.show', $program));
});

test('creating a subject confirms the save in the success toast', function () {
    $program = Program::factory()->create();
    $otherProgram = Program::factory()->create();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route('programs.subjects.store', $program), [
            'program_id' => $otherProgram->id,
            'name' => 'Financial Accounting',
            'code' => 'FINACC',
            'description' => 'Core financial reporting concepts.',
            'sort_order' => 0,
        ]);

    $subject = Subject::where('program_id', $program->id)->where('code', 'FINACC')->firstOrFail();

    $response
        ->assertRedirect(route('subjects.show', [$program, $subject]))
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'The subject was created.');

    $this->assertDatabaseHas('subjects', ['program_id' => $program->id, 'code' => 'FINACC', 'name' => 'Financial Accounting']);
    $this->assertDatabaseMissing('subjects', ['program_id' => $otherProgram->id, 'code' => 'FINACC']);
});

test('creating a subject from a program keeps its parent selected', function () {
    $program = Program::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('programs.subjects.create', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/subjects/create')
            ->where('contextProgram.id', $program->id)
            ->where('contextProgram.code', $program->code)
            ->has('programs', 1)
            ->where('programs.0.id', $program->id));
});

test('updating a subject confirms the saved changes in the success toast', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('subjects.update', [$program, $subject]), [
            'program_id' => $program->id,
            'name' => 'Updated Subject',
            'code' => $subject->code,
            'description' => 'Updated subject details.',
            'sort_order' => $subject->sort_order,
        ])
        ->assertRedirect(route('subjects.show', [$program, $subject]))
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'Subject changes were saved.');

    expect($subject->fresh()->name)->toBe('Updated Subject');
});

test('a subject with associated files cannot move to another program', function () {
    $program = Program::factory()->create();
    $otherProgram = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    ProgramFile::factory()->for($program)->for($subject)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('subjects.edit', [$program, $subject]))
        ->put(route('subjects.update', [$program, $subject]), [
            'program_id' => $otherProgram->id,
            'name' => $subject->name,
            'code' => $subject->code,
            'description' => $subject->description,
            'sort_order' => $subject->sort_order,
        ])
        ->assertRedirect(route('subjects.edit', [$program, $subject]))
        ->assertInvalid([
            'program_id' => 'Reassign or remove subject-associated program files before moving this subject.',
        ]);

    $this->assertDatabaseHas('subjects', [
        'id' => $subject->id,
        'program_id' => $program->id,
    ]);
});

test('a subject without associated files can move to another program', function () {
    $program = Program::factory()->create();
    $otherProgram = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('subjects.update', [$program, $subject]), [
            'program_id' => $otherProgram->id,
            'name' => $subject->name,
            'code' => $subject->code,
            'description' => $subject->description,
            'sort_order' => $subject->sort_order,
        ])
        ->assertRedirect(route('subjects.show', [$otherProgram, $subject]))
        ->assertInertiaFlash('toast.type', 'success');

    $this->assertDatabaseHas('subjects', [
        'id' => $subject->id,
        'program_id' => $otherProgram->id,
    ]);
});

test('editing a subject retains the requested workspace section', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('subjects.edit', [$program, $subject]).'?tab=concepts')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/subjects/edit')
            ->where('returnTab', 'concepts'));
});

test('updating a subject returns to the workspace section it came from', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('subjects.update', [$program, $subject]).'?tab=files', [
            'program_id' => $program->id,
            'name' => 'Updated Subject',
            'code' => $subject->code,
            'description' => $subject->description,
            'sort_order' => $subject->sort_order,
        ])
        ->assertRedirect(route('subjects.show', [$program, $subject]).'?tab=files');
});

test('updating a subject rejects a negative display order without changing it', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create(['sort_order' => 4]);

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('subjects.edit', [$program, $subject]))
        ->put(route('subjects.update', [$program, $subject]), [
            'program_id' => $program->id,
            'name' => $subject->name,
            'code' => $subject->code,
            'description' => $subject->description,
            'sort_order' => -1,
        ])
        ->assertRedirect(route('subjects.edit', [$program, $subject]))
        ->assertInvalid(['sort_order' => 'at least 0']);

    expect($subject->fresh()->sort_order)->toBe(4);
});

test('program workspace recognizes a completed PDF embedding as an extraction source', function () {
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create();
    $file->forceFill([
        'upload_status' => 'uploaded',
        'mime_type' => 'application/pdf',
        'embedding_status' => 'complete',
        'content_hash' => str_repeat('a', 64),
    ])->save();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('programs.show', $program))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/programs/show')
            ->where('curriculumExtractionHasSources', true));
});

test('proposal history places pending reviews ahead of completed proposals', function () {
    $program = Program::factory()->create();
    $pending = CurriculumGeneration::factory()->for($program)->create([
        'status' => CurriculumGenerationStatus::Reviewing,
        'created_at' => now()->subDays(2),
    ]);
    $completed = CurriculumGeneration::factory()->for($program)->create([
        'status' => CurriculumGenerationStatus::Approved,
        'created_at' => now(),
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('programs.curriculum-generations.index', $program))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/curriculum-generations/index')
            ->where('generations.0.id', $pending->id)
            ->where('generations.1.id', $completed->id));
});

test('an empty program can be deleted after explicit confirmation', function () {
    $program = Program::factory()->create();
    $administrator = User::factory()->admin()->create();

    $this->actingAs($administrator)
        ->get(route('programs.edit', $program))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/programs/edit')
            ->where('deleteBlockedReason', null));

    $this->actingAs($administrator)
        ->delete(route('programs.destroy', $program))
        ->assertRedirect(route('programs.index'));

    $this->assertModelMissing($program);
});

test('a program with curriculum proposals cannot be deleted', function () {
    $program = Program::factory()->create();
    CurriculumGeneration::factory()->for($program)->create();
    $administrator = User::factory()->admin()->create();

    $this->actingAs($administrator)
        ->get(route('programs.edit', $program))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/programs/edit')
            ->where('deleteBlockedReason', 'This program has retained curriculum proposal history and cannot be deleted.'));

    $this->actingAs($administrator)
        ->delete(route('programs.destroy', $program))
        ->assertRedirect(route('programs.edit', $program))
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', 'This program has retained curriculum proposal history and cannot be deleted.');

    $this->assertModelExists($program);
});

test('a program with subjects explains why deletion is blocked', function () {
    $program = Program::factory()->create();
    Subject::factory()->for($program)->create();
    $administrator = User::factory()->admin()->create();
    $blockedReason = 'Remove this program’s subjects before deleting the program.';

    $this->actingAs($administrator)
        ->get(route('programs.edit', $program))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/programs/edit')
            ->where('deleteBlockedReason', $blockedReason));

    $this->actingAs($administrator)
        ->delete(route('programs.destroy', $program))
        ->assertRedirect(route('programs.edit', $program))
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', $blockedReason);

    $this->assertModelExists($program);
});

test('a blocked program deletion returns to its originating workspace section', function () {
    $program = Program::factory()->create();
    Subject::factory()->for($program)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('programs.destroy', $program).'?tab=files')
        ->assertRedirect(route('programs.edit', $program).'?tab=files');

    $this->assertModelExists($program);
});

test('a program with source files explains why deletion is blocked', function () {
    $program = Program::factory()->create();
    ProgramFile::factory()->for($program)->create();
    $administrator = User::factory()->admin()->create();
    $blockedReason = 'Remove this program’s source files before deleting the program.';

    $this->actingAs($administrator)
        ->get(route('programs.edit', $program))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/programs/edit')
            ->where('deleteBlockedReason', $blockedReason));

    $this->actingAs($administrator)
        ->delete(route('programs.destroy', $program))
        ->assertRedirect(route('programs.edit', $program))
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', $blockedReason);

    $this->assertModelExists($program);
});

test('a program subject list keeps its parent context when empty', function () {
    $program = Program::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('programs.subjects.index', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/subjects/index')
            ->where('contextProgram.id', $program->id)
            ->has('programs', 1)
            ->has('subjects', 0));
});

test('program subject lists keep the creation order when display orders match', function () {
    $program = Program::factory()->create();
    $firstSubject = Subject::factory()->for($program)->create(['sort_order' => 2]);
    Subject::factory()->for($program)->create(['sort_order' => 2]);
    $administrator = User::factory()->admin()->create();

    $this->actingAs($administrator)
        ->get(route('programs.show', $program))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/programs/show')
            ->where('program.subjects.0.id', $firstSubject->id));

    $this->actingAs($administrator)
        ->get(route('programs.subjects.index', $program))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/subjects/index')
            ->where('subjects.0.id', $firstSubject->id));
});

test('all subjects are grouped by program and follow each program display order', function () {
    $laterProgram = Program::factory()->create(['name' => 'Zeta Program']);
    $earlierProgram = Program::factory()->create(['name' => 'Alpha Program']);
    $zetaFirst = Subject::factory()->for($laterProgram)->create(['sort_order' => 0]);
    $alphaLater = Subject::factory()->for($earlierProgram)->create(['sort_order' => 4]);
    $alphaFirst = Subject::factory()->for($earlierProgram)->create(['sort_order' => 1]);
    $zetaLater = Subject::factory()->for($laterProgram)->create(['sort_order' => 2]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('subjects.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/subjects/index')
            ->where('subjects.0.id', $alphaFirst->id)
            ->where('subjects.0.program.name', 'Alpha Program')
            ->where('subjects.1.id', $alphaLater->id)
            ->where('subjects.2.id', $zetaFirst->id)
            ->where('subjects.2.program.name', 'Zeta Program')
            ->where('subjects.3.id', $zetaLater->id));
});

test('learners see program subjects in display order', function () {
    $program = Program::factory()->create();
    $laterSubject = Subject::factory()->for($program)->create(['sort_order' => 3]);
    $firstTie = Subject::factory()->for($program)->create(['sort_order' => 1]);
    $secondTie = Subject::factory()->for($program)->create(['sort_order' => 1]);
    $learner = User::factory()->create();

    $this->actingAs($learner)
        ->get(route('study.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('study/index')
            ->where('programs.0.subjects.0.id', $firstTie->id)
            ->where('programs.0.subjects.1.id', $secondTie->id)
            ->where('programs.0.subjects.2.id', $laterSubject->id));
});

test('program file, proposal, and extraction subject lists follow the authored display order', function () {
    $program = Program::factory()->create();
    $laterSubject = Subject::factory()->for($program)->create(['sort_order' => 3]);
    $firstTie = Subject::factory()->for($program)->create(['sort_order' => 1]);
    $secondTie = Subject::factory()->for($program)->create(['sort_order' => 1]);
    $file = ProgramFile::factory()->for($program)->create();
    $extraction = CurriculumExtraction::factory()->for($program)->for($file)->create();
    $administrator = User::factory()->admin()->create();

    $this->actingAs($administrator)
        ->get(route('programs.files.index', $program))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/program-files/index')
            ->where('subjects.0.id', $firstTie->id)
            ->where('subjects.1.id', $secondTie->id)
            ->where('subjects.2.id', $laterSubject->id));

    $this->actingAs($administrator)
        ->get(route('program-files.show', $file))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/program-files/show')
            ->where('subjects.0.id', $firstTie->id)
            ->where('subjects.1.id', $secondTie->id)
            ->where('subjects.2.id', $laterSubject->id));

    $this->actingAs($administrator)
        ->get(route('programs.curriculum-generations.create', $program))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/curriculum-generations/create')
            ->where('subjects.0.id', $firstTie->id)
            ->where('subjects.1.id', $secondTie->id)
            ->where('subjects.2.id', $laterSubject->id));

    $this->actingAs($administrator)
        ->get(route('curriculum-extractions.show', $extraction))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/curriculum-extractions/show')
            ->where('existingSubjects.0.id', $firstTie->id)
            ->where('existingSubjects.1.id', $secondTie->id)
            ->where('existingSubjects.2.id', $laterSubject->id));
});

test('an empty subject can be deleted and returns to its program', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $administrator = User::factory()->admin()->create();

    $this->actingAs($administrator)
        ->get(route('subjects.edit', [$program, $subject]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/subjects/edit')
            ->where('deleteBlockedReason', null));

    $this->actingAs($administrator)
        ->delete(route('subjects.destroy', [$program, $subject]))
        ->assertRedirect(route('programs.subjects.index', $program));

    $this->assertModelMissing($subject);
});

test('subjects with concepts explain why deletion is blocked', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    Concept::factory()->for($subject)->create();
    $administrator = User::factory()->admin()->create();

    $this->actingAs($administrator)
        ->get(route('subjects.edit', [$program, $subject]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/subjects/edit')
            ->where('deleteBlockedReason', 'Remove this subject’s instructional concepts before deleting it.'));

    $this->actingAs($administrator)
        ->delete(route('subjects.destroy', [$program, $subject]))
        ->assertRedirect(route('subjects.edit', [$program, $subject]))
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', 'Remove this subject’s instructional concepts before deleting it.');

    $this->assertModelExists($subject);
});

test('subjects with official syllabus topics explain why deletion is blocked', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    SyllabusTopic::factory()->for($subject)->create();
    $administrator = User::factory()->admin()->create();
    $blockedReason = 'Remove this subject’s official syllabus topics before deleting it.';

    $this->actingAs($administrator)
        ->get(route('subjects.edit', [$program, $subject]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/subjects/edit')
            ->where('deleteBlockedReason', $blockedReason));

    $this->actingAs($administrator)
        ->delete(route('subjects.destroy', [$program, $subject]).'?tab=files')
        ->assertRedirect(route('subjects.edit', [$program, $subject]).'?tab=files')
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', $blockedReason);

    $this->assertModelExists($subject);
});

test('subjects with topic extraction history explain why deletion is blocked', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    SubjectTopicExtraction::factory()->for($subject)->create();
    $administrator = User::factory()->admin()->create();
    $blockedReason = 'Remove this subject’s topic extraction history before deleting it.';

    $this->actingAs($administrator)
        ->get(route('subjects.edit', [$program, $subject]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/subjects/edit')
            ->where('deleteBlockedReason', $blockedReason));

    $this->actingAs($administrator)
        ->delete(route('subjects.destroy', [$program, $subject]))
        ->assertRedirect(route('subjects.edit', [$program, $subject]))
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', $blockedReason);

    $this->assertModelExists($subject);
});
