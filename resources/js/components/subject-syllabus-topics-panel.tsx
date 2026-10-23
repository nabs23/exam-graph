import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { EmptyState } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
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
        <section aria-labelledby="syllabus-topics-title" className="space-y-5">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2
                        id="syllabus-topics-title"
                        className="text-xl font-semibold"
                    >
                        Official syllabus topics
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {syllabusTopics.length}{' '}
                        {syllabusTopics.length === 1 ? 'topic' : 'topics'} · The
                        published outline from the source syllabus, separate
                        from instructional concepts.
                    </p>
                </div>
                {syllabusTopics.length > 0 && (
                    <Button asChild className="shrink-0">
                        <Link href={subjectTopics.create(subject)}>
                            <Plus data-icon="inline-start" />
                            Add syllabus topic
                        </Link>
                    </Button>
                )}
            </div>
            {syllabusTopics.length === 0 ? (
                <EmptyState>
                    <div className="space-y-3">
                        <p className="font-medium text-foreground">
                            No official syllabus topics yet
                        </p>
                        <p>
                            Add the first topic using the source syllabus
                            wording. Topics can be nested to match its outline.
                        </p>
                        <Button asChild className="mt-2">
                            <Link href={subjectTopics.create(subject)}>
                                <Plus data-icon="inline-start" />
                                Add the first topic
                            </Link>
                        </Button>
                    </div>
                </EmptyState>
            ) : (
                <TopicOutline
                    items={syllabusTopics}
                    concepts={subjectConcepts}
                    parentId={null}
                />
            )}
        </section>
    );
}

function TopicOutline({
    items,
    concepts: subjectConcepts,
    parentId,
}: {
    items: SubjectSyllabusTopic[];
    concepts: SubjectConcept[];
    parentId: number | null;
}) {
    const children = items.filter((topic) => topic.parent_id === parentId);
    if (children.length === 0) return null;

    return (
        <ul className="space-y-4">
            {children.map((topic) => {
                const topicConcepts = subjectConcepts.filter(
                    (concept) => concept.syllabus_topic_id === topic.id,
                );
                return (
                    <li
                        key={topic.id}
                        className="space-y-3 rounded-xl border bg-card p-5 shadow-sm"
                    >
                        <Link
                            href={topics.show(topic)}
                            className="block rounded-sm font-semibold hover:text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        >
                            {topic.code && (
                                <span className="mr-2 text-sm text-primary">
                                    {topic.code}
                                </span>
                            )}
                            {topic.title}
                        </Link>
                        {topic.description && (
                            <p className="text-sm text-muted-foreground">
                                {topic.description}
                            </p>
                        )}
                        {topicConcepts.length > 0 ? (
                            <ConceptLinks items={topicConcepts} />
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No concepts linked to this topic yet.
                            </p>
                        )}
                        {items.some(
                            (child) => child.parent_id === topic.id,
                        ) && (
                            <div className="border-l-2 pl-4">
                                <TopicOutline
                                    items={items}
                                    concepts={subjectConcepts}
                                    parentId={topic.id}
                                />
                            </div>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}

export function ConceptLinks({ items }: { items: SubjectConcept[] }) {
    return (
        <ul className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            {items.map((concept) => (
                <li key={concept.id}>
                    <Link
                        href={concepts.show(concept)}
                        className="block rounded-md border p-3 text-sm hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    >
                        <span className="font-medium">{concept.code}</span>
                        <p className="mt-1">{concept.title}</p>
                    </Link>
                </li>
            ))}
        </ul>
    );
}
