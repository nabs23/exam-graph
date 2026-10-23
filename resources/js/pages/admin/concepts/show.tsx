import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { EmptyState, PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { concepts } from '@/lib/curriculum-routes';
import { conceptLessons as lessons, lessons as lessonRoutes } from '@/lib/curriculum-routes';
import { conceptObjectives as objectives } from '@/lib/curriculum-routes';
import { conceptQuestions as questions } from '@/lib/curriculum-routes';
import { conceptQuizzes as quizzes } from '@/lib/curriculum-routes';
import generations from '@/routes/programs/curriculum-generations';

type Concept = {
    id: number;
    code: string;
    title: string;
    description: string | null;
    lessons: { id: number; title: string; summary: string | null }[];
    learning_objectives: { id: number; description: string }[];
    questions: {
        id: number;
        prompt: string;
        choices: { id: number; content: string; is_correct: boolean }[];
    }[];
    quizzes: { id: number; title: string; questions: { id: number }[] }[];
    prerequisites: { id: number; code: string; title: string }[];
    subject: { id: number; program_id: number; name: string; code: string | null };
};

export default function Concept({ concept }: { concept: Concept }) {
    const lesson = useForm({ title: '', summary: '', content: '', sort_order: 0 });
    const objective = useForm({ description: '', sort_order: 0 });

    const createLesson = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        lesson.post(lessons.store(concept).url, {
            onSuccess: () => lesson.reset(),
        });
    };

    const createObjective = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        objective.post(objectives.store(concept).url, {
            onSuccess: () => objective.reset(),
        });
    };

    return (
        <PageShell>
            <Head title={concept.title} />
            <PageHeader
                eyebrow={`${concept.subject.name}${concept.subject.code ? ` · ${concept.subject.code}` : ''} · Instructional concept`}
                title={concept.title}
                description={concept.description || 'Build the lessons, objectives, questions, and quizzes that teach this concept.'}
                actions={(
                    <>
                        <Button asChild>
                            <Link href={generations.create(concept.subject.program_id, { query: { kind: 'lessons', subject_id: concept.subject.id, scope_type: 'concept', scope_id: concept.id } })}>Draft lesson</Link>
                        </Button>
                        <Button variant="outline" asChild><Link href={lessons.index(concept)}>Manage lessons</Link></Button>
                        <Button variant="outline" asChild><Link href={questions.index(concept)}>Manage questions</Link></Button>
                        <Button variant="outline" asChild><Link href={quizzes.index(concept)}>Manage quizzes</Link></Button>
                        <Button variant="outline" asChild><Link href={concepts.edit(concept)}>Edit concept</Link></Button>
                    </>
                )}
            />

            <section aria-labelledby="concept-prerequisites-title">
                <Card className="py-0">
                    <CardContent className="space-y-3 py-5">
                        <div>
                            <h2 id="concept-prerequisites-title" className="font-semibold">Prerequisites</h2>
                            <p className="text-sm text-muted-foreground">Concepts learners are expected to understand first.</p>
                        </div>
                        {concept.prerequisites.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No prerequisites are listed for this concept.</p>
                        ) : (
                            <ul className="flex flex-wrap gap-2">
                                {concept.prerequisites.map((item) => (
                                    <li key={item.id} className="rounded-full border px-3 py-1 text-sm">
                                        <span className="font-medium">{item.code}</span> · {item.title}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </section>

            <section aria-label="Quick authoring" className="grid gap-4 xl:grid-cols-2">
                <Card className="py-0">
                    <CardContent className="py-6">
                        <form onSubmit={createLesson} className="grid gap-4">
                            <div>
                                <h2 className="font-semibold">Add a lesson</h2>
                                <p className="mt-1 text-sm text-muted-foreground">Create an author-written lesson directly, or use Draft lesson to start a reviewable proposal.</p>
                            </div>
                            <div className="grid gap-2">
                                <label htmlFor="quick-lesson-title" className="text-sm font-medium">Lesson title</label>
                                <Input
                                    id="quick-lesson-title"
                                    value={lesson.data.title}
                                    onChange={(event) => lesson.setData('title', event.target.value)}
                                    aria-invalid={Boolean(lesson.errors.title)}
                                    aria-describedby={lesson.errors.title ? 'quick-lesson-title-error' : undefined}
                                    required
                                />
                                <InputError id="quick-lesson-title-error" role="alert" message={lesson.errors.title} />
                            </div>
                            <div className="grid gap-2">
                                <label htmlFor="quick-lesson-summary" className="text-sm font-medium">Summary <span className="font-normal text-muted-foreground">(optional)</span></label>
                                <textarea
                                    id="quick-lesson-summary"
                                    value={lesson.data.summary}
                                    onChange={(event) => lesson.setData('summary', event.target.value)}
                                    aria-invalid={Boolean(lesson.errors.summary)}
                                    aria-describedby={lesson.errors.summary ? 'quick-lesson-summary-error' : undefined}
                                    className="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive"
                                />
                                <InputError id="quick-lesson-summary-error" role="alert" message={lesson.errors.summary} />
                            </div>
                            <div className="grid gap-2">
                                <label htmlFor="quick-lesson-content" className="text-sm font-medium">Lesson content</label>
                                <textarea
                                    id="quick-lesson-content"
                                    value={lesson.data.content}
                                    onChange={(event) => lesson.setData('content', event.target.value)}
                                    aria-invalid={Boolean(lesson.errors.content)}
                                    aria-describedby={lesson.errors.content ? 'quick-lesson-content-error' : 'quick-lesson-content-hint'}
                                    className="min-h-40 w-full rounded-md border border-input bg-background px-3 py-2 text-sm leading-6 shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive"
                                    required
                                />
                                <p id="quick-lesson-content-hint" className="text-sm text-muted-foreground">Use short sections and paragraphs to keep the lesson easy to scan.</p>
                                <InputError id="quick-lesson-content-error" role="alert" message={lesson.errors.content} />
                            </div>
                            <div className="flex flex-wrap items-center gap-3">
                                <Button type="submit" disabled={lesson.processing}>
                                    {lesson.processing ? 'Saving lesson…' : 'Save lesson'}
                                </Button>
                                {lesson.recentlySuccessful && <p role="status" className="text-sm text-muted-foreground">Lesson saved.</p>}
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card className="py-0">
                    <CardContent className="py-6">
                        <form onSubmit={createObjective} className="grid gap-4">
                            <div>
                                <h2 className="font-semibold">Add a learning objective</h2>
                                <p className="mt-1 text-sm text-muted-foreground">Describe an observable outcome a learner should demonstrate after studying this concept.</p>
                            </div>
                            <div className="grid gap-2">
                                <label htmlFor="objective-description" className="text-sm font-medium">Learning objective</label>
                                <textarea
                                    id="objective-description"
                                    value={objective.data.description}
                                    onChange={(event) => objective.setData('description', event.target.value)}
                                    aria-invalid={Boolean(objective.errors.description)}
                                    aria-describedby={objective.errors.description ? 'objective-description-error' : 'objective-description-hint'}
                                    className="min-h-40 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive"
                                    required
                                />
                                <p id="objective-description-hint" className="text-sm text-muted-foreground">Start with an action learners can demonstrate, such as explain, compare, or calculate.</p>
                                <InputError id="objective-description-error" role="alert" message={objective.errors.description} />
                            </div>
                            <div className="flex flex-wrap items-center gap-3">
                                <Button type="submit" disabled={objective.processing}>
                                    {objective.processing ? 'Saving objective…' : 'Save objective'}
                                </Button>
                                <Button variant="outline" asChild><Link href={objectives.index(concept)}>Manage objectives</Link></Button>
                                {objective.recentlySuccessful && <p role="status" className="text-sm text-muted-foreground">Learning objective saved.</p>}
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </section>

            <section aria-labelledby="concept-lessons-title" className="space-y-3">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="concept-lessons-title" className="text-xl font-semibold">Lessons</h2>
                        <p className="text-sm text-muted-foreground">{concept.lessons.length} authored {concept.lessons.length === 1 ? 'lesson' : 'lessons'}</p>
                    </div>
                    <Button variant="outline" asChild><Link href={lessons.create(concept)}>Open full lesson editor</Link></Button>
                </div>
                {concept.lessons.length === 0 ? (
                    <EmptyState>No lessons have been added to this concept yet. Use the form above or create a reviewable draft.</EmptyState>
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {concept.lessons.map((item) => (
                            <li key={item.id}>
                                <Link href={lessonRoutes.show(item)} className="block h-full rounded-xl border bg-card p-4 transition-colors hover:border-primary/50 hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                                    <h3 className="font-medium">{item.title}</h3>
                                    {item.summary && <p className="mt-2 line-clamp-3 text-sm text-muted-foreground">{item.summary}</p>}
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section aria-labelledby="concept-objectives-title" className="space-y-3">
                <h2 id="concept-objectives-title" className="text-xl font-semibold">Learning objectives</h2>
                {concept.learning_objectives.length === 0 ? (
                    <EmptyState>No learning objectives are listed yet. Add an observable outcome using the form above.</EmptyState>
                ) : (
                    <ol className="grid gap-3 sm:grid-cols-2">
                        {concept.learning_objectives.map((item, index) => (
                            <li key={item.id} className="rounded-xl border bg-card p-4 text-sm">
                                <span className="mr-2 font-semibold text-primary">{index + 1}.</span>{item.description}
                            </li>
                        ))}
                    </ol>
                )}
            </section>

            <section aria-labelledby="concept-questions-title" className="space-y-3">
                <h2 id="concept-questions-title" className="text-xl font-semibold">Questions</h2>
                {concept.questions.length === 0 ? (
                    <EmptyState>No questions have been added yet. Create questions when you are ready to build assessments for this concept.</EmptyState>
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-2">
                        {concept.questions.map((item) => (
                            <li key={item.id} className="rounded-xl border bg-card p-4">
                                <p className="font-medium">{item.prompt}</p>
                                <p className="mt-2 text-sm text-muted-foreground">{item.choices.length} {item.choices.length === 1 ? 'choice' : 'choices'}</p>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section aria-labelledby="concept-quizzes-title" className="space-y-3">
                <h2 id="concept-quizzes-title" className="text-xl font-semibold">Quizzes</h2>
                {concept.quizzes.length === 0 ? (
                    <EmptyState>No quizzes have been created for this concept yet.</EmptyState>
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {concept.quizzes.map((item) => (
                            <li key={item.id} className="rounded-xl border bg-card p-4">
                                <h3 className="font-medium">{item.title}</h3>
                                <p className="mt-2 text-sm text-muted-foreground">{item.questions.length} {item.questions.length === 1 ? 'question' : 'questions'}</p>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </PageShell>
    );
}

Concept.layout = (props: { concept: Concept }) => ({
    breadcrumbs: [
        { title: 'Concepts', href: concepts.index() },
        { title: 'Concept', href: concepts.show(props.concept) },
    ],
});
