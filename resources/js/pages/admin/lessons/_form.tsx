import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { concepts } from '@/lib/curriculum-routes';
import { conceptLessons, lessons } from '@/lib/curriculum-routes';

type Concept = { id: number; title: string };
type Lesson = {
    id: number;
    title: string;
    summary: string | null;
    content: string;
    sort_order: number;
};

export default function LessonForm({
    concept,
    lesson,
}: {
    concept: Concept;
    lesson?: Lesson;
}) {
    const form = useForm({
        title: lesson?.title ?? '',
        summary: lesson?.summary ?? '',
        content: lesson?.content ?? '',
        sort_order: lesson?.sort_order ?? 0,
    });

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (lesson) {
            form.put(lessons.update(lesson).url);
        } else {
            form.post(conceptLessons.store(concept).url);
        }
    };

    const returnHref = lesson ? lessons.show(lesson) : concepts.show(concept);

    return (
        <PageShell>
            <Head title={lesson ? 'Edit lesson' : 'New lesson'} />
            <PageHeader
                eyebrow={`Concept · ${concept.title}`}
                title={lesson ? 'Edit lesson' : 'Create a lesson'}
                description="Write learning material for this concept. Keep the lesson focused on one topic and make it useful before learners take an assessment."
                actions={(
                    <Button variant="outline" asChild>
                        <Link href={returnHref}>{lesson ? 'Back to lesson' : 'Back to concept'}</Link>
                    </Button>
                )}
            />

            <Card className="py-0">
                <CardContent className="py-6">
                    <form onSubmit={submit} className="grid gap-6">
                        <div className="grid gap-2">
                            <label htmlFor="lesson-title" className="text-sm font-medium">Lesson title</label>
                            <Input
                                id="lesson-title"
                                autoFocus
                                value={form.data.title}
                                onChange={(event) => form.setData('title', event.target.value)}
                                aria-invalid={Boolean(form.errors.title)}
                                aria-describedby={form.errors.title ? 'lesson-title-error' : 'lesson-title-hint'}
                                required
                            />
                            <p id="lesson-title-hint" className="text-sm text-muted-foreground">Use a specific title that tells learners what they will study.</p>
                            <InputError id="lesson-title-error" role="alert" message={form.errors.title} />
                        </div>

                        <div className="grid gap-2">
                            <label htmlFor="lesson-summary" className="text-sm font-medium">Summary <span className="font-normal text-muted-foreground">(optional)</span></label>
                            <textarea
                                id="lesson-summary"
                                value={form.data.summary}
                                onChange={(event) => form.setData('summary', event.target.value)}
                                aria-invalid={Boolean(form.errors.summary)}
                                aria-describedby={form.errors.summary ? 'lesson-summary-error' : 'lesson-summary-hint'}
                                className="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive"
                            />
                            <p id="lesson-summary-hint" className="text-sm text-muted-foreground">Give learners a short preview of this lesson.</p>
                            <InputError id="lesson-summary-error" role="alert" message={form.errors.summary} />
                        </div>

                        <div className="grid gap-2">
                            <label htmlFor="lesson-content" className="text-sm font-medium">Lesson content</label>
                            <textarea
                                id="lesson-content"
                                value={form.data.content}
                                onChange={(event) => form.setData('content', event.target.value)}
                                aria-invalid={Boolean(form.errors.content)}
                                aria-describedby={form.errors.content ? 'lesson-content-error' : 'lesson-content-hint'}
                                className="min-h-80 w-full rounded-md border border-input bg-background px-3 py-2 text-sm leading-6 shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive"
                                required
                            />
                            <p id="lesson-content-hint" className="text-sm text-muted-foreground">Use headings and short paragraphs to make the material easy to scan.</p>
                            <InputError id="lesson-content-error" role="alert" message={form.errors.content} />
                        </div>

                        <div className="flex flex-col-reverse gap-3 border-t pt-5 sm:flex-row sm:justify-end">
                            <Button variant="outline" asChild><Link href={returnHref}>Cancel</Link></Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Saving…' : lesson ? 'Save changes' : 'Create lesson'}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </PageShell>
    );
}
