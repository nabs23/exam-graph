import { Head, Link } from "@inertiajs/react";
import {
    index as indexExtractions,
} from "@/actions/App/Http/Controllers/CurriculumExtractionController";
import { CurriculumExtractionsPanel } from "@/components/curriculum-extractions-panel";
import type { CurriculumExtractionSummary } from "@/components/curriculum-extractions-panel";
import { PageHeader, PageShell } from "@/components/page-shell";
import { Button } from "@/components/ui/button";
import programs from "@/routes/programs";

export default function CurriculumExtractionsIndex({
    program,
    extractions,
    curriculumExtractionEnabled,
    curriculumExtractionHasSources,
    curriculumExtractionUnavailableReason,
    curriculumExtractionModels,
    curriculumExtractionDefaultModel,
}: {
    program: { id: number; name: string; code: string };
    extractions: CurriculumExtractionSummary[];
    curriculumExtractionEnabled: boolean;
    curriculumExtractionHasSources: boolean;
    curriculumExtractionUnavailableReason: string | null;
    curriculumExtractionModels: string[];
    curriculumExtractionDefaultModel: string | null;
}) {
    return (
        <PageShell className="mx-auto max-w-6xl">
            <Head title={`Curriculum extractions · ${program.name}`} />
            <PageHeader
                eyebrow={`${program.code} · Program workspace`}
                title="Curriculum extractions"
                description={`Review official-curriculum proposals prepared from ${program.name} source files.`}
                actions={(
                    <Button asChild variant="outline">
                        <Link href={programs.show(program, { query: { tab: "extractions" } })}>Back to program</Link>
                    </Button>
                )}
            />

            <CurriculumExtractionsPanel
                program={program}
                extractions={extractions}
                curriculumExtractionEnabled={curriculumExtractionEnabled}
                curriculumExtractionHasSources={curriculumExtractionHasSources}
                curriculumExtractionUnavailableReason={curriculumExtractionUnavailableReason}
                curriculumExtractionModels={curriculumExtractionModels}
                curriculumExtractionDefaultModel={curriculumExtractionDefaultModel}
            />
        </PageShell>
    );
}

CurriculumExtractionsIndex.layout = (props: { program: { id: number } }) => ({
    breadcrumbs: [
        { title: "Programs", href: programs.index() },
        { title: "Program", href: programs.show(props.program, { query: { tab: "extractions" } }) },
        {
            title: "Curriculum extractions",
            href: indexExtractions(props.program),
        },
    ],
});
