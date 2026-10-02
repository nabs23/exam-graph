import { Form, Head, Link } from '@inertiajs/react';
import { embed as embedProgramFile } from '@/actions/App/Http/Controllers/ProgramFileController';
import { show as curriculumExtractionShow, store as storeCurriculumExtraction } from '@/actions/App/Http/Controllers/CurriculumExtractionController';
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
    curriculumExtractionEnabled,
    curriculumExtractionUnavailableReason,
    latestCurriculumExtraction,
}: {
    file: SourceFile & { program: { id: number; name: string; code: string } };
    fileEmbeddingsEnabled: boolean;
    embeddingUnavailableReason: string | null;
    embeddingPreviews: { page_number: number; model: string; dimensions: number; excerpt: string; values: number[] }[];
    embeddingDataBytes: number | null;
    embeddingPageCount: number | null;
    curriculumExtractionEnabled: boolean;
    curriculumExtractionUnavailableReason: string | null;
    latestCurriculumExtraction: { id: number; status: string; error_code: string | null } | null;
}) {
    const embeddingInProgress = ['queued', 'processing'].includes(file.embedding_status);
    const embeddingDisabledReason = getEmbeddingDisabledReason(
        file,
        embeddingInProgress,
        fileEmbeddingsEnabled,
        embeddingUnavailableReason,
    );
    const canRequestEmbedding = embeddingDisabledReason === null;
    const curriculumExtractionDisabledReason = getCurriculumExtractionDisabledReason(
        file,
        curriculumExtractionEnabled,
        curriculumExtractionUnavailableReason,
        latestCurriculumExtraction,
    );

    useFileEmbeddingPolling(
        embeddingInProgress,
        ['file', 'embeddingPreviews', 'embeddingDataBytes', 'embeddingPageCount'],
    );

    return (
        <main className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Head title={file.title} />
            <header className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p className="text-sm font-medium uppercase tracking-wide text-primary">
                        {file.program.code}
                    </p>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        {file.title}
                    </h1>
                    <p className="mt-2 break-all text-muted-foreground">
                        {file.original_filename}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <SourceFileDeleteButton file={file} kind="program" />
                    <Link
                        href={programUploads.index(file.program)}
                        className="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium shadow-xs hover:bg-accent"
                    >
                        Back to files
                    </Link>
                </div>
            </header>
            <dl className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div className="rounded-xl border bg-card p-4 shadow-sm">
                    <dt className="text-sm text-muted-foreground">File type</dt>
                    <dd className="mt-1 font-medium capitalize">
                        {file.file_type?.replaceAll('_', ' ') ?? 'Program file'}
                    </dd>
                </div>
                <div className="rounded-xl border bg-card p-4 shadow-sm">
                    <dt className="text-sm text-muted-foreground">Original filename</dt>
                    <dd className="mt-1 break-all font-medium">{file.original_filename}</dd>
                </div>
                <div className="rounded-xl border bg-card p-4 shadow-sm">
                    <dt className="text-sm text-muted-foreground">Upload status</dt>
                    <dd className="mt-1 font-medium capitalize">
                        {file.upload_status.replaceAll('_', ' ')}
                    </dd>
                </div>
                <div className="rounded-xl border bg-card p-4 shadow-sm">
                    <dt className="text-sm text-muted-foreground">Embeddings</dt>
                    <dd className="mt-1 font-medium capitalize">
                        {file.embedding_status.replaceAll('_', ' ')}
                    </dd>
                </div>
                <div className="rounded-xl border bg-card p-4 shadow-sm">
                    <dt className="text-sm text-muted-foreground">File size</dt>
                    <dd className="mt-1 font-medium">
                        {file.file_size === null ? 'Size pending' : `${(file.file_size / 1048576).toFixed(1)} MiB`}
                    </dd>
                </div>
                <div className="rounded-xl border bg-card p-4 shadow-sm">
                    <dt className="text-sm text-muted-foreground">Uploaded</dt>
                    <dd className="mt-1 font-medium">
                        {file.uploaded_at
                            ? new Date(file.uploaded_at).toLocaleDateString(undefined, {
                                year: 'numeric',
                                month: 'short',
                                day: 'numeric',
                            })
                            : file.upload_status.replaceAll('_', ' ')}
                    </dd>
                </div>
                <div className="rounded-xl border bg-card p-4 shadow-sm">
                    <dt className="text-sm text-muted-foreground">Uploaded by</dt>
                    <dd className="mt-1 font-medium">{file.uploader?.name ?? 'Unknown'}</dd>
                </div>
            </dl>

            <section className="space-y-4 rounded-xl border bg-card p-5 shadow-sm">
                <div>
                    <h2 className="font-semibold">Text embeddings</h2>
                    <p className="text-sm text-muted-foreground">
                        Extract each PDF page’s text and create one VoyageAI vector for every page with usable text.
                    </p>
                </div>
                <p className="text-sm" aria-live="polite">
                    Status: {file.embedding_status.replaceAll('_', ' ')}
                </p>
                {file.embedding_status === 'failed' && (
                    <p role="alert" className="text-sm text-destructive">
                        {file.embedding_error_code === 'no_extractable_text'
                            ? 'No readable text was found in this PDF. Upload a PDF with a text layer, then retry.'
                            : file.embedding_error_code === 'pdf_page_limit_exceeded'
                              ? 'This PDF exceeded the page-processing limit when embedding was attempted. Retry after increasing the configured limit.'
                              : file.embedding_error_code === 'provider_rate_limited'
                                ? 'VoyageAI’s rate limit was reached. Check the account’s request and token limits, then retry.'
                              : file.embedding_error_code === 'provider_failed'
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
                        <h3 className="text-sm font-medium">Saved text embedding preview</h3>
                        {embeddingDataBytes !== null && (
                            <p className="text-sm text-muted-foreground">
                                Stored vector payload: {formatBytes(embeddingDataBytes)} across {embeddingPageCount} pages.
                            </p>
                        )}
                        {embeddingPreviews.map((preview) => (
                            <div key={preview.page_number} className="rounded-md bg-muted p-3 text-sm">
                                <p>Page {preview.page_number} · {preview.model} · {preview.dimensions} dimensions</p>
                                <p className="mt-1 text-muted-foreground">{preview.excerpt}</p>
                                <code className="mt-1 block break-all text-xs text-muted-foreground">
                                    [{preview.values.map((value) => Number(value).toFixed(5)).join(', ')}, …]
                                </code>
                            </div>
                        ))}
                        <p className="text-xs text-muted-foreground">Showing the first 8 values for up to 5 pages.</p>
                    </div>
                )}
                <Form {...embedProgramFile.form(file.id)}>
                    {({ processing }) => (
                        <div className="space-y-2">
                            <Button
                                type="submit"
                                disabled={!canRequestEmbedding || processing}
                                aria-describedby={embeddingDisabledReason ? 'embedding-initiation-reason' : undefined}
                                variant="outline"
                            >
                                {processing ? 'Queueing…' : 'Generate embeddings'}
                            </Button>
                            {embeddingDisabledReason && (
                                <p id="embedding-initiation-reason" className="text-sm text-muted-foreground" aria-live="polite">
                                    {embeddingDisabledReason}
                                </p>
                            )}
                        </div>
                    )}
                </Form>
            </section>

            <section className="space-y-4 rounded-xl border bg-card p-5 shadow-sm">
                <div>
                    <h2 className="font-semibold">Official curriculum extraction</h2>
                    <p className="text-sm text-muted-foreground">
                        Prepare a reviewer proposal of official subjects and syllabus topics from this PDF’s extracted page text. Nothing is added to the curriculum until a reviewer publishes selected items.
                    </p>
                </div>
                {latestCurriculumExtraction && (
                    <div className="text-sm" aria-live="polite">
                        Latest proposal: <Link className="underline" href={curriculumExtractionShow(latestCurriculumExtraction.id)}>
                            {latestCurriculumExtraction.status.replaceAll('_', ' ')}
                        </Link>
                        {latestCurriculumExtraction.error_code && <span className="text-muted-foreground"> · {latestCurriculumExtraction.error_code.replaceAll('_', ' ')}</span>}
                    </div>
                )}
                <Form {...storeCurriculumExtraction.form(file.id)}>
                    {({ processing }) => (
                        <div className="space-y-2">
                            <input type="hidden" name="confirmation" value="1" />
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing || curriculumExtractionDisabledReason !== null}
                                aria-describedby={curriculumExtractionDisabledReason ? 'curriculum-extraction-initiation-reason' : undefined}
                            >
                                {processing ? 'Queueing…' : 'Extract official curriculum'}
                            </Button>
                            {curriculumExtractionDisabledReason && (
                                <p id="curriculum-extraction-initiation-reason" className="text-sm text-muted-foreground" aria-live="polite">
                                    {curriculumExtractionDisabledReason}
                                </p>
                            )}
                        </div>
                    )}
                </Form>
            </section>
        </main>
    );
}

