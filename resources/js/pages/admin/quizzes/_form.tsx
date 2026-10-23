import { Head, Link, router, useForm } from '@inertiajs/react';
import { conceptQuestions, conceptQuizzes, quizzes } from '@/lib/curriculum-routes';
import InputError from '@/components/input-error';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Question = { id: number; prompt: string };
type Quiz = { id: number; title: string; description: string | null; passing_score: string; questions: Question[] };

export default function QuizForm({ concept, quiz, questions }: { concept: { id: number; title: string }; quiz?: Quiz; questions: Question[] }) {
    const form = useForm({ title: quiz?.title ?? '', description: quiz?.description ?? '', passing_score: quiz?.passing_score ?? '80', question_ids: quiz?.questions.map((question) => question.id) ?? [] as number[] });
    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (quiz) {
            form.put(quizzes.update(quiz).url);
        } else {
            form.post(conceptQuizzes.store(concept).url);
        }
    };

    return (
        <PageShell>
            <Head title={quiz ? 'Edit quiz' : 'New quiz'} />
            <PageHeader eyebrow={concept.title} title={quiz ? 'Edit quiz' : 'New quiz'} description="Assemble the questions learners will answer for this concept." />
            <Card className="py-0">
                <CardContent className="py-6">
                    <form onSubmit={submit} className="grid w-full gap-5">
                        {Object.keys(form.errors).length > 0 && (
                            <div role="alert" className="space-y-1 rounded-md border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">
                                <p className="font-medium">The quiz could not be saved.</p>
                                {Object.values(form.errors).map((message, index) => <p key={index}>{message}</p>)}
                            </div>
                        )}
                        <div className="grid gap-2">
                            <label htmlFor="quiz-title" className="text-sm font-medium">Quiz title</label>
                            <Input id="quiz-title" value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} aria-invalid={Boolean(form.errors.title)} aria-describedby={form.errors.title ? 'quiz-title-error' : undefined} required />
                            <InputError id="quiz-title-error" message={form.errors.title} />
                        </div>
                        <div className="grid gap-2">
                            <label htmlFor="quiz-description" className="text-sm font-medium">Description <span className="font-normal text-muted-foreground">(optional)</span></label>
                            <textarea id="quiz-description" value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} aria-invalid={Boolean(form.errors.description)} aria-describedby={form.errors.description ? 'quiz-description-error' : undefined} className="min-h-24 w-full rounded-md border border-input bg-background p-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive" />
                            <InputError id="quiz-description-error" message={form.errors.description} />
                        </div>
                        <div className="grid max-w-xs gap-2">
                            <label htmlFor="quiz-passing-score" className="text-sm font-medium">Passing score (%)</label>
                            <Input id="quiz-passing-score" type="number" min="0" max="100" value={form.data.passing_score} onChange={(event) => form.setData('passing_score', event.target.value)} aria-invalid={Boolean(form.errors.passing_score)} aria-describedby={form.errors.passing_score ? 'quiz-passing-score-error' : 'quiz-passing-score-hint'} required />
                            <p id="quiz-passing-score-hint" className="text-sm text-muted-foreground">Learners pass when their score reaches this percentage.</p>
                            <InputError id="quiz-passing-score-error" message={form.errors.passing_score} />
                        </div>
                        <fieldset className="grid gap-3">
                            <legend className="text-sm font-medium">Questions</legend>
                            {form.errors.question_ids && <InputError message={form.errors.question_ids} />}
                            {questions.length === 0 ? (
                                <div className="space-y-3 rounded-md border border-dashed p-4">
                                    <p className="text-sm text-muted-foreground">Create at least one question for this concept before saving a quiz. Open the question form in a new tab to keep this quiz draft, then refresh the list when you return.</p>
                                    <div className="flex flex-wrap gap-2">
                                        <Button asChild size="sm" variant="outline">
                                            <Link href={conceptQuestions.create(concept)} target="_blank" rel="noreferrer">Create a question in a new tab</Link>
                                        </Button>
                                        <Button type="button" size="sm" variant="outline" onClick={() => router.reload({ only: ['questions'] })}>
                                            Refresh question list
                                        </Button>
                                    </div>
                                </div>
                            ) : (
                                questions.map((question) => (
                                    <label key={question.id} className="flex items-start gap-3 rounded-lg border p-3 text-sm transition-colors hover:bg-accent">
                                        <input type="checkbox" checked={form.data.question_ids.includes(question.id)} onChange={(event) => form.setData('question_ids', event.target.checked ? [...form.data.question_ids, question.id] : form.data.question_ids.filter((id) => id !== question.id))} className="mt-0.5 size-4 shrink-0 accent-primary" />
                                        <span>{question.prompt}</span>
                                    </label>
                                ))
                            )}
                        </fieldset>
                        <div className="flex justify-end border-t pt-5">
                            <Button type="submit" disabled={form.processing || questions.length === 0}>
                                {form.processing ? 'Saving…' : quiz ? 'Save changes' : 'Create quiz'}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </PageShell>
    );
}
