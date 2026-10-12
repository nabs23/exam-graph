<?php

use App\Jobs\GenerateCurriculumProposal;
use App\Models\Concept;
use App\Models\CurriculumGeneration;
use App\Models\Program;
use App\Models\ProgramFile;
use App\Models\Subject;
use App\Models\SyllabusTopic;
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

test('concept requests start from a subject and queue without program files', function () {
    Queue::fake([GenerateCurriculumProposal::class]);
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $rootTopic = SyllabusTopic::factory()->for($subject)->create(['title' => 'Root topic', 'sort_order' => 0]);
    SyllabusTopic::factory()->for($subject)->create([
        'title' => 'Nested topic',
        'parent_id' => $rootTopic->id,
        'sort_order' => 1,
    ]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('programs.curriculum-generations.create', $program).'?kind=concepts&subject_id='.$subject->id)
        ->assertInertia(fn ($page) => $page
            ->component('admin/curriculum-generations/create')
            ->where('initialKind', 'concepts')
            ->where('initialSubjectId', $subject->id)
            ->where('subjects.0.syllabus_topics.1.parent_id', $rootTopic->id));

    $this->actingAs($admin)
        ->post(route('programs.curriculum-generations.store', $program), [
            'kind' => 'concepts',
            'subject_id' => $subject->id,
            'ungrounded' => true,
        ])
        ->assertRedirect();

    $generation = CurriculumGeneration::query()->where('subject_id', $subject->id)->firstOrFail();
    expect($generation->source_files)->toBe([])
        ->and($generation->scope_snapshot['topics'])->toHaveCount(2)
        ->and($generation->status->value)->toBe('queued');
    Queue::assertPushed(GenerateCurriculumProposal::class, fn (GenerateCurriculumProposal $job): bool => $job->generationId === $generation->id);
});

test('concept requests reject a subject from another program without queueing a generation', function () {
    Queue::fake([GenerateCurriculumProposal::class]);
    $program = Program::factory()->create();
    $otherProgram = Program::factory()->create();
    $foreignSubject = Subject::factory()->for($otherProgram)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('programs.curriculum-generations.create', $program))
        ->post(route('programs.curriculum-generations.store', $program), [
            'kind' => 'concepts',
            'subject_id' => $foreignSubject->id,
            'ungrounded' => true,
        ])
        ->assertSessionHasErrors('subject_id');

    expect(CurriculumGeneration::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('authors can queue an LLM-only lesson request from a selected concept', function () {
    Queue::fake([GenerateCurriculumProposal::class]);
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $concept = Concept::factory()->for($subject)->create([
        'description' => 'A reviewed concept description.',
    ]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('programs.curriculum-generations.create', $program).'?kind=lessons&subject_id='.$subject->id.'&scope_type=concept&scope_id='.$concept->id)
        ->assertInertia(fn ($page) => $page
            ->component('admin/curriculum-generations/create')
            ->where('initialKind', 'lessons')
            ->where('initialScopeType', 'concept')
            ->where('initialScopeId', $concept->id));

    $this->actingAs($admin)->post(route('programs.curriculum-generations.store', $program), [
        'kind' => 'lessons',
        'subject_id' => $subject->id,
        'scope_type' => 'concept',
        'scope_id' => $concept->id,
        'concept_ids' => [(string) $concept->id],
        'request_context' => 'Explain the core idea clearly.',
    ])->assertRedirect();

    $generation = CurriculumGeneration::query()->where('kind', 'lessons')->firstOrFail();
    expect($generation->scope_snapshot['scope_type'])->toBe('concept')
        ->and($generation->scope_snapshot['target_concept_ids'])->toBe([$concept->id])
        ->and($generation->source_files)->toBe([])
        ->and($generation->ungrounded_requested)->toBeTrue();
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
