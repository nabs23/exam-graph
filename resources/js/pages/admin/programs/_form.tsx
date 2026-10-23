import { Form, Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import InputError from '@/components/input-error';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import programs from '@/routes/programs';

type Program = {
    id: number;
    name: string;
    code: string;
    description: string | null;
};

export default function ProgramForm({
    program,
    deleteBlockedReason = null,
    returnTab = null,
}: {
    program?: Program;
    deleteBlockedReason?: string | null;
    returnTab?: string | null;
}) {
    const returnToProgram = program
        ? programs.show(
              program,
              returnTab ? { query: { tab: returnTab } } : undefined,
          )
        : programs.index();
    const form = useForm({
        name: program?.name ?? '',
        code: program?.code ?? '',
        description: program?.description ?? '',
    });

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (program) {
            form.put(
                programs.update(
                    program,
                    returnTab ? { query: { tab: returnTab } } : undefined,
                ).url,
            );
        } else {
            form.post(programs.store().url);
        }
    };

    return (
        <PageShell>
            <Head title={program ? 'Edit program' : 'New program'} />
            <PageHeader
                eyebrow="Curriculum administration"
                title={program ? 'Edit program' : 'Create a program'}
                description="Set the program identity administrators and learners will use to find its curriculum. You can add subjects and source materials after saving."
                actions={
                    <Button variant="outline" asChild>
                        <Link href={returnToProgram}>
                            <ArrowLeft data-icon="inline-start" />
                            {program ? 'Back to program' : 'All programs'}
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
                                ? `Saving ${program ? 'program changes' : 'program'}…`
                                : ''}
                        </p>
                        <div className="grid gap-2">
                            <label
                                htmlFor="program-name"
                                className="text-sm font-medium"
                            >
                                Program name{' '}
                                <span className="font-normal text-muted-foreground">
                                    (required)
                                </span>
                            </label>
                            <Input
                                id="program-name"
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
                                        ? 'program-name-error'
                                        : 'program-name-hint'
                                }
                                required
                            />
                            <p
                                id="program-name-hint"
                                className="text-sm text-muted-foreground"
                            >
                                Use the name people recognize, such as the
                                degree or review program. Up to 255 characters.
                            </p>
                            <InputError
                                id="program-name-error"
                                role="alert"
                                message={form.errors.name}
                            />
                        </div>

                        <div className="grid content-start gap-2">
                            <label
                                htmlFor="program-code"
                                className="text-sm font-medium"
                            >
                                Program code{' '}
                                <span className="font-normal text-muted-foreground">
                                    (required)
                                </span>
                            </label>
                            <Input
                                id="program-code"
                                maxLength={50}
                                value={form.data.code}
                                onChange={(event) =>
                                    form.setData('code', event.target.value)
                                }
                                disabled={form.processing}
                                aria-invalid={Boolean(form.errors.code)}
                                aria-describedby={
                                    form.errors.code
                                        ? 'program-code-error'
                                        : 'program-code-hint'
                                }
                                required
                            />
                            <p
                                id="program-code-hint"
                                className="text-sm text-muted-foreground"
                            >
                                A short, unique identifier shown alongside the
                                program name. Up to 50 characters.
                            </p>
                            <InputError
                                id="program-code-error"
                                role="alert"
                                message={form.errors.code}
                            />
                        </div>

                        <div className="grid gap-2 lg:col-span-2">
                            <label
                                htmlFor="program-description"
                                className="text-sm font-medium"
                            >
                                Description{' '}
                                <span className="font-normal text-muted-foreground">
                                    (optional)
                                </span>
                            </label>
                            <textarea
                                id="program-description"
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
                                        ? 'program-description-error'
                                        : 'program-description-hint'
                                }
                                className="min-h-32 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive"
                            />
                            <p
                                id="program-description-hint"
                                className="text-sm text-muted-foreground"
                            >
                                Briefly explain who this program is for or what
                                it covers.
                            </p>
                            <InputError
                                id="program-description-error"
                                role="alert"
                                message={form.errors.description}
                            />
                        </div>

                        <div className="flex flex-col gap-3 border-t pt-5 sm:flex-row sm:justify-end lg:col-span-2">
                            <Button variant="outline" asChild>
                                <Link href={returnToProgram}>Cancel</Link>
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? 'Saving…'
                                    : program
                                      ? 'Save changes'
                                      : 'Create program'}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            {program && (
                <section
                    aria-labelledby="delete-program-heading"
                    className="space-y-3 rounded-xl border border-destructive/30 p-5"
                >
                    <div className="space-y-1">
                        <h2
                            id="delete-program-heading"
                            className="font-semibold"
                        >
                            Delete program
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Deleting permanently removes this program and its
                            official curriculum extraction history. Remove its
                            subjects and source files first; programs with
                            retained AI proposal history cannot be deleted. This
                            action cannot be undone.
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
                            {...programs.destroy.form(
                                program,
                                returnTab
                                    ? { query: { tab: returnTab } }
                                    : undefined,
                            )}
                            onBefore={() =>
                                window.confirm(
                                    `Permanently delete ${program.name} and its official curriculum extraction history?`,
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
                                        : 'Delete program'}
                                </Button>
                            )}
                        </Form>
                    )}
                </section>
            )}
        </PageShell>
    );
}
