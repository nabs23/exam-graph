import { Link } from '@inertiajs/react';
import { subjectConcepts } from '@/lib/curriculum-routes';
import { ConceptLinks, type SubjectConcept, type SubjectRouteParams, type SubjectSyllabusTopic } from '@/components/subject-syllabus-topics-panel';

export function SubjectConceptsPanel({
    subject,
    concepts,
    topics,
}: {
    subject: SubjectRouteParams;
    concepts: SubjectConcept[];
    topics: SubjectSyllabusTopic[];
}) {
    const unassignedConcepts = concepts.filter((concept) => concept.syllabus_topic_id === null);

    return (
        <section className="space-y-4">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <h2 className="text-xl font-semibold">Concepts</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {concepts.length} {concepts.length === 1 ? 'concept' : 'concepts'}
                    </p>
                </div>
                <Link href={subjectConcepts.create(subject)} className="text-sm font-medium text-primary hover:underline">Create a concept</Link>
            </div>
            {concepts.length === 0 ? (
                <p className="rounded-xl border border-dashed p-6 text-sm text-muted-foreground">No concepts have been added to this subject yet.</p>
            ) : (
                <div className="space-y-4">
                    {unassignedConcepts.length > 0 && (
                        <div className="space-y-3">
                            <div>
                                <h3 className="font-medium">Unassigned concepts</h3>
                                <p className="text-sm text-muted-foreground">These concepts have not been linked to a syllabus topic.</p>
                            </div>
                            <ConceptLinks items={unassignedConcepts} />
                        </div>
                    )}
                    {topics.map((topic) => {
                        const topicConcepts = concepts.filter((concept) => concept.syllabus_topic_id === topic.id);

                        return topicConcepts.length > 0 ? (
                            <div key={topic.id} className="space-y-3">
                                <h3 className="font-medium">{topic.code ? `${topic.code} · ` : ''}{topic.title}</h3>
                                <ConceptLinks items={topicConcepts} />
                            </div>
                        ) : null;
                    })}
                </div>
            )}
        </section>
    );
}
