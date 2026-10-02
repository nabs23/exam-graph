import { Head, Link } from "@inertiajs/react";
import { SourceFiles } from "@/components/source-files";
import programs from "@/routes/programs";
import { programSubjects, subjects } from '@/lib/curriculum-routes';

export default function Program({
    program,
}: {
    program: {
        id: number;
        name: string;
        code: string;
        description: string | null;
        subjects: { id: number; name: string; code: string | null }[];
        files: import("@/components/source-files").SourceFile[];
    };
}) {
    return (
        <main className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Head title={program.name} />
            <div className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p className="text-sm font-medium uppercase tracking-wide text-primary">
                        {program.code}
                    </p>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        {program.name}
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        {program.description}
                    </p>
                </div>
                <Link
                    href={programs.edit(program)}
                    className="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium shadow-xs hover:bg-accent"
                >
                    Edit program
                </Link>
            </div>
            <div className="flex items-center justify-between">
                <h2 className="text-xl font-semibold">Subjects</h2>
                <Link
                    href={programSubjects.create(program)}
                    className="text-sm font-medium text-primary hover:underline"
                >
                    Create a subject
                </Link>
            </div>
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {program.subjects.map((subject) => (
                    <Link
                        key={subject.id}
                        href={subjects.show(subject)}
                        className="rounded-xl border bg-card p-5 shadow-sm transition-colors hover:border-primary/50 hover:bg-accent"
                    >
                        {subject.code && <b>{subject.code}</b>}
                        <p className="mt-1 font-medium">{subject.name}</p>
                    </Link>
                ))}
            </div>
            <SourceFiles
                ownerId={program.id}
                kind="program"
                pollingOnly={["program"]}
                files={program.files}
            />
        </main>
    );
}

Program.layout = (props: { program: { id: number; name: string } }) => ({
    breadcrumbs: [
        { title: 'Programs', href: programs.index() },
        { title: "Program", href: programs.show(props.program) },
    ],
});
