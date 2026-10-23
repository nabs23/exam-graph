import { Head, Link, usePage, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import { ArrowRight, Circle, CircleDot } from 'lucide-react';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { show as showExtraction } from '@/actions/App/Http/Controllers/CurriculumExtractionController';
import { CurriculumExtractionsPanel } from '@/components/curriculum-extractions-panel';
import type { CurriculumExtractionSummary } from '@/components/curriculum-extractions-panel';
import { ProgramSubjectsPanel } from '@/components/program-subjects-panel';
import { SourceFiles, type SourceFile } from '@/components/source-files';
import URLTabsContainer from '@/components/url-tabs-container';
import type { TabItem } from '@/components/url-tabs-container';
import programFiles from '@/routes/program-files';
import programs from '@/routes/programs';
import curriculumGenerations from '@/routes/programs/curriculum-generations';
import { programSubjects } from '@/lib/curriculum-routes';

export default function Program({
    program,
    extractions,
    curriculumExtractionEnabled,
    curriculumExtractionHasSources,
    curriculumExtractionUnavailableReason,
    curriculumExtractionModels,
    curriculumExtractionDefaultModel,
}: {
    program: {
        id: number;
        name: string;
        code: string;
        description: string | null;
        curriculum_generations_count: number;
        pending_curriculum_generations_count: number;
        in_progress_curriculum_generations_count: number;
        failed_curriculum_generations_count: number;
        subjects: { id: number; name: string; code: string | null }[];
        files: SourceFile[];
    };
    extractions: CurriculumExtractionSummary[];
    curriculumExtractionEnabled: boolean;
    curriculumExtractionHasSources: boolean;
    curriculumExtractionUnavailableReason: string | null;
    curriculumExtractionModels: string[];
    curriculumExtractionDefaultModel: string | null;
}) {
    const { url } = usePage();
    const requestedTab = new URLSearchParams(url.split('?')[1] ?? '').get(
        'tab',
    );
    const activeTab =
        requestedTab &&
        ['subjects', 'files', 'extractions'].includes(requestedTab)
            ? requestedTab
            : 'subjects';
    const hasFastBackgroundWork =
        program.in_progress_curriculum_generations_count > 0 ||
        extractions.some((extraction) =>
            ['queued', 'processing'].includes(extraction.status),
        ) ||
        program.files.some(
            (file) =>
                ['queued', 'processing'].includes(file.embedding_status) ||
                file.upload_status === 'delete_pending',
        );
    const hasUploadAwaitingVerification = program.files.some(
        (file) => file.upload_status === 'pending_upload',
    );
    const { start, stop } = usePoll(
        hasFastBackgroundWork ? 3000 : 30000,
        { only: ['program', 'extractions'] },
        { autoStart: false, mode: 'rest' },
    );

    useEffect(() => {
        if (hasFastBackgroundWork || hasUploadAwaitingVerification) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [hasFastBackgroundWork, hasUploadAwaitingVerification, start, stop]);

    const tabs: TabItem[] = [
        {
            value: 'subjects',
            label: 'Subjects',
            content: (
                <ProgramSubjectsPanel
                    program={program}
                    subjects={program.subjects}
                />
            ),
        },
        {
            value: 'files',
            label: 'Files',
            content: (
                <SourceFiles
                    ownerId={program.id}
                    pollingOnly={['program']}
                    files={program.files}
                    subjects={program.subjects}
                    pollingEnabled={false}
                />
            ),
        },
        {
            value: 'extractions',
            label: 'Extractions',
            content: (
                <CurriculumExtractionsPanel
                    program={program}
                    extractions={extractions}
                    curriculumExtractionEnabled={curriculumExtractionEnabled}
                    curriculumExtractionHasSources={
                        curriculumExtractionHasSources
                    }
                    curriculumExtractionUnavailableReason={
                        curriculumExtractionUnavailableReason
                    }
                    curriculumExtractionModels={curriculumExtractionModels}
                    curriculumExtractionDefaultModel={
                        curriculumExtractionDefaultModel
                    }
                />
            ),
        },
    ];
    const latestExtraction = extractions[0];
    const extractionStatus = latestExtraction
        ? extractionStatusDetails(latestExtraction)
        : null;
    const sourceFilesHref = programs.show(program, { query: { tab: 'files' } });
    const sourceFileStatus = sourceFileStatusDetails(
        program.files,
        sourceFilesHref,
    );
    const extractionDescription = [
        curriculumExtractionUnavailableReason,
        !curriculumExtractionHasSources
            ? 'No processed program PDF is ready. Upload a PDF and wait for text processing to finish before preparing an official curriculum extraction.'
            : !latestExtraction
              ? 'A processed program PDF is ready for an official curriculum extraction.'
              : extractionStatus?.label === 'Failed'
                ? 'A processed program PDF is ready if another extraction is needed.'
                : null,
    ]
        .filter(Boolean)
        .join(' ');
    const extractionStateLabel = !curriculumExtractionEnabled
        ? 'Unavailable'
        : (extractionStatus?.label ??
          (curriculumExtractionHasSources ? 'Ready to extract' : 'PDF needed'));
    const extractionAction = !curriculumExtractionEnabled
        ? 'View availability'
        : latestExtraction
          ? (extractionStatus?.action ?? 'View latest extraction')
          : curriculumExtractionHasSources
            ? 'Prepare extraction'
            : 'Check PDF readiness';
    const extractionHref =
        curriculumExtractionEnabled && latestExtraction
            ? showExtraction(latestExtraction.id)
            : programs.show(program, { query: { tab: 'extractions' } });
    const proposalDescription = [
        program.pending_curriculum_generations_count > 0
            ? `${program.pending_curriculum_generations_count} ${program.pending_curriculum_generations_count === 1 ? 'proposal needs' : 'proposals need'} review.`
            : null,
        program.in_progress_curriculum_generations_count > 0
            ? `${program.in_progress_curriculum_generations_count} ${program.in_progress_curriculum_generations_count === 1 ? 'proposal is' : 'proposals are'} still generating.`
            : null,
        program.failed_curriculum_generations_count > 0
            ? `${program.failed_curriculum_generations_count} ${program.failed_curriculum_generations_count === 1 ? 'proposal failed' : 'proposals failed'}. Open proposal history for details or prepare another.`
            : null,
    ]
        .filter(Boolean)
        .join(' ');
    const proposalStateLabel =
        program.failed_curriculum_generations_count > 0
            ? 'Needs attention'
            : program.pending_curriculum_generations_count > 0
              ? 'Needs review'
              : program.in_progress_curriculum_generations_count > 0
                ? 'Generating'
                : program.curriculum_generations_count > 0
                  ? 'Available'
                  : 'Not started';
    const proposalAction =
        program.pending_curriculum_generations_count > 0
            ? 'Review pending proposals'
            : program.failed_curriculum_generations_count > 0
              ? 'Review failed proposals'
              : program.in_progress_curriculum_generations_count > 0
                ? 'Check proposal progress'
                : program.curriculum_generations_count === 0
                  ? 'Prepare a proposal'
                  : 'View proposals';

    return (
        <PageShell>
            <Head title={program.name} />
            <PageHeader
                eyebrow={`${program.code} · Program workspace`}
                title={program.name}
                description={
                    program.description ||
                    'Manage this program’s subjects, source materials, and curriculum proposals.'
                }
                actions={
                    <>
                        <Button variant="outline" asChild>
                            <Link href={curriculumGenerations.index(program)}>
                                Curriculum proposals
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link
                                href={programs.edit(program, {
                                    query: { tab: activeTab },
                                })}
                            >
                                Edit program
                            </Link>
                        </Button>
                    </>
                }
            />
            <section
                aria-labelledby="program-setup-heading"
                className="space-y-4"
            >
                <div>
                    <h2
                        id="program-setup-heading"
                        className="text-xl font-semibold tracking-tight"
                    >
                        Build this program
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Set up the curriculum structure and source material,
                        then prepare proposals for review.
                    </p>
                </div>
                <ol className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <SetupStep
                        number="1"
                        title="Subject structure"
                        count={program.subjects.length}
                        itemLabel="subject"
                        emptyText="Add subjects so topics and learning content have a home."
                        href={
                            program.subjects.length === 0
                                ? programSubjects.create(program)
                                : programs.show(program, {
                                      query: { tab: 'subjects' },
                                  })
                        }
                        action={
                            program.subjects.length === 0
                                ? 'Add subjects'
                                : 'Manage subjects'
                        }
                    />
                    <SetupStep
                        number="2"
                        title="Source library"
                        count={program.files.length}
                        started={program.files.length > 0}
                        stateLabel={sourceFileStatus.label}
                        description={sourceFileStatus.description}
                        itemLabel="source file"
                        emptyText="Add source files to prepare proposals grounded in selected materials."
                        href={sourceFileStatus.href}
                        action={sourceFileStatus.action}
                    />
                    <SetupStep
                        number="3"
                        title="Official curriculum"
                        count={extractions.length}
                        started={
                            extractions.length > 0 ||
                            curriculumExtractionHasSources
                        }
                        stateLabel={extractionStateLabel}
                        description={[
                            extractionStatus?.description,
                            extractionDescription,
                        ]
                            .filter(Boolean)
                            .join(' ')}
                        itemLabel="official curriculum extraction"
                        emptyText="Extract official curriculum from program PDFs, then review the proposal."
                        href={extractionHref}
                        action={extractionAction}
                    />
                    <SetupStep
                        number="4"
                        title="AI proposals"
                        count={program.curriculum_generations_count}
                        stateLabel={proposalStateLabel}
                        description={proposalDescription || undefined}
                        itemLabel="AI proposal"
                        emptyText="Draft curriculum proposals and review each item before it becomes canonical."
                        href={curriculumGenerations.index(program)}
                        action={proposalAction}
                    />
                </ol>
            </section>
            <URLTabsContainer
                tabs={tabs}
                defaultTab="subjects"
                variant="full-width"
                boxedBody={false}
            />
        </PageShell>
    );
}

function SetupStep({
    number,
    title,
    count,
    description,
    started,
    stateLabel,
    itemLabel,
    emptyText,
    href,
    action,
}: {
    number: string;
    title: string;
    count: number;
    description?: string;
    started?: boolean;
    stateLabel?: string;
    itemLabel: string;
    emptyText: string;
    href: string | { url: string };
    action: string;
}) {
    const isStarted = started ?? count > 0;

    return (
        <li>
            <Card className="h-full py-0">
                <CardContent className="flex h-full flex-col gap-4 p-4">
                    <div className="flex items-start justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                                {number}
                            </span>
                            <h3 className="font-semibold">{title}</h3>
                        </div>
                        <span
                            role="status"
                            className="inline-flex shrink-0 items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            {isStarted ? (
                                <CircleDot
                                    aria-hidden="true"
                                    className="size-4 text-primary"
                                />
                            ) : (
                                <Circle aria-hidden="true" className="size-4" />
                            )}
                            {stateLabel ??
                                (isStarted ? 'Started' : 'Not started')}
                        </span>
                    </div>
                    <p className="text-sm leading-5 text-muted-foreground">
                        {description ??
                            (isStarted
                                ? `${count} ${count === 1 ? itemLabel : `${itemLabel}s`} in this program.`
                                : emptyText)}
                    </p>
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="mt-auto w-full justify-between"
                    >
                        <Link href={typeof href === 'string' ? href : href.url}>
                            {action}
                            <ArrowRight aria-hidden="true" />
                        </Link>
                    </Button>
                </CardContent>
            </Card>
        </li>
    );
}

