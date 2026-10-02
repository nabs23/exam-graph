import { router } from "@inertiajs/react";
import { useState } from "react";
import programFiles from "@/routes/program-files";
import programUploads from "@/routes/programs/files";
import subjectFiles from "@/routes/subject-files";
import subjectUploads from "@/routes/subjects/files";

const fileTypes = [
    "reviewer_ebook",
    "reviewer_notes",
    "lecture_material",
    "official_reference",
    "practice_material",
    "other",
];

export function SourceFileUploadForm({
    ownerId,
    kind,
}: {
    ownerId: number;
    kind: "program" | "subject";
}) {
    const [progress, setProgress] = useState(0);
    const [error, setError] = useState("");
    const [busy, setBusy] = useState(false);
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
            if (!response.ok) {
                throw new Error(
                    result.message ?? "Could not prepare the upload.",
                );
            }
            await new Promise<void>((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.upload.onprogress = (progressEvent) => {
                    if (progressEvent.lengthComputable) {
                        setProgress(
                            Math.round(
                                (progressEvent.loaded / progressEvent.total) *
                                    100,
                            ),
                        );
                    }
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
            const completed = await fetch(fileRoutes.store(result.file_id).url, {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN":
                        document.querySelector<HTMLMetaElement>(
                            'meta[name="csrf-token"]',
                        )?.content ?? "",
                    Accept: "application/json",
                },
            });
            if (!completed.ok) {
                throw new Error(
                    "Upload verification failed. Contact an administrator or retry.",
                );
            }
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
        <>
            <form
                onSubmit={upload}
                className="flex flex-wrap items-end gap-4 rounded-xl border border-dashed bg-muted/30 p-4"
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
                            {fileTypes.map((type) => (
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
                <div
                    role="alert"
                    className="rounded-lg border border-destructive/20 bg-destructive/10 p-3 text-sm font-medium text-destructive"
                >
                    {error}
                </div>
            )}
        </>
    );
}
