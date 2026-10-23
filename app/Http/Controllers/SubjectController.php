<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubjectRequest;
use App\Models\Program;
use App\Models\Subject;
use App\Services\SubjectTopicExtractionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(?Program $program = null): Response
    {
        $subjects = Subject::query()
            ->when($program, fn (Builder $query): Builder => $query->where('subjects.program_id', $program->id))
            ->join('programs', 'subjects.program_id', '=', 'programs.id')
            ->select('subjects.*')
            ->with('program:id,name,code')
            ->orderBy('programs.name')
            ->orderBy('subjects.sort_order')
            ->orderBy('subjects.id')
            ->get();

        return Inertia::render('admin/subjects/index', ['subjects' => $subjects, 'contextProgram' => $program, 'programs' => $program ? collect([$program]) : Program::query()->orderBy('name')->get(['id', 'name', 'code'])]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(?Program $program = null): Response
    {
        return Inertia::render('admin/subjects/create', ['contextProgram' => $program, 'programs' => $program ? collect([$program]) : Program::query()->orderBy('name')->get(['id', 'name', 'code'])]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SubjectRequest $request, ?Program $program = null): RedirectResponse
    {
        $data = $request->validated();
        if ($program !== null) {
            $data['program_id'] = $program->id;
        }
        $subject = Subject::create($data);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'The subject was created.']);

        return redirect()->to($subject->curriculumRoute('subjects.show'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Program $program, Subject $subject, SubjectTopicExtractionService $topicExtractionService): Response
    {
        $latestTopicExtraction = $subject->topicExtractions()->latest()->first(['id', 'status']);
        $topicExtractionUnavailableReason = $topicExtractionService->unavailableReason();

        return Inertia::render('admin/subjects/show', [
            'subject' => $subject->load(['program', 'syllabusTopics' => fn (HasMany $query): HasMany => $query->orderBy('sort_order')->orderBy('id'), 'concepts' => fn (HasMany $query): HasMany => $query->orderBy('sort_order')->orderBy('id')]),
            'programFiles' => $subject->programFiles()->with(['uploader:id,name', 'subject:id,name'])->latest()->get(),
            'topicExtractionSummary' => [
                'count' => $subject->topicExtractions()->count(),
                'latest' => $latestTopicExtraction === null ? null : [
                    'id' => $latestTopicExtraction->id,
                    'status' => $latestTopicExtraction->status->value,
                ],
            ],
            'topicExtractionEnabled' => $topicExtractionUnavailableReason === null,
            'topicExtractionUnavailableReason' => $topicExtractionUnavailableReason,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Program $program, Subject $subject): Response
    {
        return Inertia::render('admin/subjects/edit', [
            'subject' => $subject,
            'programs' => Program::query()->orderBy('name')->get(['id', 'name', 'code']),
            'deleteBlockedReason' => $this->deleteBlockedReason($subject),
            'returnTab' => $this->workspaceTab($request->query('tab')),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SubjectRequest $request, Program $program, Subject $subject): RedirectResponse
    {
        $data = $request->validated();
        if ($subject->program_id !== (int) $data['program_id'] && $subject->programFiles()->exists()) {
            throw ValidationException::withMessages(['program_id' => 'Reassign or remove subject-associated program files before moving this subject.']);
        }

        $subject->update($data);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Subject changes were saved.']);

        $returnTab = $this->workspaceTab($request->query('tab'));

        return to_route('subjects.show', array_filter([
            'program' => $subject->program_id,
            'subject' => $subject->id,
            'tab' => $returnTab,
        ]));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Program $program, Subject $subject): RedirectResponse
    {
        $blockedReason = $this->deleteBlockedReason($subject);

        if ($blockedReason !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $blockedReason]);

            return to_route('subjects.edit', array_filter([
                'program' => $program,
                'subject' => $subject,
                'tab' => $this->workspaceTab($request->query('tab')),
            ]));
        }

        $subject->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'The subject was deleted.']);

        return to_route('programs.subjects.index', $program);
    }

    private function deleteBlockedReason(Subject $subject): ?string
    {
        if ($subject->concepts()->exists()) {
            return 'Remove this subject’s instructional concepts before deleting it.';
        }

        if ($subject->syllabusTopics()->exists()) {
            return 'Remove this subject’s official syllabus topics before deleting it.';
        }

        if ($subject->files()->exists() || $subject->programFiles()->exists()) {
            return 'Remove or reassign this subject’s source files before deleting it.';
        }

        if ($subject->topicExtractions()->exists()) {
            return 'Remove this subject’s topic extraction history before deleting it.';
        }

        return null;
    }

    private function workspaceTab(mixed $tab): ?string
    {
        return is_string($tab) && in_array($tab, ['topics', 'concepts', 'files', 'extraction'], true)
            ? $tab
            : null;
    }
}
