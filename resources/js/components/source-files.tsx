import { usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import { SourceFileList } from '@/components/source-file-list';
import { SourceFileUploadForm } from '@/components/source-file-upload-form';

export { SourceFileDeleteButton } from '@/components/source-file-list';

export type SourceFile = {
    id: number;
    title: string;
    file_type?: string;
    subject_id?: number | null;
    subject?: { id: number; name: string } | null;
    original_filename: string;
    mime_type: string | null;
    file_size: number | null;
    upload_status: string;
    embedding_status:
        | 'not_requested'
        | 'supported'
        | 'queued'
        | 'processing'
        | 'complete'
        | 'unsupported'
        | 'failed';
    embedding_error_code: string | null;
    embedding_model: string | null;
    uploaded_at: string | null;
    metadata?: Record<string, string> | null;
    uploader: { name: string } | null;
};

export function useFileEmbeddingPolling(
    active: boolean,
    only: string[],
    interval = 3000,
) {
    const { start, stop } = usePoll(
        interval,
        { only },
        {
            autoStart: false,
            mode: 'rest',
        },
    );

    useEffect(() => {
        if (active) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [active, start, stop]);
}

export function SourceFiles({
    ownerId,
    subjectId,
    files,
    subjects,
    defaultUploadSubjectId,
    onUploadComplete,
    emptyStateMessage = 'No files have been uploaded yet.',
    emptyStateHint = 'Upload a PDF, DOCX, or EPUB file up to 100 MiB.',
    pollingEnabled = true,
    pollingOnly = ['files'],
}: {
    ownerId: number;
    subjectId?: number;
    files: SourceFile[];
    subjects?: { id: number; name: string }[];
    defaultUploadSubjectId?: number | null;
    onUploadComplete?: (subjectId: string) => void;
    emptyStateMessage?: string;
    emptyStateHint?: string;
    pollingEnabled?: boolean;
    pollingOnly?: string[];
}) {
    const embeddingOrDeletionInProgress = files.some(
        (file) =>
            ['queued', 'processing'].includes(file.embedding_status) ||
            file.upload_status === 'delete_pending',
    );
    const uploadVerificationPending = files.some(
        (file) => file.upload_status === 'pending_upload',
    );

    useFileEmbeddingPolling(
        pollingEnabled &&
            (embeddingOrDeletionInProgress || uploadVerificationPending),
        pollingOnly,
        embeddingOrDeletionInProgress ? 3000 : 30000,
    );

    return (
        <section className="space-y-6 rounded-2xl border bg-card p-6 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b pb-4">
                <div>
                    <h2 className="text-xl font-semibold tracking-tight">
                        Files
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Manage private source materials for this{' '}
                        {subjectId ? 'subject' : 'program'}.
                    </p>
                </div>
                <span className="text-sm text-muted-foreground">
                    {files.length} {files.length === 1 ? 'file' : 'files'}
                </span>
            </div>
            <SourceFileUploadForm
                ownerId={ownerId}
                subjectId={subjectId}
                subjects={subjects}
                defaultSubjectId={defaultUploadSubjectId}
                onUploadComplete={onUploadComplete}
            />

            {files.length === 0 ? (
                <div className="flex flex-col items-center justify-center rounded-xl border border-dashed bg-muted/20 py-12 text-center">
                    <p className="text-sm font-medium text-muted-foreground">
                        {emptyStateMessage}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {emptyStateHint}
                    </p>
                </div>
            ) : (
                <SourceFileList files={files} />
            )}
        </section>
    );
}
