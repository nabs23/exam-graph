import { Form, Head, Link } from '@inertiajs/react';
import { embed as embedProgramFile } from '@/actions/App/Http/Controllers/ProgramFileController';
import InputError from '@/components/input-error';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { SourceFile, SourceFileDeleteButton } from '@/components/source-files';
import { useFileEmbeddingPolling } from '@/components/source-files';
import programs from '@/routes/programs';
import programFiles from '@/routes/program-files';
import programUploads from '@/routes/programs/files';

export default function ProgramFileShow({
    file,
    fileEmbeddingsEnabled,
    embeddingUnavailableReason,
    embeddingPreviews,
    embeddingDataBytes,
    embeddingPageCount,
    subjects,
}: {
    file: SourceFile & {
        program: { id: number; name: string; code: string };
        subject_id?: number | null;
    };
    subjects: { id: number; name: string }[];
    fileEmbeddingsEnabled: boolean;
    embeddingUnavailableReason: string | null;
    embeddingPreviews: {
        page_number: number;
        model: string;
        dimensions: number;
        excerpt: string;
        values: number[];
    }[];
    embeddingDataBytes: number | null;
    embeddingPageCount: number | null;
}) {
    const embeddingInProgress = ['queued', 'processing'].includes(
        file.embedding_status,
    );
    const embeddingDisabledReason = getEmbeddingDisabledReason(
        file,
        embeddingInProgress,
        fileEmbeddingsEnabled,
        embeddingUnavailableReason,
    );
    const canRequestEmbedding = embeddingDisabledReason === null;

    useFileEmbeddingPolling(embeddingInProgress, [
        'file',
        'embeddingPreviews',
        'embeddingDataBytes',
        'embeddingPageCount',
    ]);

    return (
        <PageShell className="mx-auto max-w-6xl">
            <Head title={file.title} />
            <PageHeader
                eyebrow={`${file.program.code} · ${file.program.name}`}
                title={file.title}
                description={file.original_filename}
                actions={
                    <>
                        <SourceFileDeleteButton file={file} />
                        <Button asChild variant="outline">
                            <Link href={programUploads.index(file.program)}>
                                Back to files
                            </Link>
                        </Button>
                    </>
                }
            />
            <dl className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div className="rounded-xl border bg-card p-4 shadow-sm">
                    <dt className="text-sm text-muted-foreground">File type</dt>
                    <dd className="mt-1 font-medium capitalize">
                        {file.file_type?.replaceAll('_', ' ') ?? 'Program file'}
                    </dd>
                </div>
                <div className="rounded-xl border bg-card p-4 shadow-sm">
                    <dt className="text-sm text-muted-foreground">
                        Original filename
                    </dt>
                    <dd className="mt-1 font-medium break-all">
                        {file.original_filename}
                    </dd>
                </div>
                <div className="rounded-xl border bg-card p-4 shadow-sm">
                    <dt className="text-sm text-muted-foreground">
                        Upload status
                    </dt>
                    <dd className="mt-1 font-medium capitalize">
                        {file.upload_status.replaceAll('_', ' ')}
                    </dd>
                </div>
                <div className="rounded-xl border bg-card p-4 shadow-sm">
                    <dt className="text-sm text-muted-foreground">
                        Embeddings
                    </dt>
                    <dd className="mt-1 font-medium capitalize">
                        {file.embedding_status.replaceAll('_', ' ')}
                    </dd>
                </div>
                <div className="rounded-xl border bg-card p-4 shadow-sm">
                    <dt className="text-sm text-muted-foreground">File size</dt>
                    <dd className="mt-1 font-medium">
                        {file.file_size === null
                            ? 'Size pending'
                            : `${(file.file_size / 1048576).toFixed(1)} MiB`}
                    </dd>
                </div>
                <div className="rounded-xl border bg-card p-4 shadow-sm">
                    <dt className="text-sm text-muted-foreground">Uploaded</dt>
                    <dd className="mt-1 font-medium">
                        {file.uploaded_at
                            ? new Date(file.uploaded_at).toLocaleDateString(
                                  undefined,
                                  {
                                      year: 'numeric',
                                      month: 'short',
                                      day: 'numeric',
                                  },
                              )
                            : file.upload_status.replaceAll('_', ' ')}
                    </dd>
                </div>
                <div className="rounded-xl border bg-card p-4 shadow-sm">
                    <dt className="text-sm text-muted-foreground">
                        Uploaded by
                    </dt>
                    <dd className="mt-1 font-medium">
                        {file.uploader?.name ?? 'Unknown'}
                    </dd>
                </div>
            </dl>

            <Form
                {...programFiles.update.form(file.id)}
                className="flex flex-wrap items-end gap-4 rounded-xl border bg-card p-5 shadow-sm"
            >
                {({ processing, recentlySuccessful, errors }) => (
                    <>
                        <label className="min-w-48 flex-1 text-sm font-medium">
                            <span className="mb-1.5 block">
                                Source category
                            </span>
                            <select
                                id="program-file-type"
                                name="file_type"
                                defaultValue={file.file_type ?? 'other'}
                                disabled={processing}
                                aria-invalid={Boolean(errors.file_type)}
                                aria-describedby={
                                    errors.file_type
                                        ? 'program-file-type-error'
                                        : undefined
                                }
                                className="block w-full rounded-lg border border-input bg-background px-3 py-2 text-sm shadow-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-60 aria-invalid:border-destructive"
                            >
                                {[
                                    'reviewer_ebook',
                                    'reviewer_notes',
                                    'lecture_material',
                                    'official_reference',
                                    'practice_material',
                                    'other',
                                ].map((type) => (
                                    <option key={type} value={type}>
                                        {type.replaceAll('_', ' ')}
                                    </option>
                                ))}
                            </select>
                            <InputError
                                id="program-file-type-error"
                                message={errors.file_type}
                            />
                        </label>
                        <label className="min-w-48 flex-1 text-sm font-medium">
                            <span className="mb-1.5 block">Subject scope</span>
                            <select
                                id="program-file-subject"
                                name="subject_id"
                                defaultValue={file.subject_id?.toString() ?? ''}
                                disabled={processing}
                                aria-invalid={Boolean(errors.subject_id)}
                                aria-describedby={
                                    errors.subject_id
                                        ? 'program-file-subject-error'
                                        : undefined
                                }
                                className="block w-full rounded-lg border border-input bg-background px-3 py-2 text-sm shadow-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-60 aria-invalid:border-destructive"
                            >
                                <option value="">Shared across program</option>
                                {subjects.map((subject) => (
                                    <option key={subject.id} value={subject.id}>
                                        {subject.name}
                                    </option>
                                ))}
                            </select>
                            <InputError
                                id="program-file-subject-error"
                                message={errors.subject_id}
                            />
                        </label>
                        <input type="hidden" name="title" value={file.title} />
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={processing}
                        >
                            {processing
                                ? 'Saving…'
                                : 'Save file classification'}
                        </Button>
                        {(processing || recentlySuccessful) && (
                            <p
                                role="status"
                                aria-live="polite"
                                className="w-full text-sm text-muted-foreground"
                            >
                                {processing
                                    ? 'Saving file classification…'
                                    : 'File classification saved.'}
                            </p>
                        )}
                    </>
                )}
            </Form>

            <section className="space-y-4 rounded-xl border bg-card p-5 shadow-sm">
                <div>
                    <h2 className="font-semibold">Text embeddings</h2>
                    <p className="text-sm text-muted-foreground">
                        Extract each PDF page’s text and create one VoyageAI
                        vector for every page with usable text.
                    </p>
                </div>
                <p className="text-sm" aria-live="polite">
                    Status: {file.embedding_status.replaceAll('_', ' ')}
                </p>
                {file.embedding_status === 'failed' && (
                    <p role="alert" className="text-sm text-destructive">
                        {file.embedding_error_code === 'no_extractable_text'
                            ? 'No readable text was found in this PDF. Upload a PDF with a text layer, then retry.'
                            : file.embedding_error_code ===
                                'pdf_page_limit_exceeded'
                              ? 'This PDF exceeded the page-processing limit when embedding was attempted. Retry after increasing the configured limit.'
                              : file.embedding_error_code ===
                                  'provider_rate_limited'
                                ? 'VoyageAI’s rate limit was reached. Check the account’s request and token limits, then retry.'
                                : file.embedding_error_code ===
                                    'provider_failed'
                                  ? 'VoyageAI could not complete the embedding request. You can retry this file.'
                                  : 'Embedding failed. You can retry this file.'}
                    </p>
                )}
                {file.mime_type !== 'application/pdf' && (
                    <p className="text-sm text-muted-foreground">
                        Embedding is currently available for PDF files only.
                    </p>
                )}
                {embeddingPreviews.length > 0 && (
                    <div className="space-y-3">
                        <h3 className="text-sm font-medium">
                            Saved text embedding preview
                        </h3>
                        {embeddingDataBytes !== null && (
                            <p className="text-sm text-muted-foreground">
                                Stored vector payload:{' '}
                                {formatBytes(embeddingDataBytes)} across{' '}
                                {embeddingPageCount} pages.
                            </p>
                        )}
                        {embeddingPreviews.map((preview) => (
                            <div
                                key={preview.page_number}
                                className="rounded-md bg-muted p-3 text-sm"
                            >
                                <p>
                                    Page {preview.page_number} · {preview.model}{' '}
                                    · {preview.dimensions} dimensions
                                </p>
                                <p className="mt-1 text-muted-foreground">
                                    {preview.excerpt}
                                </p>
                                <code className="mt-1 block text-xs break-all text-muted-foreground">
                                    [
                                    {preview.values
                                        .map((value) =>
                                            Number(value).toFixed(5),
                                        )
                                        .join(', ')}
                                    , …]
                                </code>
                            </div>
                        ))}
                        <p className="text-xs text-muted-foreground">
                            Showing the first 8 values for up to 5 pages.
                        </p>
                    </div>
                )}
                <Form {...embedProgramFile.form(file.id)}>
                    {({ processing }) => (
                        <div className="space-y-2">
                            <Button
                                type="submit"
                                disabled={!canRequestEmbedding || processing}
                                aria-describedby={
                                    embeddingDisabledReason
                                        ? 'embedding-initiation-reason'
                                        : undefined
                                }
                                variant="outline"
                            >
                                {processing
                                    ? 'Queueing…'
                                    : 'Generate embeddings'}
                            </Button>
                            {embeddingDisabledReason && (
                                <p
                                    id="embedding-initiation-reason"
                                    className="text-sm text-muted-foreground"
                                    aria-live="polite"
                                >
                                    {embeddingDisabledReason}
                                </p>
                            )}
                        </div>
                    )}
                </Form>
            </section>
        </PageShell>
    );
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    return `${(bytes / 1024).toFixed(2)} KiB`;
}

