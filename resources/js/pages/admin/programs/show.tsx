import { Head, Link } from "@inertiajs/react";
import { CurriculumExtractionsPanel } from "@/components/curriculum-extractions-panel";
import type { CurriculumExtractionSummary } from "@/components/curriculum-extractions-panel";
import { ProgramSubjectsPanel } from "@/components/program-subjects-panel";
import { SourceFiles } from "@/components/source-files";
import URLTabsContainer from "@/components/url-tabs-container";
import type { TabItem } from "@/components/url-tabs-container";
import programs from "@/routes/programs";
import curriculumGenerations from "@/routes/programs/curriculum-generations";

export default function Program({
    program,
    extractions,
    curriculumExtractionEnabled,
    curriculumExtractionUnavailableReason,
    curriculumExtractionModels,
    curriculumExtractionDefaultModel,
}: {
    program: {
        id: number;
        name: string;
        code: string;
        description: string | null;
        subjects: { id: number; name: string; code: string | null }[];
        files: import("@/components/source-files").SourceFile[];
    };
    extractions: CurriculumExtractionSummary[];
    curriculumExtractionEnabled: boolean;
    curriculumExtractionUnavailableReason: string | null;
    curriculumExtractionModels: string[];
    curriculumExtractionDefaultModel: string | null;
}) {
    const tabs: TabItem[] = [
        {
            value: "subjects",
            label: "Subjects",
            content: <ProgramSubjectsPanel program={program} subjects={program.subjects} />,
        },
        {
            value: "files",
            label: "Files",
            content: (
                <SourceFiles
                    ownerId={program.id}
                    pollingOnly={["program"]}
                    files={program.files}
                />
            ),
        },
        {
            value: "extractions",
            label: "Extractions",
            content: (
                <CurriculumExtractionsPanel
                    program={program}
                    extractions={extractions}
                    curriculumExtractionEnabled={curriculumExtractionEnabled}
                    curriculumExtractionUnavailableReason={curriculumExtractionUnavailableReason}
                    curriculumExtractionModels={curriculumExtractionModels}
                    curriculumExtractionDefaultModel={curriculumExtractionDefaultModel}
                />
            ),
        },
    ];

    return (
        <main className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Head title={program.name} />
            <div className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p className="text-sm font-medium uppercase tracking-wide text-primary">
                        {program.code}
                    </p>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        {program.name}
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        {program.description}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Link
                        href={curriculumGenerations.index(program)}
                        className="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium shadow-xs hover:bg-accent"
                    >
                        Curriculum proposals
                    </Link>
                    <Link
                        href={programs.edit(program)}
                        className="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium shadow-xs hover:bg-accent"
                    >
                        Edit program
                    </Link>
                </div>
            </div>
            <URLTabsContainer
                tabs={tabs}
                defaultTab="subjects"
                variant="full-width"
                boxedBody={false}
            />
        </main>
    );
}

Program.layout = (props: { program: { id: number; name: string } }) => ({
    breadcrumbs: [
        { title: 'Programs', href: programs.index() },
        { title: "Program", href: programs.show(props.program) },
    ],
});
