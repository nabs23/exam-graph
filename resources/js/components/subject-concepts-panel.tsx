import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { EmptyState } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { subjectConcepts } from '@/lib/curriculum-routes';
import {
    ConceptLinks,
    type SubjectConcept,
    type SubjectRouteParams,
    type SubjectSyllabusTopic,
} from '@/components/subject-syllabus-topics-panel';

export function SubjectConceptsPanel({
    subject,
    concepts,
    topics,
}: {
    subject: SubjectRouteParams;
    concepts: SubjectConcept[];
    topics: SubjectSyllabusTopic[];
}) {
    const unassignedConcepts = concepts.filter(
        (concept) => concept.syllabus_topic_id === null,
    );

    return (
        <section aria-labelledby="subject-concepts-title" className="space-y-4">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2
                        id="subject-concepts-title"
                        className="text-xl font-semibold"
                    >
                        Concepts
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {concepts.length}{' '}
                        {concepts.length === 1 ? 'concept' : 'concepts'}
                    </p>
                </div>
                {concepts.length > 0 && (
                    <Button asChild className="shrink-0">
                        <Link href={subjectConcepts.create(subject)}>
                            <Plus data-icon="inline-start" />
                            Create a concept
                        </Link>
                    </Button>
                )}
            </div>
            {concepts.length === 0 ? (
                <EmptyState>
                    <div className="space-y-3">
                        <p className="font-medium text-foreground">
                            No concepts in this subject yet
                        </p>
                        <p>
                            Create an instructional concept to organize lessons
                            and quizzes. Concepts stay separate from the
                            official syllabus topics.
                        </p>
                        <Button asChild className="mt-2">
                            <Link href={subjectConcepts.create(subject)}>
                                <Plus data-icon="inline-start" />
                                Create the first concept
                            </Link>
                        </Button>
                    </div>
                </EmptyState>
            ) : (
                <div className="space-y-4">
                    {unassignedConcepts.length > 0 && (
                        <div className="space-y-3">
                            <div>
                                <h3 className="font-medium">
                                    Unassigned concepts
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    These concepts have not been linked to a
                                    syllabus topic.
                                </p>
                            </div>
                            <ConceptLinks items={unassignedConcepts} />
                        </div>
                    )}
                    {topics.map((topic) => {
                        const topicConcepts = concepts.filter(
                            (concept) => concept.syllabus_topic_id === topic.id,
                        );

                        return topicConcepts.length > 0 ? (
                            <div key={topic.id} className="space-y-3">
                                <h3 className="font-medium">
                                    {topic.code ? `${topic.code} · ` : ''}
                                    {topic.title}
                                </h3>
                                <ConceptLinks items={topicConcepts} />
                            </div>
                        ) : null;
                    })}
                </div>
            )}
        </section>
    );
}
