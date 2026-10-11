import { Form, Link } from "@inertiajs/react";
import {
    destroy,
    show,
} from "@/actions/App/Http/Controllers/CurriculumExtractionController";
import { Button } from "@/components/ui/button";
import { CurriculumExtractionForm } from "@/components/curriculum-extraction-form";

export type CurriculumExtractionSourceFile = {
    id: number;
    title: string;
    content_hash: string;
};

export type CurriculumExtractionSummary = {
    id: number;
    status: string;
    source_hash: string;
    source_files: CurriculumExtractionSourceFile[];
    provider: string;
    model: string;
    prompt_version: string;
    error_code: string | null;
    error_message: string | null;
    created_at: string;
    reviewed_at: string | null;
    requester: { name: string } | null;
    reviewer: { name: string } | null;
    can_delete: boolean;
};

type Program = {
    id: number;
    name: string;
    code: string;
};

export function CurriculumExtractionsPanel({
    program,
    extractions,
    curriculumExtractionEnabled,
    curriculumExtractionUnavailableReason,
    curriculumExtractionModels,
    curriculumExtractionDefaultModel,
}: {
    program: Program;
    extractions: CurriculumExtractionSummary[];
    curriculumExtractionEnabled: boolean;
    curriculumExtractionUnavailableReason: string | null;
    curriculumExtractionModels: string[];
    curriculumExtractionDefaultModel: string | null;
}) {
    return (
        <div className="space-y-6">
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
                        enabled={curriculumExtractionEnabled}
                        models={curriculumExtractionModels}
                        defaultModel={curriculumExtractionDefaultModel}
                    />
                </div>
                {curriculumExtractionUnavailableReason && (
                    <p className="text-sm text-muted-foreground">
                        {curriculumExtractionUnavailableReason}
                    </p>
                )}
            </section>

            {extractions.length === 0 ? (
                <p className="rounded-xl border bg-card p-5 text-sm text-muted-foreground">
                    No curriculum extractions have been requested for this program.
                </p>
            ) : (
                <div className="space-y-4">
                    {extractions.map((extraction) => (
                        <article
                            key={extraction.id}
                            className="block rounded-xl border bg-card p-5 shadow-sm transition-colors hover:border-primary/50 hover:bg-accent"
                        >
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 className="font-semibold capitalize">
                                        {extraction.status.replaceAll("_", " ")}
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {extraction.provider} · {extraction.model} · {extraction.prompt_version}
                                    </p>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    Requested {formatDate(extraction.created_at)}
                                </p>
                            </div>
                            <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <dt className="text-muted-foreground">Requested by</dt>
                                    <dd>{extraction.requester?.name ?? "Unknown"}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Reviewed by</dt>
                                    <dd>{extraction.reviewer?.name ?? "Not reviewed"}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Reviewed</dt>
                                    <dd>{extraction.reviewed_at ? formatDate(extraction.reviewed_at) : "Not reviewed"}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Source snapshot</dt>
                                    <dd className="font-mono text-xs" title={extraction.source_hash}>
                                        {shortHash(extraction.source_hash)}
                                    </dd>
                                </div>
                            </dl>
                            <div className="mt-4">
                                <p className="text-sm font-medium">Source files</p>
                                <ul className="mt-1 space-y-1 text-sm text-muted-foreground">
                                    {extraction.source_files.map((file) => (
                                        <li key={file.id}>
                                            {file.title}{" "}
                                            <span className="font-mono text-xs" title={file.content_hash}>
                                                {shortHash(file.content_hash)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                            {extraction.error_code && (
                                <p className="mt-4 text-sm text-destructive">
                                    {extraction.error_message}
                                </p>
                            )}
                            <div className="mt-4 flex flex-wrap gap-2">
                                <Button asChild size="sm" variant="outline">
                                    <Link href={show(extraction.id)}>View extraction</Link>
                                </Button>
                                {extraction.can_delete && (
                                    <Form {...destroy.form([program, extraction.id])}>
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                variant="destructive"
                                                size="sm"
                                                disabled={processing}
                                            >
                                                {processing ? "Deleting…" : "Delete extraction"}
                                            </Button>
                                        )}
                                    </Form>
                                )}
                            </div>
                        </article>
                    ))}
                </div>
            )}
        </div>
    );
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString(undefined, {
        dateStyle: "medium",
        timeStyle: "short",
    });
}

function shortHash(hash: string): string {
    return `${hash.slice(0, 12)}…${hash.slice(-8)}`;
}
