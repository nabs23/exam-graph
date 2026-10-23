import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { EmptyState, PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { subjectTopics, subjects, topics } from '@/lib/curriculum-routes';

type Subject = {
    id: number;
    name: string;
    code?: string | null;
};

type Topic = {
    id: number;
    code: string | null;
    title: string;
    children: { id: number }[];
    subject: { id: number; name: string };
};

export default function Topics({
    subject,
    topics: items,
}: {
    subject: Subject | null;
    topics: Topic[];
}) {
    const isSubjectScoped = subject !== null;

    return (
        <PageShell>
            <Head
                title={
                    isSubjectScoped
                        ? `${subject.name} · Syllabus topics`
                        : 'Syllabus topics'
                }
            />
            <PageHeader
                eyebrow={
                    subject
                        ? `${subject.code ? `${subject.code} · ` : ''}${subject.name}`
                        : 'Curriculum administration'
                }
                title="Syllabus topics"
                description={
                    subject
                        ? 'Maintain the official syllabus outline for this subject. Instructional concepts are managed separately.'
                        : 'Browse official syllabus topics across subjects.'
                }
                actions={
                    subject ? (
                        <Button asChild>
                            <Link href={subjectTopics.create(subject)}>
                                <Plus data-icon="inline-start" />
                                New topic
                            </Link>
                        </Button>
                    ) : null
                }
            />

            {items.length === 0 ? (
                <EmptyState>
                    <div className="space-y-3">
                        <p className="font-medium text-foreground">
                            {subject
                                ? 'No official syllabus topics yet'
                                : 'No syllabus topics to show'}
                        </p>
                        <p>
                            {subject
                                ? 'Add the first topic using the source syllabus wording. You can nest topics to match its outline.'
                                : 'Open a subject workspace to add topics from its official syllabus.'}
                        </p>
                        <Button asChild className="mt-2">
                            <Link
                                href={
                                    subject
                                        ? subjectTopics.create(subject)
                                        : subjects.index()
                                }
                            >
                                <Plus data-icon="inline-start" />
                                {subject
                                    ? 'Add the first topic'
                                    : 'Browse subjects'}
                            </Link>
                        </Button>
                    </div>
                </EmptyState>
            ) : (
                <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {items.map((topic) => (
                        <li key={topic.id}>
                            <Link
                                href={topics.show(topic)}
                                className="group block h-full min-w-0 rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            >
                                <Card className="h-full py-0 transition-colors group-hover:border-primary/50 group-hover:bg-accent/50">
                                    <CardContent className="flex h-full flex-col gap-4 p-5">
                                        <div className="space-y-2">
                                            <p className="wrap-break-words text-sm font-semibold tracking-wide text-primary">
                                                {topic.code ?? 'Syllabus topic'}
                                            </p>
                                            <h2 className="wrap-break-words text-lg font-semibold tracking-tight">
                                                {topic.title}
                                            </h2>
                                            {!isSubjectScoped && (
                                                <p className="text-sm text-muted-foreground">
                                                    {topic.subject.name}
                                                </p>
                                            )}
                                        </div>
                                        <span className="mt-auto text-sm font-medium text-primary">
                                            {topic.children.length}{' '}
                                            {topic.children.length === 1
                                                ? 'child topic'
                                                : 'child topics'}
                                            <span aria-hidden="true"> · </span>
                                            Open topic{' '}
                                            <span aria-hidden="true">→</span>
                                        </span>
                                    </CardContent>
                                </Card>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </PageShell>
    );
}

Topics.layout = (props: { subject: Subject | null }) => ({
    breadcrumbs: props.subject
        ? [
              { title: 'Subject', href: subjects.show(props.subject) },
              {
                  title: 'Syllabus topics',
                  href: subjectTopics.index(props.subject),
              },
          ]
        : [{ title: 'Syllabus topics', href: topics.index() }],
});