function extractionStatusDetails(extraction: CurriculumExtractionSummary): {
    label: string;
    description: string;
    action: string;
} {
    switch (extraction.status) {
        case 'queued':
            return {
                label: 'Queued',
                description: 'The latest extraction is queued.',
                action: 'Check extraction status',
            };
        case 'processing':
            return {
                label: 'Processing',
                description: 'The latest extraction is being prepared.',
                action: 'Check extraction status',
            };
        case 'reviewing':
            return {
                label: 'Needs review',
                description:
                    'The latest proposal is ready for an authorized review.',
                action: 'Review proposal',
            };
        case 'published':
            return {
                label: 'Published',
                description: 'The latest proposal was reviewed and published.',
                action: 'View published extraction',
            };
        case 'rejected':
            return {
                label: 'Rejected',
                description:
                    'The latest proposal was rejected. Review it for context before preparing another extraction.',
                action: 'View rejected extraction',
            };
        case 'failed':
            return {
                label: 'Failed',
                description:
                    'The latest extraction failed. Open it for the error details and prepare another if needed.',
                action: 'View failed extraction',
            };
        default:
            return {
                label: extraction.status.replaceAll('_', ' '),
                description: 'Open the latest extraction to check its status.',
                action: 'View latest extraction',
            };
    }
}

