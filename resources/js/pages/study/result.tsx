import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, XCircle } from 'lucide-react';
import study from '@/routes/study';
import progress from '@/routes/progress';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Answer = {
    id: number;
    is_correct: boolean;
    question: {
        prompt: string;
        explanation: string | null;
        choices: { id: number; content: string; is_correct: boolean }[];
    };
    question_choice: { id: number; content: string };
};
type Attempt = {
    score: string;
    correct_answers: number;
    total_questions: number;
    passed: boolean;
    quiz: {
        id: number;
        title: string;
        concept: {
            id: number;
            title: string;
            syllabus_topic: { title: string; code: string | null } | null;
            subject: { name: string; program: { name: string; code: string } };
        };
    };
    answers: Answer[];
};

export default function StudyResult({ attempt }: { attempt: Attempt }) {
    const concept = attempt.quiz.concept;

    return (
        <PageShell>
            <Head title="Quiz result" />
            <PageHeader
                eyebrow={`${concept.subject.program.code} · ${concept.subject.name} · ${attempt.quiz.title}`}
                title={`${attempt.score}%`}
                description={`${attempt.correct_answers} of ${attempt.total_questions} correct`}
                actions={(
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant={attempt.passed ? 'default' : 'secondary'}>
                            {attempt.passed ? <CheckCircle2 aria-hidden="true" /> : <XCircle aria-hidden="true" />}
                            {attempt.passed ? 'Passed' : 'Keep studying'}
                        </Badge>
                        <Button asChild variant="outline">
                            <Link href={study.quizzes.show(attempt.quiz.id)}>Try quiz again</Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={progress.index()}>View progress</Link>
                        </Button>
                    </div>
                )}
            />
            <section aria-label="Official syllabus topic" className="rounded-xl border bg-muted/20 p-4">
                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Official syllabus topic</p>
                <p className="mt-1 font-medium">
                    {concept.syllabus_topic
                        ? `${concept.syllabus_topic.code ? `${concept.syllabus_topic.code} · ` : ''}${concept.syllabus_topic.title}`
                        : 'No official topic assigned'}
                </p>
            </section>
            <div className="grid gap-4">
                {attempt.answers.map((answer, index) => (
                    <Card key={answer.id}>
                        <CardContent className="space-y-3 pt-6">
                            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Question {index + 1}</p>
                            <p className="font-semibold">{answer.question.prompt}</p>
                            <p className={answer.is_correct ? 'flex items-center gap-2 text-emerald-700' : 'flex items-center gap-2 text-rose-700'}>
                                {answer.is_correct ? <CheckCircle2 aria-hidden="true" className="size-4" /> : <XCircle aria-hidden="true" className="size-4" />}
                                Your answer: {answer.question_choice.content}
                            </p>
                            {!answer.is_correct && (
                                <p className="text-sm text-muted-foreground">
                                    Correct answer: {answer.question.choices.find((choice) => choice.is_correct)?.content ?? 'Unavailable'}
                                </p>
                            )}
                            {answer.question.explanation && <p className="border-t pt-3 text-sm text-muted-foreground">{answer.question.explanation}</p>}
                        </CardContent>
                    </Card>
                ))}
            </div>
            <Button asChild>
                <Link href={study.concepts.show(concept.id)}><ArrowLeft aria-hidden="true" />Review {concept.title}</Link>
            </Button>
        </PageShell>
    );
}
