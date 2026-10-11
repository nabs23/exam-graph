import { SourceFiles, type SourceFile } from '@/components/source-files';

export function SubjectFilesPanel({
    programId,
    subjectId,
    files,
}: {
    programId: number;
    subjectId: number;
    files: SourceFile[];
}) {
    return <SourceFiles ownerId={programId} subjectId={subjectId} files={files} pollingOnly={['programFiles']} />;
}
