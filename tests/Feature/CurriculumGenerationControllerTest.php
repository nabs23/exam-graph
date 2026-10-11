<?php

use App\Jobs\GenerateCurriculumProposal;
use App\Models\CurriculumGeneration;
use App\Models\Program;
use App\Models\ProgramFile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config([
        'ai.curriculum_generation.enabled' => true,
        'ai.curriculum_generation.provider' => 'openai',
        'ai.curriculum_generation.model' => 'test-model',
        'ai.curriculum_generation.models' => ['test-model'],
    ]);
});

test('admins can open the generation workflow and queue a scoped proposal', function () {
    Queue::fake([GenerateCurriculumProposal::class]);
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('programs.curriculum-generations.index', $program))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/curriculum-generations/index'));

    $this->actingAs($admin)
        ->get(route('programs.curriculum-generations.create', $program))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/curriculum-generations/create'));

    $response = $this->actingAs($admin)->post(route('programs.curriculum-generations.store', $program), [
        'kind' => 'subjects',
        'source_file_ids' => [$file->id],
        'ungrounded' => false,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('curriculum_generations', [
        'program_id' => $program->id,
        'requested_by' => $admin->id,
        'kind' => 'subjects',
        'status' => 'queued',
    ]);
    Queue::assertPushed(GenerateCurriculumProposal::class);
});

test('generation detail binding is scoped to the route program', function () {
    $program = Program::factory()->create();
    $otherProgram = Program::factory()->create();
    $generation = CurriculumGeneration::factory()->for($otherProgram)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('programs.curriculum-generations.show', [$program, $generation]))
        ->assertNotFound();
});

test('review endpoint approves edited items and records the author decision', function () {
    $program = Program::factory()->create();
    $admin = User::factory()->admin()->create();
    $generation = CurriculumGeneration::factory()->for($program)->create([
        'requested_by' => $admin->id,
        'kind' => 'subjects',
        'status' => 'reviewing',
        'proposal' => ['items' => [[
            'draft_id' => 'subject-draft',
            'name' => 'Accounting',
            'code' => 'ACCT',
            'description' => null,
            'parent_draft_id' => null,
            'sort_order' => 0,
            'citations' => [],
            'citation_status' => 'unverified',
            'review_notes' => [],
        ]]],
    ]);
    $proposalHash = hash('sha256', json_encode($generation->proposal, JSON_THROW_ON_ERROR));

    $this->actingAs($admin)
        ->from(route('programs.curriculum-generations.show', [$program, $generation]))
        ->post(route('programs.curriculum-generations.review', [$program, $generation]), [
            'proposal_hash' => $proposalHash,
            'items' => [[
                'draft_id' => 'subject-draft',
                'include' => true,
                'name' => 'Financial Accounting',
                'code' => 'FINACC',
                'description' => 'Author-edited subject description.',
                'sort_order' => 4,
            ]],
        ])
        ->assertRedirect(route('programs.curriculum-generations.show', [$program, $generation]));

    $subject = Subject::query()->where('program_id', $program->id)->where('code', 'FINACC')->firstOrFail();
    expect($subject->name)->toBe('Financial Accounting')
        ->and($subject->description)->toBe('Author-edited subject description.')
        ->and($subject->sort_order)->toBe(4)
        ->and($generation->fresh()->status->value)->toBe('approved')
        ->and($generation->fresh()->reviewed_by)->toBe($admin->id)
        ->and($generation->fresh()->reviewed_proposal['items'][0]['canonical_id'])->toBe($subject->id);
});
