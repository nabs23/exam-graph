import { Form, Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import programs from '@/routes/programs';
import generations from '@/routes/programs/curriculum-generations';

type SourceFile = { id: number; title: string; original_filename: string; mime_type: string; file_type: string; subject_id: number | null; subject_name: string | null; usable_pages_count: number };

export default function CreateCurriculumGeneration({ program, subjects, files, generationEnabled, generationUnavailableReason, models, defaultModel, kinds }: {
    program: { id: number; name: string; code: string };
    subjects: { id: number; name: string; code: string | null; syllabus_topics: { id: number; title: string; code: string | null }[] }[];
    files: SourceFile[];
    generationEnabled: boolean;
    generationUnavailableReason: string | null;
    models: string[];
    defaultModel: string | null;
    kinds: { value: string; label: string }[];
}) {
    const [kind, setKind] = useState(kinds[0]?.value ?? 'subjects');
    const [subjectId, setSubjectId] = useState('');
    const selectedSubject = subjects.find((subject) => String(subject.id) === subjectId);
    const eligibleFiles = useMemo(() => files.filter((file) => file.subject_id === null || String(file.subject_id) === subjectId), [files, subjectId]);

    return (
        <main className="w-full max-w-4xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Head title={`New curriculum proposal · ${program.name}`} />
            <header className="space-y-2 border-b pb-6"><p className="text-sm font-medium uppercase tracking-wide text-primary">{program.code}</p><h1 className="text-3xl font-semibold tracking-tight">New curriculum proposal</h1><p className="text-muted-foreground">Choose the scope and source files. The selected files are snapshotted for review.</p></header>
            {!generationEnabled && <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/5 p-4 text-sm">{generationUnavailableReason}</p>}
            <Form {...generations.store.form(program.id)} className="space-y-6">
                {({ processing, errors }) => <>
                    {Object.keys(errors).length > 0 && <p role="alert" className="rounded-md bg-destructive/5 p-3 text-sm text-destructive">{Object.values(errors).join(' ')}</p>}
                    <section className="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-2">
                        <label className="space-y-1 text-sm font-medium">Proposal type<select name="kind" value={kind} onChange={(event) => setKind(event.target.value)} className="w-full rounded-md border bg-background px-3 py-2">{kinds.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select></label>
                        <label className="space-y-1 text-sm font-medium">Subject<select name="subject_id" value={subjectId} required={kind !== 'subjects'} onChange={(event) => setSubjectId(event.target.value)} disabled={kind === 'subjects'} className="w-full rounded-md border bg-background px-3 py-2 disabled:opacity-60"><option value="">Program level</option>{subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.code ? `${subject.code} · ` : ''}{subject.name}</option>)}</select></label>
                        {kind === 'concepts' && <label className="space-y-1 text-sm font-medium sm:col-span-2">Parent topic<select name="syllabus_topic_id" defaultValue="" className="w-full rounded-md border bg-background px-3 py-2"><option value="">No parent topic</option>{selectedSubject?.syllabus_topics.map((topic) => <option key={topic.id} value={topic.id}>{topic.code ? `${topic.code} · ` : ''}{topic.title}</option>)}</select></label>}
                        <label className="space-y-1 text-sm font-medium sm:col-span-2">Model<select name="model" defaultValue={defaultModel ?? ''} className="w-full rounded-md border bg-background px-3 py-2">{models.map((model) => <option key={model} value={model}>{model}</option>)}</select></label>
                        <label className="space-y-1 text-sm font-medium sm:col-span-2">Additional context<textarea name="request_context" rows={3} maxLength={4000} placeholder="Optional guidance for the proposal" className="w-full rounded-md border bg-background px-3 py-2" /></label>
                    </section>
                    <section className="space-y-3 rounded-xl border bg-card p-5">
                        <div><h2 className="text-lg font-semibold">Source files</h2><p className="text-sm text-muted-foreground">Select at least one file. Text is extracted from eligible PDFs for the proposal without requiring embedding jobs.</p></div>
                        {eligibleFiles.length === 0 ? <p className="text-sm text-muted-foreground">No uploaded files are available in this scope.</p> : eligibleFiles.map((file) => <label key={file.id} className="flex items-start gap-3 rounded-md border p-3 text-sm"><input type="checkbox" name="source_file_ids[]" value={file.id} className="mt-1 size-4" /><span className="min-w-0 flex-1"><span className="block font-medium">{file.title}</span><span className="block text-muted-foreground">{file.original_filename} · {file.file_type.replaceAll('_', ' ')} · {file.subject_name ?? 'Shared program file'} · {file.usable_pages_count} indexed text pages</span></span></label>)}
                    </section>
                    <label className="flex items-start gap-3 rounded-md border p-4 text-sm"><input type="hidden" name="ungrounded" value="0" /><input type="checkbox" name="ungrounded" value="1" className="mt-1 size-4" /><span><span className="font-medium">Allow an ungrounded draft</span><span className="mt-1 block text-muted-foreground">Use when selected files contain no usable text. The AI will receive no file content and every proposal must be checked manually.</span></span></label>
                    <div className="flex flex-wrap gap-3"><Button type="submit" disabled={processing || !generationEnabled || eligibleFiles.length === 0}>{processing ? 'Queueing…' : 'Generate proposal'}</Button><Button asChild variant="outline"><Link href={generations.index(program.id)}>Cancel</Link></Button></div>
                </>}
            </Form>
        </main>
    );
}

CreateCurriculumGeneration.layout = (props: { program: { id: number } }) => ({ breadcrumbs: [
    { title: 'Programs', href: programs.index() },
    { title: 'Program', href: programs.show(props.program) },
    { title: 'Curriculum proposals', href: generations.index(props.program) },
    { title: 'Create', href: generations.create(props.program) },
] });
