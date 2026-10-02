import { Head, useForm } from '@inertiajs/react';
import { subjectTopics } from '@/lib/curriculum-routes';
import { topics } from '@/lib/curriculum-routes';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Parent = { id: number; title: string };
type Subject = { id: number; name: string };
type Topic = { id: number; subject_id: number; parent_id: number | null; code: string | null; title: string; description: string | null; sort_order: number };

export default function TopicForm({ subject, topic, parents }: { subject: Subject; topic?: Topic; parents: Parent[] }) {
    const form = useForm({ parent_id: topic?.parent_id ?? null as number | null, code: topic?.code ?? '', title: topic?.title ?? '', description: topic?.description ?? '', sort_order: topic?.sort_order ?? 0 });
    const submit = (event: React.FormEvent) => { event.preventDefault(); if (topic) { form.put(topics.update(topic).url); } else { form.post(subjectTopics.store(subject).url); } };

    return <PageShell><Head title={topic ? 'Edit syllabus topic' : 'New syllabus topic'} /><PageHeader eyebrow={subject.name} title={topic ? 'Edit syllabus topic' : 'New syllabus topic'} description="Build the nested outline learners use to navigate the subject." /><Card><CardContent className="pt-6"><form onSubmit={submit} className="grid w-full gap-5 lg:grid-cols-2"><label className="grid gap-2 text-sm font-medium lg:col-span-2">Parent topic<select value={form.data.parent_id ?? ''} onChange={(event) => form.setData('parent_id', event.target.value ? Number(event.target.value) : null)} className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"><option value="">Top-level topic</option>{parents.map((parent) => <option key={parent.id} value={parent.id}>{parent.title}</option>)}</select></label><label className="grid gap-2 text-sm font-medium">Code (optional)<Input value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} /></label><label className="grid gap-2 text-sm font-medium">Title<Input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} required /></label><label className="grid gap-2 text-sm font-medium lg:col-span-2">Description<textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} className="min-h-32 w-full rounded-md border border-input bg-background p-3" /></label><div className="lg:col-span-2"><Button disabled={form.processing}>{form.processing ? 'Saving…' : 'Save topic'}</Button></div></form></CardContent></Card></PageShell>;
}
