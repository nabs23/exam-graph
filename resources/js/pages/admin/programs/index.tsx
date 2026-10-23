import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { EmptyState, PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import programs from '@/routes/programs';

type ProgramSummary = {
    id: number;
    name: string;
    code: string;
    subjects_count: number;
};

export default function Programs({
    programs: items,
}: {
    programs: ProgramSummary[];
}) {
    return (
        <PageShell>
            <Head title="Programs" />
            <PageHeader
                eyebrow="Curriculum administration"
                title="Programs"
                description="Create and organize review programs. Each program is the starting point for its subjects, source files, and curriculum proposals."
                actions={
                    <Button asChild>
                        <Link href={programs.create()}>
                            <Plus data-icon="inline-start" />
                            New program
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
                                    No programs yet
                                </p>
                                <p>
                                    Start by creating a program. You can add
                                    subjects and source materials after it is
                                    saved.
                                </p>
                                <Button asChild className="mt-2">
                                    <Link href={programs.create()}>
                                        <Plus data-icon="inline-start" />
                                        Create your first program
                                    </Link>
                                </Button>
                            </div>
                        </EmptyState>
                    </CardContent>
                </Card>
            ) : (
                <section
                    aria-label="Programs"
                    className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
                >
                    {items.map((program) => (
                        <Link
                            key={program.id}
                            href={programs.show(program)}
                            className="group min-w-0 rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        >
                            <Card className="h-full py-0 transition-colors group-hover:border-primary/50 group-hover:bg-accent/50">
                                <CardContent className="flex h-full flex-col gap-4 p-5">
                                    <div className="flex items-start justify-between gap-3">
                                        <span className="wrap-break-words min-w-0 text-sm font-semibold tracking-wide text-primary">
                                            {program.code}
                                        </span>
                                        <span className="shrink-0 rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
                                            {program.subjects_count}{' '}
                                            {program.subjects_count === 1
                                                ? 'subject'
                                                : 'subjects'}
                                        </span>
                                    </div>
                                    <div className="space-y-1">
                                        <h2 className="wrap-break-words text-lg font-semibold tracking-tight">
                                            {program.name}
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            Open program workspace
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        </Link>
                    ))}
                </section>
            )}
        </PageShell>
    );
}

Programs.layout = {
    breadcrumbs: [
        {
            title: 'Program',
            href: programs.index(),
        },
    ],
};
