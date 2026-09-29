import { Head, Link } from '@inertiajs/react';
import { SourceFile } from '@/components/source-files';
import subjects from '@/routes/subjects';

export default function SubjectFileShow({ file }: { file: SourceFile & { subject: { id: number; name: string; code: string } } }) {
    return <main className="space-y-4 px-4 py-6 sm:px-6 lg:px-8"><Head title={file.title} /><Link className="text-sm text-primary underline" href={subjects.show(file.subject.id)}>Back to {file.subject.name}</Link><h1 className="text-3xl font-semibold">{file.title}</h1><dl className="grid gap-3 sm:grid-cols-2"><div><dt className="text-sm text-muted-foreground">File type</dt><dd>{file.file_type}</dd></div><div><dt className="text-sm text-muted-foreground">Original filename</dt><dd>{file.original_filename}</dd></div><div><dt className="text-sm text-muted-foreground">Status</dt><dd>{file.upload_status}</dd></div><div><dt className="text-sm text-muted-foreground">Uploaded by</dt><dd>{file.uploader?.name ?? 'Unknown'}</dd></div></dl></main>;
}
