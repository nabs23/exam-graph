import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { EmptyState, PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { programSubjects, subjects } from '@/lib/curriculum-routes';
import programs from '@/routes/programs';

type Program = { id: number; name: string; code: string };
type Subject = {
    id: number;
    name: string;
    code: string | null;
    program: Program;
};
type SubjectGroup = { program: Program; subjects: Subject[] };

function groupSubjectsByProgram(items: Subject[]): SubjectGroup[] {
    const groups = new Map<number, SubjectGroup>();

    items.forEach((subject) => {
        const group = groups.get(subject.program.id);

        if (group) {
            group.subjects.push(subject);
        } else {
            groups.set(subject.program.id, {
                program: subject.program,
                subjects: [subject],
            });
        }
    });

    return [...groups.values()];
}

export default function Subjects({
    subjects: items,
    contextProgram,
    programs: availablePrograms,
}: {
    subjects: Subject[];
    contextProgram?: Program | null;
    programs: Program[];
}) {
    const createHref = contextProgram
        ? programSubjects.create(contextProgram)
        : subjects.create();
    const subjectGroups = groupSubjectsByProgram(items);

    return (
        <PageShell>
            <Head title="Subjects" />
            <PageHeader
                eyebrow={
                    contextProgram
                        ? `${contextProgram.code} · Program workspace`
                        : 'Curriculum administration'
                }
                title="Subjects"
                description={
                    contextProgram
                        ? `Organize the topics, learning materials, and assessments for ${contextProgram.name}.`
                        : 'Organize subjects within each review program.'
                }
                actions={
                    <Button asChild>
                        <Link
                            href={
                                availablePrograms.length === 0
                                    ? programs.create()
                                    : createHref
                            }
                        >
                            <Plus data-icon="inline-start" />
                            {availablePrograms.length === 0
                                ? 'Create a program first'
                                : 'New subject'}
                        </Link>
                    </Button>
                }
            />

            {items.length === 0 ? (
                <Card className="py-0">
                    <CardContent className="py-8 sm:py-12">
                        <EmptyState>
                            <div className="space-y-3">
                                <p className="font-medium text-foreground">
                                    {contextProgram
                                        ? `No subjects in ${contextProgram.name} yet`
                                        : availablePrograms.length === 0
                                          ? 'Create a program first'
                                          : 'No subjects yet'}
                                </p>
                                <p>
                                    {contextProgram
                                        ? 'Add the first subject to give this program a place for its official topics and learning content.'
                                        : availablePrograms.length === 0
                                          ? 'Subjects belong to a program. Create one before adding its subjects.'
                                          : 'Create a subject inside a program to start organizing its curriculum.'}
                                </p>
                                {contextProgram ||
                                availablePrograms.length > 0 ? (
                                    <Button asChild className="mt-2">
                                        <Link href={createHref}>
                                            <Plus data-icon="inline-start" />
                                            {contextProgram
                                                ? 'Create the first subject'
                                                : 'Create a subject'}
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button asChild className="mt-2">
                                        <Link href={programs.create()}>
                                            Create a program
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </EmptyState>
                    </CardContent>
                </Card>
            ) : (
                <div className="space-y-8">
                    {subjectGroups.map((group) => (
                        <section
                            key={group.program.id}
                            aria-labelledby={`program-subjects-${group.program.id}`}
                            className="space-y-4"
                        >
                            <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                                <h2
                                    id={`program-subjects-${group.program.id}`}
                                    className="wrap-break-words min-w-0 text-xl font-semibold tracking-tight"
                                >
                                    {contextProgram
                                        ? 'Subjects'
                                        : `${group.program.code} · ${group.program.name}`}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {group.subjects.length}{' '}
                                    {group.subjects.length === 1
                                        ? 'subject'
                                        : 'subjects'}
                                </p>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                {group.subjects.map((subject) => (
                                    <Link
                                        key={subject.id}
                                        href={subjects.show(subject)}
                                        className="group min-w-0 rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                    >
                                        <Card className="h-full py-0 transition-colors group-hover:border-primary/50 group-hover:bg-accent/50">
                                            <CardContent className="flex h-full flex-col gap-4 p-5">
                                                <div className="space-y-2">
                                                    <p className="wrap-break-words text-sm font-semibold tracking-wide text-primary">
                                                        {subject.code ??
                                                            'Subject'}
                                                    </p>
                                                    <h3 className="wrap-break-words text-lg font-semibold tracking-tight">
                                                        {subject.name}
                                                    </h3>
                                                </div>
                                                <span className="mt-auto text-sm font-medium text-primary">
                                                    Open subject{' '}
                                                    <span aria-hidden="true">
                                                        →
                                                    </span>
                                                </span>
                                            </CardContent>
                                        </Card>
                                    </Link>
                                ))}
                            </div>
                        </section>
                    ))}
                </div>
            )}
        </PageShell>
    );
}

Subjects.layout = (props: { contextProgram?: Program | null }) => ({
    breadcrumbs: [
        ...(props.contextProgram
            ? [{ title: 'Program', href: programs.show(props.contextProgram) }]
            : []),
        {
            title: 'Subjects',
            href: props.contextProgram
                ? programSubjects.index(props.contextProgram)
                : subjects.index(),
        },
    ],
});