function getEmbeddingDisabledReason(
    file: SourceFile,
    embeddingInProgress: boolean,
    embeddingsAvailable: boolean,
    availabilityReason: string | null,
): string | null {
    if (file.upload_status !== 'uploaded') {
        return 'The file upload must complete before embeddings can be generated.';
    }

    if (file.mime_type !== 'application/pdf') {
        return 'Only PDF files with a text layer can be embedded.';
    }

    if (file.embedding_status === 'complete') {
        return 'Embeddings have already been generated for this file.';
    }

    if (embeddingInProgress) {
        return 'Embedding generation is already queued or in progress.';
    }

    if (file.embedding_status === 'unsupported') {
        return 'This file is marked as unsupported for text embeddings.';
    }

    if (!embeddingsAvailable) {
        return (
            availabilityReason ?? 'Embedding requirements are not available.'
        );
    }

    return null;
}

ProgramFileShow.layout = (props: {
    file: SourceFile & { program: { id: number; name: string } };
}) => ({
    breadcrumbs: [
        {
            title: 'Program',
            href: programs.show(props.file.program, {
                query: { tab: 'files' },
            }),
        },
        {
            title: 'Program files',
            href: programUploads.index(props.file.program),
        },
        { title: 'File', href: programFiles.show(props.file) },
    ],
});
