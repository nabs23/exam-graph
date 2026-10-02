import { Form, Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { concepts, topicConcepts } from '@/lib/curriculum-routes';
import { topics } from '@/lib/curriculum-routes';
import { subjects } from '@/lib/curriculum-routes';
import { subjectTopics } from '@/lib/curriculum-routes';

type Topic = {
    id: number;
    subject_id: number;
    code: string | null;
    title: string;
    description: string | null;
    subject: { id: number; name: string };
    parent: { id: number; title: string } | null;
    children: { id: number; code: string | null; title: string; description: string | null }[];
    concepts: { id: number; code: string; title: string; description: string | null }[];
};

export default function TopicShow({ topic }: { topic: Topic }) {
    return (
        <main className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Head title={topic.title} />
            <header className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <Link href={subjects.show(topic.subject)} className="text-sm font-medium text-primary hover:underline">{topic.subject.name}</Link>
                    {topic.code && <p className="mt-2 text-sm font-medium uppercase tracking-wide text-primary">{topic.code}</p>}
                    <h1 className="mt-1 text-3xl font-semibold tracking-tight">{topic.title}</h1>
                    {topic.description && <p className="mt-2 text-muted-foreground">{topic.description}</p>}
                    {topic.parent && <p className="mt-2 text-sm text-muted-foreground">Parent topic: <Link href={topics.show(topic.parent)} className="underline">{topic.parent.title}</Link></p>}
                </div>
                <div className="flex flex-wrap gap-2">
                    <Link href={subjectTopics.index(topic.subject)} className="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium shadow-xs hover:bg-accent">All syllabus topics</Link>
                    <Link href={topics.edit(topic)} className="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium shadow-xs hover:bg-accent">Edit topic</Link>
                </div>
            </header>
            <section className="space-y-4">
                <div>
                    <h2 className="text-xl font-semibold">Child topics</h2>
                    <p className="text-sm text-muted-foreground">Add a topic nested beneath {topic.title}.</p>
                </div>
                <Form {...subjectTopics.store.form(topic.subject)} className="grid gap-4 rounded-xl border bg-card p-4 shadow-sm sm:grid-cols-2">
                    {({ errors, processing }) => <>
                        <input type="hidden" name="parent_id" value={topic.id} />
                        <label className="grid gap-2 text-sm font-medium">
                            Title
                            <Input name="title" required aria-invalid={Boolean(errors.title)} />
                            {errors.title && <p role="alert" className="text-sm text-destructive">{errors.title}</p>}
                        </label>
                        <label className="grid gap-2 text-sm font-medium">
                            Code <span className="font-normal text-muted-foreground">(optional)</span>
                            <Input name="code" aria-invalid={Boolean(errors.code)} />
                            {errors.code && <p role="alert" className="text-sm text-destructive">{errors.code}</p>}
                        </label>
                        <label className="grid gap-2 text-sm font-medium sm:col-span-2">
                            Description <span className="font-normal text-muted-foreground">(optional)</span>
                            <textarea name="description" className="min-h-24 w-full rounded-md border border-input bg-background p-3 text-sm" aria-invalid={Boolean(errors.description)} />
                            {errors.description && <p role="alert" className="text-sm text-destructive">{errors.description}</p>}
                        </label>
                        <div className="sm:col-span-2">
                            <Button type="submit" disabled={processing}>{processing ? 'Creating…' : 'Create child topic'}</Button>
                        </div>
                    </>}
                </Form>
                {topic.children.length === 0 ? <p className="text-sm text-muted-foreground">No child topics have been added yet.</p> : (
                    <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{topic.children.map((child) => <li key={child.id}><Link href={topics.show(child)} className="block h-full rounded-xl border bg-card p-4 shadow-sm hover:bg-accent"><p className="font-medium">{child.code ? `${child.code} · ` : ''}{child.title}</p>{child.description && <p className="mt-2 text-sm text-muted-foreground">{child.description}</p>}</Link></li>)}</ul>
                )}
            </section>
            <section className="space-y-3">
                <div className="flex items-center justify-between gap-3"><h2 className="text-xl font-semibold">Concepts</h2><Link href={topicConcepts.create(topic)} className="text-sm font-medium text-primary hover:underline">Create a concept</Link></div>
                {topic.concepts.length === 0 ? <p className="text-sm text-muted-foreground">No concepts are linked to this topic yet.</p> : (
                    <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{topic.concepts.map((concept) => <li key={concept.id}><Link href={concepts.show(concept)} className="block h-full rounded-xl border bg-card p-4 shadow-sm hover:bg-accent"><p className="font-medium">{concept.code} · {concept.title}</p>{concept.description && <p className="mt-2 text-sm text-muted-foreground">{concept.description}</p>}</Link></li>)}</ul>
                )}
            </section>
        </main>
    );
}

TopicShow.layout = (props: { topic: { id: number; subject_id: number; subject: { id: number } } }) => ({ breadcrumbs: [{ title: 'Subject', href: subjects.show(props.topic.subject) }, { title: 'Syllabus topics', href: subjectTopics.index(props.topic.subject) }, { title: 'Syllabus topic', href: topics.show(props.topic) }] });
