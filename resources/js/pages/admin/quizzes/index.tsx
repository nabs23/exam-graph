import { Head, Link } from '@inertiajs/react';
import concepts from '@/routes/concepts';
import conceptQuizzes from '@/routes/concepts/quizzes';
import quizzes from '@/routes/quizzes';
type Quiz = { id: number; title: string; description: string | null; questions_count: number };
export default function Quizzes({ concept, quizzes: items }: { concept: { id: number; title: string }; quizzes: Quiz[] }) {
    return <main className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8"><Head title="Quizzes" /><div className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-center sm:justify-between"><div><p className="text-sm font-medium uppercase tracking-wide text-primary">{concept.title}</p><h1 className="text-3xl font-semibold tracking-tight">Quizzes</h1></div><Link href={conceptQuizzes.create(concept)} className="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-xs hover:bg-primary/90">New quiz</Link></div><div className="grid gap-4 md:grid-cols-2">{items.map((quiz) => <Link key={quiz.id} href={quizzes.show(quiz)} className="block rounded-xl border bg-card p-5 shadow-sm transition-colors hover:border-primary/50 hover:bg-accent"><p className="text-lg font-semibold">{quiz.title}</p><span className="mt-2 block text-sm text-muted-foreground">{quiz.questions_count} questions</span></Link>)}</div></main>;
}

Quizzes.layout = (props: { concept: { id: number; title: string } }) => ({ breadcrumbs: [{ title: 'Concept', href: concepts.show(props.concept) }, { title: 'Quizzes', href: conceptQuizzes.index(props.concept) }] });
