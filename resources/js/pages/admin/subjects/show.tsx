import { Head, Link } from '@inertiajs/react';
import { SubjectConceptsPanel } from '@/components/subject-concepts-panel';
import { SubjectFilesPanel } from '@/components/subject-files-panel';
import { SubjectSyllabusTopicsPanel, type SubjectConcept, type SubjectSyllabusTopic } from '@/components/subject-syllabus-topics-panel';
import { SubjectTopicExtractionPanel } from '@/components/subject-topic-extraction-panel';
import URLTabsContainer from '@/components/url-tabs-container';
import type { TabItem } from '@/components/url-tabs-container';
import { subjects } from '@/lib/curriculum-routes';
import { subjectTopics } from '@/lib/curriculum-routes';
import generations from '@/routes/programs/curriculum-generations';

export default function Subject({ subject, programFiles, topicExtractionSummary, topicExtractionEnabled, topicExtractionUnavailableReason }: {
    subject: { id: number; name: string; code: string | null; program: { id: number }; syllabus_topics: SubjectSyllabusTopic[]; concepts: SubjectConcept[] };
    programFiles: import('@/components/source-files').SourceFile[];
    topicExtractionSummary: { count: number; latest: { id: number; status: string } | null };
    topicExtractionEnabled: boolean;
    topicExtractionUnavailableReason: string | null;
}) {
    const tabs: TabItem[] = [
        {
            value: 'topics',
            label: 'Syllabus topics',
            content: <SubjectSyllabusTopicsPanel subject={subject} topics={subject.syllabus_topics} concepts={subject.concepts} />,
        },
        {
            value: 'concepts',
            label: 'Concepts',
            content: <SubjectConceptsPanel subject={subject} concepts={subject.concepts} topics={subject.syllabus_topics} />,
        },
        {
            value: 'files',
            label: 'Files',
            content: <SubjectFilesPanel programId={subject.program.id} subjectId={subject.id} files={programFiles} />,
        },
        {
            value: 'extraction',
            label: 'Topic extraction',
            content: <SubjectTopicExtractionPanel program={subject.program} subject={subject} summary={topicExtractionSummary} enabled={topicExtractionEnabled} unavailableReason={topicExtractionUnavailableReason} />,
        },
    ];

    return (
        <main className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Head title={subject.name} />
            <header className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    {subject.code && <p className="text-sm font-medium uppercase tracking-wide text-primary">{subject.code}</p>}
                    <h1 className="text-3xl font-semibold tracking-tight">{subject.name}</h1>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Link href={generations.create(subject.program.id, { query: { kind: 'concepts', subject_id: subject.id } })} className="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium shadow-xs hover:bg-accent">Draft concepts</Link>
                    <Link href={generations.create(subject.program.id, { query: { kind: 'lessons', subject_id: subject.id, scope_type: 'subject', scope_id: subject.id } })} className="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium shadow-xs hover:bg-accent">Draft lessons</Link>
                    <Link href={subjectTopics.index(subject)} className="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium shadow-xs hover:bg-accent">Manage syllabus topics</Link>
                    <Link href={subjects.edit(subject)} className="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium shadow-xs hover:bg-accent">Edit subject</Link>
                </div>
            </header>
            <URLTabsContainer tabs={tabs} defaultTab="topics" variant="full-width" boxedBody={false} />
        </main>
    );
}

Subject.layout = (props: { subject: { id: number } }) => ({ breadcrumbs: [{ title: 'Subjects', href: subjects.index() }, { title: 'Subject', href: subjects.show(props.subject) }] });
