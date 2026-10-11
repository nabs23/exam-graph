import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import programs from '@/routes/programs';
import generations from '@/routes/programs/curriculum-generations';

type Generation = { id: number; kind: string; status: string; model: string; created_at: string; requester: { name: string } | null };

export default function CurriculumGenerationsIndex({ program, generations: entries, generationEnabled, generationUnavailableReason }: {
    program: { id: number; name: string; code: string };
    generations: Generation[];
    generationEnabled: boolean;
    generationUnavailableReason: string | null;
}) {
    return (
        <main className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Head title={`Curriculum proposals · ${program.name}`} />
            <header className="flex flex-wrap items-start justify-between gap-4 border-b pb-6">
                <div className="space-y-2"><p className="text-sm font-medium uppercase tracking-wide text-primary">{program.code}</p><h1 className="text-3xl font-semibold tracking-tight">Curriculum proposals</h1><p className="text-muted-foreground">Generate and review subject, topic, or concept proposals for {program.name}.</p></div>
                <Button asChild disabled={!generationEnabled}><Link href={generations.create(program.id)}>New proposal</Link></Button>
            </header>
            {!generationEnabled && <p role="status" className="rounded-md border p-4 text-sm text-muted-foreground">{generationUnavailableReason ?? 'Curriculum generation is currently unavailable.'}</p>}
            <section className="space-y-3">
                <h2 className="text-xl font-semibold">Recent proposals</h2>
                {entries.length === 0 ? <p className="rounded-lg border p-6 text-sm text-muted-foreground">No curriculum proposals yet.</p> : entries.map((entry) => (
                    <Link key={entry.id} href={generations.show({ program: program.id, curriculumGeneration: entry.id })} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4 hover:bg-accent/40">
                        <div><p className="font-medium capitalize">{entry.kind} · {entry.status}</p><p className="text-sm text-muted-foreground">{entry.requester?.name ?? 'Unknown reviewer'} · {new Date(entry.created_at).toLocaleString()}</p></div>
                        <span className="text-sm text-muted-foreground">{entry.model}</span>
                    </Link>
                ))}
            </section>
        </main>
    );
}

CurriculumGenerationsIndex.layout = (props: { program: { id: number } }) => ({ breadcrumbs: [
    { title: 'Programs', href: programs.index() },
    { title: 'Program', href: programs.show(props.program) },
    { title: 'Curriculum proposals', href: generations.index(props.program) },
] });
