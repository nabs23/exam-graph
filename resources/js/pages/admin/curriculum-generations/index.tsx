import { Head, Link } from '@inertiajs/react';
import { EmptyState, PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import programs from '@/routes/programs';
import generations from '@/routes/programs/curriculum-generations';

type Generation = {
    id: number;
    kind: string;
    status: string;
    model: string;
    created_at: string;
    requester: { name: string } | null;
};

export default function CurriculumGenerationsIndex({
    program,
    generations: entries,
    generationEnabled,
    generationUnavailableReason,
}: {
    program: { id: number; name: string; code: string };
    generations: Generation[];
    generationEnabled: boolean;
    generationUnavailableReason: string | null;
}) {
    const createAction = generationEnabled
        ? <Button asChild><Link href={generations.create(program.id)}>New proposal</Link></Button>
        : <Button disabled>New proposal</Button>;

    return (
        <PageShell>
            <Head title={`Curriculum proposals · ${program.name}`} />
            <PageHeader
                eyebrow={`${program.code} · Program workspace`}
                title="Curriculum proposals"
                description="Prepare AI drafts for review. Each item stays a proposal until an authorized reviewer decides whether it becomes canonical."
                actions={(
                    <>
                        <Button variant="outline" asChild><Link href={programs.show(program)}>Back to program</Link></Button>
                        {createAction}
                    </>
                )}
            />

            {!generationEnabled && (
                <p role="status" className="rounded-md border bg-muted/30 p-4 text-sm text-muted-foreground">
                    {generationUnavailableReason ?? 'Curriculum generation is currently unavailable.'}
                </p>
            )}

            <section aria-labelledby="recent-proposals-title" className="space-y-4">
                <div>
                    <h2 id="recent-proposals-title" className="text-xl font-semibold">Recent proposals</h2>
                    <p className="text-sm text-muted-foreground">{entries.length} {entries.length === 1 ? 'proposal' : 'proposals'} · up to 50 shown, pending reviews first</p>
                </div>

                {entries.length === 0 ? (
                    <Card className="py-0">
                        <CardContent className="py-6">
                            <EmptyState>
                                <div className="space-y-3">
                                    <p className="font-medium text-foreground">No proposals yet</p>
                                    <p>Choose a subject or program scope to prepare the first draft. Every proposed item will be reviewed before it becomes curriculum.</p>
                                    {generationEnabled && (
                                        <Button asChild className="mt-2">
                                            <Link href={generations.create(program.id)}>Prepare the first proposal</Link>
                                        </Button>
                                    )}
                                </div>
                            </EmptyState>
                        </CardContent>
                    </Card>
                ) : (
                    <ul className="grid gap-3">
                        {entries.map((entry) => (
                            <li key={entry.id}>
                                <Link
                                    href={generations.show({ program: program.id, curriculumGeneration: entry.id })}
                                    className="group block rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <Card className="py-0 transition-colors group-hover:border-primary/50 group-hover:bg-accent/40">
                                        <CardContent className="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                                            <div className="min-w-0 space-y-1">
                                                <h3 className="font-semibold capitalize">{entry.kind} proposal</h3>
                                                <p className="text-sm text-muted-foreground">
                                                    Requested by {entry.requester?.name ?? 'Unknown'} · {formatDate(entry.created_at)}
                                                </p>
                                            </div>
                                            <div className="flex flex-wrap items-center gap-2 text-sm">
                                                <span className="rounded-full border bg-background px-2.5 py-1 capitalize">{entry.status.replaceAll('_', ' ')}</span>
                                                <span className="text-muted-foreground">{entry.model}</span>
                                                <span className="font-medium text-primary">Review proposal <span aria-hidden="true">→</span></span>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </PageShell>
    );
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

CurriculumGenerationsIndex.layout = (props: { program: { id: number } }) => ({
    breadcrumbs: [
        { title: 'Programs', href: programs.index() },
        { title: 'Program', href: programs.show(props.program) },
        { title: 'Curriculum proposals', href: generations.index(props.program) },
    ],
});
