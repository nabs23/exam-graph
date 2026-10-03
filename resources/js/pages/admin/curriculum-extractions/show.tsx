import { Form, Head, Link, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { publish, reject } from '@/actions/App/Http/Controllers/CurriculumExtractionController';
import { Button } from '@/components/ui/button';
import programs from '@/routes/programs';

type TopicProposal = {
    code: string | null;
    title: string;
    description: string | null;
    description_origin?: 'source' | 'ai_generated' | 'reviewer' | 'unavailable';
    source_file_id: number | null;
    source_page: number | null;
    parent_index: number | null;
};

type SubjectProposal = {
    code: string | null;
    name: string;
    description: string | null;
    description_origin?: 'source' | 'ai_generated' | 'reviewer' | 'unavailable';
    source_file_id: number | null;
    source_page: number | null;
    topics: TopicProposal[];
};

type ExistingSubject = {
    id: number;
    name: string;
    code: string | null;
    description: string | null;
    concepts_count: number;
    files_count: number;
    topics: { id: number; parent_id: number | null; code: string | null; title: string; description: string | null }[];
};

export default function CurriculumExtractionShow({
    extraction,
    proposal,
    reviewedProposal,
    sourcePages,
    existingSubjects,
    canReview,
}: {
    extraction: {
        id: number;
        status: string;
        error_code: string | null;
        error_message: string | null;
        source_hash: string;
        provider: string;
        model: string;
        created_at: string;
        program: { id: number; name: string; code: string };
        source_files: { id: number; title: string; content_hash: string }[];
        requester: { name: string } | null;
        reviewer: { name: string } | null;
    };
    proposal: { subjects: SubjectProposal[] } | null;
    reviewedProposal: { subjects: (SubjectProposal & { topics: (TopicProposal & { parent_index: number | null })[] })[] } | null;
    sourcePages: { source_file_id: number; page_number: number; text: string }[];
    existingSubjects: ExistingSubject[];
    canReview: boolean;
}) {
    const pages = new Map(sourcePages.map((page) => [`${page.source_file_id}:${page.page_number}`, page.text]));
    const files = new Map(extraction.source_files.map((file) => [file.id, file.title]));
    const { start, stop } = usePoll(3000, { only: ['extraction', 'proposal', 'reviewedProposal', 'sourcePages', 'canReview'] }, {
        autoStart: false,
        mode: 'rest',
    });
    const processing = ['queued', 'processing'].includes(extraction.status);

    useEffect(() => {
        if (processing) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [processing, start, stop]);

    return (
        <main className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Head title="Official curriculum proposal" />
            <header className="space-y-2 border-b pb-6">
                <p className="text-sm font-medium uppercase tracking-wide text-primary">{extraction.program.code}</p>
                <h1 className="text-3xl font-semibold tracking-tight">Official curriculum proposal</h1>
                <p className="text-muted-foreground">
                    {extraction.status.replaceAll('_', ' ')} · {extraction.source_files.length} source {extraction.source_files.length === 1 ? 'file' : 'files'}
                </p>
            </header>

            <dl className="grid gap-3 rounded-xl border bg-card p-4 text-sm sm:grid-cols-2">
                <div><dt className="text-muted-foreground">Source PDFs</dt><dd className="font-medium">{extraction.source_files.map((file) => file.title).join(', ')}</dd></div>
                <div><dt className="text-muted-foreground">Provider and model</dt><dd className="font-medium">{extraction.provider} · {extraction.model}</dd></div>
                <div><dt className="text-muted-foreground">Requested by</dt><dd className="font-medium">{extraction.requester?.name ?? 'Unknown'}</dd></div>
                <div><dt className="text-muted-foreground">Source version</dt><dd className="break-all font-mono text-xs">{extraction.source_hash}</dd></div>
            </dl>

            {extraction.error_code && (
                <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/5 p-4 text-sm">
                    {extraction.error_message}
                </p>
            )}

            {canReview && proposal && (
                <Form {...publish.form(extraction.id)} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            {Object.keys(errors).length > 0 && <p role="alert" className="text-sm text-destructive">{Object.values(errors).join(' ')}</p>}
                            {proposal.subjects.map((subject, subjectIndex) => (
                                <section key={`${subject.name}-${subjectIndex}`} className="space-y-4 rounded-xl border bg-card p-5 shadow-sm">
                                    <div className="flex items-start gap-3">
                                        <input type="hidden" name={`subjects[${subjectIndex}][include]`} value="0" />
                                        <input className="mt-1 size-4" type="checkbox" name={`subjects[${subjectIndex}][include]`} value="1" defaultChecked aria-label={`Select ${subject.name}`} />
                                        <div className="grid flex-1 gap-3 sm:grid-cols-2">
                                            <label className="space-y-1 text-sm">Official subject name<input className="w-full rounded-md border bg-background px-3 py-2" name={`subjects[${subjectIndex}][name]`} defaultValue={subject.name} /></label>
                                            <label className="space-y-1 text-sm">Subject code (optional)<input className="w-full rounded-md border bg-background px-3 py-2" name={`subjects[${subjectIndex}][code]`} defaultValue={subject.code ?? ''} /></label>
                                            <label className="space-y-1 text-sm sm:col-span-2">{descriptionLabel(subject.description_origin)}<textarea className="w-full rounded-md border bg-background px-3 py-2" name={`subjects[${subjectIndex}][description]`} defaultValue={subject.description ?? ''} rows={2} /></label>
                                        </div>
                                    </div>
                                    <Citation fileName={subject.source_file_id === null ? undefined : files.get(subject.source_file_id)} page={subject.source_page} text={pages.get(`${subject.source_file_id}:${subject.source_page}`)} />
                                    <SubjectMergePreview subject={subject} subjectIndex={subjectIndex} existingSubjects={existingSubjects} />
                                    {subject.topics.length > 0 && <h2 className="font-medium">Syllabus topics</h2>}
                                    {subject.topics.map((topic, topicIndex) => (
                                        <div key={`${topic.title}-${topicIndex}`} className="ml-4 space-y-3 border-l-2 pl-4">
                                            <input type="hidden" name={`subjects[${subjectIndex}][topics][${topicIndex}][include]`} value="0" />
                                            <div className="flex items-start gap-3">
                                                <input className="mt-1 size-4" type="checkbox" name={`subjects[${subjectIndex}][topics][${topicIndex}][include]`} value="1" defaultChecked aria-label={`Select ${topic.title}`} />
                                                <div className="grid flex-1 gap-3 sm:grid-cols-2">
                                                    <label className="space-y-1 text-sm">Topic title<input className="w-full rounded-md border bg-background px-3 py-2" name={`subjects[${subjectIndex}][topics][${topicIndex}][title]`} defaultValue={topic.title} /></label>
                                                    <label className="space-y-1 text-sm">Topic code (optional)<input className="w-full rounded-md border bg-background px-3 py-2" name={`subjects[${subjectIndex}][topics][${topicIndex}][code]`} defaultValue={topic.code ?? ''} /></label>
                                                    <label className="space-y-1 text-sm sm:col-span-2">{descriptionLabel(topic.description_origin)}<textarea className="w-full rounded-md border bg-background px-3 py-2" name={`subjects[${subjectIndex}][topics][${topicIndex}][description]`} defaultValue={topic.description ?? ''} rows={2} /></label>
                                                </div>
                                            </div>
                                            <Citation fileName={topic.source_file_id === null ? undefined : files.get(topic.source_file_id)} page={topic.source_page} text={pages.get(`${topic.source_file_id}:${topic.source_page}`)} />
                                            {topic.parent_index !== null && <p className="text-xs text-muted-foreground">Parent: {subject.topics[topic.parent_index]?.title ?? 'Unavailable'}</p>}
                                        </div>
                                    ))}
                                </section>
                            ))}
                            <div className="flex flex-wrap gap-3">
                                <Button type="submit" disabled={processing}>{processing ? 'Publishing…' : 'Publish selected curriculum'}</Button>
                            </div>
                        </>
                    )}
                </Form>
            )}

            {canReview && (
                <Form {...reject.form(extraction.id)}>
                    {({ processing }) => <Button type="submit" variant="outline" disabled={processing}>Reject proposal</Button>}
                </Form>
            )}

            {!canReview && extraction.status === 'published' && (
                <section className="space-y-4 rounded-xl border bg-card p-5 shadow-sm">
                    <p className="text-sm">Published by {extraction.reviewer?.name ?? 'a reviewer'}. These are the selected and reviewed records.</p>
                    {reviewedProposal?.subjects.map((subject, subjectIndex) => (
                        <div key={`${subject.code}-${subjectIndex}`} className="space-y-3 border-t pt-4">
                            <h2 className="text-lg font-semibold">{subject.code ? `${subject.code} · ` : ''}{subject.name}</h2>
                            {subject.description && <p className="text-sm text-muted-foreground">{subject.description}{subject.description_origin === 'ai_generated' && <span className="ml-2 text-xs">AI-drafted</span>}{subject.description_origin === 'reviewer' && <span className="ml-2 text-xs">Reviewer-edited</span>}</p>}
                            <Citation fileName={subject.source_file_id === null ? undefined : files.get(subject.source_file_id)} page={subject.source_page} text={pages.get(`${subject.source_file_id}:${subject.source_page}`)} />
                            <ul className="space-y-3">
                                {subject.topics.map((topic, topicIndex) => (
                                    <li key={`${topic.code}-${topicIndex}`} className="ml-4 space-y-2 border-l-2 pl-4">
                                        <h3 className="font-medium">{topic.code ? `${topic.code} · ` : ''}{topic.title}</h3>
                                        {topic.description && <p className="text-sm text-muted-foreground">{topic.description}{topic.description_origin === 'ai_generated' && <span className="ml-2 text-xs">AI-drafted</span>}{topic.description_origin === 'reviewer' && <span className="ml-2 text-xs">Reviewer-edited</span>}</p>}
                                        <Citation fileName={topic.source_file_id === null ? undefined : files.get(topic.source_file_id)} page={topic.source_page} text={pages.get(`${topic.source_file_id}:${topic.source_page}`)} />
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </section>
            )}

            <p className="text-sm text-muted-foreground">AI output is a proposal. Verify every name and citation against the source before publishing.</p>
        </main>
    );
}

function SubjectMergePreview({ subject, subjectIndex, existingSubjects }: { subject: SubjectProposal; subjectIndex: number; existingSubjects: ExistingSubject[] }) {
    const candidates = existingSubjects.filter((existing) => normalize(existing.name) === normalize(subject.name));
    const preferred = candidates.find((candidate) => candidate.code !== null && normalize(candidate.code) === normalize(subject.code ?? ''));
    const [targetId, setTargetId] = useState<number | null>(preferred?.id ?? (candidates.length === 1 ? candidates[0].id : null));
    const [sourceIds, setSourceIds] = useState<number[]>([]);
    const target = candidates.find((candidate) => candidate.id === targetId);
    const mergeSources = candidates.filter((candidate) => sourceIds.includes(candidate.id));
    const mergePlan = planTopicMerge(target, mergeSources);
    const unresolved = candidates.length > 1 && target === undefined;
    const selectedCodes = [target, ...mergeSources]
        .map((candidate) => candidate?.code)
        .filter((code): code is string => code !== null && code !== undefined)
        .map(normalize);
    const incompatibleCodes = new Set(selectedCodes).size > 1 || (subject.code !== null && selectedCodes.length > 0 && !selectedCodes.includes(normalize(subject.code)));

    function toggleSource(id: number, checked: boolean) {
        setSourceIds((current) => checked ? [...current, id] : current.filter((sourceId) => sourceId !== id));
    }

    if (candidates.length === 0) {
        return null;
    }

    return (
        <section className="space-y-3 rounded-lg border bg-muted/30 p-4" aria-label={`Existing subject match for ${subject.name}`}>
            <div>
                <h3 className="font-medium">Existing subject match</h3>
                <p className="text-sm text-muted-foreground">Choose the record to keep. Select duplicates to merge and review the resulting topic list before publishing.</p>
            </div>
            <input type="hidden" name={`subjects[${subjectIndex}][merge_target_id]`} value={targetId ?? ''} />
            {candidates.map((candidate) => (
                <div key={candidate.id} className="space-y-2 rounded-md border bg-card p-3">
                    <label className="flex cursor-pointer items-start gap-3 text-sm">
                        <input type="radio" name={`merge-target-${subjectIndex}`} value={candidate.id} checked={targetId === candidate.id} onChange={() => { setTargetId(candidate.id); setSourceIds((current) => current.filter((sourceId) => sourceId !== candidate.id)); }} className="mt-1 size-4" />
                        <span><span className="font-medium">Keep: {candidate.code ? `${candidate.code} · ` : ''}{candidate.name}</span><span className="block text-muted-foreground">{candidate.topics.length} topics · {candidate.concepts_count} concepts · {candidate.files_count} files</span></span>
                    </label>
                    {candidate.id !== targetId && (
                        <label className="ml-7 flex cursor-pointer items-start gap-2 text-sm">
                            <input type="checkbox" name={`subjects[${subjectIndex}][merge_source_ids][]`} value={candidate.id} checked={sourceIds.includes(candidate.id)} onChange={(event) => toggleSource(candidate.id, event.target.checked)} className="mt-1 size-4" />
                            <span>Merge this record into the selected subject</span>
                        </label>
                    )}
                    <details className="ml-7 text-sm">
                        <summary className="cursor-pointer text-muted-foreground">Review its topics</summary>
                        {candidate.description && <p className="mt-2 text-muted-foreground">{candidate.description}</p>}
                        <ul className="mt-2 list-inside list-disc space-y-1">{candidate.topics.map((topic) => <li key={topic.id}>{topic.code ? `${topic.code} · ` : ''}{topic.title}</li>)}</ul>
                    </details>
                </div>
            ))}
            {candidates.length > 1 && (
                <div className="space-y-2 border-t pt-3">
                    <h4 className="text-sm font-medium">Merge preview</h4>
                    {unresolved && <p role="status" className="text-sm text-amber-700 dark:text-amber-300">Choose the existing subject that should receive this proposal.</p>}
                    {incompatibleCodes && <p role="alert" className="text-sm text-destructive">The selected records have different codes. Choose records with matching or missing codes.</p>}
                    {mergePlan.conflicts.length > 0 && <div role="alert" className="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm"><p className="font-medium">Topic conflicts need review before merging these records.</p>{mergePlan.conflicts.map((conflict) => <p key={conflict}>{conflict}</p>)}<p className="mt-1 text-muted-foreground">Unselect the conflicting subject to keep it separate, or resolve its topic codes and hierarchy first.</p></div>}
                    {target && <p className="text-xs text-muted-foreground">Result: {target.code ? `${target.code} · ` : ''}{target.name} with {mergePlan.topics.length} topics, {target.concepts_count + mergeSources.reduce((count, candidate) => count + candidate.concepts_count, 0)} concepts, and {target.files_count + mergeSources.reduce((count, candidate) => count + candidate.files_count, 0)} files.</p>}
                    {mergePlan.topics.map((topic) => <p key={topic.key} className="text-sm">{topic.title}{topic.sources.length > 1 && <span className="ml-2 text-xs text-muted-foreground">Combined from {topic.sources.join(', ')}</span>}</p>)}
                </div>
            )}
        </section>
    );
}

function planTopicMerge(target: ExistingSubject | undefined, sources: ExistingSubject[]): { topics: { key: string; title: string; sources: string[] }[]; conflicts: string[] } {
    const subjects = [target, ...sources].filter((subject): subject is ExistingSubject => subject !== undefined);
    const paths = new Map<number, { key: string; display: string }>();
    const topics = new Map<string, { key: string; title: string; code: string | null; sources: string[] }>();
    const codes = new Map<string, { key: string; title: string }>();
    const conflicts = new Set<string>();

    for (const subject of subjects) {
        for (const topic of [...subject.topics].sort((left, right) => left.id - right.id)) {
            const parentPath = topic.parent_id === null ? undefined : paths.get(topic.parent_id);
            const key = `${parentPath?.key ?? ''}/${normalize(topic.title)}`;
            const display = `${parentPath ? `${parentPath.display} › ` : ''}${topic.code ? `${topic.code} · ` : ''}${topic.title}`;
            paths.set(topic.id, { key, display });
            const existing = topics.get(key);
            if (existing) {
                if (topic.code !== null && existing.code !== null && normalize(topic.code) !== normalize(existing.code)) {
                    conflicts.add(`${existing.title} has different codes in the selected records.`);
                }
                const existingCode = codes.get(normalize(topic.code ?? ''));
                if (topic.code !== null && existingCode && existingCode.key !== key) {
                    conflicts.add(`${topic.code}: ${existingCode.title} and ${display} use the same code in different places.`);
                }
                if (!existing.sources.includes(subject.name)) {
                    existing.sources.push(subject.name);
                }
            } else {
                topics.set(key, { key, title: display, code: topic.code, sources: [subject.name] });
            }
            if (topic.code !== null) {
                const existingCode = codes.get(normalize(topic.code));
                if (existingCode && existingCode.key !== key) {
                    conflicts.add(`${topic.code}: ${existingCode.title} and ${display} use the same code in different places.`);
                } else {
                    codes.set(normalize(topic.code), { key, title: display });
                }
            }
        }
    }

    return { topics: [...topics.values()], conflicts: [...conflicts] };
}

function normalize(value: string): string {
    return value.trim().toLocaleLowerCase();
}

function Citation({ fileName, page, text }: { fileName?: string; page: number | null; text?: string }) {
    if (page === null) {
        return <p>No source citation.</p>;
    }

    return (
        <details className="rounded-md bg-muted/60 p-3 text-sm">
            <summary className="cursor-pointer font-medium">{fileName ?? 'Source file'} · page {page}</summary>
            <p className="mt-2 whitespace-pre-wrap text-muted-foreground">{text ?? 'Source page text is no longer available.'}</p>
        </details>
    );
}

function descriptionLabel(origin: TopicProposal['description_origin']): string {
    if (origin === 'ai_generated') {
        return 'AI-drafted description — review for accuracy';
    }

    if (origin === 'unavailable') {
        return 'Description (not supported by available source text)';
    }

    if (origin === 'reviewer') {
        return 'Description (reviewer-edited)';
    }

    return 'Description (source-supported)';
}

CurriculumExtractionShow.layout = (props: { extraction: { program: { id: number } } }) => ({
    breadcrumbs: [
        { title: 'Program', href: programs.show(props.extraction.program) },
        { title: 'Curriculum review', href: programs.show(props.extraction.program) },
    ],
});
