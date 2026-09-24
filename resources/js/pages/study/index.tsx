import { Head, Link } from "@inertiajs/react";
import { ArrowRight, ChartNoAxesCombined } from "lucide-react";
import study from "@/routes/study";
import progress from "@/routes/progress";
import { PageHeader, PageShell, EmptyState } from "@/components/page-shell";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

type Subject = { id: number; name: string; code: string };
type Program = { id: number; name: string; code: string; subjects: Subject[] };

export default function StudyIndex({ programs }: { programs: Program[] }) {
    return (
        <PageShell>
            <Head title="Study" />
            <PageHeader
                eyebrow="ExamGraph learning workspace"
                title="Choose a subject"
                description="Work through the curriculum, open lessons, and test your understanding with curated quizzes."
                actions={
                    <Button asChild variant="outline">
                        <Link href={progress.index()}>
                            <ChartNoAxesCombined />
                            View progress
                        </Link>
                    </Button>
                }
            />
            {programs.length === 0 ? (
                <EmptyState>
                    No study content has been added yet.
                </EmptyState>
            ) : (
                <div className="grid gap-5 lg:grid-cols-2">
                    {programs.map((program) => (
                        <Card key={program.id} className="h-full">
                            <CardHeader>
                                <div className="flex items-center justify-between gap-3">
                                    <CardTitle>{program.name}</CardTitle>
                                    <Badge variant="secondary">{program.code}</Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-3">
                            {program.subjects.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No subjects yet.
                                </p>
                            ) : (
                                <div className="grid gap-2">
                                    {program.subjects.map((subject) => (
                                        <Link
                                            key={subject.id}
                                            href={study.subjects.show(subject)}
                                            className="group flex items-center justify-between rounded-lg border p-4 text-sm font-medium transition-colors hover:border-primary/40 hover:bg-accent"
                                        >
                                            <span><Badge variant="outline" className="mr-2">{subject.code}</Badge>{subject.name}</span>
                                            <ArrowRight className="size-4 text-muted-foreground transition-transform group-hover:translate-x-1" />
                                        </Link>
                                    ))}
                                </div>
                            )}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}
        </PageShell>
    );
}
