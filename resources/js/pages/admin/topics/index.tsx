import { Head, Link } from '@inertiajs/react';
import { subjectTopics } from '@/lib/curriculum-routes';
import { topics } from '@/lib/curriculum-routes';
import { subjects } from '@/lib/curriculum-routes';

type Topic = {
    id: number;
    code: string | null;
    title: string;
    children: Topic[];
    subject: { id: number; name: string };
};

export default function Topics({
    subject,
    topics: items,
}: {
    subject: { id: number; name: string } | null;
    topics: Topic[];
}) {
    const isSubjectScoped = subject !== null;

    return (
        <main className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Head title={isSubjectScoped ? `${subject.name} topics` : 'Topics'} />
            <div className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm font-medium uppercase tracking-wide text-primary">
                        {isSubjectScoped
                            ? subject.name
                            : 'Curriculum administration'}
                    </p>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Syllabus topics
                    </h1>
                </div>
                {isSubjectScoped && (
                    <Link
                        href={subjectTopics.create(subject)}
                        className="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-xs hover:bg-primary/90"
                    >
                        New topic
                    </Link>
                )}
            </div>
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {items.map((topic) => (
                    <Link
                        key={topic.id}
                        href={topics.show(topic)}
                        className="rounded-xl border bg-card p-5 shadow-sm transition-colors hover:border-primary/50 hover:bg-accent"
                    >
                        {topic.code && (
                            <p className="text-sm font-medium text-primary">
                                {topic.code}
                            </p>
                        )}
                        <p className="mt-1 text-lg font-semibold">
                            {topic.title}
                        </p>
                        {!isSubjectScoped && (
                            <p className="mt-1 text-sm text-muted-foreground">
                                {topic.subject.name}
                            </p>
                        )}
                        <span className="mt-2 block text-sm text-muted-foreground">
                            {topic.children.length} child topics
                        </span>
                    </Link>
                ))}
            </div>
        </main>
    );
}

Topics.layout = (props: { subject: { id: number; name: string } | null }) => ({
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
