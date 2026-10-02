import { Head, Link } from '@inertiajs/react';
import { SourceFiles, type SourceFile } from '@/components/source-files';
import { concepts, subjectConcepts } from '@/lib/curriculum-routes';
import { subjects } from '@/lib/curriculum-routes';
import { topics } from '@/lib/curriculum-routes';
import { subjectTopics } from '@/lib/curriculum-routes';

type Concept = { id: number; code: string; title: string; syllabus_topic_id: number | null };
type Topic = { id: number; parent_id: number | null; code: string | null; title: string; description: string | null };

export default function Subject({ subject }: {
    subject: { id: number; name: string; code: string | null; syllabus_topics: Topic[]; concepts: Concept[]; files: SourceFile[] };
}) {
    const unassignedConcepts = subject.concepts.filter((concept) => concept.syllabus_topic_id === null);

    return (
        <main className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Head title={subject.name} />
            <header className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    {subject.code && <p className="text-sm font-medium uppercase tracking-wide text-primary">{subject.code}</p>}
                    <h1 className="text-3xl font-semibold tracking-tight">{subject.name}</h1>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Link href={subjectTopics.index(subject)} className="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium shadow-xs hover:bg-accent">Manage syllabus topics</Link>
                    <Link href={subjects.edit(subject)} className="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium shadow-xs hover:bg-accent">Edit subject</Link>
                </div>
            </header>
            <section className="space-y-4">
                <div className="flex items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold">Syllabus topics</h2>
                    <Link href={subjectTopics.create(subject)} className="text-sm font-medium text-primary hover:underline">Create a topic</Link>
                </div>
                {subject.syllabus_topics.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-6 text-sm text-muted-foreground">No syllabus topics have been added to this subject yet.</p>
                ) : (
                    <TopicOutline items={subject.syllabus_topics} concepts={subject.concepts} parentId={null} />
                )}
            </section>
            {unassignedConcepts.length > 0 && (
                <section className="space-y-3">
                    <h2 className="text-xl font-semibold">Unassigned concepts</h2>
                    <p className="text-sm text-muted-foreground">These concepts have not been linked to a syllabus topic.</p>
                    <ConceptLinks items={unassignedConcepts} />
                </section>
            )}
            <Link href={subjectConcepts.create(subject)} className="inline-block text-sm font-medium text-primary hover:underline">Create a concept</Link>
            <SourceFiles ownerId={subject.id} kind="subject" files={subject.files} />
        </main>
    );
}

function TopicOutline({ items, concepts: subjectConcepts, parentId }: { items: Topic[]; concepts: Concept[]; parentId: number | null }) {
    const children = items.filter((topic) => topic.parent_id === parentId);
    if (children.length === 0) return null;

    return (
        <ul className="space-y-4">
            {children.map((topic) => {
                const topicConcepts = subjectConcepts.filter((concept) => concept.syllabus_topic_id === topic.id);
                return (
                    <li key={topic.id} className="space-y-3 rounded-xl border bg-card p-5 shadow-sm">
                        <Link href={topics.show(topic)} className="block font-semibold hover:text-primary">
                            {topic.code && <span className="mr-2 text-sm text-primary">{topic.code}</span>}{topic.title}
                        </Link>
                        {topic.description && <p className="text-sm text-muted-foreground">{topic.description}</p>}
                        {topicConcepts.length > 0 ? <ConceptLinks items={topicConcepts} /> : <p className="text-sm text-muted-foreground">No concepts linked to this topic yet.</p>}
                        {items.some((child) => child.parent_id === topic.id) && (
                            <div className="border-l-2 pl-4">
                                <TopicOutline items={items} concepts={subjectConcepts} parentId={topic.id} />
                            </div>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}

function ConceptLinks({ items }: { items: Concept[] }) {
    return <ul className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">{items.map((concept) => <li key={concept.id}><Link href={concepts.show(concept)} className="block rounded-md border p-3 text-sm hover:bg-accent"><span className="font-medium">{concept.code}</span><p className="mt-1">{concept.title}</p></Link></li>)}</ul>;
}

Subject.layout = (props: { subject: { id: number } }) => ({ breadcrumbs: [{ title: 'Subjects', href: subjects.index() }, { title: 'Subject', href: subjects.show(props.subject) }] });
