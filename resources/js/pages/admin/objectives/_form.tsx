import { Head, useForm } from '@inertiajs/react';
import { conceptObjectives, objectives } from '@/lib/curriculum-routes';
import InputError from '@/components/input-error';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

export default function ObjectiveForm({ concept, objective }: { concept: { id: number; title: string }; objective?: { id: number; description: string; sort_order: number } }) {
    const form = useForm({ description: objective?.description ?? '', sort_order: objective?.sort_order ?? 0 });
    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (objective) {
            form.put(objectives.update(objective).url);
        } else {
            form.post(conceptObjectives.store(concept).url);
        }
    };

    return (
        <PageShell>
            <Head title={objective ? 'Edit objective' : 'New objective'} />
            <PageHeader eyebrow={concept.title} title={objective ? 'Edit learning objective' : 'New learning objective'} description="Describe an observable outcome for this concept." />
            <Card className="py-0">
                <CardContent className="py-6">
                    <form onSubmit={submit} className="grid w-full gap-5">
                        {Object.keys(form.errors).length > 0 && (
                            <div role="alert" className="space-y-1 rounded-md border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">
                                <p className="font-medium">The learning objective could not be saved.</p>
                                {Object.values(form.errors).map((message, index) => <p key={index}>{message}</p>)}
                            </div>
                        )}
                        <div className="grid gap-2">
                            <label htmlFor="objective-description" className="text-sm font-medium">Learning objective</label>
                            <textarea
                                id="objective-description"
                                value={form.data.description}
                                onChange={(event) => form.setData('description', event.target.value)}
                                aria-invalid={Boolean(form.errors.description)}
                                aria-describedby={form.errors.description ? 'objective-description-error' : 'objective-description-hint'}
                                className="min-h-36 w-full rounded-md border border-input bg-background p-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive"
                                required
                            />
                            <p id="objective-description-hint" className="text-sm text-muted-foreground">Describe what a learner should be able to explain or do after studying this concept.</p>
                            <InputError id="objective-description-error" message={form.errors.description} />
                        </div>
                        <div className="flex justify-end border-t pt-5">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Saving…' : objective ? 'Save changes' : 'Create learning objective'}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </PageShell>
    );
}
