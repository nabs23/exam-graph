<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyllabusTopicRequest;
use App\Models\Program;
use App\Models\Subject;
use App\Models\SyllabusTopic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SyllabusTopicController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(?Program $program = null, ?Subject $subject = null): Response
    {
        $topics = SyllabusTopic::query()
            ->when($subject, fn (Builder $query): Builder => $query->where('subject_id', $subject->id))
            ->with('children')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('admin/topics/index', [
            'subject' => $subject,
            'topics' => $topics,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Program $program, Subject $subject): Response
    {
        return Inertia::render('admin/topics/create', ['subject' => $subject, 'parents' => $subject->syllabusTopics()->orderBy('sort_order')->get(['id', 'subject_id', 'title'])]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SyllabusTopicRequest $request, Program $program, Subject $subject): RedirectResponse
    {
        $data = $request->validated();
        $this->ensureParentBelongsToSubject($data['parent_id'] ?? null, $subject);
        $topic = $subject->syllabusTopics()->create($data);

        return redirect()->to($subject->curriculumRoute('subjects.show'))->with('success', "Created {$topic->title}.");
    }

    /**
     * Display the specified resource.
     */
    public function show(Program $program, Subject $subject, SyllabusTopic $topic): Response
    {
        return Inertia::render('admin/topics/show', ['topic' => $topic->load(['subject', 'parent', 'children', 'concepts'])]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Program $program, Subject $subject, SyllabusTopic $topic): Response
    {
        return Inertia::render('admin/topics/edit', ['topic' => $topic, 'subject' => $topic->subject, 'parents' => $topic->subject->syllabusTopics()->where('id', '!=', $topic->id)->get(['id', 'subject_id', 'title'])]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SyllabusTopicRequest $request, Program $program, Subject $subject, SyllabusTopic $topic): RedirectResponse
    {
        $data = $request->validated();
        $this->ensureParentBelongsToSubject($data['parent_id'] ?? null, $topic->subject);
        abort_if((int) ($data['parent_id'] ?? 0) === $topic->id, 422, 'A topic cannot be its own parent.');
        $topic->update($data);

        return redirect()->to($topic->subject->curriculumRoute('subjects.show'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Program $program, Subject $subject, SyllabusTopic $topic): RedirectResponse
    {
        abort_if($topic->children()->exists() || $topic->concepts()->exists(), 422, 'Remove child topics and concepts before deleting this topic.');
        $subject = $topic->subject;
        $topic->delete();

        return redirect()->to($subject->curriculumRoute('subjects.show'));
    }

    private function ensureParentBelongsToSubject(?int $parentId, Subject $subject): void
    {
        if ($parentId !== null && ! $subject->syllabusTopics()->whereKey($parentId)->exists()) {
            abort(422, 'The parent topic must belong to the same subject.');
        }
    }
}
