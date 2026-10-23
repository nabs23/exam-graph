import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { EmptyState, PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    concepts,
    subjectTopics,
    subjects,
    topics,
    topicConcepts,
} from '@/lib/curriculum-routes';
import generations from '@/routes/programs/curriculum-generations';

type TopicConcept = {
    id: number;
    code: string;
    title: string;
    description: string | null;
};

type ChildTopic = {
    id: number;
    code: string | null;
    title: string;
    description: string | null;
};

type Topic = {
    id: number;
    subject_id: number;
    code: string | null;
    title: string;
    description: string | null;
    subject: {
        id: number;
        name: string;
        code: string | null;
        program: { id: number };
    };
    parent: { id: number; title: string } | null;
    children: ChildTopic[];
    concepts: TopicConcept[];
};

export default function TopicShow({
    topic,
    deleteBlockedReason,
}: {
    topic: Topic;
    deleteBlockedReason: string | null;
}) {
    return (
        <PageShell>
            <Head title={topic.title} />
            <PageHeader
                eyebrow={`${topic.subject.code ? `${topic.subject.code} · ` : ''}${topic.subject.name} · Official syllabus`}
                title={topic.title}
                description={
                    topic.description ||
                    'Maintain this official syllabus topic and its child topics. Instructional concepts linked here remain separate from the source outline.'
                }
                actions={
                    <>
                        <Button variant="outline" asChild>
                            <Link href={subjectTopics.index(topic.subject)}>
                                All syllabus topics
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link
                                href={generations.create(
                                    topic.subject.program.id,
                                    {
                                        query: {
                                            kind: 'lessons',
                                            subject_id: topic.subject.id,
                                            scope_type: 'topic',
                                            scope_id: topic.id,
                                        },
                                    },
                                )}
                            >
                                Draft lessons
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={topics.edit(topic)}>Edit topic</Link>
                        </Button>
                    </>
                }
            />

            {topic.parent && (
                <p className="text-sm text-muted-foreground">
                    Parent topic:{' '}
                    <Link
                        href={topics.show(topic.parent)}
                        className="rounded-sm font-medium text-foreground underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    >
                        {topic.parent.title}
                    </Link>
                </p>
            )}

            <section
                aria-labelledby="child-topics-heading"
                className="space-y-4"
            >
                <div>
                    <h2
                        id="child-topics-heading"
                        className="text-xl font-semibold tracking-tight"
                    >
                        Child topics
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Add a topic nested beneath {topic.title} to preserve the
                        syllabus outline.
                    </p>
                </div>

                <Form
                    {...subjectTopics.store.form(topic.subject)}
                    className="grid gap-4 rounded-xl border bg-card p-4 shadow-sm sm:grid-cols-2"
                >
                    {({ errors, processing }) => (
                        <>
                            <p role="status" className="sr-only">
                                {processing ? 'Creating child topic…' : ''}
                            </p>
                            <input
                                type="hidden"
                                name="parent_id"
                                value={topic.id}
                            />
                            <label
                                htmlFor="child-topic-title"
                                className="grid content-start gap-2 text-sm font-medium"
                            >
                                Title
                                <Input
                                    id="child-topic-title"
                                    name="title"
                                    required
                                    disabled={processing}
                                    aria-invalid={Boolean(errors.title)}
                                    aria-describedby={
                                        errors.title
                                            ? 'child-topic-title-error'
                                            : 'child-topic-title-hint'
                                    }
                                />
                                <span
                                    id="child-topic-title-hint"
                                    className="font-normal text-muted-foreground"
                                >
                                    Use the wording from the source syllabus.
                                </span>
                                <InputError
                                    id="child-topic-title-error"
                                    role="alert"
                                    message={errors.title}
                                />
                            </label>
                            <label
                                htmlFor="child-topic-code"
                                className="grid content-start gap-2 text-sm font-medium"
                            >
                                Code{' '}
                                <span className="font-normal text-muted-foreground">
                                    (optional)
                                </span>
                                <Input
                                    id="child-topic-code"
                                    name="code"
                                    disabled={processing}
                                    aria-invalid={Boolean(errors.code)}
                                    aria-describedby={
                                        errors.code
                                            ? 'child-topic-code-error'
                                            : 'child-topic-code-hint'
                                    }
                                />
                                <span
                                    id="child-topic-code-hint"
                                    className="font-normal text-muted-foreground"
                                >
                                    Enter the official code if the syllabus has
                                    one.
                                </span>
                                <InputError
                                    id="child-topic-code-error"
                                    role="alert"
                                    message={errors.code}
                                />
                            </label>
                            <label
                                htmlFor="child-topic-description"
                                className="grid gap-2 text-sm font-medium sm:col-span-2"
                            >
                                Description{' '}
                                <span className="font-normal text-muted-foreground">
                                    (optional)
                                </span>
                                <textarea
                                    id="child-topic-description"
                                    name="description"
                                    disabled={processing}
                                    aria-invalid={Boolean(errors.description)}
                                    aria-describedby={
                                        errors.description
                                            ? 'child-topic-description-error'
                                            : 'child-topic-description-hint'
                                    }
                                    className="min-h-24 w-full rounded-md border border-input bg-background p-3 text-sm outline-none focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-60 aria-invalid:border-destructive"
                                />
                                <span
                                    id="child-topic-description-hint"
                                    className="font-normal text-muted-foreground"
                                >
                                    Add a short note if it helps distinguish
                                    this topic.
                                </span>
                                <InputError
                                    id="child-topic-description-error"
                                    role="alert"
                                    message={errors.description}
                                />
                            </label>
                            <div className="sm:col-span-2">
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Creating…'
                                        : 'Create child topic'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                {topic.children.length === 0 ? (
                    <EmptyState>
                        <p>No child topics have been added yet.</p>
                    </EmptyState>
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {topic.children.map((child) => (
                            <li key={child.id}>
                                <Link
                                    href={topics.show(child)}
                                    className="block h-full rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                >
                                    <Card className="h-full py-0 transition-colors hover:border-primary/50 hover:bg-accent">
                                        <CardContent className="p-4">
                                            <h3 className="font-medium">
                                                {child.code
                                                    ? `${child.code} · `
                                                    : ''}
                                                {child.title}
                                            </h3>
                                            {child.description && (
                                                <p className="mt-2 text-sm text-muted-foreground">
                                                    {child.description}
                                                </p>
                                            )}
                                            <span className="mt-3 block text-sm font-medium text-primary">
                                                Open topic{' '}
                                                <span aria-hidden="true">
                                                    →
                                                </span>
                                            </span>
                                        </CardContent>
                                    </Card>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section
                aria-labelledby="topic-concepts-heading"
                className="space-y-4"
            >
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2
                            id="topic-concepts-heading"
                            className="text-xl font-semibold tracking-tight"
                        >
                            Concepts
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {topic.concepts.length}{' '}
                            {topic.concepts.length === 1
                                ? 'concept linked to this topic'
                                : 'concepts linked to this topic'}
                        </p>
                    </div>
                    {topic.concepts.length > 0 && (
                        <Button asChild className="shrink-0">
                            <Link href={topicConcepts.create(topic)}>
                                Create a concept
                            </Link>
                        </Button>
                    )}
                </div>
                {topic.concepts.length === 0 ? (
                    <EmptyState>
                        <div className="space-y-3">
                            <p className="font-medium text-foreground">
                                No concepts linked to this topic yet
                            </p>
                            <p>
                                Concepts are instructional content that can be
                                linked to this official syllabus topic.
                            </p>
                            <Button asChild className="mt-2">
                                <Link href={topicConcepts.create(topic)}>
                                    Create the first concept
                                </Link>
                            </Button>
                        </div>
                    </EmptyState>
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {topic.concepts.map((concept) => (
                            <li key={concept.id}>
                                <Link
                                    href={concepts.show(concept)}
                                    className="block h-full rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                >
                                    <Card className="h-full py-0 transition-colors hover:border-primary/50 hover:bg-accent">
                                        <CardContent className="p-4">
                                            <h3 className="font-medium">
                                                {concept.code} · {concept.title}
                                            </h3>
                                            {concept.description && (
                                                <p className="mt-2 text-sm text-muted-foreground">
                                                    {concept.description}
                                                </p>
                                            )}
                                            <span className="mt-3 block text-sm font-medium text-primary">
                                                Open concept{' '}
                                                <span aria-hidden="true">
                                                    →
                                                </span>
                                            </span>
                                        </CardContent>
                                    </Card>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section
                aria-labelledby="delete-topic-heading"
                className="space-y-3 rounded-xl border border-destructive/30 p-5"
            >
                <div className="space-y-1">
                    <h2 id="delete-topic-heading" className="font-semibold">
                        Delete syllabus topic
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Deleting permanently removes this topic from the
                        subject’s official outline. This action cannot be
                        undone.
                    </p>
                </div>
                {deleteBlockedReason ? (
                    <p
                        role="status"
                        className="rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground"
                    >
                        {deleteBlockedReason}
                    </p>
                ) : (
                    <Form
                        {...topics.destroy.form(topic)}
                        onBefore={() =>
                            window.confirm(
                                `Permanently delete ${topic.title}? This action cannot be undone.`,
                            )
                        }
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                {processing
                                    ? 'Deleting…'
                                    : 'Delete syllabus topic'}
                            </Button>
                        )}
                    </Form>
                )}
            </section>
        </PageShell>
    );
}

TopicShow.layout = (props: {
    topic: { id: number; subject: { id: number } };
}) => ({
    breadcrumbs: [
        { title: 'Subject', href: subjects.show(props.topic.subject) },
        {
            title: 'Syllabus topics',
            href: subjectTopics.index(props.topic.subject),
        },
        { title: 'Syllabus topic', href: topics.show(props.topic) },
    ],
});
