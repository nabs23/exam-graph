import { Link } from "@inertiajs/react";
import {
    index as curriculumExtractionsIndex,
    show as curriculumExtractionShow,
} from "@/actions/App/Http/Controllers/CurriculumExtractionController";
import { CurriculumExtractionForm } from "@/components/curriculum-extraction-form";

type Program = {
    id: number;
};

type CurriculumExtraction = {
    id: number;
    status: string;
};

export function OfficialCurriculumExtraction({
    program,
    enabled,
    unavailableReason,
    latestExtraction,
    models,
    defaultModel,
}: {
    program: Program;
    enabled: boolean;
    unavailableReason: string | null;
    latestExtraction: CurriculumExtraction | null;
    models: string[];
    defaultModel: string | null;
}) {
    return (
        <section className="space-y-3 rounded-xl border bg-card p-5 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-xl font-semibold">Official curriculum</h2>
                    <p className="text-sm text-muted-foreground">
                        Build one reviewer-controlled proposal from all completed program PDFs.
                    </p>
                </div>
                <CurriculumExtractionForm
                    program={program}
                    enabled={enabled}
                    models={models}
                    defaultModel={defaultModel}
                />
            </div>
            <p className="text-sm text-muted-foreground">
                <Link className="underline" href={curriculumExtractionsIndex(program)}>
                    View all extractions
                </Link>
                {latestExtraction && (
                    <>
                        {" "}· Latest proposal: {" "}
                        <Link
                            className="underline"
                            href={curriculumExtractionShow(latestExtraction.id)}
                        >
                            {latestExtraction.status.replaceAll("_", " ")}
                        </Link>
                    </>
                )}
            </p>
            {unavailableReason && (
                <p className="text-sm text-muted-foreground">{unavailableReason}</p>
            )}
        </section>
    );
}