function sourceFileStatusDetails(
    files: SourceFile[],
    sourceFilesHref: string | { url: string },
): {
    label: string;
    description?: string;
    action: string;
    href: string | { url: string };
} {
    if (files.length === 0) {
        return {
            label: 'Not started',
            action: 'Add source files',
            href: sourceFilesHref,
        };
    }

    const failedUploads = files.filter(
        (file) => file.upload_status === 'failed',
    ).length;
    const pendingDeletions = files.filter(
        (file) => file.upload_status === 'delete_pending',
    ).length;
    const failedPdfFiles = files.filter(
        (file) =>
            file.upload_status === 'uploaded' &&
            file.mime_type === 'application/pdf' &&
            ['failed', 'unsupported'].includes(file.embedding_status),
    );

    if (failedUploads + pendingDeletions + failedPdfFiles.length > 0) {
        const canOpenPdfRetryDirectly =
            failedPdfFiles.length > 0 &&
            failedUploads === 0 &&
            pendingDeletions === 0;

        return {
            label: 'Needs attention',
            description:
                'Some source files failed upload or PDF processing, or are still being deleted. Review the file details to remove, retry, or resolve them.',
            action: canOpenPdfRetryDirectly
                ? 'Review PDF processing'
                : 'Review source files',
            href: canOpenPdfRetryDirectly
                ? programFiles.show(failedPdfFiles[0].id)
                : sourceFilesHref,
        };
    }

    const pendingUploads = files.filter(
        (file) => file.upload_status === 'pending_upload',
    ).length;

    if (pendingUploads > 0) {
        return {
            label: 'Upload pending',
            description:
                'One or more uploads are awaiting verification. If an upload stays pending, the incomplete entry will be cleaned up automatically; try again after it clears.',
            action: 'Check upload status',
            href: sourceFilesHref,
        };
    }

    const processingPdfs = files.filter(
        (file) =>
            file.upload_status === 'uploaded' &&
            file.mime_type === 'application/pdf' &&
            ['queued', 'processing'].includes(file.embedding_status),
    ).length;

    if (processingPdfs > 0) {
        return {
            label: 'Processing PDFs',
            description:
                'PDF text is being processed. Wait for it to finish before preparing official curriculum extraction.',
            action: 'Check PDF processing',
            href: sourceFilesHref,
        };
    }

    const pdfsNeedingProcessing = files.filter(
        (file) =>
            file.upload_status === 'uploaded' &&
            file.mime_type === 'application/pdf' &&
            ['not_requested', 'supported'].includes(file.embedding_status),
    );

    if (pdfsNeedingProcessing.length > 0) {
        return {
            label: 'PDF text needed',
            description:
                'Uploaded PDFs need text embeddings before they can support official curriculum extraction.',
            action: 'Generate PDF text embeddings',
            href: programFiles.show(pdfsNeedingProcessing[0].id),
        };
    }

    const hasProcessedPdf = files.some(
        (file) =>
            file.upload_status === 'uploaded' &&
            file.mime_type === 'application/pdf' &&
            file.embedding_status === 'complete',
    );

    return {
        label: 'Available',
        description: hasProcessedPdf
            ? 'Uploaded sources are available for curriculum proposals, and a processed PDF is ready for official extraction.'
            : 'Uploaded sources are available for curriculum proposals. Official curriculum extraction requires a processed PDF.',
        action: 'Manage source files',
        href: sourceFilesHref,
    };
}

Program.layout = (props: { program: { id: number; name: string } }) => ({
    breadcrumbs: [
        { title: 'Programs', href: programs.index() },
        { title: 'Program', href: programs.show(props.program) },
    ],
});
