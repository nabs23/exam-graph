import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import study from '@/routes/study';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Lesson = {
    id: number;
    title: string;
    summary: string | null;
    content: string;
    curriculum_generation_id: number | null;
    concept: {
        id: number;
        title: string;
        syllabus_topic: { title: string; code: string | null } | null;
        subject: { id: number; name: string; program: { name: string; code: string } };
    };
};

export default function StudyLesson({ lesson }: { lesson: Lesson }) {
    return (
        <PageShell>
            <Head title={lesson.title} />
            <PageHeader
                eyebrow={`${lesson.concept.subject.program.code} · ${lesson.concept.subject.name} · ${lesson.concept.title}`}
                title={lesson.title}
                description={lesson.summary}
                actions={(
                    <Button asChild variant="outline">
                        <Link href={study.concepts.show(lesson.concept.id)}><ArrowLeft />Back to concept</Link>
                    </Button>
                )}
            />
            <section aria-label="Official syllabus topic" className="rounded-xl border bg-muted/20 p-4">
                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Official syllabus topic</p>
                <p className="mt-1 font-medium">
                    {lesson.concept.syllabus_topic
                        ? `${lesson.concept.syllabus_topic.code ? `${lesson.concept.syllabus_topic.code} · ` : ''}${lesson.concept.syllabus_topic.title}`
                        : 'No official topic assigned'}
                </p>
            </section>
            {lesson.curriculum_generation_id !== null && (
                <p className="rounded-md border p-3 text-sm text-muted-foreground">
                    Author approved · not source-verified
                </p>
            )}
            <Card>
                <CardContent className="pt-6">
                    <article className="whitespace-pre-wrap text-base leading-8 text-foreground">{lesson.content}</article>
                </CardContent>
            </Card>
        </PageShell>
    );
}
