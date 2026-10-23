import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { SourceFiles, type SourceFile } from '@/components/source-files';
import programs from '@/routes/programs';
import programUploads from '@/routes/programs/files';

export default function ProgramFilesIndex({
    program,
    files,
    subjects,
    subjectFilter,
}: {
    program: { id: number; name: string; code: string };
    files: SourceFile[];
    subjects: { id: number; name: string }[];
    subjectFilter: string;
}) {
    const [pendingSubjectFilter, setPendingSubjectFilter] = useState<
        string | null
    >(null);
    const [uploadSuccessMessage, setUploadSuccessMessage] = useState<
        string | null
    >(null);
    const activeSubjectFilter = pendingSubjectFilter ?? subjectFilter;
    const selectedSubject = subjects.find(
        (subject) => subject.id.toString() === activeSubjectFilter,
    );
    const emptyStateMessage =
        activeSubjectFilter === 'shared'
            ? 'No files are shared across this program yet.'
            : selectedSubject
              ? `No files are assigned to ${selectedSubject.name} yet.`
              : 'No source files have been uploaded to this program yet.';
    const emptyStateHint =
        activeSubjectFilter === 'all'
            ? 'Upload a PDF, DOCX, or EPUB file up to 100 MiB to start the library.'
            : 'Upload a file in this scope, or choose another scope to view its files.';

    return (
        <PageShell className="mx-auto max-w-6xl">
            <Head title={`${program.name} files`} />
            <PageHeader
                eyebrow={`${program.code} · ${program.name}`}
                title="Source files"
                description="Upload and organize reference material for this program and its subjects."
                actions={
                    <Button asChild variant="outline">
                        <Link
                            href={programs.show(program, {
                                query: { tab: 'files' },
                            })}
                        >
                            Back to program
                        </Link>
                    </Button>
                }
            />
            <label
                htmlFor="source-file-subject-filter"
                className="grid max-w-sm gap-2 text-sm font-medium"
            >
                <span>Show files for</span>
                <select
                    id="source-file-subject-filter"
                    value={activeSubjectFilter}
                    disabled={pendingSubjectFilter !== null}
                    onChange={(event) => {
                        const nextFilter = event.target.value;
                        setPendingSubjectFilter(nextFilter);
                        router.visit(
                            programUploads.index(program, {
                                query: { subject: nextFilter },
                            }),
                            {
                                onFinish: () => setPendingSubjectFilter(null),
                            },
                        );
                    }}
                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                >
                    <option value="all">All program files</option>
                    <option value="shared">Shared across program</option>
                    {subjects.map((subject) => (
                        <option key={subject.id} value={subject.id}>
                            {subject.name}
                        </option>
                    ))}
                </select>
            </label>
            {pendingSubjectFilter !== null && (
                <p role="status" className="text-sm text-muted-foreground">
                    Loading{' '}
                    {selectedSubject?.name ??
                        (activeSubjectFilter === 'shared'
                            ? 'shared'
                            : 'all program')}{' '}
                    files…
                </p>
            )}
            {uploadSuccessMessage && (
                <p
                    role="status"
                    aria-live="polite"
                    className="rounded-md border border-primary/20 bg-primary/5 px-3 py-2 text-sm"
                >
                    {uploadSuccessMessage}
                </p>
            )}
            <div aria-busy={pendingSubjectFilter !== null}>
                <SourceFiles
                    ownerId={program.id}
                    files={files}
                    subjects={subjects}
                    defaultUploadSubjectId={selectedSubject?.id ?? null}
                    emptyStateMessage={emptyStateMessage}
                    emptyStateHint={emptyStateHint}
                    onUploadComplete={(uploadedSubjectId) => {
                        setUploadSuccessMessage(null);
                        router.visit(
                            programUploads.index(program, {
                                query: {
                                    subject: uploadedSubjectId || 'shared',
                                },
                            }),
                            {
                                preserveState: true,
                                onSuccess: () =>
                                    setUploadSuccessMessage(
                                        'Upload complete. The file appears below with its current text processing status.',
                                    ),
                            },
                        );
                    }}
                />
            </div>
        </PageShell>
    );
}

ProgramFilesIndex.layout = (props: { program: { id: number } }) => ({
    breadcrumbs: [
        {
            title: 'Program',
            href: programs.show(props.program, {
                query: { tab: 'files' },
            }),
        },
        { title: 'Program files', href: programUploads.index(props.program) },
    ],
});
