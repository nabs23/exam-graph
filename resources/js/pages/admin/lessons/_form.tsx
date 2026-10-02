import { Head, useForm } from '@inertiajs/react';
import { conceptLessons } from '@/lib/curriculum-routes';
import { lessons } from '@/lib/curriculum-routes';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

export default function LessonForm({ concept, lesson }: { concept: { id: number; title: string }; lesson?: { id: number; title: string; summary: string | null; content: string; sort_order: number } }) {
    const form = useForm({ title: lesson?.title ?? '', summary: lesson?.summary ?? '', content: lesson?.content ?? '', sort_order: lesson?.sort_order ?? 0 });
    const submit = (event: React.FormEvent) => { event.preventDefault(); if (lesson) { form.put(lessons.update(lesson).url); } else { form.post(conceptLessons.store(concept).url); } };

    return <PageShell><Head title={lesson ? 'Edit lesson' : 'New lesson'} /><PageHeader eyebrow={concept.title} title={lesson ? 'Edit lesson' : 'New lesson'} description="Write the lesson learners will read before taking a quiz." /><Card><CardContent className="pt-6"><form onSubmit={submit} className="grid w-full gap-5"><label className="grid gap-2 text-sm font-medium">Title<Input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} required /></label><label className="grid gap-2 text-sm font-medium">Summary<textarea value={form.data.summary} onChange={(event) => form.setData('summary', event.target.value)} className="min-h-24 w-full rounded-md border border-input bg-background p-3" /></label><label className="grid gap-2 text-sm font-medium">Content<textarea value={form.data.content} onChange={(event) => form.setData('content', event.target.value)} className="min-h-64 w-full rounded-md border border-input bg-background p-3" required /></label><div><Button disabled={form.processing}>{form.processing ? 'Saving…' : 'Save lesson'}</Button></div></form></CardContent></Card></PageShell>;
}
