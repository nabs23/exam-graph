import { Head, Link } from '@inertiajs/react';
import subjects from '@/routes/subjects';

export default function Subjects({ subjects: items }: { subjects: { id: number; name: string; code: string; program: { name: string } }[] }) {
    return <main className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8"><Head title="Subjects" /><div className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-center sm:justify-between"><div><p className="text-sm font-medium uppercase tracking-wide text-primary">Curriculum administration</p><h1 className="text-3xl font-semibold tracking-tight">Subjects</h1><p className="mt-1 text-muted-foreground">Organize subjects within each review program.</p></div><Link href={subjects.create()} className="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-xs hover:bg-primary/90">New subject</Link></div><div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{items.map((subject) => <Link key={subject.id} href={subjects.show(subject)} className="block rounded-xl border bg-card p-5 shadow-sm transition-colors hover:border-primary/50 hover:bg-accent"><p className="text-sm font-medium text-primary">{subject.code}</p><p className="mt-1 text-lg font-semibold">{subject.name}</p><span className="mt-2 block text-sm text-muted-foreground">{subject.program.name}</span></Link>)}</div></main>;
}

Subjects.layout = {
    breadcrumbs: [{ title: 'Subject', href: subjects.index() }],
};
