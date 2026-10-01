import { Head, Link } from '@inertiajs/react';
import concepts from '@/routes/concepts';
import conceptLessons from '@/routes/concepts/lessons';
import lessons from '@/routes/lessons';
export default function Lessons({ concept, lessons: items }: { concept: { id: number; title: string }; lessons: { id: number; title: string; summary: string | null }[] }) { return <main className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8"><Head title="Lessons" /><div className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-center sm:justify-between"><div><p className="text-sm font-medium uppercase tracking-wide text-primary">{concept.title}</p><h1 className="text-3xl font-semibold tracking-tight">Lessons</h1></div><Link href={conceptLessons.create(concept)} className="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-xs hover:bg-primary/90">New lesson</Link></div>{items.length === 0 ? <div className="flex min-h-32 items-center justify-center rounded-xl border border-dashed bg-muted/20 p-8 text-center text-sm text-muted-foreground">No lessons yet.</div> : <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{items.map((lesson) => <Link key={lesson.id} href={lessons.show(lesson)} className="block rounded-xl border bg-card p-5 shadow-sm transition-colors hover:border-primary/50 hover:bg-accent"><b className="text-lg">{lesson.title}</b><span className="mt-2 block text-sm text-muted-foreground">{lesson.summary}</span></Link>)}</div>}</main>; }

Lessons.layout = (props: { concept: { id: number; title: string } }) => ({
    breadcrumbs: [
        { title: 'Concept', href: concepts.show(props.concept) },
        { title: 'Lessons', href: conceptLessons.index(props.concept) },
    ],
});
