import { Head, Link } from '@inertiajs/react';
import { SourceFile } from '@/components/source-files';
import subjects from '@/routes/subjects';
import subjectFiles from '@/routes/subject-files';
import subjectUploads from '@/routes/subjects/files';

export default function SubjectFilesIndex({ subject, files }: { subject: { id: number; name: string; code: string }; files: SourceFile[] }) {
    return <main className="space-y-4 px-4 py-6 sm:px-6 lg:px-8"><Head title={`${subject.name} files`} /><Link className="text-sm text-primary underline" href={subjects.show(subject.id)}>Back to {subject.name}</Link><h1 className="text-3xl font-semibold">Subject files</h1>{files.length === 0 ? <p>No files found.</p> : <ul className="divide-y">{files.map((file) => <li key={file.id} className="py-3"><Link className="font-medium text-primary underline" href={subjectFiles.show(file.id)}>{file.title}</Link><p className="text-sm text-muted-foreground">{file.file_type} · {file.original_filename} · {file.upload_status}</p></li>)}</ul>}</main>;
}

SubjectFilesIndex.layout = (props: { subject: { id: number } }) => ({ breadcrumbs: [{ title: 'Subject', href: subjects.show(props.subject) }, { title: 'Subject files', href: subjectUploads.index(props.subject) }] });
