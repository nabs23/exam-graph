import { Head, Link } from '@inertiajs/react';
import { SourceFile } from '@/components/source-files';
import programs from '@/routes/programs';
import programFiles from '@/routes/program-files';
import programUploads from '@/routes/programs/files';

export default function ProgramFileShow({ file }: { file: SourceFile & { program: { id: number; name: string; code: string } } }) {
    return <main className="space-y-4 px-4 py-6 sm:px-6 lg:px-8"><Head title={file.title} /><Link className="text-sm text-primary underline" href={programs.show(file.program.id)}>Back to {file.program.name}</Link><h1 className="text-3xl font-semibold">{file.title}</h1><dl className="grid gap-3 sm:grid-cols-2"><div><dt className="text-sm text-muted-foreground">Original filename</dt><dd>{file.original_filename}</dd></div><div><dt className="text-sm text-muted-foreground">Status</dt><dd>{file.upload_status}</dd></div><div><dt className="text-sm text-muted-foreground">Uploaded by</dt><dd>{file.uploader?.name ?? 'Unknown'}</dd></div></dl></main>;
}

ProgramFileShow.layout = (props: { file: SourceFile & { program: { id: number; name: string } } }) => ({ breadcrumbs: [{ title: 'Program', href: programs.show(props.file.program) }, { title: 'Program files', href: programUploads.index(props.file.program) }, { title: 'File', href: programFiles.show(props.file) }] });
