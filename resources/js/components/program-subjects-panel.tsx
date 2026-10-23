import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { EmptyState } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    programSubjects,
    subjects as subjectRoutes,
} from '@/lib/curriculum-routes';

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
    const subjectCount = subjects.length;

    return (
        <section aria-labelledby="program-subjects-title" className="space-y-5">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2
                        id="program-subjects-title"
                        className="text-xl font-semibold"
                    >
                        Subjects
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {subjectCount}{' '}
                        {subjectCount === 1 ? 'subject' : 'subjects'} in this
                        program. Subjects organize the topics, learning
                        materials, and assessments learners will use.
                    </p>
                </div>
                {subjectCount > 0 && (
                    <Button asChild className="shrink-0">
                        <Link href={programSubjects.create(program)}>
                            <Plus data-icon="inline-start" />
                            Create subject
                        </Link>
                    </Button>
                )}
            </div>

            {subjectCount === 0 ? (
                <EmptyState>
                    <div className="space-y-3">
                        <p className="font-medium text-foreground">
                            This program has no subjects yet
                        </p>
                        <p>
                            Add the first subject to start building its
                            curriculum. You can then add syllabus topics,
                            concepts, lessons, and assessments to that subject.
                        </p>
                        <Button asChild className="mt-2">
                            <Link href={programSubjects.create(program)}>
                                <Plus data-icon="inline-start" />
                                Create the first subject
                            </Link>
                        </Button>
                    </div>
                </EmptyState>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {subjects.map((subject) => (
                        <Link
                            key={subject.id}
                            href={subjectRoutes.show(subject)}
                            className="group min-w-0 rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        >
                            <Card className="h-full py-0 transition-colors group-hover:border-primary/50 group-hover:bg-accent/50">
                                <CardContent className="flex h-full min-h-32 flex-col justify-between gap-5 py-5">
                                    <div className="space-y-2">
                                        {subject.code ? (
                                            <p className="wrap-break-words text-sm font-semibold tracking-wide text-primary">
                                                {subject.code}
                                            </p>
                                        ) : (
                                            <p className="text-sm text-muted-foreground">
                                                Subject
                                            </p>
                                        )}
                                        <h3 className="wrap-break-words font-semibold tracking-tight">
                                            {subject.name}
                                        </h3>
                                    </div>
                                    <span className="text-sm font-medium text-primary">
                                        Open subject{' '}
                                        <span aria-hidden="true">→</span>
                                    </span>
                                </CardContent>
                            </Card>
                        </Link>
                    ))}
                </div>
            )}
        </section>
    );
}
