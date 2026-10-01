import { router } from "@inertiajs/react";
import { useState } from "react";
import programFiles from "@/routes/program-files";
import programUploads from "@/routes/programs/files";
import subjectFiles from "@/routes/subject-files";
import subjectUploads from "@/routes/subjects/files";

export type SourceFile = {
    id: number;
    title: string;
    file_type?: string;
    original_filename: string;
    file_size: number | null;
    upload_status: string;
    uploaded_at: string | null;
    metadata?: Record<string, string> | null;
    uploader: { name: string } | null;
};

export function SourceFiles({
    ownerId,
    kind,
    files,
}: {
    ownerId: number;
    kind: "program" | "subject";
    files: SourceFile[];
}) {
    const [progress, setProgress] = useState(0);
    const [error, setError] = useState("");
    const [busy, setBusy] = useState(false);
    const types = [
        "reviewer_ebook",
        "reviewer_notes",
        "lecture_material",
        "official_reference",
        "practice_material",
        "other",
    ];
    const uploads = kind === "program" ? programUploads : subjectUploads;
    const fileRoutes = kind === "program" ? programFiles : subjectFiles;

    async function upload(event: React.ChangeEvent<HTMLFormElement>) {
        event.preventDefault();
        setError("");
        const form = event.currentTarget;
        const input = form.elements.namedItem("file") as HTMLInputElement;
        const file = input.files?.[0];
        if (!file) return;
        if (
            !/\.(pdf|docx|epub)$/i.test(file.name) ||
            file.size > 100 * 1024 * 1024
        ) {
            setError("Choose a PDF, DOCX, or EPUB file up to 100 MiB.");
            return;
        }
        setBusy(true);
        try {
            const mime =
                file.type ||
                (file.name.toLowerCase().endsWith(".pdf")
                    ? "application/pdf"
                    : file.name.toLowerCase().endsWith(".epub")
                      ? "application/epub+zip"
                      : "application/vnd.openxmlformats-officedocument.wordprocessingml.document");
            const response = await fetch(uploads.uploadUrl(ownerId).url, {
                method: "POST",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN":
                        document.querySelector<HTMLMetaElement>(
                            'meta[name="csrf-token"]',
                        )?.content ?? "",
                },
                body: JSON.stringify({
                    title: (
                        form.elements.namedItem("title") as HTMLInputElement
                    ).value,
                    ...(kind === "subject" && {
                        file_type: (
                            form.elements.namedItem(
                                "file_type",
                            ) as HTMLSelectElement
                        ).value,
                    }),
                    original_filename: file.name,
                    mime_type: mime,
                    extension: file.name.split(".").pop()?.toLowerCase(),
                    file_size: file.size,
                }),
            });
            const responseBody = await response.text();
            let result: {
                file_id: number;
                upload_url: string;
                required_headers: Record<string, string>;
                message?: string;
            };
            try {
                result = JSON.parse(responseBody);
            } catch {
                throw new Error(
                    `Could not prepare the upload (HTTP ${response.status}).`,
                );
            }
            if (!response.ok)
                throw new Error(
                    result.message ?? "Could not prepare the upload.",
                );
            await new Promise<void>((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.upload.onprogress = (progressEvent) => {
                    if (progressEvent.lengthComputable)
                        setProgress(
                            Math.round(
                                (progressEvent.loaded / progressEvent.total) *
                                    100,
                            ),
                        );
                };
                xhr.onload = () =>
                    xhr.status >= 200 && xhr.status < 300
                        ? resolve()
                        : reject(
                              new Error("S3 upload failed. Retry the upload."),
                          );
                xhr.onerror = () =>
                    reject(
                        new Error(
                            "S3 upload failed. Check your connection and retry.",
                        ),
                    );
                xhr.open("PUT", result.upload_url);
                Object.entries(
                    result.required_headers as Record<string, string>,
                ).forEach(([name, value]) => xhr.setRequestHeader(name, value));
                xhr.send(file);
            });
            const completed = await fetch(
                fileRoutes.store(result.file_id).url,
                {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN":
                            document.querySelector<HTMLMetaElement>(
                                'meta[name="csrf-token"]',
                            )?.content ?? "",
                        Accept: "application/json",
                    },
                },
            );
            if (!completed.ok)
                throw new Error(
                    "Upload verification failed. Contact an administrator or retry.",
                );
            form.reset();
            setProgress(0);
            router.reload();
        } catch (uploadError) {
            setError(
                uploadError instanceof Error
                    ? uploadError.message
                    : "Upload failed.",
            );
        } finally {
            setBusy(false);
        }
    }

    return (
        <section className="space-y-6 rounded-2xl border bg-card p-6 shadow-sm">
            <div className="border-b pb-4">
                <h2 className="text-xl font-semibold tracking-tight">Files</h2>
                <p className="text-sm text-muted-foreground mt-1">
                    Manage private source materials for this {kind}.
                </p>
            </div>
            
            <form
                onSubmit={upload}
                className="flex flex-wrap items-end gap-4 bg-muted/30 p-4 rounded-xl border border-dashed"
            >
                <label className="min-w-48 flex-1 text-sm font-medium">
                    <span className="mb-1.5 block">Title</span>
                    <input
                        name="title"
                        required
                        maxLength={255}
                        className="block w-full rounded-lg border-input bg-background px-3 py-2 text-sm shadow-sm transition-colors focus:border-primary focus:ring-1 focus:ring-primary"
                        placeholder="Enter a descriptive title"
                    />
                </label>
                {kind === "subject" && (
                    <label className="min-w-48 flex-1 text-sm font-medium">
                        <span className="mb-1.5 block">File type</span>
                        <select
                            name="file_type"
                            className="block w-full rounded-lg border-input bg-background px-3 py-2 text-sm shadow-sm transition-colors focus:border-primary focus:ring-1 focus:ring-primary"
                        >
                            {types.map((type) => (
                                <option key={type} value={type}>
                                    {type
                                        .replaceAll("_", " ")
                                        .replace(/\b\w/g, (character) =>
                                            character.toUpperCase(),
                                        )}
                                </option>
                            ))}
                        </select>
                    </label>
                )}
                <label className="min-w-48 flex-1 text-sm font-medium">
                    <span className="mb-1.5 block">Document</span>
                    <input
                        name="file"
                        type="file"
                        accept=".pdf,.docx,.epub"
                        required
                        className="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary hover:file:bg-primary/20"
                    />
                </label>
                <div className="ml-auto flex shrink-0 justify-end">
                    <button
                        disabled={busy}
                        className="rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground shadow transition-colors hover:bg-primary/90 disabled:opacity-50"
                    >
                        {busy ? "Uploading..." : "Upload file"}
                    </button>
                </div>
            </form>

            {busy && (
                <div className="space-y-2">
                    <div className="flex justify-between text-xs text-muted-foreground">
                        <span>Uploading...</span>
                        <span>{progress}%</span>
                    </div>
                    <div className="h-2 w-full overflow-hidden rounded-full bg-secondary">
                        <div 
                            className="h-full bg-primary transition-all duration-300 ease-in-out" 
                            style={{ width: `${progress}%` }}
                        />
                    </div>
                </div>
            )}

            {error && (
                <div role="alert" className="rounded-lg bg-destructive/10 p-3 text-sm font-medium text-destructive border border-destructive/20">
                    {error}
                </div>
            )}

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
                <ul className="divide-y rounded-xl border">
                    {files.map((file) => (
                        <li
                            key={file.id}
                            className="flex flex-col gap-3 p-4 transition-colors hover:bg-muted/30 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div className="flex-1 space-y-1">
                                <p className="text-sm font-semibold">{file.title}</p>
                                <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                    {file.file_type && (
                                        <span className="rounded-md bg-secondary px-2 py-0.5 capitalize">
                                            {file.file_type.replaceAll("_", " ")}
                                        </span>
                                    )}
                                    <span>{file.original_filename}</span>
                                    <span>&bull;</span>
                                    <span>
                                        {file.file_size === null
                                            ? "Size pending"
                                            : `${(file.file_size / 1048576).toFixed(1)} MiB`}
                                    </span>
                                    <span>&bull;</span>
                                    <span>
                                        {file.uploaded_at
                                            ? new Date(file.uploaded_at).toLocaleDateString(undefined, { 
                                                year: 'numeric', 
                                                month: 'short', 
                                                day: 'numeric' 
                                              })
                                            : file.upload_status}
                                    </span>
                                    {file.uploader && (
                                        <>
                                            <span>&bull;</span>
                                            <span>Uploaded by {file.uploader.name}</span>
                                        </>
                                    )}
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                {file.upload_status === "uploaded" && (
                                    <button
                                        type="button"
                                        onClick={async () => {
                                            const result = await fetch(
                                                fileRoutes.download(file.id).url,
                                                {
                                                    method: "POST",
                                                    headers: {
                                                        "X-CSRF-TOKEN":
                                                            document.querySelector<HTMLMetaElement>(
                                                                'meta[name="csrf-token"]',
                                                            )?.content ?? "",
                                                        Accept: "application/json",
                                                    },
                                                },
                                            ).then((response) => response.json());
                                            window.location.assign(result.url);
                                        }}
                                        className="rounded-md px-3 py-1.5 text-sm font-medium text-primary transition-colors hover:bg-primary/10"
                                    >
                                        Download
                                    </button>
                                )}
                                <button
                                    type="button"
                                    onClick={() => {
                                        if (window.confirm(`Delete ${file.title}?`))
                                            router.delete(fileRoutes.destroy(file.id).url);
                                    }}
                                    className="rounded-md px-3 py-1.5 text-sm font-medium text-destructive transition-colors hover:bg-destructive/10"
                                >
                                    {file.upload_status === "delete_pending"
                                        ? "Retry deletion"
                                        : "Delete"}
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
