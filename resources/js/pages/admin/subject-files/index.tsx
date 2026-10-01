import { Head, Link } from "@inertiajs/react";
import { SourceFile } from "@/components/source-files";
import subjects from "@/routes/subjects";
import subjectFiles from "@/routes/subject-files";
import subjectUploads from "@/routes/subjects/files";

export default function SubjectFilesIndex({
    subject,
    files,
}: {
    subject: { id: number; name: string; code: string };
    files: SourceFile[];
}) {
    return (
        <main className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Head title={`${subject.name} files`} />
            <header className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p className="text-sm font-medium uppercase tracking-wide text-primary">
                        {subject.code}
                    </p>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        {subject.name}
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        Source files for this subject.
                    </p>
                </div>
                <Link
                    href={subjects.show(subject)}
                    className="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium shadow-xs hover:bg-accent"
                >
                    Back to subject
                </Link>
            </header>
            <section className="space-y-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold">Files</h2>
                    <span className="text-sm text-muted-foreground">
                        {files.length} {files.length === 1 ? "file" : "files"}
                    </span>
                </div>
                {files.length === 0 ? (
                    <div className="rounded-xl border border-dashed bg-muted/20 px-6 py-12 text-center">
                        <p className="font-medium">No files found</p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Files uploaded for this subject will appear here.
                        </p>
                    </div>
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {files.map((file) => (
                            <li key={file.id}>
                                <Link
                                    href={subjectFiles.show(file.id)}
                                    className="block h-full rounded-xl border bg-card p-5 shadow-sm transition-colors hover:border-primary/50 hover:bg-accent"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <h3 className="font-semibold leading-snug">
                                            {file.title}
                                        </h3>
                                        <span className="shrink-0 rounded-md bg-secondary px-2 py-0.5 text-xs capitalize">
                                            {file.upload_status.replaceAll("_", " ")}
                                        </span>
                                    </div>
                                    <p className="mt-2 break-all text-sm text-muted-foreground">
                                        {file.original_filename}
                                    </p>
                                    {file.file_type && (
                                        <p className="mt-3 text-xs text-muted-foreground">
                                            {file.file_type.replaceAll("_", " ")}
                                        </p>
                                    )}
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </main>
    );
}

SubjectFilesIndex.layout = (props: { subject: { id: number } }) => ({
    breadcrumbs: [
        { title: "Subject", href: subjects.show(props.subject) },
        { title: "Subject files", href: subjectUploads.index(props.subject) },
    ],
});
