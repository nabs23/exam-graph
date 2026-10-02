import { Form, Head, Link, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import { publish, reject } from '@/actions/App/Http/Controllers/CurriculumExtractionController';
import { Button } from '@/components/ui/button';
import programs from '@/routes/programs';
import programFiles from '@/routes/program-files';

type TopicProposal = {
    code: string | null;
    title: string;
    description: string | null;
    description_origin?: 'source' | 'ai_generated' | 'reviewer' | 'unavailable';
    source_page: number;
    parent_index: number | null;
};

type SubjectProposal = {
    code: string | null;
    name: string;
    description: string | null;
    description_origin?: 'source' | 'ai_generated' | 'reviewer' | 'unavailable';
    source_page: number;
    topics: TopicProposal[];
};

export default function CurriculumExtractionShow({
    extraction,
    proposal,
    reviewedProposal,
    sourcePages,
    canReview,
}: {
    extraction: {
        id: number;
        status: string;
        error_code: string | null;
        source_hash: string;
        provider: string;
        model: string;
        created_at: string;
        program_file: {
            id: number;
            title: string;
            program: { id: number; name: string; code: string };
        };
        requester: { name: string } | null;
        reviewer: { name: string } | null;
    };
    proposal: { subjects: SubjectProposal[] } | null;
    reviewedProposal: { subjects: (SubjectProposal & { topics: (TopicProposal & { parent_index: number | null })[] })[] } | null;
    sourcePages: { page_number: number; text: string }[];
    canReview: boolean;
}) {
    const pages = new Map(sourcePages.map((page) => [page.page_number, page.text]));
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
                <p className="text-sm font-medium uppercase tracking-wide text-primary">{extraction.program_file.program.code}</p>
                <h1 className="text-3xl font-semibold tracking-tight">Official curriculum proposal</h1>
                <p className="text-muted-foreground">
                    {extraction.program_file.title} · {extraction.status.replaceAll('_', ' ')}
                </p>
            </header>

            <dl className="grid gap-3 rounded-xl border bg-card p-4 text-sm sm:grid-cols-2">
                <div><dt className="text-muted-foreground">Source PDF</dt><dd className="font-medium">{extraction.program_file.title}</dd></div>
                <div><dt className="text-muted-foreground">Provider and model</dt><dd className="font-medium">{extraction.provider} · {extraction.model}</dd></div>
                <div><dt className="text-muted-foreground">Requested by</dt><dd className="font-medium">{extraction.requester?.name ?? 'Unknown'}</dd></div>
                <div><dt className="text-muted-foreground">Source version</dt><dd className="break-all font-mono text-xs">{extraction.source_hash}</dd></div>
            </dl>

            {extraction.error_code && (
                <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/5 p-4 text-sm">
                    This extraction ended with: {extraction.error_code.replaceAll('_', ' ')}.
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
                                    <Citation page={subject.source_page} text={pages.get(subject.source_page)} />
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
                                            <Citation page={topic.source_page} text={pages.get(topic.source_page)} />
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
                            <Citation page={subject.source_page} text={pages.get(subject.source_page)} />
                            <ul className="space-y-3">
                                {subject.topics.map((topic, topicIndex) => (
                                    <li key={`${topic.code}-${topicIndex}`} className="ml-4 space-y-2 border-l-2 pl-4">
                                        <h3 className="font-medium">{topic.code ? `${topic.code} · ` : ''}{topic.title}</h3>
                                        {topic.description && <p className="text-sm text-muted-foreground">{topic.description}{topic.description_origin === 'ai_generated' && <span className="ml-2 text-xs">AI-drafted</span>}{topic.description_origin === 'reviewer' && <span className="ml-2 text-xs">Reviewer-edited</span>}</p>}
                                        <Citation page={topic.source_page} text={pages.get(topic.source_page)} />
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

function Citation({ page, text }: { page: number; text?: string }) {
    return (
        <details className="rounded-md bg-muted/60 p-3 text-sm">
            <summary className="cursor-pointer font-medium">Source page {page}</summary>
            <p className="mt-2 whitespace-pre-wrap text-muted-foreground">{text ?? 'Source page text is no longer available.'}</p>
        </details>
    );
}

function descriptionLabel(origin: TopicProposal['description_origin']): string {
    if (origin === 'ai_generated') {
        return 'AI-drafted description — verify against the source';
    }

    if (origin === 'unavailable') {
        return 'Description (not supported by available source text)';
    }

    if (origin === 'reviewer') {
        return 'Description (reviewer-edited)';
    }

    return 'Description (source-supported)';
}

CurriculumExtractionShow.layout = (props: { extraction: { program_file: { id: number; program: { id: number } } } }) => ({
    breadcrumbs: [
        { title: 'Program', href: programs.show(props.extraction.program_file.program) },
        { title: 'Program file', href: programFiles.show(props.extraction.program_file) },
        { title: 'Curriculum review', href: programFiles.show(props.extraction.program_file) },
    ],
});
