import { Head, Link } from "@inertiajs/react";
import { concepts, subjectConcepts, topicConcepts } from '@/lib/curriculum-routes';

export default function Concepts({
    concepts: items,
    contextSubject,
    contextTopic,
}: {
    concepts: {
        id: number;
        code: string;
        title: string;
        subject: { name: string };
    }[];
    contextSubject?: { id: number } | null;
    contextTopic?: { id: number } | null;
}) {
    return (
        <main className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Head title="Concepts" />
            <div className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-center sm:justify-between">
                <div><p className="text-sm font-medium uppercase tracking-wide text-primary">Curriculum administration</p><h1 className="text-3xl font-semibold tracking-tight">Concepts</h1><p className="mt-1 text-muted-foreground">Manage teachable units, prerequisites, lessons, and assessments.</p></div>
                <Link
                    href={contextTopic ? topicConcepts.create(contextTopic) : contextSubject ? subjectConcepts.create(contextSubject) : concepts.create()}
                    className="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-xs hover:bg-primary/90"
                >
                    New concept
                </Link>
            </div>
            {items.map((concept) => (
                <Link
                    key={concept.id}
                    href={concepts.show(concept)}
                    className="block rounded-xl border bg-card p-5 shadow-sm transition-colors hover:border-primary/50 hover:bg-accent"
                >
                    <b>{concept.code}</b> · {concept.title}
                    <span className="ml-2 text-sm text-slate-500">
                        {concept.subject.name}
                    </span>
                </Link>
            ))}
        </main>
    );
}

Concepts.layout = {
    breadcrumbs: [{ title: 'Concept', href: concepts.index() }],
};
