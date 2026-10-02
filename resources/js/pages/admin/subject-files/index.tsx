import { Head, Link } from "@inertiajs/react";
import { SourceFiles, type SourceFile } from "@/components/source-files";
import { subjects } from '@/lib/curriculum-routes';
import subjectUploads from "@/routes/subjects/files";

export default function SubjectFilesIndex({
    subject,
    files,
}: {
    subject: { id: number; name: string; code: string | null };
    files: SourceFile[];
}) {
    return (
        <main className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Head title={`${subject.name} files`} />
            <header className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    {subject.code && <p className="text-sm font-medium uppercase tracking-wide text-primary">{subject.code}</p>}
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
            <SourceFiles ownerId={subject.id} kind="subject" files={files} />
        </main>
    );
}

SubjectFilesIndex.layout = (props: { subject: { id: number } }) => ({
    breadcrumbs: [
        { title: "Subject", href: subjects.show(props.subject) },
        { title: "Subject files", href: subjectUploads.index(props.subject) },
    ],
});
