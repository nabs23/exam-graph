import { Head, useForm } from '@inertiajs/react';
import conceptQuizzes from '@/routes/concepts/quizzes';
import quizzes from '@/routes/quizzes';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Question = { id: number; prompt: string };
type Quiz = { id: number; title: string; description: string | null; passing_score: string; questions: Question[] };

export default function QuizForm({ concept, quiz, questions }: { concept: { id: number; title: string }; quiz?: Quiz; questions: Question[] }) {
    const form = useForm({ title: quiz?.title ?? '', description: quiz?.description ?? '', passing_score: quiz?.passing_score ?? '80', question_ids: quiz?.questions.map((question) => question.id) ?? [] as number[] });
    const submit = (event: React.FormEvent) => { event.preventDefault(); if (quiz) { form.put(quizzes.update(quiz).url); } else { form.post(conceptQuizzes.store(concept).url); } };

    return <PageShell><Head title={quiz ? 'Edit quiz' : 'New quiz'} /><PageHeader eyebrow={concept.title} title={quiz ? 'Edit quiz' : 'New quiz'} description="Assemble the questions learners will answer for this concept." /><Card><CardContent className="pt-6"><form onSubmit={submit} className="grid w-full gap-5"><label className="grid gap-2 text-sm font-medium">Title<Input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} required /></label><label className="grid gap-2 text-sm font-medium">Description<textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} className="min-h-24 w-full rounded-md border border-input bg-background p-3" /></label><label className="grid max-w-xs gap-2 text-sm font-medium">Passing score<Input type="number" min="0" max="100" value={form.data.passing_score} onChange={(event) => form.setData('passing_score', event.target.value)} required /></label><fieldset className="grid gap-3"><legend className="text-sm font-medium">Questions</legend>{questions.length === 0 ? <p className="text-sm text-muted-foreground">Create quiz-ready questions for this concept first.</p> : questions.map((question) => <label key={question.id} className="flex items-start gap-3 rounded-lg border p-3 text-sm transition-colors hover:bg-accent"><input type="checkbox" checked={form.data.question_ids.includes(question.id)} onChange={(event) => form.setData('question_ids', event.target.checked ? [...form.data.question_ids, question.id] : form.data.question_ids.filter((id) => id !== question.id))} className="mt-0.5" />{question.prompt}</label>)}</fieldset><div><Button disabled={form.processing}>{form.processing ? 'Saving…' : 'Save quiz'}</Button></div></form></CardContent></Card></PageShell>;
}
