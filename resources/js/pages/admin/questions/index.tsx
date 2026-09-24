import { Head, Link } from '@inertiajs/react';
import conceptQuestions from '@/routes/concepts/questions';
import questions from '@/routes/questions';

type Question = { id: number; prompt: string; choices: { id: number; content: string; is_correct: boolean }[] };
export default function Questions({ concept, questions: items }: { concept: { id: number; title: string }; questions: Question[] }) {
    return <main className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8"><Head title="Questions" /><div className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-center sm:justify-between"><div><p className="text-sm font-medium uppercase tracking-wide text-primary">{concept.title}</p><h1 className="text-3xl font-semibold tracking-tight">Questions</h1></div><Link href={conceptQuestions.create(concept)} className="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-xs hover:bg-primary/90">New question</Link></div><div className="grid gap-4 md:grid-cols-2">{items.map((question) => <Link key={question.id} href={questions.show(question)} className="block rounded-xl border bg-card p-5 shadow-sm transition-colors hover:border-primary/50 hover:bg-accent"><p className="font-medium">{question.prompt}</p><span className="mt-2 block text-sm text-muted-foreground">{question.choices.length} choices</span></Link>)}</div></main>;
}
