import { Head, Link } from "@inertiajs/react";
import { SourceFile } from "@/components/source-files";
import programs from "@/routes/programs";
import programFiles from "@/routes/program-files";
import programUploads from "@/routes/programs/files";

export default function ProgramFilesIndex({
    program,
    files,
}: {
    program: { id: number; name: string; code: string };
    files: SourceFile[];
}) {
    return (
        <main className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Head title={`${program.name} files`} />
            <header className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p className="text-sm font-medium uppercase tracking-wide text-primary">
                        {program.code}
                    </p>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        {program.name}
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        Source files for this program.
                    </p>
                </div>
                <Link
                    href={programs.show(program)}
                    className="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium shadow-xs hover:bg-accent"
                >
                    Back to program
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
                            Files uploaded for this program will appear here.
                        </p>
                    </div>
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {files.map((file) => (
                            <li key={file.id}>
                                <Link
                                    href={programFiles.show(file.id)}
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

ProgramFilesIndex.layout = (props: { program: { id: number } }) => ({
    breadcrumbs: [
        { title: "Program", href: programs.show(props.program) },
        { title: "Program files", href: programUploads.index(props.program) },
    ],
});
