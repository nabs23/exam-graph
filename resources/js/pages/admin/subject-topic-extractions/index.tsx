import { Form, Head, Link } from '@inertiajs/react';
import { destroy, index as extractionIndex, show, store } from '@/actions/App/Http/Controllers/SubjectTopicExtractionController';
import { Button } from '@/components/ui/button';
import programs from '@/routes/programs';
import { subjects } from '@/lib/curriculum-routes';

type ExtractionSummary = {
    id: number;
    status: string;
    source_hash: string;
    source_files: { id: number; title: string; content_hash: string }[];
    provider: string;
    model: string;
    prompt_version: string;
    error_code: string | null;
    error_message: string | null;
    created_at: string;
    reviewed_at: string | null;
    requester: { name: string } | null;
    reviewer: { name: string } | null;
};

type SourceFile = {
    id: number;
    title: string;
    file_type: string | null;
    mime_type: string;
    upload_status: string;
    embedding_status: string;
    usable_pages_count: number;
    eligible: boolean;
};

export default function SubjectTopicExtractionsIndex({
    program,
    subject,
    files,
    extractions,
    topicExtractionEnabled,
    topicExtractionUnavailableReason,
    topicExtractionModels,
    topicExtractionDefaultModel,
}: {
    program: { id: number; name: string; code: string };
    subject: { id: number; name: string; code: string | null };
    files: SourceFile[];
    extractions: ExtractionSummary[];
    topicExtractionEnabled: boolean;
    topicExtractionUnavailableReason: string | null;
    topicExtractionModels: string[];
    topicExtractionDefaultModel: string | null;
}) {
    const routeParams = { program, subject };

    return (
        <main className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Head title={`Topic extraction · ${subject.name}`} />
            <header className="space-y-2 border-b pb-6">
                <p className="text-sm font-medium uppercase tracking-wide text-primary">{program.code} · {subject.code ?? subject.name}</p>
                <h1 className="text-3xl font-semibold tracking-tight">Subject topic extraction</h1>
                <p className="text-muted-foreground">Create a reviewer-controlled syllabus-topic proposal from selected source files.</p>
            </header>

            <section className="space-y-4 rounded-xl border bg-card p-5 shadow-sm">
                <div>
                    <h2 className="text-xl font-semibold">Select source files</h2>
                    <p className="mt-1 text-sm text-muted-foreground">Only uploaded PDFs with completed page text can be selected. Topic citations retain each file and original page number.</p>
                </div>
                {topicExtractionUnavailableReason && <p className="rounded-md border p-3 text-sm text-muted-foreground">{topicExtractionUnavailableReason}</p>}
                {files.length === 0 ? (
                    <p className="rounded-md border border-dashed p-5 text-sm text-muted-foreground">This subject has no source files yet. Add files in the Files tab first.</p>
                ) : (
                    <Form {...store.form(routeParams)} className="space-y-4">
                        {({ processing, errors }) => (
                            <>
                                <fieldset disabled={!topicExtractionEnabled || processing} className="space-y-2">
                                    <legend className="sr-only">Source files</legend>
                                    {files.map((file) => (
                                        <label key={file.id} className={`flex items-start gap-3 rounded-lg border p-3 ${file.eligible ? 'cursor-pointer' : 'opacity-60'}`}>
                                            <input type="checkbox" name="source_file_ids[]" value={file.id} disabled={!file.eligible} className="mt-1 size-4" />
                                            <span className="min-w-0 flex-1">
                                                <span className="block font-medium">{file.title}</span>
                                                <span className="block text-sm text-muted-foreground">
                                                    {file.eligible ? `Ready · ${file.usable_pages_count} pages · ${file.file_type ?? 'PDF'}` : `${file.upload_status.replaceAll('_', ' ')} · ${file.embedding_status.replaceAll('_', ' ')} · ${file.usable_pages_count} usable pages`}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                </fieldset>
                                {errors.source_file_ids && <p role="alert" className="text-sm text-destructive">{errors.source_file_ids}</p>}
                                {Object.entries(errors).filter(([key]) => key.startsWith('source_file_ids.')).map(([key, message]) => <p key={key} role="alert" className="text-sm text-destructive">{message}</p>)}
                                <label className="block max-w-sm space-y-1 text-sm">
                                    <span>Model</span>
                                    <select name="model" defaultValue={topicExtractionDefaultModel ?? ''} className="w-full rounded-md border border-input bg-background px-3 py-2">
                                        {topicExtractionDefaultModel === null && <option value="">Configured default</option>}
                                        {topicExtractionModels.map((model) => <option key={model} value={model}>{model}</option>)}
                                    </select>
                                </label>
                                <Button type="submit" disabled={!topicExtractionEnabled || processing || files.every((file) => !file.eligible)}>
                                    {processing ? 'Starting…' : 'Generate topic proposal'}
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </section>

            <section className="space-y-4">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h2 className="text-xl font-semibold">Extraction history</h2>
                        <p className="text-sm text-muted-foreground">Recent proposals for {subject.name}.</p>
                    </div>
                    <Button asChild variant="outline"><Link href={subjects.show(subject)}>Back to subject</Link></Button>
                </div>
                {extractions.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-6 text-sm text-muted-foreground">No topic extractions have been requested for this subject.</p>
                ) : (
                    <div className="space-y-3">
                        {extractions.map((extraction) => (
                            <article key={extraction.id} className="rounded-xl border bg-card p-5 shadow-sm">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h3 className="font-semibold capitalize">{extraction.status.replaceAll('_', ' ')}</h3>
                                        <p className="mt-1 text-sm text-muted-foreground">{extraction.provider} · {extraction.model} · {extraction.prompt_version}</p>
                                    </div>
                                    <p className="text-sm text-muted-foreground">Requested {formatDate(extraction.created_at)}</p>
                                </div>
                                <p className="mt-3 text-sm">{extraction.source_files.map((file) => file.title).join(', ')}</p>
                                {extraction.error_message && <p className="mt-2 text-sm text-destructive">{extraction.error_message}</p>}
                                <div className="mt-4 flex flex-wrap gap-2">
                                    <Button asChild size="sm" variant="outline"><Link href={show({ program: program.id, subject: subject.id, topicExtraction: extraction.id })}>View extraction</Link></Button>
                                    {(extraction.status === 'failed' || extraction.status === 'rejected') && (
                                        <Form {...destroy.form({ program: program.id, subject: subject.id, topicExtraction: extraction.id })}>
                                            {({ processing: deleting }) => <Button type="submit" size="sm" variant="destructive" disabled={deleting}>{deleting ? 'Deleting…' : 'Delete'}</Button>}
                                        </Form>
                                    )}
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </section>
        </main>
    );
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

SubjectTopicExtractionsIndex.layout = (props: { program: { id: number }; subject: { id: number } }) => ({
    breadcrumbs: [
        { title: 'Programs', href: programs.index() },
        { title: 'Program', href: programs.show(props.program) },
        { title: 'Subject', href: subjects.show(props.subject) },
        { title: 'Topic extraction', href: extractionIndex({ program: props.program, subject: props.subject }) },
    ],
});
