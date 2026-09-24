import { Head, useForm } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { store } from '@/actions/App/Http/Controllers/QuizAttemptController';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Choice = { id: number; content: string };
type Question = { id: number; prompt: string; choices: Choice[] };
type Quiz = { id: number; title: string; description: string | null; questions: Question[] };

export default function StudyQuiz({ quiz }: { quiz: Quiz }) {
    const form = useForm<{ answers: Record<number, number> }>({ answers: {} });
    const submit = (event: React.FormEvent) => { event.preventDefault(); form.post(store(quiz).url); };
    return <PageShell><Head title={quiz.title} /><form onSubmit={submit} className="space-y-6"><PageHeader eyebrow="Knowledge check" title={quiz.title} description={quiz.description} />{quiz.questions.map((question, index) => <Card key={question.id}><CardContent className="pt-6"><fieldset><legend className="text-base font-semibold">{index + 1}. {question.prompt}</legend><div className="mt-4 grid gap-3">{question.choices.map((choice) => <label key={choice.id} className="flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition-colors hover:border-primary/50 hover:bg-accent has-[:checked]:border-primary has-[:checked]:bg-primary/5"><input className="mt-1 accent-primary" type="radio" name={`question-${question.id}`} required value={choice.id} onChange={() => form.setData('answers', { ...form.data.answers, [question.id]: choice.id })} />{choice.content}</label>)}</div></fieldset></CardContent></Card>)}<Button type="submit" size="lg" disabled={form.processing}><CheckCircle2 />{form.processing ? 'Submitting…' : 'Submit quiz'}</Button></form></PageShell>;
}
