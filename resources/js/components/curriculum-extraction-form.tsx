import { Form } from '@inertiajs/react';
import { store as storeCurriculumExtraction } from '@/actions/App/Http/Controllers/CurriculumExtractionController';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

export function CurriculumExtractionForm({
    program,
    enabled,
    models,
    defaultModel,
}: {
    program: { id: number };
    enabled: boolean;
    models: string[];
    defaultModel: string | null;
}) {
    return (
        <Form {...storeCurriculumExtraction.form(program)}>
            {({ processing, errors }) => (
                <div className="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-end">
                    <input type="hidden" name="confirmation" value="1" />
                    <div className="grid min-w-0 gap-1.5 sm:max-w-64">
                        <Label htmlFor="curriculum-extraction-model">
                            Generation model
                        </Label>
                        <select
                            id="curriculum-extraction-model"
                            name="model"
                            defaultValue={defaultModel ?? models[0] ?? ''}
                            disabled={
                                processing || !enabled || models.length === 0
                            }
                            aria-invalid={Boolean(errors.model)}
                            aria-describedby={
                                errors.model
                                    ? 'curriculum-extraction-model-error'
                                    : undefined
                            }
                            className="h-9 w-full min-w-0 rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 sm:w-fit"
                        >
                            {models.map((model) => (
                                <option key={model} value={model}>
                                    {model}
                                </option>
                            ))}
                        </select>
                        {errors.model && (
                            <p
                                id="curriculum-extraction-model-error"
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {errors.model}
                            </p>
                        )}
                    </div>
                    <Button
                        type="submit"
                        disabled={processing || !enabled || models.length === 0}
                        className="w-full shrink-0 sm:w-auto"
                    >
                        {processing
                            ? 'Preparing proposal…'
                            : 'Prepare curriculum proposal'}
                    </Button>
                    {errors.program && (
                        <p
                            role="alert"
                            className="text-sm text-destructive sm:max-w-64"
                        >
                            {errors.program}
                        </p>
                    )}
                </div>
            )}
        </Form>
    );
}
