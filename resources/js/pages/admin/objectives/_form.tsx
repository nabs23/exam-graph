import { Head, useForm } from '@inertiajs/react';
import conceptObjectives from '@/routes/concepts/objectives';
import objectives from '@/routes/objectives';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

export default function ObjectiveForm({ concept, objective }: { concept: { id: number; title: string }; objective?: { id: number; description: string; sort_order: number } }) {
    const form = useForm({ description: objective?.description ?? '', sort_order: objective?.sort_order ?? 0 });
    const submit = (event: React.FormEvent) => { event.preventDefault(); if (objective) { form.put(objectives.update(objective).url); } else { form.post(conceptObjectives.store(concept).url); } };

    return <PageShell><Head title={objective ? 'Edit objective' : 'New objective'} /><PageHeader eyebrow={concept.title} title={objective ? 'Edit learning objective' : 'New learning objective'} description="Describe an observable outcome for this concept." /><Card><CardContent className="pt-6"><form onSubmit={submit} className="grid w-full gap-5"><label className="grid gap-2 text-sm font-medium">Objective<textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} className="min-h-36 w-full rounded-md border border-input bg-background p-3" required /></label><div><Button disabled={form.processing}>{form.processing ? 'Saving…' : 'Save objective'}</Button></div></form></CardContent></Card></PageShell>;
}
