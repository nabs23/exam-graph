import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { EmptyState, PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { concepts, subjectConcepts, topicConcepts } from '@/lib/curriculum-routes';
import programRoutes from '@/routes/programs';

type Subject = { id: number; program_id: number; name: string; code: string | null };
type Topic = { id: number; subject_id: number; title: string };
type Concept = {
    id: number;
    subject_id: number;
    syllabus_topic_id: number | null;
    code: string;
    title: string;
    description: string | null;
    sort_order: number;
};

export default function ConceptForm({
    concept,
    subjects,
    topics,
    contextSubject,
    contextTopic,
}: {
    concept?: Concept;
    subjects: Subject[];
    topics: Topic[];
    contextSubject?: { id: number } | null;
    contextTopic?: { id: number } | null;
}) {
    const contextTopicSubjectId = contextTopic
        ? topics.find((topic) => topic.id === contextTopic.id)?.subject_id
        : undefined;
    const form = useForm({
        subject_id: concept?.subject_id ?? contextSubject?.id ?? contextTopicSubjectId ?? subjects[0]?.id ?? 0,
        syllabus_topic_id: concept?.syllabus_topic_id ?? contextTopic?.id ?? null as number | null,
        code: concept?.code ?? '',
        title: concept?.title ?? '',
        description: concept?.description ?? '',
        sort_order: concept?.sort_order ?? 0,
    });
    const hasSubjects = subjects.length > 0;
    const subjectIsFixed = Boolean(contextSubject || contextTopic);
    const topicIsFixed = Boolean(contextTopic);

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (concept) {
            form.put(concepts.update(concept).url);
        } else {
            form.post(contextTopic
                ? topicConcepts.store(contextTopic).url
                : contextSubject
                  ? subjectConcepts.store(contextSubject).url
                  : concepts.store().url);
        }
    };

    if (!hasSubjects && !concept) {
        return (
            <PageShell>
                <Head title="New concept" />
                <PageHeader
                    eyebrow="Curriculum administration"
                    title="Create an instructional concept"
                    description="Concepts belong to a subject. Create a program and subject before adding instructional content."
                    actions={(
                        <Button variant="outline" asChild>
                            <Link href={programRoutes.index()}>Programs</Link>
                        </Button>
                    )}
                />
                <EmptyState>
                    <div className="space-y-3">
                        <p className="font-medium text-foreground">Create a subject first</p>
                        <p>Start with a program, add a subject, then author concepts that can link lessons and assessments.</p>
                        <Button asChild><Link href={programRoutes.index()}>Go to programs</Link></Button>
                    </div>
                </EmptyState>
            </PageShell>
        );
    }

    const returnHref = concept
        ? concepts.show(concept)
        : contextTopic
          ? topicConcepts.index(contextTopic)
          : contextSubject
            ? subjectConcepts.index(contextSubject)
            : concepts.index();

    return (
        <PageShell>
            <Head title={concept ? 'Edit concept' : 'New concept'} />
            <PageHeader
                eyebrow="Instructional concept"
                title={concept ? 'Edit concept' : 'Create a concept'}
                description="Author a teachable unit that connects lessons and assessments. Concepts are instructional content; official syllabus topics remain a separate source outline."
                actions={(
                    <Button variant="outline" asChild>
                        <Link href={returnHref}>{concept ? 'Back to concept' : 'Back to curriculum'}</Link>
                    </Button>
                )}
            />

            <Card className="py-0">
                <CardContent className="py-6">
                    <form onSubmit={submit} className="grid gap-6 lg:grid-cols-2">
                        <div className="grid gap-2">
                            <label htmlFor="concept-subject" className="text-sm font-medium">Subject</label>
                            <select
                                id="concept-subject"
                                value={form.data.subject_id}
                                onChange={(event) => {
                                    form.setData('subject_id', Number(event.target.value));
                                    form.setData('syllabus_topic_id', null);
                                }}
                                disabled={subjectIsFixed}
                                aria-invalid={Boolean(form.errors.subject_id)}
                                aria-describedby={form.errors.subject_id ? 'concept-subject-error' : 'concept-subject-hint'}
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-70 aria-invalid:border-destructive"
                            >
                                {subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.code ? `${subject.code} — ` : ''}{subject.name}</option>)}
                            </select>
                            <p id="concept-subject-hint" className="text-sm text-muted-foreground">Concepts belong to one subject.</p>
                            <InputError id="concept-subject-error" role="alert" message={form.errors.subject_id} />
                        </div>

                        <div className="grid gap-2">
                            <label htmlFor="concept-topic" className="text-sm font-medium">Official syllabus topic <span className="font-normal text-muted-foreground">(optional)</span></label>
                            <select
                                id="concept-topic"
                                value={form.data.syllabus_topic_id ?? ''}
                                onChange={(event) => form.setData('syllabus_topic_id', event.target.value ? Number(event.target.value) : null)}
                                disabled={topicIsFixed}
                                aria-invalid={Boolean(form.errors.syllabus_topic_id)}
                                aria-describedby={form.errors.syllabus_topic_id ? 'concept-topic-error' : 'concept-topic-hint'}
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-70 aria-invalid:border-destructive"
                            >
                                <option value="">No syllabus topic</option>
                                {topics.filter((topic) => topic.subject_id === form.data.subject_id).map((topic) => <option key={topic.id} value={topic.id}>{topic.title}</option>)}
                            </select>
                            <p id="concept-topic-hint" className="text-sm text-muted-foreground">Link the concept to the source outline when a matching topic exists.</p>
                            <InputError id="concept-topic-error" role="alert" message={form.errors.syllabus_topic_id} />
                        </div>

                        <div className="grid gap-2">
                            <label htmlFor="concept-code" className="text-sm font-medium">Concept code</label>
                            <Input
                                id="concept-code"
                                value={form.data.code}
                                onChange={(event) => form.setData('code', event.target.value)}
                                aria-invalid={Boolean(form.errors.code)}
                                aria-describedby={form.errors.code ? 'concept-code-error' : 'concept-code-hint'}
                                required
                            />
                            <p id="concept-code-hint" className="text-sm text-muted-foreground">Use the internal code learners and authors will see for this concept.</p>
                            <InputError id="concept-code-error" role="alert" message={form.errors.code} />
                        </div>

                        <div className="grid gap-2">
                            <label htmlFor="concept-title" className="text-sm font-medium">Concept title</label>
                            <Input
                                id="concept-title"
                                autoFocus
                                value={form.data.title}
                                onChange={(event) => form.setData('title', event.target.value)}
                                aria-invalid={Boolean(form.errors.title)}
                                aria-describedby={form.errors.title ? 'concept-title-error' : 'concept-title-hint'}
                                required
                            />
                            <p id="concept-title-hint" className="text-sm text-muted-foreground">Name one coherent idea or skill learners can study.</p>
                            <InputError id="concept-title-error" role="alert" message={form.errors.title} />
                        </div>

                        <div className="grid gap-2 lg:col-span-2">
                            <label htmlFor="concept-description" className="text-sm font-medium">Description <span className="font-normal text-muted-foreground">(optional)</span></label>
                            <textarea
                                id="concept-description"
                                value={form.data.description}
                                onChange={(event) => form.setData('description', event.target.value)}
                                aria-invalid={Boolean(form.errors.description)}
                                aria-describedby={form.errors.description ? 'concept-description-error' : 'concept-description-hint'}
                                className="min-h-32 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive"
                            />
                            <p id="concept-description-hint" className="text-sm text-muted-foreground">Describe the idea or skill in language that supports lesson and assessment authors.</p>
                            <InputError id="concept-description-error" role="alert" message={form.errors.description} />
                        </div>

                        <div className="flex flex-col-reverse gap-3 border-t pt-5 sm:flex-row sm:justify-end lg:col-span-2">
                            <Button variant="outline" asChild><Link href={returnHref}>Cancel</Link></Button>
                            <Button type="submit" disabled={form.processing || !hasSubjects}>
                                {form.processing ? 'Saving…' : concept ? 'Save changes' : 'Create concept'}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </PageShell>
    );
}
