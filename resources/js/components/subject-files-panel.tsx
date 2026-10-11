import { SourceFiles, type SourceFile } from '@/components/source-files';

export function SubjectFilesPanel({
    subjectId,
    files,
}: {
    subjectId: number;
    files: SourceFile[];
}) {
    return <SourceFiles ownerId={subjectId} kind="subject" files={files} pollingOnly={['subject']} />;
}
