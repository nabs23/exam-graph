import { Head, Link } from '@inertiajs/react';
import quizzes from '@/routes/quizzes';
import concepts from '@/routes/concepts';
import conceptQuizzes from '@/routes/concepts/quizzes';
export default function Quiz({ quiz }: { quiz: { id: number; title: string; description: string | null; passing_score: string; concept: { id: number; title: string }; questions: { id: number; prompt: string }[] } }) {
    return <main className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8"><Head title={quiz.title} /><div className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-start sm:justify-between"><div><p className="text-sm font-medium uppercase tracking-wide text-primary">{quiz.concept.title}</p><h1 className="text-3xl font-semibold tracking-tight">{quiz.title}</h1><p className="mt-2 text-muted-foreground">{quiz.description}</p></div><Link href={quizzes.edit(quiz)} className="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium shadow-xs hover:bg-accent">Edit quiz</Link></div><div className="rounded-xl border bg-card p-6 shadow-sm"><p className="text-sm font-medium text-primary">Passing score: {quiz.passing_score}%</p><ol className="mt-5 grid gap-3">{quiz.questions.map((question, index) => <li key={question.id} className="rounded-lg border p-4"><span className="mr-2 text-sm font-medium text-muted-foreground">{index + 1}.</span>{question.prompt}</li>)}</ol></div></main>;
}

Quiz.layout = (props: { quiz: { id: number; concept: { id: number } } }) => ({ breadcrumbs: [{ title: 'Concept', href: concepts.show(props.quiz.concept) }, { title: 'Quizzes', href: conceptQuizzes.index(props.quiz.concept) }, { title: 'Quiz', href: quizzes.show(props.quiz) }] });
