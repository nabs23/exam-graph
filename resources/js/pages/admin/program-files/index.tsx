import { Head, Link } from "@inertiajs/react";
import { SourceFiles, type SourceFile } from "@/components/source-files";
import programs from "@/routes/programs";
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
            <SourceFiles ownerId={program.id} kind="program" files={files} />
        </main>
    );
}

ProgramFilesIndex.layout = (props: { program: { id: number } }) => ({
    breadcrumbs: [
        { title: "Program", href: programs.show(props.program) },
        { title: "Program files", href: programUploads.index(props.program) },
    ],
});
