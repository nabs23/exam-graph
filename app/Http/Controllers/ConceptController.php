<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddPrerequisiteRequest;
use App\Http\Requests\ConceptRequest;
use App\Http\Requests\LearningObjectiveRequest;
use App\Http\Requests\LessonRequest;
use App\Http\Requests\QuestionRequest;
use App\Http\Requests\QuizRequest;
use App\Models\Concept;
use App\Models\Subject;
use App\Models\SyllabusTopic;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ConceptController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('admin/concepts/index', ['concepts' => Concept::query()->with(['subject:id,name,code', 'syllabusTopic:id,title'])->orderBy('sort_order')->get(), 'subjects' => Subject::query()->orderBy('name')->get(['id', 'name', 'code'])]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('admin/concepts/create', $this->formOptions());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ConceptRequest $request): RedirectResponse
    {
        $concept = Concept::create($this->validatedConcept($request->validated()));

        return to_route('concepts.show', $concept);
    }

    /**
     * Display the specified resource.
     */
    public function show(Concept $concept): Response
    {
        return Inertia::render('admin/concepts/show', ['concept' => $concept->load(['subject', 'syllabusTopic', 'prerequisites', 'lessons', 'learningObjectives', 'questions.choices', 'quizzes.questions'])] + $this->formOptions());
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Concept $concept): Response
    {
        return Inertia::render('admin/concepts/edit', ['concept' => $concept] + $this->formOptions());
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ConceptRequest $request, Concept $concept): RedirectResponse
    {
        $concept->update($this->validatedConcept($request->validated()));

        return to_route('concepts.show', $concept);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Concept $concept): RedirectResponse
    {
        abort_if($concept->quizzes()->exists() || $concept->questions()->exists(), 422, 'Remove quizzes and questions before deleting this concept.');
        $subject = $concept->subject;
        $concept->delete();

        return to_route('subjects.show', $subject);
    }

    public function addPrerequisite(AddPrerequisiteRequest $request, Concept $concept): RedirectResponse
    {
        $prerequisiteId = $request->validated('prerequisite_concept_id');
        abort_if($concept->id === (int) $prerequisiteId, 422, 'A concept cannot be its own prerequisite.');
        $concept->prerequisites()->syncWithoutDetaching([$prerequisiteId]);

        return back();
    }

    public function removePrerequisite(Concept $concept, Concept $prerequisite): RedirectResponse
    {
        $concept->prerequisites()->detach($prerequisite);

        return back();
    }

    public function storeLesson(LessonRequest $request, Concept $concept): RedirectResponse
    {
        $concept->lessons()->create($request->validated());

        return back();
    }

    public function storeObjective(LearningObjectiveRequest $request, Concept $concept): RedirectResponse
    {
        $concept->learningObjectives()->create($request->validated());

        return back();
    }

    public function storeQuestion(QuestionRequest $request, Concept $concept): RedirectResponse
    {
        $data = $request->validated();
        abort_unless(collect($data['choices'])->where('is_correct', true)->count() === 1, 422, 'A question needs exactly one correct choice.');
        $question = $concept->questions()->create(collect($data)->except('choices')->all());
        foreach ($data['choices'] as $index => $choice) {
            $question->choices()->create($choice + ['sort_order' => $index]);
        }

        return back();
    }

    public function storeQuiz(QuizRequest $request, Concept $concept): RedirectResponse
    {
        $data = $request->validated();
        $quiz = $concept->quizzes()->create(collect($data)->except('question_ids')->all());
        $quiz->questions()->sync(collect($data['question_ids'])->values()->mapWithKeys(fn (int $id, int $index): array => [$id => ['sort_order' => $index]])->all());

        return back();
    }

    private function validatedConcept(array $data): array
    {
        if (($data['syllabus_topic_id'] ?? null) !== null && ! SyllabusTopic::query()->whereKey($data['syllabus_topic_id'])->where('subject_id', $data['subject_id'])->exists()) {
            abort(422, 'The syllabus topic must belong to the selected subject.');
        }

        return $data;
    }

    private function formOptions(): array
    {
        return ['subjects' => Subject::query()->orderBy('name')->get(['id', 'name', 'code']), 'topics' => SyllabusTopic::query()->orderBy('sort_order')->get(['id', 'subject_id', 'title']), 'allConcepts' => Concept::query()->orderBy('title')->get(['id', 'subject_id', 'code', 'title'])];
    }
}
