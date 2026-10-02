import { Form, Link } from "@inertiajs/react";
import programFiles from "@/routes/program-files";
import subjectFiles from "@/routes/subject-files";
import { Button } from "@/components/ui/button";
import type { SourceFile } from "@/components/source-files";

type SourceFileKind = "program" | "subject";

export function SourceFileDeleteButton({
    file,
    kind,
}: {
    file: SourceFile;
    kind: SourceFileKind;
}) {
    const fileRoutes = kind === "program" ? programFiles : subjectFiles;
    const canDelete = ["uploaded", "failed", "delete_pending"].includes(
        file.upload_status,
    );

    return (
        <Form
            {...fileRoutes.destroy.form(file.id)}
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
                    aria-label={`${file.upload_status === "delete_pending" ? "Retry deletion of" : "Delete"} ${file.title}`}
                >
                    {processing
                        ? "Deleting…"
                        : file.upload_status === "delete_pending"
                          ? "Retry deletion"
                          : "Delete file"}
                </Button>
            )}
        </Form>
    );
}

export function SourceFileList({
    files,
    kind,
}: {
    files: SourceFile[];
    kind: SourceFileKind;
}) {
    const fileRoutes = kind === "program" ? programFiles : subjectFiles;

    return (
        <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {files.map((file) => (
                <li
                    key={file.id}
                    className="flex min-w-0 flex-col rounded-xl border bg-card shadow-sm"
                >
                    <Link
                        href={fileRoutes.show(file.id)}
                        className="block flex-1 rounded-t-xl p-5 transition-colors hover:bg-accent"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <h3 className="min-w-0 wrap-break-words font-semibold leading-snug">
                                {file.title}
                            </h3>
                            <span className="shrink-0 rounded-md bg-secondary px-2 py-0.5 text-xs capitalize">
                                {file.upload_status.replaceAll("_", " ")}
                            </span>
                        </div>
                        <dl className="mt-3 space-y-2 text-sm">
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    File type
                                </dt>
                                <dd className="capitalize">
                                    {file.file_type?.replaceAll("_", " ") ??
                                        (kind === "program"
                                            ? "Program file"
                                            : "Subject file")}
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
                                    {file.embedding_status.replaceAll("_", " ")}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    File size
                                </dt>
                                <dd>
                                    {file.file_size === null
                                        ? "Size pending"
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
                                              year: "numeric",
                                              month: "short",
                                              day: "numeric",
                                          })
                                        : file.upload_status.replaceAll(
                                              "_",
                                              " ",
                                          )}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Uploaded by
                                </dt>
                                <dd>{file.uploader?.name ?? "Unknown"}</dd>
                            </div>
                        </dl>
                    </Link>
                    <div className="flex flex-wrap items-center justify-end gap-3 px-5 py-2">
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
                        <SourceFileDeleteButton file={file} kind={kind} />
                    </div>
                </li>
            ))}
        </ul>
    );
}
