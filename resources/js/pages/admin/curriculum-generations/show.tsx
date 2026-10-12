import { Form, Head, Link, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import programs from '@/routes/programs';
import generations from '@/routes/programs/curriculum-generations';

type TopicSnapshot = { id: number; parent_id: number | null; code: string | null; title: string; description: string | null; path: { id: number; code: string | null; title: string }[] };
type ProposalItem = { draft_id: string; name?: string; title?: string; code: string; description: string | null; parent_draft_id?: string | null; syllabus_topic_id?: number | null; sort_order?: number; citations?: { source_file_id: number; page_number: number; verification?: string; excerpt?: string | null }[]; citation_status?: string; notes?: string[]; review_notes?: string[] };

export default function ShowCurriculumGeneration({ program, generation, sourceFiles, proposalHash, canReview }: {
    program: { id: number; name: string; code: string };
    generation: {
        id: number; kind: string; status: string; model: string; provider: string; error_message: string | null;
        request_context: string | null; ungrounded_requested: boolean; proposal: { items: ProposalItem[]; notes?: string[] } | null;
        scope_snapshot: { subject: { id: number; name: string; code: string | null }; topics: TopicSnapshot[] } | null;
        reviewed_proposal: { items: (ProposalItem & { decision: string; canonical_id: number | null; review_notes: string[] })[] } | null;
        requester: { name: string } | null; reviewer: { name: string } | null; created_at: string; reviewed_at: string | null;
    };
    sourceFiles: { id: number; title: string; file_type: string; content_hash: string; page_count: number }[];
    proposalHash: string | null;
    canReview: boolean;
}) {
    const active = ['queued', 'generating'].includes(generation.status);
    const { start, stop } = usePoll(3000, { only: ['generation', 'sourceFiles', 'proposalHash', 'canReview'] }, { autoStart: false, mode: 'rest' });
    useEffect(() => { if (active) start(); else stop(); return stop; }, [active, start, stop]);
    const files = new Map(sourceFiles.map((file) => [file.id, file.title]));
    const items = generation.proposal?.items ?? [];
    const routeParams = { program: program.id, curriculumGeneration: generation.id };

    return (
        <main className="w-full max-w-5xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Head title={`Curriculum proposal · ${program.name}`} />
            <header className="space-y-2 border-b pb-6"><p className="text-sm font-medium uppercase tracking-wide text-primary">{program.code} · {generation.kind}</p><h1 className="text-3xl font-semibold tracking-tight">Curriculum proposal</h1><p className="text-muted-foreground capitalize">{generation.status.replaceAll('_', ' ')} · Requested by {generation.requester?.name ?? 'unknown'}</p></header>
            <dl className="grid gap-3 rounded-xl border bg-card p-4 text-sm sm:grid-cols-2"><div><dt className="text-muted-foreground">Provider and model</dt><dd className="font-medium">{generation.provider} · {generation.model}</dd></div><div><dt className="text-muted-foreground">Source mode</dt><dd className="font-medium">{generation.kind === 'concepts' ? 'LLM knowledge · no selected program files' : generation.ungrounded_requested ? 'Ungrounded draft' : 'Source grounded'}</dd></div>{generation.kind !== 'concepts' && <div className="sm:col-span-2"><dt className="text-muted-foreground">Source snapshot</dt><dd className="mt-1 space-y-1">{sourceFiles.map((file) => <p key={file.id}>{file.title} · {file.file_type.replaceAll('_', ' ')} · {file.page_count} pages · <span className="break-all font-mono text-xs">{file.content_hash}</span></p>)}</dd></div>}</dl>
            {generation.scope_snapshot && <section className="space-y-3 rounded-xl border bg-card p-4"><div><h2 className="font-semibold">Subject scope: {generation.scope_snapshot.subject.code ? `${generation.scope_snapshot.subject.code} · ` : ''}{generation.scope_snapshot.subject.name}</h2><p className="text-sm text-muted-foreground">Complete topic tree captured for this request. Concepts are generated from LLM knowledge and are not grounded in selected program files.</p></div><ul className="space-y-2">{generation.scope_snapshot.topics.map((topic) => <li key={topic.id} className="text-sm"><span className="text-muted-foreground">{topic.path.map((part) => part.code ? `${part.code} ${part.title}` : part.title).join(' / ')}</span>{topic.description && <p className="mt-1 text-muted-foreground">{topic.description}</p>}</li>)}</ul></section>}
            {generation.request_context && <p className="rounded-md border p-4 text-sm"><span className="font-medium">Request context: </span>{generation.request_context}</p>}
            <p className="rounded-md border bg-muted/30 p-4 text-sm text-muted-foreground">AI output remains a private proposal until an author approves individual items. Approval records the author’s decision; it does not certify official completeness, factual correctness, or PRC compliance.{generation.kind !== 'concepts' && ' A citation marked valid only matches a selected source file and page.'}</p>
            {generation.error_message && <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">{generation.error_message}</p>}
            {active && <p role="status" className="rounded-md border p-4 text-sm text-muted-foreground">The proposal is generating. This page refreshes automatically.</p>}
            {generation.proposal?.notes && generation.proposal.notes.length > 0 && <section className="rounded-md border border-amber-600/30 bg-amber-600/5 p-4 text-sm"><h2 className="font-semibold">Review notes</h2><ul className="mt-2 list-disc space-y-1 pl-5">{generation.proposal.notes.map((note, index) => <li key={index}>{note}</li>)}</ul></section>}
            {canReview && generation.proposal && proposalHash && <Form {...generations.review.form(routeParams)} className="space-y-5">
                {({ processing, errors }) => <>
                    <input type="hidden" name="proposal_hash" value={proposalHash} />
                    {Object.keys(errors).length > 0 && <p role="alert" className="rounded-md bg-destructive/5 p-3 text-sm text-destructive">{Object.values(errors).join(' ')}</p>}
                    <section className="space-y-4"><div><h2 className="text-xl font-semibold">Review each proposed item</h2><p className="text-sm text-muted-foreground">Edit the fields and select the items to approve. Conflicts remain available to resolve in a later review.</p></div>
                        {items.map((item, index) => {
                            const name = item.name ?? item.title ?? '';
                            const previous = generation.reviewed_proposal?.items.find((reviewed) => reviewed.draft_id === item.draft_id);
                            const locked = ['approved', 'rejected'].includes(previous?.decision ?? '');
                            return <article key={item.draft_id} className="space-y-3 rounded-xl border bg-card p-4">
                                <input type="hidden" name={`items[${index}][draft_id]`} value={item.draft_id} />
                                <input type="hidden" name={`items[${index}][include]`} value="0" />
                                <div className="flex items-start gap-3"><input type="checkbox" name={`items[${index}][include]`} value="1" defaultChecked={!previous || previous.decision === 'pending' || previous.decision === 'conflict'} disabled={locked} className="mt-2 size-4" aria-label={`Approve ${name}`} />
                                    <div className="grid flex-1 gap-3 sm:grid-cols-2">
                                        <label className="space-y-1 text-sm">{generation.kind === 'subjects' ? 'Subject name' : 'Title'}<input name={`items[${index}][${generation.kind === 'subjects' ? 'name' : 'title'}]`} defaultValue={previous?.[generation.kind === 'subjects' ? 'name' : 'title'] ?? name} readOnly={locked} className="w-full rounded-md border bg-background px-3 py-2" /></label>
                                        <label className="space-y-1 text-sm">{generation.kind === 'concepts' ? 'App-local code' : 'Code'}<input name={`items[${index}][code]`} defaultValue={previous?.code ?? item.code} readOnly={locked} className="w-full rounded-md border bg-background px-3 py-2" /></label>
                                        {generation.kind === 'topics' && <label className="space-y-1 text-sm sm:col-span-2">Parent proposal<select name={`items[${index}][parent_draft_id]`} defaultValue={previous?.parent_draft_id ?? item.parent_draft_id ?? ''} disabled={locked} className="w-full rounded-md border bg-background px-3 py-2"><option value="">Top level</option>{items.filter((parent) => parent.draft_id !== item.draft_id).map((parent) => <option key={parent.draft_id} value={parent.draft_id}>{parent.code ? `${parent.code} · ` : ''}{parent.title}</option>)}</select></label>}
                                        {generation.scope_snapshot && <label className="space-y-1 text-sm sm:col-span-2">Syllabus topic<select name={`items[${index}][syllabus_topic_id]`} defaultValue={previous?.syllabus_topic_id ?? item.syllabus_topic_id ?? ''} disabled={locked} className="w-full rounded-md border bg-background px-3 py-2"><option value="">Choose a topic</option>{generation.scope_snapshot.topics.map((topic) => <option key={topic.id} value={topic.id}>{topic.path.map((part) => part.code ? `${part.code} · ${part.title}` : part.title).join(' / ')}</option>)}</select></label>}
                                        <label className="space-y-1 text-sm sm:col-span-2">Description<textarea name={`items[${index}][description]`} defaultValue={previous?.description ?? item.description ?? ''} readOnly={locked} rows={2} className="w-full rounded-md border bg-background px-3 py-2" /></label>
                                        <label className="space-y-1 text-sm">Sort order<input type="number" name={`items[${index}][sort_order]`} defaultValue={previous?.sort_order ?? item.sort_order ?? index} min={0} readOnly={locked} className="w-full rounded-md border bg-background px-3 py-2" /></label>
                                    </div>
                                </div>
                                <p className="text-sm text-muted-foreground">Draft decision: {previous?.decision ?? 'awaiting review'}{previous?.canonical_id ? ` · canonical record #${previous.canonical_id}` : ''}{previous?.decision === 'approved' && <span className="ml-2 inline-flex rounded-full bg-green-600/10 px-2 py-0.5 text-xs font-medium text-green-700">Author approved</span>}</p>
                                {previous?.review_notes?.map((note) => <p key={note} className="text-sm text-amber-700">{note}</p>)}
                                {item.review_notes?.map((note) => <p key={note} className="text-sm text-amber-700">{note}</p>)}
                                {(item.citations ?? []).length > 0 && <ul className="space-y-2 text-sm">{item.citations?.map((citation) => <li key={`${citation.source_file_id}:${citation.page_number}`} className="rounded-md bg-muted/40 p-3"><p className="font-medium">{files.get(citation.source_file_id) ?? 'Source file'} · page {citation.page_number} · {citation.verification ?? item.citation_status ?? 'unverified'}</p>{citation.excerpt && <p className="mt-1 whitespace-pre-wrap text-muted-foreground">{citation.excerpt}</p>}</li>)}</ul>}
                                {(item.citations ?? []).length === 0 && <p className="text-sm text-amber-700">{generation.kind === 'concepts' ? 'No program-file sources were used. Review factual accuracy and topic fit before approval.' : 'No page citations supplied. Verify against the source files before approval.'}</p>}
                            </article>;
                        })}
                    </section>
                    <Button type="submit" disabled={processing}>{processing ? 'Saving review…' : 'Save review decisions'}</Button>
                </>}
            </Form>}
            {generation.reviewed_proposal && !canReview && <section className="space-y-3 rounded-xl border p-5"><h2 className="text-xl font-semibold">Review result</h2><p className="text-sm text-muted-foreground">Reviewed by {generation.reviewer?.name ?? 'a reviewer'}{generation.reviewed_at ? ` · ${new Date(generation.reviewed_at).toLocaleString()}` : ''}</p>{generation.reviewed_proposal.items.map((item) => { const topic = generation.scope_snapshot?.topics.find((candidate) => candidate.id === item.syllabus_topic_id); return <article key={item.draft_id} className="border-t pt-3"><p className="font-medium">{item.code} · {item.name ?? item.title} <span className="font-normal text-muted-foreground">({item.decision})</span>{item.decision === 'approved' && <span className="ml-2 inline-flex rounded-full bg-green-600/10 px-2 py-0.5 text-xs font-medium text-green-700">Author approved</span>}</p>{topic && <p className="text-sm text-muted-foreground">{topic.path.map((part) => part.title).join(' / ')}</p>}{item.review_notes.map((note) => <p key={note} className="text-sm text-amber-700">{note}</p>)}</article>; })}</section>}
            <Button asChild variant="outline"><Link href={generations.index(program.id)}>Back to proposals</Link></Button>
        </main>
    );
}

ShowCurriculumGeneration.layout = (props: { program: { id: number }; generation: { id: number } }) => ({ breadcrumbs: [
    { title: 'Programs', href: programs.index() },
    { title: 'Program', href: programs.show(props.program) },
    { title: 'Curriculum proposals', href: generations.index(props.program) },
    { title: 'Proposal', href: generations.show({ program: props.program, curriculumGeneration: props.generation }) },
] });
