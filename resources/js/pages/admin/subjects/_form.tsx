import { Head, useForm } from '@inertiajs/react';
import subjects from '@/routes/subjects';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Program = { id: number; name: string; code: string };
type Subject = { id: number; program_id: number; name: string; code: string; description: string | null; sort_order: number };

export default function SubjectForm({ subject, programs }: { subject?: Subject; programs: Program[] }) {
    const form = useForm({ program_id: subject?.program_id ?? programs[0]?.id ?? 0, name: subject?.name ?? '', code: subject?.code ?? '', description: subject?.description ?? '', sort_order: subject?.sort_order ?? 0 });
    const submit = (event: React.FormEvent) => { event.preventDefault(); if (subject) { form.put(subjects.update(subject).url); } else { form.post(subjects.store().url); } };

    return <PageShell><Head title={subject ? 'Edit subject' : 'New subject'} /><PageHeader eyebrow="Curriculum administration" title={subject ? 'Edit subject' : 'New subject'} description="Place a subject within a review program." /><Card><CardContent className="pt-6"><form onSubmit={submit} className="grid w-full gap-5 lg:grid-cols-2"><label className="grid gap-2 text-sm font-medium lg:col-span-2">Program<select value={form.data.program_id} onChange={(event) => form.setData('program_id', Number(event.target.value))} className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">{programs.map((program) => <option key={program.id} value={program.id}>{program.code} — {program.name}</option>)}</select></label><label className="grid gap-2 text-sm font-medium">Name<Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required /></label><label className="grid gap-2 text-sm font-medium">Code<Input value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} required /></label><label className="grid gap-2 text-sm font-medium lg:col-span-2">Description<textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} className="min-h-32 w-full rounded-md border border-input bg-background p-3" /></label><div className="lg:col-span-2"><Button disabled={form.processing}>{form.processing ? 'Saving…' : 'Save subject'}</Button></div></form></CardContent></Card></PageShell>;
}
