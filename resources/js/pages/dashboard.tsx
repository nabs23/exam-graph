import { Head, Link } from '@inertiajs/react';
import { BookOpen, ClipboardCheck, Library, ListTree } from 'lucide-react';
import { dashboard } from '@/routes';
import programs from '@/routes/programs';
import progress from '@/routes/progress';
import study from '@/routes/study';
import { subjects } from '@/lib/curriculum-routes';

type DashboardProps = {
    isAdmin: boolean;
    counts: {
        programs: number;
        subjects: number;
        studyHistory: number;
        reviewWork: number;
    };
};

export default function Dashboard({ isAdmin, counts }: DashboardProps) {
    return (
        <>
            <Head title="Dashboard" />
            <main className="mx-auto w-full max-w-6xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
                <header className="space-y-2">
                    <p className="text-sm font-medium text-primary">{isAdmin ? 'Content workspace' : 'Your learning space'}</p>
                    <h1 className="text-3xl font-semibold tracking-tight">Dashboard</h1>
                    <p className="max-w-2xl text-muted-foreground">
                        {isAdmin
                            ? 'Manage curriculum and keep proposed changes moving through review.'
                            : 'Choose a subject to begin studying, or pick up where you left off.'}
                    </p>
                </header>

                <section aria-labelledby="next-steps-heading" className="space-y-4">
                    <h2 id="next-steps-heading" className="text-xl font-semibold tracking-tight">{isAdmin ? 'Content work' : 'Next steps'}</h2>
                    <div className="grid gap-4 md:grid-cols-2">
                        {isAdmin ? (
                            <>
                                <DashboardCard href={programs.index()} icon={Library} title="Manage programs" description={`${counts.programs} ${counts.programs === 1 ? 'program' : 'programs'} available. Create a program or continue editing its curriculum.`} action="Open programs" />
                                <DashboardCard href={subjects.index()} icon={ListTree} title="Manage subjects" description={`${counts.subjects} ${counts.subjects === 1 ? 'subject' : 'subjects'} available across programs.`} action="Open subjects" />
                                <DashboardCard href={programs.index()} icon={ClipboardCheck} title="Review proposals" description={counts.reviewWork > 0 ? `${counts.reviewWork} ${counts.reviewWork === 1 ? 'proposal is' : 'proposals are'} waiting for a decision.` : 'No proposals are waiting for review. New proposals appear here through their program or subject.'} action="Find review work" />
                            </>
                        ) : (
                            <>
                                <DashboardCard href={study.index()} icon={BookOpen} title="Browse study subjects" description={counts.subjects > 0 ? `${counts.subjects} ${counts.subjects === 1 ? 'subject is' : 'subjects are'} ready to explore.` : 'No subjects are available yet. Check back after your program adds curriculum.'} action="Go to Study" />
                                <DashboardCard href={progress.index()} icon={ClipboardCheck} title="View your progress" description={counts.studyHistory > 0 ? `${counts.studyHistory} quiz ${counts.studyHistory === 1 ? 'attempt' : 'attempts'} in your study history.` : 'Your study history will appear here after you complete a quiz.'} action="Open progress" />
                            </>
                        )}
                    </div>
                </section>
            </main>
        </>
    );
}

function DashboardCard({ href, icon: Icon, title, description, action }: {
    href: string | { url: string };
    icon: typeof BookOpen;
    title: string;
    description: string;
    action: string;
}) {
    const url = typeof href === 'string' ? href : href.url;

    return (
        <Link href={url} className="group flex min-h-44 flex-col gap-4 rounded-xl border bg-card p-5 shadow-sm transition-colors hover:border-primary/50 hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
            <div className="flex items-center gap-3">
                <span className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"><Icon aria-hidden="true" className="size-5" /></span>
                <h3 className="text-lg font-semibold">{title}</h3>
            </div>
            <p className="flex-1 text-sm leading-6 text-muted-foreground">{description}</p>
            <span className="text-sm font-medium text-primary group-hover:underline">{action} <span aria-hidden="true">→</span></span>
        </Link>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
