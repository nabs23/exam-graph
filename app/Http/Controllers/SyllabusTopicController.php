<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyllabusTopicRequest;
use App\Models\Subject;
use App\Models\SyllabusTopic;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SyllabusTopicController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Subject $subject): Response
    {
        return Inertia::render('admin/topics/index', ['subject' => $subject, 'topics' => $subject->syllabusTopics()->with('children')->orderBy('sort_order')->get()]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Subject $subject): Response
    {
        return Inertia::render('admin/topics/create', ['subject' => $subject, 'parents' => $subject->syllabusTopics()->orderBy('sort_order')->get(['id', 'title'])]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SyllabusTopicRequest $request, Subject $subject): RedirectResponse
    {
        $data = $request->validated();
        $this->ensureParentBelongsToSubject($data['parent_id'] ?? null, $subject);
        $topic = $subject->syllabusTopics()->create($data);

        return to_route('subjects.show', $subject)->with('success', "Created {$topic->title}.");
    }

    /**
     * Display the specified resource.
     */
    public function show(SyllabusTopic $syllabusTopic): Response
    {
        return Inertia::render('admin/topics/show', ['topic' => $syllabusTopic->load(['subject', 'parent', 'children', 'concepts'])]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SyllabusTopic $syllabusTopic): Response
    {
        return Inertia::render('admin/topics/edit', ['topic' => $syllabusTopic, 'subject' => $syllabusTopic->subject, 'parents' => $syllabusTopic->subject->syllabusTopics()->where('id', '!=', $syllabusTopic->id)->get(['id', 'title'])]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SyllabusTopicRequest $request, SyllabusTopic $syllabusTopic): RedirectResponse
    {
        $data = $request->validated();
        $this->ensureParentBelongsToSubject($data['parent_id'] ?? null, $syllabusTopic->subject);
        abort_if((int) ($data['parent_id'] ?? 0) === $syllabusTopic->id, 422, 'A topic cannot be its own parent.');
        $syllabusTopic->update($data);

        return to_route('subjects.show', $syllabusTopic->subject);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SyllabusTopic $syllabusTopic): RedirectResponse
    {
        abort_if($syllabusTopic->children()->exists() || $syllabusTopic->concepts()->exists(), 422, 'Remove child topics and concepts before deleting this topic.');
        $subject = $syllabusTopic->subject;
        $syllabusTopic->delete();

        return to_route('subjects.show', $subject);
    }

    private function ensureParentBelongsToSubject(?int $parentId, Subject $subject): void
    {
        if ($parentId !== null && ! $subject->syllabusTopics()->whereKey($parentId)->exists()) {
            abort(422, 'The parent topic must belong to the same subject.');
        }
    }
}
