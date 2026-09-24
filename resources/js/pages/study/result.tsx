import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, XCircle } from 'lucide-react';
import study from '@/routes/study';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Answer = { id: number; is_correct: boolean; question: { prompt: string; explanation: string | null; choices: { id: number; content: string; is_correct: boolean }[] }; question_choice: { id: number; content: string } };
type Attempt = { score: string; correct_answers: number; total_questions: number; passed: boolean; quiz: { concept: { id: number; title: string } }; answers: Answer[] };

export default function StudyResult({ attempt }: { attempt: Attempt }) {
    return <PageShell><Head title="Quiz result" /><PageHeader eyebrow="Quiz result" title={`${attempt.score}%`} description={`${attempt.correct_answers} of ${attempt.total_questions} correct`} actions={<Badge variant={attempt.passed ? 'default' : 'secondary'}>{attempt.passed ? <CheckCircle2 /> : <XCircle />}{attempt.passed ? 'Passed' : 'Keep studying'}</Badge>} />{attempt.answers.map((answer) => <Card key={answer.id}><CardContent className="space-y-3 pt-6"><p className="font-semibold">{answer.question.prompt}</p><p className={answer.is_correct ? 'flex items-center gap-2 text-emerald-700' : 'flex items-center gap-2 text-rose-700'}>{answer.is_correct ? <CheckCircle2 className="size-4" /> : <XCircle className="size-4" />}Your answer: {answer.question_choice.content}</p>{!answer.is_correct && <p className="text-sm text-muted-foreground">Correct: {answer.question.choices.find((choice) => choice.is_correct)?.content}</p>}<p className="border-t pt-3 text-sm text-muted-foreground">{answer.question.explanation}</p></CardContent></Card>)}<Button asChild><Link href={study.concepts.show(attempt.quiz.concept.id)}><ArrowLeft />Back to {attempt.quiz.concept.title}</Link></Button></PageShell>;
}
