import { Form, Head, Link, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import programs from '@/routes/programs';
import generations from '@/routes/programs/curriculum-generations';

type TopicSnapshot = {
    id: number;
    parent_id: number | null;
    code: string | null;
    title: string;
    description: string | null;
    path: { id: number; code: string | null; title: string }[];
};
type ProposalItem = {
    draft_id: string;
    concept_id?: number;
    name?: string;
    title?: string;
    code?: string;
    description: string | null;
    summary?: string;
    sections?: {
        heading: string;
        explanation: string;
        example: null | { scenario: string; steps: string[]; takeaway: string };
    }[];
    common_misconceptions?: { misconception: string; correction: string }[];
    recap?: string;
    parent_draft_id?: string | null;
    syllabus_topic_id?: number | null;
    sort_order?: number;
    citations?: {
        source_file_id: number;
        page_number: number;
        verification?: string;
        excerpt?: string | null;
    }[];
    citation_status?: string;
    notes?: string[];
    review_notes?: string[];
};
type ExistingLesson = {
    id: number;
    title: string;
    generation_id: number | null;
};

export default function ShowCurriculumGeneration({
    program,
    generation,
    sourceFiles,
    proposalHash,
    canReview,
    existingLessonsByConcept,
}: {
    program: { id: number; name: string; code: string };
    generation: {
        id: number;
        kind: string;
        status: string;
        model: string;
        provider: string;
        error_code: string | null;
        error_message: string | null;
        request_context: string | null;
        ungrounded_requested: boolean;
        proposal: { items: ProposalItem[]; notes?: string[] } | null;
        scope_snapshot: {
            scope_type?: string;
            scope_id?: number;
            target_concept_ids?: number[];
            concepts?: {
                id: number;
                topic_id: number | null;
                title: string;
                code: string;
                description: string | null;
                learning_objectives: { id: number; description: string }[];
            }[];
            subject: { id: number; name: string; code: string | null };
            topics: TopicSnapshot[];
        } | null;
        reviewed_proposal: {
            items: (ProposalItem & {
                decision: string;
                canonical_id: number | null;
                review_notes: string[];
            })[];
        } | null;
        requester: { name: string } | null;
        reviewer: { name: string } | null;
        created_at: string;
        reviewed_at: string | null;
    };
    sourceFiles: {
        id: number;
        title: string;
        file_type: string;
        content_hash: string;
        page_count: number;
    }[];
    proposalHash: string | null;
    canReview: boolean;
    existingLessonsByConcept: Record<number, ExistingLesson[]>;
}) {
    const active = ['queued', 'generating'].includes(generation.status);
    const { start, stop } = usePoll(
        3000,
        {
            only: [
                'generation',
                'sourceFiles',
                'proposalHash',
                'canReview',
                'existingLessonsByConcept',
            ],
        },
        { autoStart: false, mode: 'rest' },
    );
    useEffect(() => {
        if (active) start();
        else stop();
        return stop;
    }, [active, start, stop]);
    const files = new Map(sourceFiles.map((file) => [file.id, file.title]));
    const targetConcepts = new Map(
        (generation.scope_snapshot?.concepts ?? []).map((concept) => [
            concept.id,
            concept,
        ]),
    );
    const items = generation.proposal?.items ?? [];
    const decisions = generation.reviewed_proposal?.items ?? [];
    const decisionCounts = decisions.reduce<Record<string, number>>(
        (counts, item) => {
            counts[item.decision] = (counts[item.decision] ?? 0) + 1;
            return counts;
        },
        {},
    );
    const routeParams = {
        program: program.id,
        curriculumGeneration: generation.id,
    };
    const statusLabel =
        generation.status === 'reviewing'
            ? canReview
                ? 'Awaiting your review'
                : 'Under review'
            : generation.status.replaceAll('_', ' ');

    return (
        <PageShell className="max-w-5xl">
            <Head title={`Curriculum proposal · ${program.name}`} />
            <PageHeader
                eyebrow={`${program.code} · ${generation.kind} proposal`}
                title="Curriculum proposal"
                description={`${items.length} ${items.length === 1 ? 'proposed item' : 'proposed items'} · Requested by ${generation.requester?.name ?? 'unknown'} · ${new Date(generation.created_at).toLocaleString()}`}
                actions={
                    <Button variant="outline" asChild>
                        <Link href={generations.index(program.id)}>
                            All proposals
                        </Link>
                    </Button>
                }
            />
            <div
                role="status"
                aria-label="Proposal status"
                className="flex flex-wrap items-center gap-2 rounded-md border bg-muted/30 px-4 py-3 text-sm"
            >
                <span className="font-medium">Status</span>
                <span className="rounded-full border bg-background px-2.5 py-1 capitalize">
                    {statusLabel}
                </span>
                {generation.reviewer && (
                    <span className="text-muted-foreground">
                        Reviewer: {generation.reviewer.name}
                    </span>
                )}
                {generation.reviewed_at && (
                    <span className="text-muted-foreground">
                        Reviewed{' '}
                        {new Date(generation.reviewed_at).toLocaleString()}
                    </span>
                )}
            </div>
            {generation.status === 'stale' && (
                <section
                    role="alert"
                    className="space-y-1 rounded-md border border-amber-600/30 bg-amber-600/5 p-4 text-sm"
                >
                    <h2 className="font-semibold">
                        This proposal needs a fresh review
                    </h2>
                    <p>
                        {generation.error_code === 'scope_changed'
                            ? 'The curriculum context changed after this proposal was generated, so its draft cannot be approved against the earlier scope.'
                            : (generation.error_message ??
                              'The proposal changed after this review page was loaded.')}{' '}
                        Return to the program, confirm the current curriculum,
                        and create a new proposal before reviewing again.
                    </p>
                </section>
            )}
            {decisions.length > 0 && (
                <section
                    aria-label="Review decision summary"
                    className="flex flex-wrap gap-x-5 gap-y-2 rounded-md border px-4 py-3 text-sm"
                >
                    <span className="font-medium">Review progress</span>
                    {['approved', 'rejected', 'conflict', 'pending']
                        .filter((decision) => decisionCounts[decision])
                        .map((decision) => (
                            <span key={decision} className="capitalize">
                                {decision}: {decisionCounts[decision]}
                            </span>
                        ))}
                </section>
            )}
            <Card className="py-0">
                <CardContent className="py-5">
                    <dl className="grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-muted-foreground">
                                Provider and model
                            </dt>
                            <dd className="font-medium">
                                {generation.provider} · {generation.model}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Source mode
                            </dt>
                            <dd className="font-medium">
                                {generation.kind === 'concepts' ||
                                generation.kind === 'lessons'
                                    ? 'LLM knowledge · no selected source materials'
                                    : generation.ungrounded_requested
                                      ? 'Ungrounded draft'
                                      : 'Source grounded'}
                            </dd>
                        </div>
                        {generation.kind !== 'concepts' &&
                            generation.kind !== 'lessons' && (
                                <div className="sm:col-span-2">
                                    <dt className="text-muted-foreground">
                                        Source snapshot
                                    </dt>
                                    <dd className="mt-1 space-y-1">
                                        {sourceFiles.map((file) => (
                                            <p key={file.id}>
                                                {file.title} ·{' '}
                                                {file.file_type.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}{' '}
                                                · {file.page_count} pages ·{' '}
                                                <span
                                                    className="font-mono text-xs break-all"
                                                    title={file.content_hash}
                                                >
                                                    {file.content_hash}
                                                </span>
                                            </p>
                                        ))}
                                    </dd>
                                </div>
                            )}
                    </dl>
                </CardContent>
            </Card>
            {generation.kind === 'lessons' && generation.scope_snapshot && (
                <section className="space-y-3 rounded-xl border bg-card p-4">
                    <h2 className="font-semibold">
                        Lesson scope: {generation.scope_snapshot.scope_type} ·{' '}
                        {generation.scope_snapshot.subject.name}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Lessons use LLM knowledge and the selected curriculum
                        context, without selected source materials.
                    </p>
                    <ul className="space-y-2">
                        {(generation.scope_snapshot.concepts ?? []).map(
                            (concept) => {
                                const topic =
                                    generation.scope_snapshot?.topics.find(
                                        (candidate) =>
                                            candidate.id === concept.topic_id,
                                    );
                                return (
                                    <li key={concept.id} className="text-sm">
                                        <span className="font-medium">
                                            {concept.code} · {concept.title}
                                        </span>
                                        {topic && (
                                            <p className="text-muted-foreground">
                                                {topic.path
                                                    .map((part) => part.title)
                                                    .join(' / ')}
                                            </p>
                                        )}
                                        {concept.description ? (
                                            <p className="text-muted-foreground">
                                                {concept.description}
                                            </p>
                                        ) : (
                                            <p className="text-muted-foreground">
                                                No concept description is
                                                available.
                                            </p>
                                        )}
                                        {concept.learning_objectives.length >
                                            0 && (
                                            <ul className="mt-1 list-disc pl-5 text-muted-foreground">
                                                {concept.learning_objectives.map(
                                                    (objective) => (
                                                        <li key={objective.id}>
                                                            {
                                                                objective.description
                                                            }
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        )}
                                    </li>
                                );
                            },
                        )}
                    </ul>
                </section>
            )}
            {generation.scope_snapshot && generation.kind === 'concepts' && (
                <section className="space-y-3 rounded-xl border bg-card p-4">
                    <div>
                        <h2 className="font-semibold">
                            Subject scope:{' '}
                            {generation.scope_snapshot.subject.code
                                ? `${generation.scope_snapshot.subject.code} · `
                                : ''}
                            {generation.scope_snapshot.subject.name}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Complete topic tree captured for this request.
                            Concepts are generated from LLM knowledge and are
                            not grounded in selected program files.
                        </p>
                    </div>
                    <ul className="space-y-2">
                        {generation.scope_snapshot.topics.map((topic) => (
                            <li key={topic.id} className="text-sm">
                                <span className="text-muted-foreground">
                                    {topic.path
                                        .map((part) =>
                                            part.code
                                                ? `${part.code} ${part.title}`
                                                : part.title,
                                        )
                                        .join(' / ')}
                                </span>
                                {topic.description && (
                                    <p className="mt-1 text-muted-foreground">
                                        {topic.description}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            )}
            {generation.request_context && (
                <p className="rounded-md border p-4 text-sm">
                    <span className="font-medium">Request context: </span>
                    {generation.request_context}
                </p>
            )}
            <p className="rounded-md border bg-muted/30 p-4 text-sm text-muted-foreground">
                AI output remains a private proposal until an author approves
                individual items. Approval records the author’s decision; it
                does not certify official completeness, factual correctness, or
                PRC compliance.
                {generation.kind !== 'concepts' &&
                    ' A citation marked valid only matches a selected source file and page.'}
            </p>
            {generation.error_message && (
                <p
                    role="alert"
                    className="rounded-md border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive"
                >
                    {generation.error_message}
                </p>
            )}
            {active && (
                <p
                    role="status"
                    className="rounded-md border p-4 text-sm text-muted-foreground"
                >
                    The proposal is generating. This page refreshes
                    automatically.
                </p>
            )}
            {generation.proposal?.notes &&
                generation.proposal.notes.length > 0 && (
                    <section className="rounded-md border border-amber-600/30 bg-amber-600/5 p-4 text-sm">
                        <h2 className="font-semibold">Review notes</h2>
                        <ul className="mt-2 list-disc space-y-1 pl-5">
                            {generation.proposal.notes.map((note, index) => (
                                <li key={index}>{note}</li>
                            ))}
                        </ul>
                    </section>
                )}
            {canReview && generation.proposal && proposalHash && (
                <Form
                    {...generations.review.form(routeParams)}
                    className="space-y-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="proposal_hash"
                                value={proposalHash}
                            />
                            {Object.keys(errors).length > 0 && (
                                <div
                                    role="alert"
                                    className="space-y-1 rounded-md border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive"
                                >
                                    <p className="font-medium">
                                        The review could not be saved.
                                    </p>
                                    {errors.proposal_hash && (
                                        <p>
                                            {errors.proposal_hash} Reload the
                                            proposal and reapply your edits.
                                        </p>
                                    )}
                                    {errors.generation && (
                                        <p>{errors.generation}</p>
                                    )}
                                    {errors.items && <p>{errors.items}</p>}
                                    {Object.entries(errors)
                                        .filter(
                                            ([key]) =>
                                                ![
                                                    'proposal_hash',
                                                    'generation',
                                                    'items',
                                                ].includes(key),
                                        )
                                        .map(([key, message]) => (
                                            <p key={key}>{message}</p>
                                        ))}
                                </div>
                            )}
                            <section className="space-y-4">
                                <div>
                                    <h2 className="text-xl font-semibold">
                                        Review proposed items
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Review each item separately. Approving
                                        creates its canonical curriculum record;
                                        conflicts stay open for another review.
                                    </p>
                                </div>
                                {items.map((item, index) => {
                                    const name = item.name ?? item.title ?? '';
                                    const previous =
                                        generation.reviewed_proposal?.items.find(
                                            (reviewed) =>
                                                reviewed.draft_id ===
                                                item.draft_id,
                                        );
                                    const locked = [
                                        'approved',
                                        'rejected',
                                    ].includes(previous?.decision ?? '');
                                    const existingLessons =
                                        existingLessonsByConcept[
                                            item.concept_id ?? -1
                                        ] ?? [];
                                    return (
                                        <article
                                            key={item.draft_id}
                                            aria-labelledby={`proposal-item-${index}-title`}
                                            className="space-y-4 rounded-xl border bg-card p-4 sm:p-5"
                                        >
                                            <div className="flex flex-wrap items-start justify-between gap-3 border-b pb-3">
                                                <div>
                                                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                        Item {index + 1} of{' '}
                                                        {items.length}
                                                    </p>
                                                    <h3
                                                        id={`proposal-item-${index}-title`}
                                                        className="mt-1 font-semibold"
                                                    >
                                                        {name ||
                                                            'Untitled proposal item'}
                                                    </h3>
                                                </div>
                                                <span className="rounded-full border px-2.5 py-1 text-xs capitalize">
                                                    {previous?.decision ??
                                                        'awaiting review'}
                                                </span>
                                            </div>
                                            {generation.kind === 'lessons' && (
                                                <p className="text-sm text-muted-foreground">
                                                    For concept:{' '}
                                                    {targetConcepts.get(
                                                        item.concept_id ?? -1,
                                                    )?.title ??
                                                        'Selected concept'}
                                                </p>
                                            )}
                                            {generation.kind === 'lessons' &&
                                                existingLessons.length > 0 && (
                                                    <div
                                                        role="note"
                                                        className="space-y-1 rounded-md border border-amber-600/30 bg-amber-600/5 p-3 text-sm"
                                                    >
                                                        <p className="font-medium text-amber-800">
                                                            Existing lessons are
                                                            already attached to
                                                            this concept. A
                                                            case-insensitive
                                                            title match will be
                                                            marked as a conflict
                                                            and cannot be
                                                            approved.
                                                        </p>
                                                        <ul className="list-disc pl-5 text-amber-800">
                                                            {existingLessons.map(
                                                                (lesson) => (
                                                                    <li
                                                                        key={
                                                                            lesson.id
                                                                        }
                                                                    >
                                                                        {
                                                                            lesson.title
                                                                        }{' '}
                                                                        · lesson
                                                                        #
                                                                        {
                                                                            lesson.id
                                                                        }
                                                                        {lesson.generation_id !==
                                                                        null
                                                                            ? ` · generation #${lesson.generation_id}`
                                                                            : ' · added outside generation'}
                                                                    </li>
                                                                ),
                                                            )}
                                                        </ul>
                                                    </div>
                                                )}
                                            <input
                                                type="hidden"
                                                name={`items[${index}][draft_id]`}
                                                value={item.draft_id}
                                            />
                                            {locked ? (
                                                <input
                                                    type="hidden"
                                                    name={`items[${index}][include]`}
                                                    value={
                                                        previous?.decision ===
                                                        'approved'
                                                            ? '1'
                                                            : '0'
                                                    }
                                                />
                                            ) : (
                                                <fieldset className="grid gap-3 rounded-md bg-muted/30 p-3">
                                                    <legend className="text-sm font-medium">
                                                        Choose a decision for
                                                        this item
                                                    </legend>
                                                    <label
                                                        htmlFor={`proposal-item-${index}-approve`}
                                                        className="flex items-start gap-3 text-sm"
                                                    >
                                                        <input
                                                            id={`proposal-item-${index}-approve`}
                                                            type="radio"
                                                            name={`items[${index}][include]`}
                                                            value="1"
                                                            required
                                                            defaultChecked={
                                                                previous?.decision ===
                                                                    'pending' ||
                                                                previous?.decision ===
                                                                    'conflict'
                                                            }
                                                            className="mt-0.5 size-4 accent-primary focus-visible:outline-2 focus-visible:outline-ring"
                                                        />
                                                        <span>
                                                            Approve and add this
                                                            item to the
                                                            canonical curriculum
                                                        </span>
                                                    </label>
                                                    <label
                                                        htmlFor={`proposal-item-${index}-reject`}
                                                        className="flex items-start gap-3 text-sm"
                                                    >
                                                        <input
                                                            id={`proposal-item-${index}-reject`}
                                                            type="radio"
                                                            name={`items[${index}][include]`}
                                                            value="0"
                                                            required
                                                            defaultChecked={
                                                                previous?.decision ===
                                                                'rejected'
                                                            }
                                                            className="mt-0.5 size-4 accent-primary focus-visible:outline-2 focus-visible:outline-ring"
                                                        />
                                                        <span>
                                                            Reject this draft
                                                        </span>
                                                    </label>
                                                </fieldset>
                                            )}
                                            <div className="flex items-start gap-3">
                                                <div className="grid flex-1 gap-3 sm:grid-cols-2">
                                                    <label className="space-y-1 text-sm">
                                                        {generation.kind ===
                                                        'subjects'
                                                            ? 'Subject name'
                                                            : 'Title'}
                                                        <input
                                                            name={`items[${index}][${generation.kind === 'subjects' ? 'name' : 'title'}]`}
                                                            defaultValue={
                                                                previous?.[
                                                                    generation.kind ===
                                                                    'subjects'
                                                                        ? 'name'
                                                                        : 'title'
                                                                ] ?? name
                                                            }
                                                            readOnly={locked}
                                                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                                        />
                                                    </label>
                                                    {generation.kind !==
                                                        'lessons' && (
                                                        <label className="space-y-1 text-sm">
                                                            {generation.kind ===
                                                            'concepts'
                                                                ? 'App-local code'
                                                                : 'Code'}
                                                            <input
                                                                name={`items[${index}][code]`}
                                                                defaultValue={
                                                                    previous?.code ??
                                                                    item.code ??
                                                                    ''
                                                                }
                                                                readOnly={
                                                                    locked
                                                                }
                                                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                                            />
                                                        </label>
                                                    )}
                                                    {generation.kind ===
                                                        'topics' && (
                                                        <label className="space-y-1 text-sm sm:col-span-2">
                                                            Parent proposal
                                                            <select
                                                                name={`items[${index}][parent_draft_id]`}
                                                                defaultValue={
                                                                    previous?.parent_draft_id ??
                                                                    item.parent_draft_id ??
                                                                    ''
                                                                }
                                                                disabled={
                                                                    locked
                                                                }
                                                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                                            >
                                                                <option value="">
                                                                    Top level
                                                                </option>
                                                                {items
                                                                    .filter(
                                                                        (
                                                                            parent,
                                                                        ) =>
                                                                            parent.draft_id !==
                                                                            item.draft_id,
                                                                    )
                                                                    .map(
                                                                        (
                                                                            parent,
                                                                        ) => (
                                                                            <option
                                                                                key={
                                                                                    parent.draft_id
                                                                                }
                                                                                value={
                                                                                    parent.draft_id
                                                                                }
                                                                            >
                                                                                {parent.code
                                                                                    ? `${parent.code} · `
                                                                                    : ''}
                                                                                {
                                                                                    parent.title
                                                                                }
                                                                            </option>
                                                                        ),
                                                                    )}
                                                            </select>
                                                        </label>
                                                    )}
                                                    {generation.kind ===
                                                        'concepts' &&
                                                        generation.scope_snapshot && (
                                                            <label className="space-y-1 text-sm sm:col-span-2">
                                                                Syllabus topic
                                                                <select
                                                                    name={`items[${index}][syllabus_topic_id]`}
                                                                    defaultValue={
                                                                        previous?.syllabus_topic_id ??
                                                                        item.syllabus_topic_id ??
                                                                        ''
                                                                    }
                                                                    disabled={
                                                                        locked
                                                                    }
                                                                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                                                >
                                                                    <option value="">
                                                                        Choose a
                                                                        topic
                                                                    </option>
                                                                    {generation.scope_snapshot.topics.map(
                                                                        (
                                                                            topic,
                                                                        ) => (
                                                                            <option
                                                                                key={
                                                                                    topic.id
                                                                                }
                                                                                value={
                                                                                    topic.id
                                                                                }
                                                                            >
                                                                                {topic.path
                                                                                    .map(
                                                                                        (
                                                                                            part,
                                                                                        ) =>
                                                                                            part.code
                                                                                                ? `${part.code} · ${part.title}`
                                                                                                : part.title,
                                                                                    )
                                                                                    .join(
                                                                                        ' / ',
                                                                                    )}
                                                                            </option>
                                                                        ),
                                                                    )}
                                                                </select>
                                                            </label>
                                                        )}
                                                    {generation.kind ===
                                                    'lessons' ? (
                                                        <>
                                                            <label className="space-y-1 text-sm sm:col-span-2">
                                                                Summary
                                                                <textarea
                                                                    name={`items[${index}][summary]`}
                                                                    defaultValue={
                                                                        previous?.summary ??
                                                                        item.summary ??
                                                                        ''
                                                                    }
                                                                    readOnly={
                                                                        locked
                                                                    }
                                                                    rows={2}
                                                                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                                                />
                                                            </label>
                                                            {(
                                                                previous?.sections ??
                                                                item.sections ??
                                                                []
                                                            ).map(
                                                                (
                                                                    section,
                                                                    sectionIndex,
                                                                ) => (
                                                                    <div
                                                                        key={
                                                                            sectionIndex
                                                                        }
                                                                        className="grid gap-2 rounded-md border p-3 sm:col-span-2"
                                                                    >
                                                                        <label className="text-sm">
                                                                            Section
                                                                            heading
                                                                            <input
                                                                                name={`items[${index}][sections][${sectionIndex}][heading]`}
                                                                                defaultValue={
                                                                                    section.heading
                                                                                }
                                                                                readOnly={
                                                                                    locked
                                                                                }
                                                                                className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                                                            />
                                                                        </label>
                                                                        <label className="text-sm">
                                                                            Explanation
                                                                            <textarea
                                                                                name={`items[${index}][sections][${sectionIndex}][explanation]`}
                                                                                defaultValue={
                                                                                    section.explanation
                                                                                }
                                                                                readOnly={
                                                                                    locked
                                                                                }
                                                                                rows={
                                                                                    4
                                                                                }
                                                                                className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                                                            />
                                                                        </label>
                                                                        {section.example && (
                                                                            <div className="grid gap-2 border-t pt-2">
                                                                                <p className="text-sm font-medium">
                                                                                    Worked
                                                                                    example
                                                                                </p>
                                                                                <label className="text-sm">
                                                                                    Scenario
                                                                                    <textarea
                                                                                        name={`items[${index}][sections][${sectionIndex}][example][scenario]`}
                                                                                        defaultValue={
                                                                                            section
                                                                                                .example
                                                                                                .scenario
                                                                                        }
                                                                                        readOnly={
                                                                                            locked
                                                                                        }
                                                                                        className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                                                                    />
                                                                                </label>
                                                                                {section.example.steps.map(
                                                                                    (
                                                                                        step,
                                                                                        stepIndex,
                                                                                    ) => (
                                                                                        <label
                                                                                            key={
                                                                                                stepIndex
                                                                                            }
                                                                                            className="text-sm"
                                                                                        >
                                                                                            Step{' '}
                                                                                            {stepIndex +
                                                                                                1}
                                                                                            <input
                                                                                                name={`items[${index}][sections][${sectionIndex}][example][steps][${stepIndex}]`}
                                                                                                defaultValue={
                                                                                                    step
                                                                                                }
                                                                                                readOnly={
                                                                                                    locked
                                                                                                }
                                                                                                className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                                                                            />
                                                                                        </label>
                                                                                    ),
                                                                                )}
                                                                                <label className="text-sm">
                                                                                    Takeaway
                                                                                    <textarea
                                                                                        name={`items[${index}][sections][${sectionIndex}][example][takeaway]`}
                                                                                        defaultValue={
                                                                                            section
                                                                                                .example
                                                                                                .takeaway
                                                                                        }
                                                                                        readOnly={
                                                                                            locked
                                                                                        }
                                                                                        className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                                                                    />
                                                                                </label>
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                ),
                                                            )}
                                                            {(
                                                                previous?.common_misconceptions ??
                                                                item.common_misconceptions ??
                                                                []
                                                            ).map(
                                                                (
                                                                    entry,
                                                                    entryIndex,
                                                                ) => (
                                                                    <div
                                                                        key={
                                                                            entryIndex
                                                                        }
                                                                        className="grid gap-2 sm:col-span-2"
                                                                    >
                                                                        <label className="text-sm">
                                                                            Common
                                                                            misconception
                                                                            <textarea
                                                                                name={`items[${index}][common_misconceptions][${entryIndex}][misconception]`}
                                                                                defaultValue={
                                                                                    entry.misconception
                                                                                }
                                                                                readOnly={
                                                                                    locked
                                                                                }
                                                                                className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                                                            />
                                                                        </label>
                                                                        <label className="text-sm">
                                                                            Correction
                                                                            <textarea
                                                                                name={`items[${index}][common_misconceptions][${entryIndex}][correction]`}
                                                                                defaultValue={
                                                                                    entry.correction
                                                                                }
                                                                                readOnly={
                                                                                    locked
                                                                                }
                                                                                className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                                                            />
                                                                        </label>
                                                                    </div>
                                                                ),
                                                            )}
                                                            <label className="space-y-1 text-sm sm:col-span-2">
                                                                Recap
                                                                <textarea
                                                                    name={`items[${index}][recap]`}
                                                                    defaultValue={
                                                                        previous?.recap ??
                                                                        item.recap ??
                                                                        ''
                                                                    }
                                                                    readOnly={
                                                                        locked
                                                                    }
                                                                    rows={2}
                                                                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                                                />
                                                            </label>
                                                        </>
                                                    ) : (
                                                        <label className="space-y-1 text-sm sm:col-span-2">
                                                            Description
                                                            <textarea
                                                                name={`items[${index}][description]`}
                                                                defaultValue={
                                                                    previous?.description ??
                                                                    item.description ??
                                                                    ''
                                                                }
                                                                readOnly={
                                                                    locked
                                                                }
                                                                rows={2}
                                                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                                            />
                                                        </label>
                                                    )}
                                                    <label className="space-y-1 text-sm">
                                                        Sort order
                                                        <input
                                                            type="number"
                                                            name={`items[${index}][sort_order]`}
                                                            defaultValue={
                                                                previous?.sort_order ??
                                                                item.sort_order ??
                                                                index
                                                            }
                                                            min={0}
                                                            readOnly={locked}
                                                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                                        />
                                                    </label>
                                                </div>
                                            </div>
                                            <p className="text-sm text-muted-foreground">
                                                {locked
                                                    ? 'Final decision'
                                                    : 'Current decision'}
                                                :{' '}
                                                {previous?.decision ??
                                                    'awaiting review'}
                                                {previous?.canonical_id
                                                    ? ` · canonical record #${previous.canonical_id}`
                                                    : ''}
                                                {previous?.decision ===
                                                    'approved' && (
                                                    <span className="ml-2 inline-flex rounded-full bg-green-600/10 px-2 py-0.5 text-xs font-medium text-green-700">
                                                        Author approved
                                                    </span>
                                                )}
                                            </p>
                                            {previous?.review_notes?.map(
                                                (note) => (
                                                    <p
                                                        key={note}
                                                        className="text-sm text-amber-700"
                                                    >
                                                        {note}
                                                    </p>
                                                ),
                                            )}
                                            {item.review_notes?.map((note) => (
                                                <p
                                                    key={note}
                                                    className="text-sm text-amber-700"
                                                >
                                                    {note}
                                                </p>
                                            ))}
                                            {(item.citations ?? []).length >
                                                0 && (
                                                <ul className="space-y-2 text-sm">
                                                    {item.citations?.map(
                                                        (citation) => (
                                                            <li
                                                                key={`${citation.source_file_id}:${citation.page_number}`}
                                                                className="rounded-md bg-muted/40 p-3"
                                                            >
                                                                <p className="font-medium">
                                                                    {files.get(
                                                                        citation.source_file_id,
                                                                    ) ??
                                                                        'Source file'}{' '}
                                                                    · page{' '}
                                                                    {
                                                                        citation.page_number
                                                                    }{' '}
                                                                    ·{' '}
                                                                    {citation.verification ??
                                                                        item.citation_status ??
                                                                        'unverified'}
                                                                </p>
                                                                {citation.excerpt && (
                                                                    <p className="mt-1 whitespace-pre-wrap text-muted-foreground">
                                                                        {
                                                                            citation.excerpt
                                                                        }
                                                                    </p>
                                                                )}
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            )}
                                            {(item.citations ?? []).length ===
                                                0 && (
                                                <p className="text-sm text-amber-700">
                                                    {generation.kind ===
                                                    'concepts'
                                                        ? 'No program-file sources were used. Review factual accuracy and topic fit before approval.'
                                                        : 'No page citations supplied. Verify against the source files before approval.'}
                                                </p>
                                            )}
                                        </article>
                                    );
                                })}
                            </section>
                            <Button type="submit" disabled={processing}>
                                {processing
                                    ? 'Saving review…'
                                    : 'Save review decisions'}
                            </Button>
                        </>
                    )}
                </Form>
            )}
            {generation.reviewed_proposal && !canReview && (
                <section className="space-y-3 rounded-xl border p-5">
                    <h2 className="text-xl font-semibold">Review result</h2>
                    <p className="text-sm text-muted-foreground">
                        Reviewed by {generation.reviewer?.name ?? 'a reviewer'}
                        {generation.reviewed_at
                            ? ` · ${new Date(generation.reviewed_at).toLocaleString()}`
                            : ''}
                    </p>
                    {generation.reviewed_proposal.items.map((item, index) => {
                        const topic = generation.scope_snapshot?.topics.find(
                            (candidate) =>
                                candidate.id === item.syllabus_topic_id,
                        );
                        const existingLessons =
                            existingLessonsByConcept[item.concept_id ?? -1] ??
                            [];

                        return (
                            <article
                                key={item.draft_id}
                                className="space-y-3 border-t pt-4"
                            >
                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Item {index + 1}
                                </p>
                                <p className="font-medium">
                                    {generation.kind === 'lessons'
                                        ? item.title
                                        : `${item.code} · ${item.name ?? item.title}`}{' '}
                                    <span className="font-normal text-muted-foreground capitalize">
                                        ({item.decision})
                                    </span>
                                    {item.decision === 'approved' && (
                                        <span className="ml-2 inline-flex rounded-full bg-green-600/10 px-2 py-0.5 text-xs font-medium text-green-700">
                                            Author approved · not
                                            source-verified
                                        </span>
                                    )}
                                </p>
                                {generation.kind === 'lessons' && (
                                    <>
                                        <p className="text-sm">
                                            {item.summary}
                                        </p>
                                        {item.sections?.map(
                                            (section, sectionIndex) => (
                                                <div key={sectionIndex}>
                                                    <h3 className="font-medium">
                                                        {section.heading}
                                                    </h3>
                                                    <p className="text-sm whitespace-pre-wrap">
                                                        {section.explanation}
                                                    </p>
                                                </div>
                                            ),
                                        )}
                                        <p className="text-sm">
                                            Recap: {item.recap}
                                        </p>
                                        {existingLessons.length > 0 && (
                                            <p className="text-sm text-muted-foreground">
                                                Other lessons already existed
                                                for this concept at review time:{' '}
                                                {existingLessons
                                                    .map(
                                                        (lesson) =>
                                                            `${lesson.title} (lesson #${lesson.id}${lesson.generation_id !== null ? `, generation #${lesson.generation_id}` : ''})`,
                                                    )
                                                    .join('; ')}
                                                .
                                            </p>
                                        )}
                                    </>
                                )}
                                {topic && (
                                    <p className="text-sm text-muted-foreground">
                                        {topic.path
                                            .map((part) => part.title)
                                            .join(' / ')}
                                    </p>
                                )}
                                {item.review_notes?.map((note) => (
                                    <p
                                        key={note}
                                        className="text-sm text-amber-700"
                                    >
                                        {note}
                                    </p>
                                ))}
                            </article>
                        );
                    })}
                </section>
            )}
            {!active && !generation.error_message && !generation.proposal && (
                <p
                    role="status"
                    className="rounded-md border p-4 text-sm text-muted-foreground"
                >
                    No proposal content is available for this generation.
                </p>
            )}
            <Button asChild variant="outline">
                <Link href={programs.show(program)}>Back to program</Link>
            </Button>
        </PageShell>
    );
}

ShowCurriculumGeneration.layout = (props: {
    program: { id: number };
    generation: { id: number };
}) => ({
    breadcrumbs: [
        { title: 'Programs', href: programs.index() },
        { title: 'Program', href: programs.show(props.program) },
        {
            title: 'Curriculum proposals',
            href: generations.index(props.program),
        },
        {
            title: 'Proposal',
            href: generations.show({
                program: props.program,
                curriculumGeneration: props.generation,
            }),
        },
    ],
});
