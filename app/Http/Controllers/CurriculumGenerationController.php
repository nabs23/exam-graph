<?php

namespace App\Http\Controllers;

use App\CurriculumGenerationKind;
use App\CurriculumGenerationStatus;
use App\Http\Requests\ReviewCurriculumGenerationRequest;
use App\Http\Requests\StoreCurriculumGenerationRequest;
use App\Models\Concept;
use App\Models\CurriculumGeneration;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\ProgramFile;
use App\Models\Subject;
use App\Services\CurriculumGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CurriculumGenerationController extends Controller
{
    public function index(Program $program, CurriculumGenerationService $service): Response
    {
        return Inertia::render('admin/curriculum-generations/index', [
            'program' => $program->only(['id', 'name', 'code']),
            'generationEnabled' => $service->unavailableReason() === null,
            'generationUnavailableReason' => $service->unavailableReason(),
            'generations' => $program->curriculumGenerations()
                ->with(['requester:id,name', 'reviewer:id,name'])
                ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [CurriculumGenerationStatus::Reviewing->value])
                ->latest()
                ->limit(50)
                ->get(),
        ]);
    }

    public function create(Request $request, Program $program, CurriculumGenerationService $service): Response
    {
        $files = ProgramFile::query()
            ->where('program_id', $program->id)
            ->where('upload_status', 'uploaded')
            ->with('subject:id,name')
            ->withCount(['pageEmbeddings as usable_pages_count' => fn ($query) => $query
                ->whereNotNull('content')
                ->whereColumn('file_page_embeddings.source_hash', 'program_files.content_hash')])
            ->orderBy('title')
            ->get(['id', 'program_id', 'subject_id', 'title', 'original_filename', 'mime_type', 'file_type', 'file_size', 'content_hash']);

        $subjects = $program->subjects()
            ->with([
                'syllabusTopics' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')->select(['id', 'subject_id', 'parent_id', 'title', 'code', 'description', 'sort_order']),
                'concepts' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')->with(['syllabusTopic:id,title,code', 'learningObjectives:id,concept_id,description,sort_order']),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'code']);
        $requestedSubjectId = filter_var($request->query('subject_id'), FILTER_VALIDATE_INT);
        $initialSubjectId = $requestedSubjectId !== false && $subjects->contains('id', $requestedSubjectId) ? $requestedSubjectId : null;
        $requestedKind = $request->query('kind');
        $initialKind = is_string($requestedKind) && CurriculumGenerationKind::tryFrom($requestedKind) !== null ? $requestedKind : null;

        return Inertia::render('admin/curriculum-generations/create', [
            'program' => $program->only(['id', 'name', 'code']),
            'subjects' => $subjects,
            'initialKind' => $initialKind,
            'initialSubjectId' => $initialSubjectId,
            'initialScopeType' => $request->query('scope_type'),
            'initialScopeId' => filter_var($request->query('scope_id'), FILTER_VALIDATE_INT) ?: null,
            'files' => $files->map(fn (ProgramFile $file): array => [
                'id' => $file->id,
                'title' => $file->title,
                'original_filename' => $file->original_filename,
                'mime_type' => $file->mime_type,
                'file_type' => $file->file_type?->value,
                'subject_id' => $file->subject_id,
                'subject_name' => $file->subject?->name,
                'usable_pages_count' => (int) $file->usable_pages_count,
            ])->all(),
            'generationEnabled' => $service->unavailableReason() === null,
            'generationUnavailableReason' => $service->unavailableReason(),
            'maxSourceFiles' => max(1, (int) config('ai.curriculum_generation.max_files', 10)),
            'models' => $service->availableModels(),
            'defaultModel' => config('ai.curriculum_generation.model'),
            'kinds' => array_map(fn (CurriculumGenerationKind $kind): array => [
                'value' => $kind->value,
                'label' => match ($kind) {
                    CurriculumGenerationKind::Subjects => 'Subjects',
                    CurriculumGenerationKind::Topics => 'Topics',
                    CurriculumGenerationKind::Concepts => 'Concepts',
                    CurriculumGenerationKind::Lessons => 'Lessons',
                },
            ], CurriculumGenerationKind::cases()),
        ]);
    }

    public function store(
        StoreCurriculumGenerationRequest $request,
        Program $program,
        CurriculumGenerationService $service,
    ): RedirectResponse {
        $data = $request->validated();
        $subject = isset($data['subject_id']) ? Subject::query()->findOrFail($data['subject_id']) : null;
        $scopeType = $data['scope_type'] ?? null;
        $topic = isset($data['syllabus_topic_id']) && $scopeType !== 'concept' ? $subject?->syllabusTopics()->findOrFail($data['syllabus_topic_id']) : null;
        if ($scopeType === 'concept') {
            $targetConcept = Concept::query()->where('subject_id', $subject?->id)->findOrFail($data['scope_id']);
            $topic = $targetConcept->syllabusTopic;
        }
        $generation = $service->queue(
            $program,
            CurriculumGenerationKind::from($data['kind']),
            $data['source_file_ids'] ?? [],
            $request->user(),
            subject: $subject,
            topic: $topic,
            requestContext: $data['request_context'] ?? null,
            ungrounded: CurriculumGenerationKind::from($data['kind']) === CurriculumGenerationKind::Concepts || (bool) ($data['ungrounded'] ?? false),
            model: $data['model'] ?? null,
            scopeType: $scopeType,
            scopeId: isset($data['scope_id']) ? (int) $data['scope_id'] : null,
            targetConceptIds: $data['concept_ids'] ?? [],
        );

        return to_route('programs.curriculum-generations.show', [$program, $generation]);
    }

    public function show(Program $program, CurriculumGeneration $curriculumGeneration): Response
    {
        $curriculumGeneration->load(['program:id,name,code', 'subject:id,name,code', 'syllabusTopic:id,title,code', 'requester:id,name', 'reviewer:id,name']);
        $snapshotFiles = collect($curriculumGeneration->source_files)->map(fn (array $file): array => [
            'id' => $file['id'],
            'title' => $file['title'],
            'file_type' => $file['file_type'],
            'subject_id' => $file['subject_id'],
            'content_hash' => $file['content_hash'],
            'page_count' => $file['page_count'] ?? 0,
        ])->values();
        $proposalHash = $curriculumGeneration->proposal === null
            ? null
            : hash('sha256', json_encode($curriculumGeneration->proposal, JSON_THROW_ON_ERROR));
        $existingLessonsByConcept = [];
        if ($curriculumGeneration->kind === CurriculumGenerationKind::Lessons) {
            $conceptIds = collect($curriculumGeneration->scope_snapshot['target_concept_ids'] ?? [])
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
            $existingLessonsByConcept = Lesson::query()
                ->whereIn('concept_id', $conceptIds)
                ->where(fn ($query) => $query
                    ->whereNull('curriculum_generation_id')
                    ->orWhere('curriculum_generation_id', '!=', $curriculumGeneration->id))
                ->orderBy('title')
                ->get(['id', 'concept_id', 'curriculum_generation_id', 'title'])
                ->groupBy('concept_id')
                ->map(fn ($lessons) => $lessons->map(fn (Lesson $lesson): array => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'generation_id' => $lesson->curriculum_generation_id,
                ])->values()->all())
                ->all();
        }

        return Inertia::render('admin/curriculum-generations/show', [
            'program' => $program->only(['id', 'name', 'code']),
            'generation' => $curriculumGeneration,
            'sourceFiles' => $snapshotFiles,
            'proposalHash' => $proposalHash,
            'canReview' => $curriculumGeneration->status === CurriculumGenerationStatus::Reviewing,
            'existingLessonsByConcept' => $existingLessonsByConcept,
        ]);
    }

    public function review(
        ReviewCurriculumGenerationRequest $request,
        Program $program,
        CurriculumGeneration $curriculumGeneration,
        CurriculumGenerationService $service,
    ): RedirectResponse {
        abort_unless((int) $curriculumGeneration->program_id === (int) $program->id, 404);
        $data = $request->validated();
        $service->review($curriculumGeneration, $data['proposal_hash'], $data['items'], $request->user());

        return back();
    }
}
