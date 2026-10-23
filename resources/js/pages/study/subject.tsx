import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import study from '@/routes/study';
import { PageHeader, PageShell, EmptyState } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type SyllabusTopic = { title: string; code: string | null };
type Concept = {
    id: number;
    code: string;
    title: string;
    description: string | null;
    syllabus_topic: SyllabusTopic | null;
    quizzes: { id: number }[];
};
type Subject = {
    id: number;
    name: string;
    code: string | null;
    program: { name: string; code: string };
    syllabus_topics: { id: number }[];
    concepts: Concept[];
};

export default function StudySubject({ subject }: { subject: Subject }) {
    return (
        <PageShell>
            <Head title={subject.name} />
            <PageHeader
                eyebrow={`${subject.program.code} · ${subject.program.name}`}
                title={subject.name}
                description="Choose an instructional concept to review its objectives, lessons, prerequisites, and quizzes. Official syllabus topics are shown separately from learning concepts."
                actions={(
                    <Button asChild variant="outline">
                        <Link href={study.index()}><ArrowLeft />All subjects</Link>
                    </Button>
                )}
            />

            {subject.concepts.length === 0 ? (
                <EmptyState>
                    <div className="space-y-2">
                        <p className="font-medium text-foreground">No learning concepts are available yet</p>
                        <p>
                            {subject.syllabus_topics.length > 0
                                ? `This subject has ${subject.syllabus_topics.length} official syllabus ${subject.syllabus_topics.length === 1 ? 'topic' : 'topics'}, but no instructional concepts yet.`
                                : 'No official syllabus topics or instructional concepts have been added to this subject yet.'}
                        </p>
                    </div>
                </EmptyState>
            ) : (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {subject.concepts.map((concept) => (
                        <Card key={concept.id} className="group h-full transition-colors hover:border-primary/50">
                            <Link
                                href={study.concepts.show(concept)}
                                className="flex h-full rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset"
                            >
                                <CardContent className="flex h-full w-full flex-col gap-3 pt-6">
                                    <div className="flex items-center justify-between">
                                        <Badge variant="outline">{concept.code}</Badge>
                                        <ArrowRight aria-hidden="true" className="size-4 text-muted-foreground transition-transform group-hover:translate-x-1" />
                                    </div>
                                    <h2 className="text-lg font-semibold">{concept.title}</h2>
                                    {concept.description && <p className="text-sm text-muted-foreground">{concept.description}</p>}
                                    <div className="mt-auto border-t pt-3 text-sm">
                                        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Official syllabus topic</p>
                                        <p className="mt-1">{concept.syllabus_topic
                                            ? `${concept.syllabus_topic.code ? `${concept.syllabus_topic.code} · ` : ''}${concept.syllabus_topic.title}`
                                            : 'No topic assigned'}</p>
                                    </div>
                                    <p className="text-sm font-medium text-primary">{concept.quizzes.length} quiz{concept.quizzes.length === 1 ? '' : 'zes'} available</p>
                                </CardContent>
                            </Link>
                        </Card>
                    ))}
                </div>
            )}
        </PageShell>
    );
}
