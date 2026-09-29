import { router } from "@inertiajs/react";
import { useState } from "react";
import programFiles from "@/routes/program-files";
import programUploads from "@/routes/programs/files";
import subjectFiles from "@/routes/subject-files";
import subjectUploads from "@/routes/subjects/files";

export type SourceFile = {
    id: number;
    title: string;
    file_type: string;
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
    const types =
        kind === "program"
            ? [
                  "exam_specification",
                  "official_syllabus",
                  "board_resolution",
                  "amendment_or_clarification",
                  "program_reference",
                  "other",
              ]
            : [
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
                    file_type: (
                        form.elements.namedItem(
                            "file_type",
                        ) as HTMLSelectElement
                    ).value,
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
        <section className="space-y-4 rounded-xl border p-5">
            <div>
                <h2 className="text-xl font-semibold">Files</h2>
                <p className="text-sm text-muted-foreground">
                    Private source materials for this {kind}.
                </p>
            </div>
            <form
                onSubmit={upload}
                className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
            >
                <label className="text-sm">
                    Title
                    <input
                        name="title"
                        required
                        maxLength={255}
                        className="mt-1 block w-full rounded-md border bg-background px-3 py-2"
                    />
                </label>
                <label className="text-sm">
                    File type
                    <select
                        name="file_type"
                        className="mt-1 block w-full rounded-md border bg-background px-3 py-2"
                    >
                        {types.map((type) => (
                            <option key={type} value={type}>
                                {type.replaceAll("_", " ")}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="text-sm">
                    Document
                    <input
                        name="file"
                        type="file"
                        accept=".pdf,.docx,.epub"
                        required
                        className="mt-1 block w-full"
                    />
                </label>
                <button
                    disabled={busy}
                    className="self-end rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
                >
                    {busy ? `Uploading ${progress}%` : "Upload file"}
                </button>
            </form>
            {error && (
                <p role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            )}
            {files.length === 0 ? (
                <p className="rounded-md bg-muted p-4 text-sm">
                    No files have been uploaded.
                </p>
            ) : (
                <ul className="divide-y">
                    {files.map((file) => (
                        <li
                            key={file.id}
                            className="flex flex-wrap items-center justify-between gap-3 py-3"
                        >
                            <div>
                                <p className="font-medium">{file.title}</p>
                                <p className="text-sm text-muted-foreground">
                                    {file.file_type.replaceAll("_", " ")} ·{" "}
                                    {file.original_filename} ·{" "}
                                    {file.file_size === null
                                        ? "Size pending"
                                        : `${(file.file_size / 1048576).toFixed(1)} MiB`}{" "}
                                    ·{" "}
                                    {file.uploaded_at
                                        ? new Date(
                                              file.uploaded_at,
                                          ).toLocaleString()
                                        : file.upload_status}
                                    {file.uploader
                                        ? ` · ${file.uploader.name}`
                                        : ""}
                                </p>
                            </div>
                            <div className="flex gap-2">
                                {file.upload_status === "uploaded" && (
                                    <button
                                        type="button"
                                        onClick={async () => {
                                            const result = await fetch(
                                                fileRoutes.download(file.id)
                                                    .url,
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
                                            ).then((response) =>
                                                response.json(),
                                            );
                                            window.location.assign(result.url);
                                        }}
                                        className="text-sm text-primary underline"
                                    >
                                        Download
                                    </button>
                                )}
                                <button
                                    type="button"
                                    onClick={() => {
                                        if (
                                            window.confirm(
                                                `Delete ${file.title}?`,
                                            )
                                        )
                                            router.delete(
                                                fileRoutes.destroy(file.id).url,
                                            );
                                    }}
                                    className="text-sm text-destructive underline"
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
