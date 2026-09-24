import { Head, useForm } from '@inertiajs/react';
import programs from '@/routes/programs';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

export default function ProgramForm({ program }: { program?: { id: number; name: string; code: string; description: string | null } }) {
    const form = useForm({ name: program?.name ?? '', code: program?.code ?? '', description: program?.description ?? '' });
    const submit = (event: React.FormEvent) => { event.preventDefault(); if (program) { form.put(programs.update(program).url); } else { form.post(programs.store().url); } };

    return <PageShell><Head title={program ? 'Edit program' : 'New program'} /><PageHeader eyebrow="Curriculum administration" title={program ? 'Edit program' : 'New program'} description="Define the program identity used by subjects and learners." /><Card><CardContent className="pt-6"><form onSubmit={submit} className="grid w-full gap-5 lg:grid-cols-2"><label className="grid gap-2 text-sm font-medium">Name<Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required /></label><label className="grid gap-2 text-sm font-medium">Code<Input value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} required /></label><label className="grid gap-2 text-sm font-medium lg:col-span-2">Description<textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} className="min-h-32 w-full rounded-md border border-input bg-background p-3" /></label><div className="lg:col-span-2"><Button disabled={form.processing}>{form.processing ? 'Saving…' : 'Save program'}</Button></div></form></CardContent></Card></PageShell>;
}
