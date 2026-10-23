import { Form, Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    programSubjects,
    subjectConcepts,
    subjectTopics,
} from '@/lib/curriculum-routes';
import programs from '@/routes/programs';
import generations from '@/routes/programs/curriculum-generations';

type SourceFile = {
    id: number;
    title: string;
    original_filename: string;
    mime_type: string;
    file_type: string;
    subject_id: number | null;
    subject_name: string | null;
    usable_pages_count: number;
};
type Topic = {
    id: number;
    subject_id: number;
    parent_id: number | null;
    title: string;
    code: string | null;
    description: string | null;
    sort_order: number;
};
type Concept = {
    id: number;
    subject_id: number;
    syllabus_topic_id: number | null;
    code: string;
    title: string;
    description: string | null;
    syllabus_topic: { title: string; code: string | null } | null;
    learning_objectives: { description: string }[];
};
type Subject = {
    id: number;
    name: string;
    code: string | null;
    syllabus_topics: Topic[];
    concepts: Concept[];
};

function TopicTree({
    topics,
    parentId = null,
    depth = 0,
}: {
    topics: Topic[];
    parentId?: number | null;
    depth?: number;
}) {
    const children = topics.filter((topic) => topic.parent_id === parentId);

    if (children.length === 0) {
        return null;
    }

    return (
        <ul
            className={
                depth === 0 ? 'space-y-2' : 'mt-2 space-y-2 border-l pl-4'
            }
        >
            {children.map((topic) => (
                <li key={topic.id} className="text-sm">
                    <span className="font-medium">
                        {topic.code ? `${topic.code} · ` : ''}
                        {topic.title}
                    </span>
                    {topic.description && (
                        <p className="mt-1 text-muted-foreground">
                            {topic.description}
                        </p>
                    )}
                    <TopicTree
                        topics={topics}
                        parentId={topic.id}
                        depth={depth + 1}
                    />
                </li>
            ))}
        </ul>
    );
}

function topicPath(topics: Topic[], topicId: number | null): string {
    const path: Topic[] = [];
    let topic = topics.find((candidate) => candidate.id === topicId);

    while (topic) {
        path.unshift(topic);
        topic = topics.find((candidate) => candidate.id === topic?.parent_id);
    }

    return (
        path
            .map((part) =>
                part.code ? `${part.code} · ${part.title}` : part.title,
            )
            .join(' / ') || 'No syllabus topic'
    );
}

