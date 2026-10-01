import { Head, Link } from '@inertiajs/react';
import { SourceFile } from '@/components/source-files';
import programs from '@/routes/programs';
import programFiles from '@/routes/program-files';

export default function ProgramFilesIndex({ program, files }: { program: { id: number; name: string; code: string }; files: SourceFile[] }) {
    return <main className="space-y-4 px-4 py-6 sm:px-6 lg:px-8"><Head title={`${program.name} files`} /><Link className="text-sm text-primary underline" href={programs.show(program.id)}>Back to {program.name}</Link><h1 className="text-3xl font-semibold">Program files</h1>{files.length === 0 ? <p>No files found.</p> : <ul className="divide-y">{files.map((file) => <li key={file.id} className="py-3"><Link className="font-medium text-primary underline" href={programFiles.show(file.id)}>{file.title}</Link><p className="text-sm text-muted-foreground">{file.original_filename} · {file.upload_status}</p></li>)}</ul>}</main>;
}
