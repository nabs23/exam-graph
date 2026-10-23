import { Head, Link, usePage } from '@inertiajs/react';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { SubjectConceptsPanel } from '@/components/subject-concepts-panel';
import { SubjectFilesPanel } from '@/components/subject-files-panel';
import {
    SubjectSyllabusTopicsPanel,
    type SubjectConcept,
    type SubjectSyllabusTopic,
} from '@/components/subject-syllabus-topics-panel';
import { SubjectTopicExtractionPanel } from '@/components/subject-topic-extraction-panel';
import URLTabsContainer from '@/components/url-tabs-container';
import type { TabItem } from '@/components/url-tabs-container';
import { subjects } from '@/lib/curriculum-routes';
import { subjectTopics } from '@/lib/curriculum-routes';
import { programSubjects } from '@/lib/curriculum-routes';
import programs from '@/routes/programs';
import generations from '@/routes/programs/curriculum-generations';

export default function Subject({
    subject,
    programFiles,
    topicExtractionSummary,
    topicExtractionEnabled,
    topicExtractionUnavailableReason,
}: {
    subject: {
        id: number;
        name: string;
        code: string | null;
        program: { id: number; name: string };
        syllabus_topics: SubjectSyllabusTopic[];
        concepts: SubjectConcept[];
    };
    programFiles: import('@/components/source-files').SourceFile[];
    topicExtractionSummary: {
        count: number;
        latest: { id: number; status: string } | null;
    };
    topicExtractionEnabled: boolean;
    topicExtractionUnavailableReason: string | null;
}) {
    const { url } = usePage();
    const requestedTab = new URLSearchParams(url.split('?')[1] ?? '').get(
        'tab',
    );
    const activeTab =
        requestedTab &&
        ['topics', 'concepts', 'files', 'extraction'].includes(requestedTab)
            ? requestedTab
            : 'topics';
    const tabs: TabItem[] = [
        {
            value: 'topics',
            label: 'Syllabus topics',
            content: (
                <SubjectSyllabusTopicsPanel
                    subject={subject}
                    topics={subject.syllabus_topics}
                    concepts={subject.concepts}
                />
            ),
        },
        {
            value: 'concepts',
            label: 'Concepts',
            content: (
                <SubjectConceptsPanel
                    subject={subject}
                    concepts={subject.concepts}
                    topics={subject.syllabus_topics}
                />
            ),
        },
        {
            value: 'files',
            label: 'Files',
            content: (
                <SubjectFilesPanel
                    programId={subject.program.id}
                    subjectId={subject.id}
                    files={programFiles}
                />
            ),
        },
        {
            value: 'extraction',
            label: 'Topic extraction',
            content: (
                <SubjectTopicExtractionPanel
                    program={subject.program}
                    subject={subject}
                    summary={topicExtractionSummary}
                    enabled={topicExtractionEnabled}
                    unavailableReason={topicExtractionUnavailableReason}
                />
            ),
        },
    ];

    return (
        <PageShell>
            <Head title={subject.name} />
            <PageHeader
                eyebrow={`${subject.program.name}${subject.code ? ` · ${subject.code}` : ''}`}
                title={subject.name}
                description="Manage this subject’s official syllabus outline, instructional concepts, source materials, and review proposals. Official syllabus topics and author-approved learning content are shown in separate sections."
                actions={
                    <>
                        <Button asChild>
                            <Link href={subjectTopics.create(subject)}>
                                Add syllabus topic
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link
                                href={generations.create(subject.program.id, {
                                    query: {
                                        kind: 'concepts',
                                        subject_id: subject.id,
                                    },
                                })}
                            >
                                Draft concepts
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link
                                href={generations.create(subject.program.id, {
                                    query: {
                                        kind: 'lessons',
                                        subject_id: subject.id,
                                        scope_type: 'subject',
                                        scope_id: subject.id,
                                    },
                                })}
                            >
                                Draft lessons
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link
                                href={subjects.edit(subject, {
                                    query: { tab: activeTab },
                                })}
                            >
                                Edit subject
                            </Link>
                        </Button>
                    </>
                }
            />
            <URLTabsContainer
                tabs={tabs}
                defaultTab="topics"
                variant="full-width"
                boxedBody={false}
            />
        </PageShell>
    );
}

Subject.layout = (props: {
    subject: { id: number; program: { id: number } };
}) => ({
    breadcrumbs: [
        { title: 'Program', href: programs.show(props.subject.program) },
        {
            title: 'Subjects',
            href: programSubjects.index(props.subject.program),
        },
        { title: 'Subject', href: subjects.show(props.subject) },
    ],
});
