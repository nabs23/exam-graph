import { Form, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import programFiles from '@/routes/program-files';
import { Button } from '@/components/ui/button';
import type { SourceFile } from '@/components/source-files';

export function SourceFileDeleteButton({ file }: { file: SourceFile }) {
    const { url } = usePage();
    const [path, search = ''] = url.split('?');
    const query = new URLSearchParams(search);
    const isProgramFilesTab =
        /\/programs\/\d+$/.test(path) && query.get('tab') === 'files';
    const subjectFilter = query.get('subject');
    const returnOptions = isProgramFilesTab
        ? { query: { tab: 'files' } }
        : subjectFilter
          ? { query: { subject: subjectFilter } }
          : undefined;
    const canDelete = ['uploaded', 'failed', 'delete_pending'].includes(
        file.upload_status,
    );

    return (
        <Form
            {...programFiles.destroy.form(file.id, returnOptions)}
            onBefore={() =>
                window.confirm(
                    `Delete ${file.title}? This also deletes its extracted text and embedding vectors.`,
                )
            }
        >
            {({ processing }) => (
                <Button
                    type="submit"
                    variant="destructive"
                    size="sm"
                    disabled={!canDelete || processing}
                    aria-label={`${file.upload_status === 'delete_pending' ? 'Retry deletion of' : 'Delete'} ${file.title}`}
                >
                    {processing
                        ? 'Deleting…'
                        : file.upload_status === 'delete_pending'
                          ? 'Retry deletion'
                          : 'Delete file'}
                </Button>
            )}
        </Form>
    );
}

export function SourceFileList({ files }: { files: SourceFile[] }) {
    const [downloadingFileId, setDownloadingFileId] = useState<number | null>(
        null,
    );
    const [downloadErrors, setDownloadErrors] = useState<
        Record<number, string>
    >({});

    return (
        <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {files.map((file) => (
                <li
                    key={file.id}
                    className="flex min-w-0 flex-col rounded-xl border bg-card shadow-sm"
                >
                    <Link
                        href={programFiles.show(file.id)}
                        className="block flex-1 rounded-t-xl p-5 transition-colors hover:bg-accent focus-visible:relative focus-visible:z-10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <h3 className="wrap-break-words min-w-0 leading-snug font-semibold">
                                {file.title}
                            </h3>
                            <span className="shrink-0 rounded-md bg-secondary px-2 py-0.5 text-xs capitalize">
                                {file.upload_status.replaceAll('_', ' ')}
                            </span>
                        </div>
                        {getSourceFileGuidance(file) && (
                            <p
                                className="mt-2 text-sm text-muted-foreground"
                                aria-live={
                                    file.upload_status === 'uploaded' &&
                                    file.mime_type === 'application/pdf' &&
                                    [
                                        'queued',
                                        'processing',
                                        'complete',
                                        'failed',
                                    ].includes(file.embedding_status)
                                        ? 'polite'
                                        : undefined
                                }
                            >
                                {getSourceFileGuidance(file)}
                            </p>
                        )}
                        <dl className="mt-3 space-y-2 text-sm">
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    File type
                                </dt>
                                <dd className="capitalize">
                                    {file.file_type?.replaceAll('_', ' ') ??
                                        'Program file'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Subject scope
                                </dt>
                                <dd>
                                    {file.subject?.name ??
                                        'Shared across program'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Original filename
                                </dt>
                                <dd className="break-all">
                                    {file.original_filename}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Embeddings
                                </dt>
                                <dd className="capitalize">
                                    {file.embedding_status.replaceAll('_', ' ')}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    File size
                                </dt>
                                <dd>
                                    {file.file_size === null
                                        ? 'Size pending'
                                        : `${(file.file_size / 1048576).toFixed(1)} MiB`}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Uploaded
                                </dt>
                                <dd>
                                    {file.uploaded_at
                                        ? new Date(
                                              file.uploaded_at,
                                          ).toLocaleDateString(undefined, {
                                              year: 'numeric',
                                              month: 'short',
                                              day: 'numeric',
                                          })
                                        : file.upload_status.replaceAll(
                                              '_',
                                              ' ',
                                          )}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Uploaded by
                                </dt>
                                <dd>{file.uploader?.name ?? 'Unknown'}</dd>
                            </div>
                        </dl>
                    </Link>
                    <div className="flex flex-wrap items-center justify-end gap-3 px-5 py-2">
                        {file.upload_status === 'uploaded' && (
                            <button
                                type="button"
                                disabled={downloadingFileId !== null}
                                onClick={async () => {
                                    setDownloadingFileId(file.id);
                                    setDownloadErrors((errors) => ({
                                        ...errors,
                                        [file.id]: '',
                                    }));

                                    try {
                                        const response = await fetch(
                                            programFiles.download(file.id).url,
                                            {
                                                method: 'POST',
                                                headers: {
                                                    'X-CSRF-TOKEN':
                                                        document.querySelector<HTMLMetaElement>(
                                                            'meta[name="csrf-token"]',
                                                        )?.content ?? '',
                                                    Accept: 'application/json',
                                                },
                                            },
                                        );

                                        if (!response.ok) {
                                            throw new Error(
                                                'The server could not prepare the download.',
                                            );
                                        }

                                        const result: { url?: unknown } =
                                            await response.json();

                                        if (typeof result.url !== 'string') {
                                            throw new Error(
                                                'The server returned an invalid download link.',
                                            );
                                        }

                                        window.location.assign(result.url);
                                    } catch {
                                        setDownloadErrors((errors) => ({
                                            ...errors,
                                            [file.id]:
                                                'Could not prepare this download. Check your connection or refresh the page, then try again.',
                                        }));
                                    } finally {
                                        setDownloadingFileId(null);
                                    }
                                }}
                                aria-live="polite"
                                className="rounded-md px-3 py-1.5 text-sm font-medium text-primary transition-colors hover:bg-primary/10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {downloadingFileId === file.id
                                    ? 'Preparing download…'
                                    : 'Download'}
                            </button>
                        )}
                        <SourceFileDeleteButton file={file} />
                    </div>
                    {downloadErrors[file.id] && (
                        <p
                            role="alert"
                            className="px-5 pb-3 text-sm text-destructive"
                        >
                            {downloadErrors[file.id]}
                        </p>
                    )}
                </li>
            ))}
        </ul>
    );
}

function getSourceFileGuidance(file: SourceFile): string | null {
    if (file.upload_status === 'pending_upload') {
        return 'Awaiting verification. If this upload stays pending, the incomplete entry will be cleared automatically; retry after it disappears.';
    }

    if (file.upload_status === 'failed') {
        return 'Upload failed. Delete this entry and upload the source file again.';
    }

    if (file.upload_status !== 'uploaded') {
        return null;
    }

    if (file.embedding_status === 'failed') {
        return 'PDF text processing failed. Open file details to review the error and retry.';
    }

    if (file.embedding_status === 'queued') {
        return 'PDF text processing is queued.';
    }

    if (file.embedding_status === 'processing') {
        return 'PDF text processing is in progress. This status updates automatically.';
    }

    if (
        file.mime_type === 'application/pdf' &&
        file.embedding_status === 'complete'
    ) {
        return 'PDF text is ready for official curriculum extraction.';
    }

    if (
        file.mime_type === 'application/pdf' &&
        ['not_requested', 'supported'].includes(file.embedding_status)
    ) {
        return 'Open file details to prepare PDF text for curriculum extraction.';
    }

    if (file.embedding_status === 'unsupported') {
        return 'Text processing is unavailable for this file type.';
    }

    return null;
}
