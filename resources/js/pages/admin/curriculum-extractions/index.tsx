import { Head } from "@inertiajs/react";
import {
    index as indexExtractions,
} from "@/actions/App/Http/Controllers/CurriculumExtractionController";
import { CurriculumExtractionsPanel } from "@/components/curriculum-extractions-panel";
import type { CurriculumExtractionSummary } from "@/components/curriculum-extractions-panel";
import programs from "@/routes/programs";

export default function CurriculumExtractionsIndex({
    program,
    extractions,
    curriculumExtractionEnabled,
    curriculumExtractionUnavailableReason,
    curriculumExtractionModels,
    curriculumExtractionDefaultModel,
}: {
    program: { id: number; name: string; code: string };
    extractions: CurriculumExtractionSummary[];
    curriculumExtractionEnabled: boolean;
    curriculumExtractionUnavailableReason: string | null;
    curriculumExtractionModels: string[];
    curriculumExtractionDefaultModel: string | null;
}) {
    return (
        <main className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Head title={`Curriculum extractions · ${program.name}`} />
            <header className="space-y-2 border-b pb-6">
                <p className="text-sm font-medium uppercase tracking-wide text-primary">
                    {program.code}
                </p>
                <h1 className="text-3xl font-semibold tracking-tight">
                    Curriculum extractions
                </h1>
                <p className="text-muted-foreground">
                    Every official-curriculum proposal generated for{" "}
                    {program.name}.
                </p>
            </header>

            <CurriculumExtractionsPanel
                program={program}
                extractions={extractions}
                curriculumExtractionEnabled={curriculumExtractionEnabled}
                curriculumExtractionUnavailableReason={curriculumExtractionUnavailableReason}
                curriculumExtractionModels={curriculumExtractionModels}
                curriculumExtractionDefaultModel={curriculumExtractionDefaultModel}
            />
        </main>
    );
}

CurriculumExtractionsIndex.layout = (props: { program: { id: number } }) => ({
    breadcrumbs: [
        { title: "Programs", href: programs.index() },
        { title: "Program", href: programs.show(props.program) },
        {
            title: "Curriculum extractions",
            href: indexExtractions(props.program),
        },
    ],
});
