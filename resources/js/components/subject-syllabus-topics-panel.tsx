import { Link } from '@inertiajs/react';
import { concepts } from '@/lib/curriculum-routes';
import { topics } from '@/lib/curriculum-routes';
import { subjectTopics } from '@/lib/curriculum-routes';

export type SubjectConcept = {
    id: number;
    code: string;
    title: string;
    syllabus_topic_id: number | null;
};

export type SubjectSyllabusTopic = {
    id: number;
    parent_id: number | null;
    code: string | null;
    title: string;
    description: string | null;
};

export type SubjectRouteParams = {
    id: number;
};

export function SubjectSyllabusTopicsPanel({
    subject,
    topics: syllabusTopics,
    concepts: subjectConcepts,
}: {
    subject: SubjectRouteParams;
    topics: SubjectSyllabusTopic[];
    concepts: SubjectConcept[];
}) {
    return (
        <section className="space-y-4">
            <div className="flex items-center justify-between gap-3">
                <h2 className="text-xl font-semibold">Syllabus topics</h2>
                <Link href={subjectTopics.create(subject)} className="text-sm font-medium text-primary hover:underline">Create a topic</Link>
            </div>
            {syllabusTopics.length === 0 ? (
                <p className="rounded-xl border border-dashed p-6 text-sm text-muted-foreground">No syllabus topics have been added to this subject yet.</p>
            ) : (
                <TopicOutline items={syllabusTopics} concepts={subjectConcepts} parentId={null} />
            )}
        </section>
    );
}

function TopicOutline({ items, concepts: subjectConcepts, parentId }: { items: SubjectSyllabusTopic[]; concepts: SubjectConcept[]; parentId: number | null }) {
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

export function ConceptLinks({ items }: { items: SubjectConcept[] }) {
    return <ul className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">{items.map((concept) => <li key={concept.id}><Link href={concepts.show(concept)} className="block rounded-md border p-3 text-sm hover:bg-accent"><span className="font-medium">{concept.code}</span><p className="mt-1">{concept.title}</p></Link></li>)}</ul>;
}
