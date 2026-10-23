import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { subjectTopics, topics } from '@/lib/curriculum-routes';
import { subjects } from '@/lib/curriculum-routes';

type Parent = {
    id: number;
    parent_id: number | null;
    code: string | null;
    title: string;
};
type Subject = { id: number; name: string; code?: string | null };
type Topic = {
    id: number;
    subject_id: number;
    parent_id: number | null;
    code: string | null;
    title: string;
    description: string | null;
    sort_order: number;
};

function parentPath(parents: Parent[], parentId: number): string {
    const path: Parent[] = [];
    const visited = new Set<number>();
    let current = parents.find((parent) => parent.id === parentId);

    while (current && !visited.has(current.id)) {
        visited.add(current.id);
        path.unshift(current);
        current =
            current.parent_id === null
                ? undefined
                : parents.find((parent) => parent.id === current?.parent_id);
    }

    return path
        .map((parent) =>
            parent.code ? `${parent.code} · ${parent.title}` : parent.title,
        )
        .join(' / ');
}

export default function TopicForm({
    subject,
    topic,
    parents,
}: {
    subject: Subject;
    topic?: Topic;
    parents: Parent[];
}) {
    const form = useForm({
        parent_id: topic?.parent_id ?? (null as number | null),
        code: topic?.code ?? '',
        title: topic?.title ?? '',
        description: topic?.description ?? '',
        sort_order: topic?.sort_order ?? 0,
    });

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (topic) {
            form.put(topics.update(topic).url);
        } else {
            form.post(subjectTopics.store(subject).url);
        }
    };

    const returnHref = topic ? topics.show(topic) : subjects.show(subject);

    return (
        <PageShell>
            <Head
                title={topic ? 'Edit syllabus topic' : 'New syllabus topic'}
            />
            <PageHeader
                eyebrow={`${subject.code ? `${subject.code} · ` : ''}${subject.name} · Official syllabus`}
                title={
                    topic ? 'Edit syllabus topic' : 'Create a syllabus topic'
                }
                description="Maintain the official syllabus outline here. Instructional concepts are authored separately and can be linked to these topics after review."
                actions={
                    <Button variant="outline" asChild>
                        <Link href={returnHref}>
                            {topic ? 'Back to topic' : 'Back to subject'}
                        </Link>
                    </Button>
                }
            />

            <Card className="py-0">
                <CardContent className="py-6">
                    <form
                        onSubmit={submit}
                        className="grid gap-6 lg:grid-cols-2"
                    >
                        <p role="status" className="sr-only">
                            {form.processing
                                ? topic
                                    ? 'Saving syllabus topic changes…'
                                    : 'Creating syllabus topic…'
                                : ''}
                        </p>
                        <div className="grid gap-2 lg:col-span-2">
                            <label
                                htmlFor="syllabus-topic-parent"
                                className="text-sm font-medium"
                            >
                                Parent topic
                            </label>
                            <select
                                id="syllabus-topic-parent"
                                value={form.data.parent_id ?? ''}
                                disabled={form.processing}
                                onChange={(event) =>
                                    form.setData(
                                        'parent_id',
                                        event.target.value
                                            ? Number(event.target.value)
                                            : null,
                                    )
                                }
                                aria-invalid={Boolean(form.errors.parent_id)}
                                aria-describedby={
                                    form.errors.parent_id
                                        ? 'syllabus-topic-parent-error'
                                        : 'syllabus-topic-parent-hint'
                                }
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive"
                            >
                                <option value="">Top-level topic</option>
                                {parents.map((parent) => (
                                    <option key={parent.id} value={parent.id}>
                                        {parentPath(parents, parent.id)}
                                    </option>
                                ))}
                            </select>
                            <p
                                id="syllabus-topic-parent-hint"
                                className="text-sm text-muted-foreground"
                            >
                                Choose a parent to nest this topic, or leave it
                                at the top level. Parent topics must belong to
                                this subject.
                            </p>
                            <InputError
                                id="syllabus-topic-parent-error"
                                role="alert"
                                message={form.errors.parent_id}
                            />
                        </div>

                        <div className="grid content-start gap-2">
                            <label
                                htmlFor="syllabus-topic-code"
                                className="text-sm font-medium"
                            >
                                Official code{' '}
                                <span className="font-normal text-muted-foreground">
                                    (optional)
                                </span>
                            </label>
                            <Input
                                id="syllabus-topic-code"
                                value={form.data.code}
                                disabled={form.processing}
                                onChange={(event) =>
                                    form.setData('code', event.target.value)
                                }
                                aria-invalid={Boolean(form.errors.code)}
                                aria-describedby={
                                    form.errors.code
                                        ? 'syllabus-topic-code-error'
                                        : 'syllabus-topic-code-hint'
                                }
                            />
                            <p
                                id="syllabus-topic-code-hint"
                                className="text-sm text-muted-foreground"
                            >
                                Use the code from the source syllabus when it
                                provides one.
                            </p>
                            <InputError
                                id="syllabus-topic-code-error"
                                role="alert"
                                message={form.errors.code}
                            />
                        </div>

                        <div className="grid gap-2">
                            <label
                                htmlFor="syllabus-topic-title"
                                className="text-sm font-medium"
                            >
                                Topic title
                            </label>
                            <Input
                                id="syllabus-topic-title"
                                autoFocus
                                value={form.data.title}
                                disabled={form.processing}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                                aria-invalid={Boolean(form.errors.title)}
                                aria-describedby={
                                    form.errors.title
                                        ? 'syllabus-topic-title-error'
                                        : 'syllabus-topic-title-hint'
                                }
                                required
                            />
                            <p
                                id="syllabus-topic-title-hint"
                                className="text-sm text-muted-foreground"
                            >
                                Keep the official topic wording recognizable to
                                reviewers.
                            </p>
                            <InputError
                                id="syllabus-topic-title-error"
                                role="alert"
                                message={form.errors.title}
                            />
                        </div>

                        <div className="grid content-start gap-2">
                            <label
                                htmlFor="syllabus-topic-sort-order"
                                className="text-sm font-medium"
                            >
                                Display order
                            </label>
                            <Input
                                id="syllabus-topic-sort-order"
                                type="number"
                                min={0}
                                step={1}
                                value={form.data.sort_order}
                                disabled={form.processing}
                                onChange={(event) =>
                                    form.setData(
                                        'sort_order',
                                        Number(event.target.value),
                                    )
                                }
                                aria-invalid={Boolean(form.errors.sort_order)}
                                aria-describedby={
                                    form.errors.sort_order
                                        ? 'syllabus-topic-sort-order-error'
                                        : 'syllabus-topic-sort-order-hint'
                                }
                            />
                            <p
                                id="syllabus-topic-sort-order-hint"
                                className="text-sm text-muted-foreground"
                            >
                                Lower numbers appear first among topics with the
                                same parent.
                            </p>
                            <InputError
                                id="syllabus-topic-sort-order-error"
                                role="alert"
                                message={form.errors.sort_order}
                            />
                        </div>

                        <div className="grid gap-2 lg:col-span-2">
                            <label
                                htmlFor="syllabus-topic-description"
                                className="text-sm font-medium"
                            >
                                Description{' '}
                                <span className="font-normal text-muted-foreground">
                                    (optional)
                                </span>
                            </label>
                            <textarea
                                id="syllabus-topic-description"
                                value={form.data.description}
                                disabled={form.processing}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(form.errors.description)}
                                aria-describedby={
                                    form.errors.description
                                        ? 'syllabus-topic-description-error'
                                        : 'syllabus-topic-description-hint'
                                }
                                className="min-h-32 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive"
                            />
                            <p
                                id="syllabus-topic-description-hint"
                                className="text-sm text-muted-foreground"
                            >
                                Add a short note only when it helps distinguish
                                this topic in the outline.
                            </p>
                            <InputError
                                id="syllabus-topic-description-error"
                                role="alert"
                                message={form.errors.description}
                            />
                        </div>

                        <div className="flex flex-col-reverse gap-3 border-t pt-5 sm:flex-row sm:justify-end lg:col-span-2">
                            <Button variant="outline" asChild>
                                <Link href={returnHref}>Cancel</Link>
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? 'Saving…'
                                    : topic
                                      ? 'Save changes'
                                      : 'Create syllabus topic'}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </PageShell>
    );
}
