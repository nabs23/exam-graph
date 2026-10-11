import { Link } from "@inertiajs/react";
import { programSubjects, subjects as subjectRoutes } from '@/lib/curriculum-routes';

type Program = {
    id: number;
};

type Subject = {
    id: number;
    name: string;
    code: string | null;
};

export function ProgramSubjectsPanel({
    program,
    subjects,
}: {
    program: Program;
    subjects: Subject[];
}) {
    return (
        <section className="space-y-5">
            <div className="flex items-center justify-between gap-4">
                <div>
                    <h2 className="text-xl font-semibold">Subjects</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {subjects.length} {subjects.length === 1 ? "subject" : "subjects"}
                    </p>
                </div>
                <Link
                    href={programSubjects.create(program)}
                    className="shrink-0 text-sm font-medium text-primary hover:underline"
                >
                    Create a subject
                </Link>
            </div>
            {subjects.length === 0 ? (
                <div className="rounded-xl border border-dashed py-12 text-center">
                    <p className="font-medium">No subjects yet</p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Create a subject to start organizing this program’s curriculum.
                    </p>
                </div>
            ) : (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {subjects.map((subject) => (
                        <Link
                            key={subject.id}
                            href={subjectRoutes.show(subject)}
                            className="rounded-xl border bg-card p-5 shadow-sm transition-colors hover:border-primary/50 hover:bg-accent"
                        >
                            {subject.code && <b>{subject.code}</b>}
                            <p className="mt-1 font-medium">{subject.name}</p>
                        </Link>
                    ))}
                </div>
            )}
        </section>
    );
}
