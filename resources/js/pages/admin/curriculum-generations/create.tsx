import { Form, Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import programs from '@/routes/programs';
import generations from '@/routes/programs/curriculum-generations';

type SourceFile = { id: number; title: string; original_filename: string; mime_type: string; file_type: string; subject_id: number | null; subject_name: string | null; usable_pages_count: number };
type Topic = { id: number; subject_id: number; parent_id: number | null; title: string; code: string | null; description: string | null; sort_order: number };
type Concept = { id: number; subject_id: number; syllabus_topic_id: number | null; code: string; title: string; description: string | null; syllabus_topic: { title: string; code: string | null } | null; learning_objectives: { description: string }[] };

function TopicTree({ topics, parentId = null, depth = 0 }: { topics: Topic[]; parentId?: number | null; depth?: number }) {
    const children = topics.filter((topic) => topic.parent_id === parentId);
    if (children.length === 0) return null;

    return <ul className={depth === 0 ? 'space-y-2' : 'mt-2 space-y-2 border-l pl-4'}>{children.map((topic) => <li key={topic.id} className="text-sm"><span className="font-medium">{topic.code ? `${topic.code} · ` : ''}{topic.title}</span>{topic.description && <p className="mt-1 text-muted-foreground">{topic.description}</p>}<TopicTree topics={topics} parentId={topic.id} depth={depth + 1} /></li>)}</ul>;
}

function topicPath(topics: Topic[], topicId: number | null): string {
    const path: Topic[] = [];
    let topic = topics.find((candidate) => candidate.id === topicId);
    while (topic) {
        path.unshift(topic);
        topic = topics.find((candidate) => candidate.id === topic?.parent_id);
    }
    return path.map((part) => part.code ? `${part.code} · ${part.title}` : part.title).join(' / ') || 'No syllabus topic';
}

export default function CreateCurriculumGeneration({ program, subjects, files, generationEnabled, generationUnavailableReason, models, defaultModel, kinds, initialKind, initialSubjectId, initialScopeType, initialScopeId }: {
    program: { id: number; name: string; code: string };
    subjects: { id: number; name: string; code: string | null; syllabus_topics: Topic[]; concepts: Concept[] }[];
    files: SourceFile[];
    generationEnabled: boolean;
    generationUnavailableReason: string | null;
    models: string[];
    defaultModel: string | null;
    kinds: { value: string; label: string }[];
    initialKind: string | null;
    initialSubjectId: number | null;
    initialScopeType: string | null;
    initialScopeId: number | null;
}) {
    const [kind, setKind] = useState(initialKind ?? kinds[0]?.value ?? 'subjects');
    const [subjectId, setSubjectId] = useState(initialSubjectId === null ? '' : String(initialSubjectId));
    const [scopeType, setScopeType] = useState(initialScopeType ?? 'subject');
    const [scopeId, setScopeId] = useState(initialScopeId === null ? '' : String(initialScopeId));
    const [selectedConceptIds, setSelectedConceptIds] = useState<number[]>(initialScopeType === 'concept' && initialScopeId ? [initialScopeId] : []);
    const selectedSubject = subjects.find((subject) => String(subject.id) === subjectId);
    const eligibleFiles = useMemo(() => files.filter((file) => file.subject_id === null || String(file.subject_id) === subjectId), [files, subjectId]);
    const isConceptGeneration = kind === 'concepts';
    const isLessonGeneration = kind === 'lessons';
    const eligibleConcepts = useMemo(() => {
        if (!selectedSubject) return [];
        const topics = selectedSubject.syllabus_topics;
        const descendants = new Set<number>();
        const addDescendants = (id: number) => {
            descendants.add(id);
            topics.filter((topic) => topic.parent_id === id).forEach((topic) => addDescendants(topic.id));
        };
        if (scopeType === 'topic' && scopeId) addDescendants(Number(scopeId));
        return selectedSubject.concepts.filter((concept) => scopeType === 'topic'
            ? concept.syllabus_topic_id !== null && descendants.has(concept.syllabus_topic_id)
            : scopeType === 'concept' ? String(concept.id) === scopeId : true);
    }, [selectedSubject, scopeType, scopeId]);

    return (
        <main className="w-full max-w-4xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Head title={`New curriculum proposal · ${program.name}`} />
            <header className="space-y-2 border-b pb-6"><p className="text-sm font-medium uppercase tracking-wide text-primary">{program.code}</p><h1 className="text-3xl font-semibold tracking-tight">New curriculum proposal</h1><p className="text-muted-foreground">{isConceptGeneration ? 'Draft concepts across every topic in the selected subject.' : isLessonGeneration ? 'Draft lessons from selected concept, topic, or subject context.' : 'Choose the scope and source files. The selected files are snapshotted for review.'}</p></header>
            {!generationEnabled && <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/5 p-4 text-sm">{generationUnavailableReason}</p>}
            <Form {...generations.store.form(program.id)} className="space-y-6">
                {({ processing, errors }) => <>
                    {Object.keys(errors).length > 0 && <p role="alert" className="rounded-md bg-destructive/5 p-3 text-sm text-destructive">{Object.values(errors).join(' ')}</p>}
                    <section className="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-2">
                        <label className="space-y-1 text-sm font-medium">Proposal type<select name="kind" value={kind} onChange={(event) => setKind(event.target.value)} className="w-full rounded-md border bg-background px-3 py-2">{kinds.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select></label>
                        <label className="space-y-1 text-sm font-medium">Subject<select name="subject_id" value={subjectId} required={kind !== 'subjects'} onChange={(event) => { setSubjectId(event.target.value); setScopeId(''); setSelectedConceptIds([]); }} disabled={kind === 'subjects'} className="w-full rounded-md border bg-background px-3 py-2 disabled:opacity-60"><option value="">Program level</option>{subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.code ? `${subject.code} · ` : ''}{subject.name}</option>)}</select></label>
                        <label className="space-y-1 text-sm font-medium sm:col-span-2">Model<select name="model" defaultValue={defaultModel ?? ''} className="w-full rounded-md border bg-background px-3 py-2">{models.map((model) => <option key={model} value={model}>{model}</option>)}</select></label>
                        <label className="space-y-1 text-sm font-medium sm:col-span-2">Additional context<textarea name="request_context" rows={3} maxLength={4000} placeholder="Optional guidance for the proposal" className="w-full rounded-md border bg-background px-3 py-2" /></label>
                    </section>
                    {isLessonGeneration ? <section className="space-y-4 rounded-xl border bg-card p-5">
                        <div><h2 className="text-lg font-semibold">Lesson scope</h2><p className="text-sm text-muted-foreground">Lessons use LLM knowledge and the selected curriculum context. No source materials, web lookup, or citations are used.</p></div>
                        <label className="block space-y-1 text-sm font-medium">Start from<select name="scope_type" value={scopeType} onChange={(event) => { setScopeType(event.target.value); setScopeId(''); setSelectedConceptIds([]); }} className="w-full rounded-md border bg-background px-3 py-2"><option value="concept">One concept</option><option value="topic">Topic and descendants</option><option value="subject">Full subject</option></select></label>
                        {scopeType !== 'subject' && <label className="block space-y-1 text-sm font-medium">{scopeType === 'topic' ? 'Topic' : 'Concept'}<select name="scope_id" required value={scopeId} onChange={(event) => { setScopeId(event.target.value); setSelectedConceptIds(scopeType === 'concept' && event.target.value ? [Number(event.target.value)] : []); }} className="w-full rounded-md border bg-background px-3 py-2"><option value="">Choose {scopeType}</option>{scopeType === 'topic' ? selectedSubject?.syllabus_topics.map((topic) => <option key={topic.id} value={topic.id}>{`${'— '.repeat((topicPath(selectedSubject?.syllabus_topics ?? [], topic.id).match(/ \/ /g) ?? []).length)}${topicPath(selectedSubject?.syllabus_topics ?? [], topic.id)}`}</option>) : selectedSubject?.concepts.map((concept) => <option key={concept.id} value={concept.id}>{concept.code} · {concept.title} — {topicPath(selectedSubject?.syllabus_topics ?? [], concept.syllabus_topic_id)}</option>)}</select></label>}
                        {scopeType === 'subject' && <input type="hidden" name="scope_id" value={subjectId} />}
                        <input type="hidden" name="syllabus_topic_id" value={scopeType === 'topic' ? scopeId : ''} />
                        <div className="space-y-2"><p className="text-sm font-medium">Choose concepts to receive lessons ({selectedConceptIds.length}/5 selected)</p>{eligibleConcepts.length === 0 ? <p className="text-sm text-muted-foreground">No concepts are available in this scope.</p> : eligibleConcepts.map((concept) => <label key={concept.id} className="flex items-start gap-3 rounded-md border p-3 text-sm"><input type="checkbox" name="concept_ids[]" value={concept.id} checked={selectedConceptIds.includes(concept.id)} onChange={(event) => setSelectedConceptIds((current) => event.target.checked ? [...current, concept.id] : current.filter((id) => id !== concept.id))} disabled={!selectedConceptIds.includes(concept.id) && selectedConceptIds.length >= 5} className="mt-1 size-4" /><span><span className="font-medium">{concept.code} · {concept.title}</span><span className="block text-muted-foreground">{topicPath(selectedSubject?.syllabus_topics ?? [], concept.syllabus_topic_id)}{concept.description ? ` · ${concept.description}` : ' · No description'}</span>{concept.learning_objectives.length > 0 && <span className="mt-1 block text-muted-foreground">Objectives: {concept.learning_objectives.map((objective) => objective.description).join(' · ')}</span>}</span></label>)}</div>
                    </section> : isConceptGeneration ? <section className="space-y-3 rounded-xl border bg-card p-5"><div><h2 className="text-lg font-semibold">Subject topic tree</h2><p className="text-sm text-muted-foreground">Concepts will be proposed across this full hierarchy. No program files are used.</p></div>{selectedSubject ? selectedSubject.syllabus_topics.length > 0 ? <TopicTree topics={selectedSubject.syllabus_topics} /> : <p className="text-sm text-amber-700">Add syllabus topics to this subject before drafting concepts.</p> : <p className="text-sm text-muted-foreground">Choose a subject to preview its topics.</p>}</section> : <section className="space-y-3 rounded-xl border bg-card p-5">
                        <div><h2 className="text-lg font-semibold">Source files</h2><p className="text-sm text-muted-foreground">Select at least one file. Text is extracted from eligible PDFs for the proposal without requiring embedding jobs.</p></div>
                        {eligibleFiles.length === 0 ? <p className="text-sm text-muted-foreground">No uploaded files are available in this scope.</p> : eligibleFiles.map((file) => <label key={file.id} className="flex items-start gap-3 rounded-md border p-3 text-sm"><input type="checkbox" name="source_file_ids[]" value={file.id} className="mt-1 size-4" /><span className="min-w-0 flex-1"><span className="block font-medium">{file.title}</span><span className="block text-muted-foreground">{file.original_filename} · {file.file_type.replaceAll('_', ' ')} · {file.subject_name ?? 'Shared program file'} · {file.usable_pages_count} indexed text pages</span></span></label>)}
                    </section>}
                    {isConceptGeneration && <input type="hidden" name="ungrounded" value="1" />}
                    {!isConceptGeneration && !isLessonGeneration && <label className="flex items-start gap-3 rounded-md border p-4 text-sm"><input type="hidden" name="ungrounded" value="0" /><input type="checkbox" name="ungrounded" value="1" className="mt-1 size-4" /><span><span className="font-medium">Allow an ungrounded draft</span><span className="mt-1 block text-muted-foreground">Use when selected files contain no usable text. The AI will receive no file content and every proposal must be checked manually.</span></span></label>}
                    <div className="flex flex-wrap gap-3"><Button type="submit" disabled={processing || !generationEnabled || (isConceptGeneration ? !selectedSubject || selectedSubject.syllabus_topics.length === 0 : isLessonGeneration ? !selectedSubject || selectedConceptIds.length === 0 : eligibleFiles.length === 0)}>{processing ? 'Queueing…' : 'Generate proposal'}</Button><Button asChild variant="outline"><Link href={generations.index(program.id)}>Cancel</Link></Button></div>
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
