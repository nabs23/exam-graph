import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2 } from 'lucide-react';
import { store } from '@/actions/App/Http/Controllers/QuizAttemptController';
import study from '@/routes/study';
import { EmptyState, PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Choice = { id: number; content: string };
type Question = { id: number; prompt: string; choices: Choice[] };
type Quiz = {
    id: number;
    title: string;
    description: string | null;
    questions: Question[];
    concept: {
        id: number;
        title: string;
        subject: { name: string; program: { name: string; code: string } };
    };
};

export default function StudyQuiz({ quiz }: { quiz: Quiz }) {
    const form = useForm<{ answers: Record<number, number> }>({ answers: {} });
    const answeredCount = Object.keys(form.data.answers).length;
    const conceptUrl = study.concepts.show(quiz.concept.id);

    if (quiz.questions.length === 0) {
        return (
            <PageShell>
                <Head title={quiz.title} />
                <PageHeader
                    eyebrow={`${quiz.concept.subject.program.code} · ${quiz.concept.subject.name} · Knowledge check`}
                    title={quiz.title}
                    description={quiz.description}
                    actions={<Button asChild variant="outline"><Link href={conceptUrl}><ArrowLeft />Back to concept</Link></Button>}
                />
                <EmptyState>
                    <div className="space-y-3">
                        <p className="font-medium text-foreground">This quiz is not ready yet</p>
                        <p>No questions have been added. Return to the concept to continue studying.</p>
                        <Button asChild><Link href={conceptUrl}>Return to concept</Link></Button>
                    </div>
                </EmptyState>
            </PageShell>
        );
    }

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store(quiz).url);
    };

    return (
        <PageShell>
            <Head title={quiz.title} />
            <form onSubmit={submit} className="space-y-6">
                <PageHeader
                    eyebrow={`${quiz.concept.subject.program.code} · ${quiz.concept.subject.name} · Knowledge check`}
                    title={quiz.title}
                    description={quiz.description}
                    actions={<Button asChild variant="outline"><Link href={conceptUrl}><ArrowLeft />Back to concept</Link></Button>}
                />
                <p role="status" className="rounded-md border bg-muted/30 px-4 py-3 text-sm">
                    {answeredCount} of {quiz.questions.length} questions answered. Answer each question before submitting.
                </p>
                {form.errors.answers && <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">{form.errors.answers}</p>}
                {quiz.questions.map((question, index) => (
                    <Card key={question.id}>
                        <CardContent className="pt-6">
                            <fieldset>
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <legend className="text-base font-semibold">{index + 1}. {question.prompt}</legend>
                                    <span className="text-xs font-medium text-muted-foreground" role="status">
                                        {form.data.answers[question.id] ? 'Answered' : 'Not answered'}
                                    </span>
                                </div>
                                <div className="mt-4 grid gap-3">
                                    {question.choices.map((choice) => (
                                        <label key={choice.id} className="flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition-colors hover:border-primary/50 hover:bg-accent has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                            <input
                                                className="mt-1 accent-primary"
                                                type="radio"
                                                name={`question-${question.id}`}
                                                required
                                                value={choice.id}
                                                onChange={() => form.setData('answers', { ...form.data.answers, [question.id]: choice.id })}
                                            />
                                            {choice.content}
                                        </label>
                                    ))}
                                </div>
                            </fieldset>
                        </CardContent>
                    </Card>
                ))}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <Button type="submit" size="lg" disabled={form.processing}>
                        <CheckCircle2 aria-hidden="true" />
                        {form.processing ? 'Submitting…' : 'Submit quiz'}
                    </Button>
                    <p className="text-sm text-muted-foreground">You can review your answers after submitting.</p>
                </div>
            </form>
        </PageShell>
    );
}
