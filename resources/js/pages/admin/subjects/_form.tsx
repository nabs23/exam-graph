import { Form, Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { EmptyState, PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { programSubjects, subjects } from '@/lib/curriculum-routes';
import programRoutes from '@/routes/programs';

type Program = { id: number; name: string; code: string };
type Subject = {
    id: number;
    program_id: number;
    name: string;
    code: string | null;
    description: string | null;
    sort_order: number;
};

export default function SubjectForm({
    subject,
    programs,
    contextProgram,
    deleteBlockedReason = null,
    returnTab = null,
}: {
    subject?: Subject;
    programs: Program[];
    contextProgram?: Program | null;
    deleteBlockedReason?: string | null;
    returnTab?: string | null;
}) {
    const form = useForm({
        program_id: subject?.program_id ?? contextProgram?.id ?? '',
        name: subject?.name ?? '',
        code: subject?.code ?? '',
        description: subject?.description ?? '',
        sort_order: subject?.sort_order ?? 0,
    });
    const hasPrograms = programs.length > 0;

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (subject) {
            form.put(
                subjects.update(
                    subject,
                    returnTab ? { query: { tab: returnTab } } : undefined,
                ).url,
            );
        } else {
            form.post(
                contextProgram
                    ? programSubjects.store(contextProgram).url
                    : subjects.store().url,
            );
        }
    };

    const returnHref = subject
        ? subjects.show(
              subject,
              returnTab ? { query: { tab: returnTab } } : undefined,
          )
        : contextProgram
          ? programRoutes.show(contextProgram)
          : subjects.index();

    if (!hasPrograms && !subject) {
        return (
            <PageShell>
                <Head title="New subject" />
                <PageHeader
                    eyebrow="Curriculum administration"
                    title="Create a subject"
                    description="Subjects belong to a program, so create a program before adding its subjects."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={subjects.index()}>All subjects</Link>
                        </Button>
                    }
                />
                <EmptyState>
                    <div className="space-y-3">
                        <p className="font-medium text-foreground">
                            Create a program first
                        </p>
                        <p>
                            After saving a program, you can return here or add
                            the first subject from its workspace.
                        </p>
                        <Button asChild>
                            <Link href={programRoutes.create()}>
                                Create a program
                            </Link>
                        </Button>
                    </div>
                </EmptyState>
            </PageShell>
        );
    }

    return (
        <PageShell>
            <Head title={subject ? 'Edit subject' : 'New subject'} />
            <PageHeader
                eyebrow={contextProgram?.name ?? 'Curriculum administration'}
                title={subject ? 'Edit subject' : 'Create a subject'}
                description="Give this subject a clear identity within its program. You can organize its topics, concepts, learning materials, and assessments after saving."
                actions={
                    <Button variant="outline" asChild>
                        <Link href={returnHref}>
                            {subject
                                ? 'Back to subject'
                                : contextProgram
                                  ? 'Back to program'
                                  : 'All subjects'}
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
                                ? subject
                                    ? 'Saving subject changes…'
                                    : 'Creating subject…'
                                : ''}
                        </p>
                        <div className="grid gap-2 lg:col-span-2">
                            <label
                                htmlFor="subject-program"
                                className="text-sm font-medium"
                            >
                                Program{' '}
                                <span className="font-normal text-muted-foreground">
                                    (required)
                                </span>
                            </label>
                            <select
                                id="subject-program"
                                value={form.data.program_id}
                                onChange={(event) =>
                                    form.setData(
                                        'program_id',
                                        event.target.value === ''
                                            ? ''
                                            : Number(event.target.value),
                                    )
                                }
                                disabled={
                                    Boolean(contextProgram) || form.processing
                                }
                                required
                                aria-invalid={Boolean(form.errors.program_id)}
                                aria-describedby={
                                    form.errors.program_id
                                        ? 'subject-program-error'
                                        : 'subject-program-hint'
                                }
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-70 aria-invalid:border-destructive"
                            >
                                <option value="" disabled>
                                    Select a program
                                </option>
                                {programs.map((program) => (
                                    <option key={program.id} value={program.id}>
                                        {program.code} — {program.name}
                                    </option>
                                ))}
                            </select>
                            <p
                                id="subject-program-hint"
                                className="text-sm text-muted-foreground"
                            >
                                {contextProgram
                                    ? `This subject belongs to ${contextProgram.code} — ${contextProgram.name}.`
                                    : 'Choose the program this subject belongs to. If you move it later, reassign or remove its subject-associated source files first.'}
                            </p>
                            <InputError
                                id="subject-program-error"
                                role="alert"
                                message={form.errors.program_id}
                            />
                        </div>

                        <div className="grid gap-2">
                            <label
                                htmlFor="subject-name"
                                className="text-sm font-medium"
                            >
                                Subject name{' '}
                                <span className="font-normal text-muted-foreground">
                                    (required)
                                </span>
                            </label>
                            <Input
                                id="subject-name"
                                autoFocus
                                maxLength={255}
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                disabled={form.processing}
                                aria-invalid={Boolean(form.errors.name)}
                                aria-describedby={
                                    form.errors.name
                                        ? 'subject-name-error'
                                        : 'subject-name-hint'
                                }
                                required
                            />
                            <p
                                id="subject-name-hint"
                                className="text-sm text-muted-foreground"
                            >
                                Use the name learners and administrators will
                                recognize. Up to 255 characters.
                            </p>
                            <InputError
                                id="subject-name-error"
                                role="alert"
                                message={form.errors.name}
                            />
                        </div>

                        <div className="grid content-start gap-2">
                            <label
                                htmlFor="subject-code"
                                className="text-sm font-medium"
                            >
                                Subject code{' '}
                                <span className="font-normal text-muted-foreground">
                                    (optional)
                                </span>
                            </label>
                            <Input
                                id="subject-code"
                                maxLength={50}
                                value={form.data.code}
                                onChange={(event) =>
                                    form.setData('code', event.target.value)
                                }
                                disabled={form.processing}
                                aria-invalid={Boolean(form.errors.code)}
                                aria-describedby={
                                    form.errors.code
                                        ? 'subject-code-error'
                                        : 'subject-code-hint'
                                }
                            />
                            <p
                                id="subject-code-hint"
                                className="text-sm text-muted-foreground"
                            >
                                A short identifier used in the official
                                curriculum, if available. Up to 50 characters.
                            </p>
                            <InputError
                                id="subject-code-error"
                                role="alert"
                                message={form.errors.code}
                            />
                        </div>

                        <div className="grid content-start gap-2">
                            <label
                                htmlFor="subject-sort-order"
                                className="text-sm font-medium"
                            >
                                Display order
                            </label>
                            <Input
                                id="subject-sort-order"
                                type="number"
                                min={0}
                                step={1}
                                value={form.data.sort_order}
                                onChange={(event) =>
                                    form.setData(
                                        'sort_order',
                                        Number(event.target.value),
                                    )
                                }
                                disabled={form.processing}
                                aria-invalid={Boolean(form.errors.sort_order)}
                                aria-describedby={
                                    form.errors.sort_order
                                        ? 'subject-sort-order-error'
                                        : 'subject-sort-order-hint'
                                }
                            />
                            <p
                                id="subject-sort-order-hint"
                                className="text-sm text-muted-foreground"
                            >
                                Lower numbers appear first in the program’s
                                subject list. Subjects start at 0.
                            </p>
                            <InputError
                                id="subject-sort-order-error"
                                role="alert"
                                message={form.errors.sort_order}
                            />
                        </div>

                        <div className="grid gap-2 lg:col-span-2">
                            <label
                                htmlFor="subject-description"
                                className="text-sm font-medium"
                            >
                                Description{' '}
                                <span className="font-normal text-muted-foreground">
                                    (optional)
                                </span>
                            </label>
                            <textarea
                                id="subject-description"
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                disabled={form.processing}
                                aria-invalid={Boolean(form.errors.description)}
                                aria-describedby={
                                    form.errors.description
                                        ? 'subject-description-error'
                                        : 'subject-description-hint'
                                }
                                className="min-h-32 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive"
                            />
                            <p
                                id="subject-description-hint"
                                className="text-sm text-muted-foreground"
                            >
                                Summarize the subject’s scope or intended
                                outcomes.
                            </p>
                            <InputError
                                id="subject-description-error"
                                role="alert"
                                message={form.errors.description}
                            />
                        </div>

                        <div className="flex flex-col gap-3 border-t pt-5 sm:flex-row sm:justify-end lg:col-span-2">
                            <Button variant="outline" asChild>
                                <Link href={returnHref}>Cancel</Link>
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    !hasPrograms ||
                                    !form.data.program_id
                                }
                            >
                                {form.processing
                                    ? 'Saving…'
                                    : subject
                                      ? 'Save changes'
                                      : 'Create subject'}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            {subject && (
                <section
                    aria-labelledby="delete-subject-heading"
                    className="space-y-3 rounded-xl border border-destructive/30 p-5"
                >
                    <div className="space-y-1">
                        <h2
                            id="delete-subject-heading"
                            className="font-semibold"
                        >
                            Delete subject
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Deleting removes this subject. Its topics, concepts,
                            source files, and topic extraction history must be
                            removed or reassigned first.
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
                            {...subjects.destroy.form(
                                subject,
                                returnTab
                                    ? { query: { tab: returnTab } }
                                    : undefined,
                            )}
                            onBefore={() =>
                                window.confirm(
                                    `Permanently delete ${subject.name}? This action cannot be undone.`,
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
                                        : 'Delete subject'}
                                </Button>
                            )}
                        </Form>
                    )}
                </section>
            )}
        </PageShell>
    );
}
