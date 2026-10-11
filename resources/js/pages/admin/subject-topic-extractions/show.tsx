import { Form, Head, Link, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import { index, publish, reject, retry, show as showExtraction } from '@/actions/App/Http/Controllers/SubjectTopicExtractionController';
import { Button } from '@/components/ui/button';
import programs from '@/routes/programs';
import { subjects } from '@/lib/curriculum-routes';

type SourceCitation = { source_file_id: number; page_number: number };
type Topic = {
    code: string | null;
    title: string;
    description: string | null;
    parent_index: number | null;
    source_pages: SourceCitation[];
};
type SourceFile = { id: number; title: string; content_hash: string; mime_type: string };

export default function SubjectTopicExtractionShow({
    program,
    subject,
    extraction,
    sourcePages,
    canReview,
    sourceSnapshotCurrent,
}: {
    program: { id: number; name: string; code: string };
    subject: { id: number; name: string; code: string | null };
    extraction: {
        id: number;
        status: string;
        source_hash: string;
        source_files: SourceFile[];
        provider: string;
        model: string;
        prompt_version: string;
        error_code: string | null;
        error_message: string | null;
        proposal: { topics: Topic[]; validation?: { status: string; notes: string[] } } | null;
        reviewed_proposal: {
            topics: (Topic & { published_topic_id: number; sort_order: number })[];
            skipped_topics?: { code: string | null; title: string; reasons: string[] }[];
        } | null;
        requester: { name: string } | null;
        reviewer: { name: string } | null;
        created_at: string;
        reviewed_at: string | null;
    };
    sourcePages: { source_file_id: number; page_number: number; text: string }[];
    canReview: boolean;
    sourceSnapshotCurrent: boolean;
}) {
    const citations = new Map(sourcePages.map((page) => [`${page.source_file_id}:${page.page_number}`, page.text]));
    const files = new Map(extraction.source_files.map((file) => [file.id, file.title]));
    const proposal = extraction.proposal;
    const processing = ['queued', 'processing'].includes(extraction.status);
    const routeParams = { program: program.id, subject: subject.id, topicExtraction: extraction.id };
    const { start, stop } = usePoll(3000, { only: ['extraction', 'sourcePages', 'canReview', 'sourceSnapshotCurrent'] }, { autoStart: false, mode: 'rest' });

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
            <Head title={`Topic extraction · ${subject.name}`} />
            <header className="space-y-2 border-b pb-6">
                <p className="text-sm font-medium uppercase tracking-wide text-primary">{program.code} · {subject.code ?? subject.name}</p>
                <h1 className="text-3xl font-semibold tracking-tight">Syllabus topic proposal</h1>
                <p className="text-muted-foreground capitalize">{extraction.status.replaceAll('_', ' ')} · {extraction.source_files.length} source {extraction.source_files.length === 1 ? 'file' : 'files'}</p>
            </header>

            <dl className="grid gap-3 rounded-xl border bg-card p-4 text-sm sm:grid-cols-2">
                <div><dt className="text-muted-foreground">Source files</dt><dd className="font-medium">{extraction.source_files.map((file) => file.title).join(', ')}</dd></div>
                <div><dt className="text-muted-foreground">Provider and model</dt><dd className="font-medium">{extraction.provider} · {extraction.model}</dd></div>
                <div><dt className="text-muted-foreground">Requested by</dt><dd className="font-medium">{extraction.requester?.name ?? 'Unknown'}</dd></div>
                <div><dt className="text-muted-foreground">Source version</dt><dd className="break-all font-mono text-xs">{extraction.source_hash}</dd></div>
            </dl>

            {extraction.error_message && <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">{extraction.error_message}</p>}
            {!sourceSnapshotCurrent && extraction.status === 'reviewing' && <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/5 p-4 text-sm">A source file changed or was removed. This proposal cannot be published; start a new extraction.</p>}
            {proposal?.validation?.status === 'passed' && <p className="rounded-md border border-green-600/30 bg-green-600/5 p-4 text-sm">Automatic validation passed for the generated hierarchy and source citations.</p>}
            {proposal?.validation?.status === 'needs_review' && (
                <section role="alert" className="space-y-2 rounded-md border border-amber-600/30 bg-amber-600/5 p-4 text-sm">
                    <p className="font-semibold">Automatic validation notes</p>
                    <p>The proposal is available for review, but these checks did not pass. Verify and correct it before publishing.</p>
                    <ul className="list-disc space-y-1 pl-5">{proposal.validation.notes.map((note, index) => <li key={index}>{note}</li>)}</ul>
                </section>
            )}

            {canReview && proposal && (
                <Form {...publish.form(routeParams)} className="space-y-6">
                    {({ processing: publishing, errors }) => (
                        <>
                            {Object.keys(errors).length > 0 && <p role="alert" className="rounded-md bg-destructive/5 p-3 text-sm text-destructive">{Object.values(errors).join(' ')}</p>}
                            <section className="space-y-4 rounded-xl border bg-card p-5 shadow-sm">
                                <div>
                                    <h2 className="text-xl font-semibold">Review proposed topics</h2>
                                    <p className="text-sm text-muted-foreground">Edit titles, codes, parent topics, and order. Remove the selection check to exclude a topic.</p>
                                </div>
                                {proposal.topics.map((topic, topicIndex) => (
                                    <article key={topicIndex} className="space-y-4 rounded-lg border p-4">
                                        <input type="hidden" name={`topics[${topicIndex}][include]`} value="0" />
                                        <div className="flex items-start gap-3">
                                            <input type="checkbox" name={`topics[${topicIndex}][include]`} value="1" defaultChecked aria-label={`Include ${topic.title}`} className="mt-2 size-4" />
                                            <div className="grid flex-1 gap-3 sm:grid-cols-2">
                                                <label className="space-y-1 text-sm">Topic title<input name={`topics[${topicIndex}][title]`} defaultValue={topic.title} className="w-full rounded-md border bg-background px-3 py-2" /></label>
                                                <label className="space-y-1 text-sm">Official code (optional)<input name={`topics[${topicIndex}][code]`} defaultValue={topic.code ?? ''} className="w-full rounded-md border bg-background px-3 py-2" /></label>
                                                <label className="space-y-1 text-sm">Parent topic
                                                    <select name={`topics[${topicIndex}][parent_index]`} defaultValue={topic.parent_index ?? ''} className="w-full rounded-md border bg-background px-3 py-2">
                                                        <option value="">Top level</option>
                                                        {proposal.topics.map((parent, parentIndex) => parentIndex !== topicIndex ? <option key={parentIndex} value={parentIndex}>{parent.code ? `${parent.code} · ` : ''}{parent.title}</option> : null)}
                                                    </select>
                                                </label>
                                                <label className="space-y-1 text-sm">Order<input type="number" name={`topics[${topicIndex}][sort_order]`} defaultValue={topicIndex} min={0} className="w-full rounded-md border bg-background px-3 py-2" /></label>
                                                <label className="space-y-1 text-sm sm:col-span-2">Description<textarea name={`topics[${topicIndex}][description]`} defaultValue={topic.description ?? ''} rows={2} className="w-full rounded-md border bg-background px-3 py-2" /></label>
                                            </div>
                                        </div>
                                        <CitationList citations={topic.source_pages} files={files} pages={citations} />
                                    </article>
                                ))}
                            </section>
                            <div className="flex flex-wrap gap-3">
                                <Button type="submit" disabled={publishing || !sourceSnapshotCurrent}>{publishing ? 'Publishing…' : 'Publish selected topics'}</Button>
                            </div>
                        </>
                    )}
                </Form>
            )}

            {canReview && (
                <Form {...reject.form(routeParams)}>
                    {({ processing: rejecting }) => <Button type="submit" variant="outline" disabled={rejecting}>{rejecting ? 'Rejecting…' : 'Reject proposal'}</Button>}
                </Form>
            )}

            {extraction.status === 'published' && extraction.reviewed_proposal && (
                <section className="space-y-4 rounded-xl border bg-card p-5 shadow-sm">
                    <p className="text-sm">Published by {extraction.reviewer?.name ?? 'a reviewer'} on {extraction.reviewed_at ? formatDate(extraction.reviewed_at) : 'an unknown date'}.</p>
                    <p className="text-sm font-medium">Added {extraction.reviewed_proposal.topics.length} {extraction.reviewed_proposal.topics.length === 1 ? 'topic' : 'topics'}.</p>
                    {extraction.reviewed_proposal.topics.map((topic) => (
                        <article key={topic.published_topic_id} className="space-y-2 border-t pt-4">
                            <h2 className="font-semibold">{topic.code ? `${topic.code} · ` : ''}{topic.title}</h2>
                            {topic.description && <p className="text-sm text-muted-foreground">{topic.description}</p>}
                            <CitationList citations={topic.source_pages} files={files} pages={citations} />
                        </article>
                    ))}
                    {(extraction.reviewed_proposal.skipped_topics?.length ?? 0) > 0 && (
                        <section className="space-y-2 rounded-lg border border-amber-600/30 bg-amber-600/5 p-4">
                            <h3 className="font-semibold">Skipped topics</h3>
                            <ul className="space-y-3 text-sm">
                                {extraction.reviewed_proposal.skipped_topics?.map((topic, index) => (
                                    <li key={`${topic.code ?? 'topic'}:${topic.title}:${index}`}>
                                        <p className="font-medium">{topic.code ? `${topic.code} · ` : ''}{topic.title}</p>
                                        {topic.reasons.map((reason) => <p key={reason} className="text-muted-foreground">{reason}</p>)}
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}
                </section>
            )}

            {extraction.status === 'failed' && (
                <Form {...retry.form(routeParams)}>
                    {({ processing: retrying }) => <Button type="submit" variant="outline" disabled={retrying || !sourceSnapshotCurrent}>{retrying ? 'Retrying…' : 'Retry extraction'}</Button>}
                </Form>
            )}
            {extraction.status === 'rejected' && <p className="rounded-md border p-4 text-sm text-muted-foreground">This proposal was rejected by {extraction.reviewer?.name ?? 'a reviewer'}.</p>}

            <Button asChild variant="ghost"><Link href={index({ program, subject })}>Back to topic extractions</Link></Button>
            <p className="text-sm text-muted-foreground">AI output is a proposal. Verify the complete hierarchy and every citation against the original source before publishing.</p>
        </main>
    );
}

function CitationList({ citations, files, pages }: {
    citations: SourceCitation[];
    files: Map<number, string>;
    pages: Map<string, string>;
}) {
    return (
        <ul className="space-y-2 rounded-md bg-muted/40 p-3 text-sm">
            {citations.map((citation) => (
                <li key={`${citation.source_file_id}:${citation.page_number}`}>
                    <p className="font-medium">{files.get(citation.source_file_id) ?? 'Removed source'} · page {citation.page_number}</p>
                    <p className="mt-1 whitespace-pre-wrap text-muted-foreground">{pages.get(`${citation.source_file_id}:${citation.page_number}`) ?? 'Source page text is no longer available.'}</p>
                </li>
            ))}
        </ul>
    );
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

SubjectTopicExtractionShow.layout = (props: { program: { id: number }; subject: { id: number }; extraction: { id: number } }) => ({
    breadcrumbs: [
        { title: 'Programs', href: programs.index() },
        { title: 'Program', href: programs.show(props.program) },
        { title: 'Subject', href: subjects.show(props.subject) },
        { title: 'Topic extraction', href: index({ program: props.program, subject: props.subject }) },
        { title: 'Proposal', href: showExtraction({ program: props.program, subject: props.subject, topicExtraction: props.extraction }) },
    ],
});