function getCurriculumExtractionDisabledReason(
    file: SourceFile,
    extractionAvailable: boolean,
    availabilityReason: string | null,
    latestExtraction: { status: string } | null,
): string | null {
    if (file.upload_status !== 'uploaded') {
        return 'The file upload must complete before curriculum extraction can start.';
    }

    if (file.mime_type !== 'application/pdf') {
        return 'Official curriculum extraction is available for program PDFs only.';
    }

    if (file.embedding_status !== 'complete') {
        return 'Generate complete text embeddings for this PDF before extracting curriculum.';
    }

    if (!extractionAvailable) {
        return availabilityReason ?? 'Curriculum extraction requirements are unavailable.';
    }

    if (latestExtraction?.status === 'queued' || latestExtraction?.status === 'processing') {
        return 'Curriculum extraction is already queued or processing.';
    }

    if (latestExtraction?.status === 'reviewing') {
        return 'Review or reject the current proposal before starting another extraction.';
    }

    return null;
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
        return availabilityReason ?? 'Embedding requirements are not available.';
    }

    return null;
}

ProgramFileShow.layout = (props: { file: SourceFile & { program: { id: number; name: string } } }) => ({
    breadcrumbs: [
        { title: 'Program', href: programs.show(props.file.program) },
        { title: 'Program files', href: programUploads.index(props.file.program) },
        { title: 'File', href: programFiles.show(props.file) },
    ],
});
