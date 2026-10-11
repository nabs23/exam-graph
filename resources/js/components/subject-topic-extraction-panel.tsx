import { Link } from '@inertiajs/react';
import { index } from '@/actions/App/Http/Controllers/SubjectTopicExtractionController';
import { Button } from '@/components/ui/button';

type RouteSubject = { id: number };
type RouteProgram = { id: number };

export function SubjectTopicExtractionPanel({
    program,
    subject,
    summary,
    enabled,
    unavailableReason,
}: {
    program: RouteProgram;
    subject: RouteSubject;
    summary: { count: number; latest: { id: number; status: string } | null };
    enabled: boolean;
    unavailableReason: string | null;
}) {
    return (
        <section className="space-y-4 rounded-xl border bg-card p-5 shadow-sm">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 className="text-xl font-semibold">Subject topic extraction</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {summary.count === 0
                            ? 'Create a source-grounded topic proposal from this subject’s files.'
                            : `${summary.count} ${summary.count === 1 ? 'extraction' : 'extractions'} · latest ${summary.latest?.status.replaceAll('_', ' ') ?? 'unknown'}`}
                    </p>
                </div>
                <Button asChild>
                    <Link href={index({ program, subject })}>
                        {summary.count === 0 ? 'Start topic extraction' : 'Manage extractions'}
                    </Link>
                </Button>
            </div>
            {!enabled && unavailableReason && (
                <p className="text-sm text-muted-foreground">{unavailableReason}</p>
            )}
        </section>
    );
}