export default function CreateCurriculumGeneration({
    program,
    subjects,
    files,
    generationEnabled,
    generationUnavailableReason,
    maxSourceFiles,
    models,
    defaultModel,
    kinds,
    initialKind,
    initialSubjectId,
    initialScopeType,
    initialScopeId,
}: {
    program: { id: number; name: string; code: string };
    subjects: Subject[];
    files: SourceFile[];
    generationEnabled: boolean;
    generationUnavailableReason: string | null;
    maxSourceFiles: number;
    models: string[];
    defaultModel: string | null;
    kinds: { value: string; label: string }[];
    initialKind: string | null;
    initialSubjectId: number | null;
    initialScopeType: string | null;
    initialScopeId: number | null;
}) {
    const [kind, setKind] = useState(
        initialKind ?? kinds[0]?.value ?? 'subjects',
    );
    const [subjectId, setSubjectId] = useState(
        initialSubjectId === null ? '' : String(initialSubjectId),
    );
    const [scopeType, setScopeType] = useState(initialScopeType ?? 'subject');
    const [scopeId, setScopeId] = useState(
        initialScopeId === null ? '' : String(initialScopeId),
    );
    const [selectedConceptIds, setSelectedConceptIds] = useState<number[]>(
        initialScopeType === 'concept' && initialScopeId
            ? [initialScopeId]
            : [],
    );
    const [selectedSourceFileIds, setSelectedSourceFileIds] = useState<
        number[]
    >([]);
    const selectedSubject = subjects.find(
        (subject) => String(subject.id) === subjectId,
    );
    const eligibleFiles = useMemo(
        () =>
            kind === 'subjects'
                ? files
                : files.filter(
                      (file) =>
                          file.subject_id === null ||
                          String(file.subject_id) === subjectId,
                  ),
        [files, kind, subjectId],
    );
    const isConceptGeneration = kind === 'concepts';
    const isLessonGeneration = kind === 'lessons';
    const requiresSubject = kind !== 'subjects';

    const eligibleConcepts = useMemo(() => {
        if (!selectedSubject) {
            return [];
        }

        const topics = selectedSubject.syllabus_topics;
        const descendants = new Set<number>();
        const addDescendants = (id: number) => {
            descendants.add(id);
            topics
                .filter((topic) => topic.parent_id === id)
                .forEach((topic) => addDescendants(topic.id));
        };

        if (scopeType === 'topic' && scopeId) {
            addDescendants(Number(scopeId));
        }

        return selectedSubject.concepts.filter((concept) =>
            scopeType === 'topic'
                ? concept.syllabus_topic_id !== null &&
                  descendants.has(concept.syllabus_topic_id)
                : scopeType === 'concept'
                  ? String(concept.id) === scopeId
                  : true,
        );
    }, [selectedSubject, scopeType, scopeId]);

    const cannotSubmit =
        !generationEnabled ||
        models.length === 0 ||
        (isConceptGeneration
            ? !selectedSubject || selectedSubject.syllabus_topics.length === 0
            : isLessonGeneration
              ? !selectedSubject || selectedConceptIds.length === 0
              : (requiresSubject && !selectedSubject) ||
                eligibleFiles.length === 0 ||
                selectedSourceFileIds.length === 0);

    return (
        <PageShell className="max-w-4xl">
            <Head title={`New curriculum proposal · ${program.name}`} />
            <PageHeader
                eyebrow={`${program.code} · Program workspace`}
                title="Prepare a curriculum proposal"
                description={
                    isConceptGeneration
                        ? 'Choose a subject and review its official syllabus outline before proposing instructional concepts.'
                        : isLessonGeneration
                          ? 'Select curriculum context and the concepts that should receive draft lessons.'
                          : 'Choose a proposal type and source files. Selected files are snapshotted so reviewers can verify the evidence used.'
                }
                actions={
                    <Button variant="outline" asChild>
                        <Link href={generations.index(program.id)}>
                            Back to proposals
                        </Link>
                    </Button>
                }
            />

            {!generationEnabled && (
                <p
                    role="status"
                    className="rounded-md border bg-muted/30 p-4 text-sm text-muted-foreground"
                >
                    {generationUnavailableReason ??
                        'Curriculum generation is currently unavailable.'}
                </p>
            )}

            {subjects.length === 0 && (
                <div
                    role="status"
                    className="flex flex-col gap-3 rounded-xl border border-dashed bg-muted/20 p-4 text-sm sm:flex-row sm:items-center sm:justify-between"
                >
                    <p className="text-muted-foreground">
                        This program has no subjects yet. Subject proposals can
                        be prepared at program level; add a subject first to
                        draft topics, concepts, or lessons.
                    </p>
                    <Button
                        asChild
                        size="sm"
                        variant="outline"
                        className="shrink-0"
                    >
                        <Link href={programSubjects.create(program)}>
                            Create a subject
                        </Link>
                    </Button>
                </div>
            )}

            <Form {...generations.store.form(program.id)} className="space-y-6">
                {({ processing, errors }) => (
                    <>
                        {Object.keys(errors).length > 0 && (
                            <div
                                role="alert"
                                className="space-y-1 rounded-md border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive"
                            >
                                <p className="font-medium">
                                    The proposal could not be started.
                                </p>
                                {Object.values(errors).map((message, index) => (
                                    <p key={index}>{message}</p>
                                ))}
                            </div>
                        )}

                        <Card className="py-0">
                            <CardContent className="grid gap-5 py-6 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <label
                                        htmlFor="proposal-kind"
                                        className="text-sm font-medium"
                                    >
                                        Proposal type
                                    </label>
                                    <select
                                        id="proposal-kind"
                                        name="kind"
                                        value={kind}
                                        onChange={(event) => {
                                            setKind(event.target.value);
                                            setSelectedConceptIds([]);
                                            setSelectedSourceFileIds([]);
                                            setScopeId('');
                                        }}
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                    >
                                        {kinds.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                    <p className="text-sm text-muted-foreground">
                                        Choose what kind of curriculum item the
                                        AI should draft.
                                    </p>
                                </div>

                                <div className="grid gap-2">
                                    <label
                                        htmlFor="proposal-subject"
                                        className="text-sm font-medium"
                                    >
                                        Subject
                                    </label>
                                    <select
                                        id="proposal-subject"
                                        name="subject_id"
                                        value={
                                            kind === 'subjects' ? '' : subjectId
                                        }
                                        required={requiresSubject}
                                        onChange={(event) => {
                                            setSubjectId(event.target.value);
                                            setScopeId('');
                                            setSelectedConceptIds([]);
                                            setSelectedSourceFileIds([]);
                                        }}
                                        disabled={kind === 'subjects'}
                                        aria-describedby="proposal-subject-hint"
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        <option value="">Program level</option>
                                        {subjects.map((subject) => (
                                            <option
                                                key={subject.id}
                                                value={subject.id}
                                            >
                                                {subject.code
                                                    ? `${subject.code} · `
                                                    : ''}
                                                {subject.name}
                                            </option>
                                        ))}
                                    </select>
                                    <p
                                        id="proposal-subject-hint"
                                        className="text-sm text-muted-foreground"
                                    >
                                        {kind === 'subjects'
                                            ? 'Subject proposals can use source files from any scope in this program.'
                                            : 'The subject determines which source files and curriculum items are available.'}
                                    </p>
                                </div>

                                <div className="grid gap-2 sm:col-span-2">
                                    <label
                                        htmlFor="proposal-model"
                                        className="text-sm font-medium"
                                    >
                                        Generation model
                                    </label>
                                    <select
                                        id="proposal-model"
                                        name="model"
                                        defaultValue={
                                            defaultModel ?? models[0] ?? ''
                                        }
                                        disabled={
                                            models.length === 0 ||
                                            !generationEnabled
                                        }
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        {models.map((model) => (
                                            <option key={model} value={model}>
                                                {model}
                                            </option>
                                        ))}
                                    </select>
                                    {models.length === 0 && (
                                        <p
                                            role="status"
                                            className="text-sm text-muted-foreground"
                                        >
                                            No generation model is available for
                                            this program.
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-2 sm:col-span-2">
                                    <label
                                        htmlFor="proposal-context"
                                        className="text-sm font-medium"
                                    >
                                        Additional instructions{' '}
                                        <span className="font-normal text-muted-foreground">
                                            (optional)
                                        </span>
                                    </label>
                                    <textarea
                                        id="proposal-context"
                                        name="request_context"
                                        rows={3}
                                        maxLength={4000}
                                        placeholder="Optional guidance for the reviewer-controlled draft"
                                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                    />
                                    <p className="text-sm text-muted-foreground">
                                        Instructions guide the draft; an
                                        authorized reviewer still decides which
                                        items become canonical.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        {isLessonGeneration ? (
                            <section
                                aria-labelledby="lesson-scope-heading"
                                className="space-y-5 rounded-xl border bg-card p-5"
                            >
                                <div>
                                    <h2
                                        id="lesson-scope-heading"
                                        className="text-lg font-semibold"
                                    >
                                        Lesson scope
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Lessons use LLM knowledge and the
                                        selected curriculum context. No source
                                        materials, web lookup, or citations are
                                        used.
                                    </p>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <label
                                            htmlFor="lesson-scope-type"
                                            className="text-sm font-medium"
                                        >
                                            Start from
                                        </label>
                                        <select
                                            id="lesson-scope-type"
                                            name="scope_type"
                                            value={scopeType}
                                            onChange={(event) => {
                                                setScopeType(
                                                    event.target.value,
                                                );
                                                setScopeId('');
                                                setSelectedConceptIds([]);
                                            }}
                                            className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                        >
                                            <option value="concept">
                                                One concept
                                            </option>
                                            <option value="topic">
                                                Topic and descendants
                                            </option>
                                            <option value="subject">
                                                Full subject
                                            </option>
                                        </select>
                                    </div>
                                    {scopeType !== 'subject' && (
                                        <div className="grid gap-2">
                                            <label
                                                htmlFor="lesson-scope-id"
                                                className="text-sm font-medium"
                                            >
                                                {scopeType === 'topic'
                                                    ? 'Syllabus topic'
                                                    : 'Concept'}
                                            </label>
                                            <select
                                                id="lesson-scope-id"
                                                name="scope_id"
                                                required
                                                value={scopeId}
                                                onChange={(event) => {
                                                    setScopeId(
                                                        event.target.value,
                                                    );
                                                    setSelectedConceptIds(
                                                        scopeType ===
                                                            'concept' &&
                                                            event.target.value
                                                            ? [
                                                                  Number(
                                                                      event
                                                                          .target
                                                                          .value,
                                                                  ),
                                                              ]
                                                            : [],
                                                    );
                                                }}
                                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                            >
                                                <option value="">
                                                    Choose{' '}
                                                    {scopeType === 'topic'
                                                        ? 'a syllabus topic'
                                                        : 'a concept'}
                                                </option>
                                                {scopeType === 'topic'
                                                    ? selectedSubject?.syllabus_topics.map(
                                                          (topic) => (
                                                              <option
                                                                  key={topic.id}
                                                                  value={
                                                                      topic.id
                                                                  }
                                                              >{`${'— '.repeat((topicPath(selectedSubject.syllabus_topics, topic.id).match(/ \/ /g) ?? []).length)}${topicPath(selectedSubject.syllabus_topics, topic.id)}`}</option>
                                                          ),
                                                      )
                                                    : selectedSubject?.concepts.map(
                                                          (concept) => (
                                                              <option
                                                                  key={
                                                                      concept.id
                                                                  }
                                                                  value={
                                                                      concept.id
                                                                  }
                                                              >
                                                                  {concept.code}{' '}
                                                                  ·{' '}
                                                                  {
                                                                      concept.title
                                                                  }{' '}
                                                                  —{' '}
                                                                  {topicPath(
                                                                      selectedSubject.syllabus_topics,
                                                                      concept.syllabus_topic_id,
                                                                  )}
                                                              </option>
                                                          ),
                                                      )}
                                            </select>
                                        </div>
                                    )}
                                </div>
                                {scopeType === 'subject' && (
                                    <input
                                        type="hidden"
                                        name="scope_id"
                                        value={subjectId}
                                    />
                                )}
                                <input
                                    type="hidden"
                                    name="syllabus_topic_id"
                                    value={scopeType === 'topic' ? scopeId : ''}
                                />

                                <fieldset className="space-y-3">
                                    <legend className="text-sm font-medium">
                                        Choose concepts to receive lessons (
                                        {selectedConceptIds.length}/5 selected)
                                    </legend>
                                    {eligibleConcepts.length === 0 ? (
                                        <div className="space-y-3 rounded-md border border-dashed p-4">
                                            <p className="text-sm text-muted-foreground">
                                                {selectedSubject
                                                    ? 'No concepts are available in this scope. Create concepts for this subject first.'
                                                    : 'Choose a subject to see available concepts.'}
                                            </p>
                                            {selectedSubject && (
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link
                                                        href={subjectConcepts.create(
                                                            selectedSubject,
                                                        )}
                                                    >
                                                        Create a concept
                                                    </Link>
                                                </Button>
                                            )}
                                        </div>
                                    ) : (
                                        eligibleConcepts.map((concept) => (
                                            <label
                                                key={concept.id}
                                                className="flex items-start gap-3 rounded-md border p-3 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    name="concept_ids[]"
                                                    value={concept.id}
                                                    checked={selectedConceptIds.includes(
                                                        concept.id,
                                                    )}
                                                    onChange={(event) =>
                                                        setSelectedConceptIds(
                                                            (current) =>
                                                                event.target
                                                                    .checked
                                                                    ? [
                                                                          ...current,
                                                                          concept.id,
                                                                      ]
                                                                    : current.filter(
                                                                          (
                                                                              id,
                                                                          ) =>
                                                                              id !==
                                                                              concept.id,
                                                                      ),
                                                        )
                                                    }
                                                    disabled={
                                                        !selectedConceptIds.includes(
                                                            concept.id,
                                                        ) &&
                                                        selectedConceptIds.length >=
                                                            5
                                                    }
                                                    className="mt-1 size-4 shrink-0"
                                                />
                                                <span>
                                                    <span className="font-medium">
                                                        {concept.code} ·{' '}
                                                        {concept.title}
                                                    </span>
                                                    <span className="block text-muted-foreground">
                                                        {topicPath(
                                                            selectedSubject?.syllabus_topics ??
                                                                [],
                                                            concept.syllabus_topic_id,
                                                        )}
                                                        {concept.description
                                                            ? ` · ${concept.description}`
                                                            : ' · No description'}
                                                    </span>
                                                    {concept.learning_objectives
                                                        .length > 0 && (
                                                        <span className="mt-1 block text-muted-foreground">
                                                            Objectives:{' '}
                                                            {concept.learning_objectives
                                                                .map(
                                                                    (
                                                                        objective,
                                                                    ) =>
                                                                        objective.description,
                                                                )
                                                                .join(' · ')}
                                                        </span>
                                                    )}
                                                </span>
                                            </label>
                                        ))
                                    )}
                                </fieldset>
                            </section>
                        ) : isConceptGeneration ? (
                            <section
                                aria-labelledby="concept-scope-heading"
                                className="space-y-3 rounded-xl border bg-card p-5"
                            >
                                <div>
                                    <h2
                                        id="concept-scope-heading"
                                        className="text-lg font-semibold"
                                    >
                                        Official syllabus scope
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Concepts are instructional content
                                        proposed against the official topic
                                        tree. They are not grounded in selected
                                        program files.
                                    </p>
                                </div>
                                {!selectedSubject ? (
                                    <p className="text-sm text-muted-foreground">
                                        Choose a subject to preview its syllabus
                                        topics.
                                    </p>
                                ) : selectedSubject.syllabus_topics.length ===
                                  0 ? (
                                    <div className="space-y-3 rounded-md border border-dashed p-4">
                                        <p className="text-sm text-muted-foreground">
                                            Add official syllabus topics to this
                                            subject before drafting concepts.
                                        </p>
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={subjectTopics.create(
                                                    selectedSubject,
                                                )}
                                            >
                                                Add official syllabus topic
                                            </Link>
                                        </Button>
                                    </div>
                                ) : (
                                    <TopicTree
                                        topics={selectedSubject.syllabus_topics}
                                    />
                                )}
                            </section>
                        ) : (
                            <section
                                aria-labelledby="source-files-heading"
                                className="space-y-4 rounded-xl border bg-card p-5"
                            >
                                <div>
                                    <h2
                                        id="source-files-heading"
                                        className="text-lg font-semibold"
                                    >
                                        Source files
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Select at least one uploaded file.
                                        Reviewers will see this source snapshot
                                        with the proposal.
                                    </p>
                                </div>
                                {eligibleFiles.length === 0 ? (
                                    <div className="space-y-3 rounded-md border border-dashed p-4">
                                        <p className="text-sm text-muted-foreground">
                                            No uploaded files are available in
                                            this scope yet.
                                        </p>
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={programs.show(program, {
                                                    query: { tab: 'files' },
                                                })}
                                            >
                                                Add a source file
                                            </Link>
                                        </Button>
                                    </div>
                                ) : (
                                    <>
                                        <p
                                            id="source-files-selection-status"
                                            role="status"
                                            className="text-sm text-muted-foreground"
                                        >
                                            {selectedSourceFileIds.length === 0
                                                ? `Select at least one source file to enable proposal generation. You can select up to ${maxSourceFiles}.`
                                                : `${selectedSourceFileIds.length} of ${maxSourceFiles} source files selected${selectedSourceFileIds.length >= maxSourceFiles ? ' · selection limit reached.' : '.'}`}
                                        </p>
                                        {eligibleFiles.map((file) => (
                                            <label
                                                key={file.id}
                                                className="flex items-start gap-3 rounded-md border p-3 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    name="source_file_ids[]"
                                                    value={file.id}
                                                    checked={selectedSourceFileIds.includes(
                                                        file.id,
                                                    )}
                                                    onChange={(event) =>
                                                        setSelectedSourceFileIds(
                                                            (current) =>
                                                                event.target
                                                                    .checked
                                                                    ? [
                                                                          ...current,
                                                                          file.id,
                                                                      ]
                                                                    : current.filter(
                                                                          (
                                                                              id,
                                                                          ) =>
                                                                              id !==
                                                                              file.id,
                                                                      ),
                                                        )
                                                    }
                                                    disabled={
                                                        !selectedSourceFileIds.includes(
                                                            file.id,
                                                        ) &&
                                                        selectedSourceFileIds.length >=
                                                            maxSourceFiles
                                                    }
                                                    aria-describedby="source-files-selection-status"
                                                    className="mt-1 size-4 shrink-0 accent-primary"
                                                />
                                                <span className="min-w-0 flex-1">
                                                    <span className="block font-medium">
                                                        {file.title}
                                                    </span>
                                                    <span className="block break-words text-muted-foreground">
                                                        {file.original_filename}{' '}
                                                        ·{' '}
                                                        {file.file_type.replaceAll(
                                                            '_',
                                                            ' ',
                                                        )}{' '}
                                                        ·{' '}
                                                        {file.subject_name ??
                                                            'Shared program file'}{' '}
                                                        ·{' '}
                                                        {
                                                            file.usable_pages_count
                                                        }{' '}
                                                        indexed text pages
                                                    </span>
                                                </span>
                                            </label>
                                        ))}
                                    </>
                                )}
                            </section>
                        )}

                        {isConceptGeneration && (
                            <input type="hidden" name="ungrounded" value="1" />
                        )}
                        {!isConceptGeneration && !isLessonGeneration && (
                            <label className="flex items-start gap-3 rounded-md border p-4 text-sm">
                                <input
                                    type="hidden"
                                    name="ungrounded"
                                    value="0"
                                />
                                <input
                                    type="checkbox"
                                    name="ungrounded"
                                    value="1"
                                    className="mt-1 size-4 shrink-0"
                                />
                                <span>
                                    <span className="font-medium">
                                        Allow an ungrounded draft
                                    </span>
                                    <span className="mt-1 block text-muted-foreground">
                                        Use only when selected files have no
                                        usable text. No file content will be
                                        sent; every item must be checked
                                        manually.
                                    </span>
                                </span>
                            </label>
                        )}

                        <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <Button asChild variant="outline">
                                <Link href={generations.index(program.id)}>
                                    Cancel
                                </Link>
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing || cannotSubmit}
                            >
                                {processing
                                    ? 'Queueing proposal…'
                                    : 'Generate proposal'}
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </PageShell>
    );
}

CreateCurriculumGeneration.layout = (props: { program: { id: number } }) => ({
    breadcrumbs: [
        { title: 'Programs', href: programs.index() },
        { title: 'Program', href: programs.show(props.program) },
        {
            title: 'Curriculum proposals',
            href: generations.index(props.program),
        },
        { title: 'Create', href: generations.create(props.program) },
    ],
});
