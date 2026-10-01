import { Form, Head, Link } from '@inertiajs/react';
import { embed as embedProgramFile } from '@/actions/App/Http/Controllers/ProgramFileController';
import { Button } from '@/components/ui/button';
import { SourceFile } from '@/components/source-files';
import { useFileEmbeddingPolling } from '@/components/source-files';
import programs from '@/routes/programs';
import programFiles from '@/routes/program-files';
import programUploads from '@/routes/programs/files';

export default function ProgramFileShow({
    file,
    fileEmbeddingsEnabled,
    embeddingPreviews,
    embeddingDataBytes,
    embeddingPageCount,
}: {
    file: SourceFile & { program: { id: number; name: string; code: string } };
    fileEmbeddingsEnabled: boolean;
    embeddingPreviews: { page_number: number; model: string; dimensions: number; excerpt: string; values: number[] }[];
    embeddingDataBytes: number | null;
    embeddingPageCount: number | null;
}) {
    const embeddingInProgress = ['queued', 'processing'].includes(file.embedding_status);
    const canRequestEmbedding =
        fileEmbeddingsEnabled &&
        file.upload_status === 'uploaded' &&
        file.mime_type === 'application/pdf' &&
        !embeddingInProgress &&
        file.embedding_status !== 'complete';

    useFileEmbeddingPolling(
        fileEmbeddingsEnabled && embeddingInProgress,
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
                <Link
                    href={programUploads.index(file.program)}
                    className="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium shadow-xs hover:bg-accent"
                >
                    Back to files
                </Link>
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
                    <dt className="text-sm text-muted-foreground">Status</dt>
                    <dd className="mt-1 font-medium capitalize">
                        {file.upload_status.replaceAll('_', ' ')}
                    </dd>
                </div>
                <div className="rounded-xl border bg-card p-4 shadow-sm">
                    <dt className="text-sm text-muted-foreground">Uploaded by</dt>
                    <dd className="mt-1 font-medium">{file.uploader?.name ?? 'Unknown'}</dd>
                </div>
            </dl>

            {fileEmbeddingsEnabled && (
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
                    {canRequestEmbedding && (
                        <Form {...embedProgramFile.form(file.id)}>
                            {({ processing }) => (
                                <Button type="submit" disabled={processing} variant="outline">
                                    {processing ? 'Queueing…' : 'Generate embeddings'}
                                </Button>
                            )}
                        </Form>
                    )}
                </section>
            )}
        </main>
    );
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    return `${(bytes / 1024).toFixed(2)} KiB`;
}

ProgramFileShow.layout = (props: { file: SourceFile & { program: { id: number; name: string } } }) => ({
    breadcrumbs: [
        { title: 'Program', href: programs.show(props.file.program) },
        { title: 'Program files', href: programUploads.index(props.file.program) },
        { title: 'File', href: programFiles.show(props.file) },
    ],
});
