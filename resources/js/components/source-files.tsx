import { usePoll } from "@inertiajs/react";
import { useEffect } from "react";
import {
    SourceFileDeleteButton,
    SourceFileList,
} from "@/components/source-file-list";
import { SourceFileUploadForm } from "@/components/source-file-upload-form";

export { SourceFileDeleteButton } from "@/components/source-file-list";

export type SourceFile = {
    id: number;
    title: string;
    file_type?: string;
    original_filename: string;
    mime_type: string | null;
    file_size: number | null;
    upload_status: string;
    embedding_status: 'not_requested' | 'supported' | 'queued' | 'processing' | 'complete' | 'unsupported' | 'failed';
    embedding_error_code: string | null;
    embedding_model: string | null;
    uploaded_at: string | null;
    metadata?: Record<string, string> | null;
    uploader: { name: string } | null;
};

export function useFileEmbeddingPolling(active: boolean, only: string[]) {
    const { start, stop } = usePoll(3000, { only }, {
        autoStart: false,
        mode: "rest",
    });

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
    kind,
    files,
    pollingOnly = ["files"],
}: {
    ownerId: number;
    kind: "program" | "subject";
    files: SourceFile[];
    pollingOnly?: string[];
}) {
    useFileEmbeddingPolling(
        files.some((file) =>
            ["queued", "processing"].includes(file.embedding_status) ||
            file.upload_status === "delete_pending",
        ),
        pollingOnly,
    );

    return (
        <section className="space-y-6 rounded-2xl border bg-card p-6 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b pb-4">
                <div>
                    <h2 className="text-xl font-semibold tracking-tight">Files</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Manage private source materials for this {kind}.
                    </p>
                </div>
                <span className="text-sm text-muted-foreground">
                    {files.length} {files.length === 1 ? "file" : "files"}
                </span>
            </div>
            <SourceFileUploadForm ownerId={ownerId} kind={kind} />

            {files.length === 0 ? (
                <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-12 text-center bg-muted/20">
                    <p className="text-sm font-medium text-muted-foreground">
                        No files have been uploaded yet.
                    </p>
                    <p className="text-xs text-muted-foreground mt-1">
                        Supported formats: PDF, DOCX, EPUB (max 100MB)
                    </p>
                </div>
            ) : (
                <SourceFileList files={files} kind={kind} />
            )}
        </section>
    );
}
