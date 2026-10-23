import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { conceptQuestions, questions } from '@/lib/curriculum-routes';
import InputError from '@/components/input-error';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Choice = { id?: number; content: string; is_correct: boolean };
type Question = { id: number; prompt: string; explanation: string | null; difficulty: 'easy' | 'medium' | 'hard'; choices: Choice[] };

export default function QuestionForm({ concept, question }: { concept: { id: number; title: string }; question?: Question }) {
    const form = useForm<{ prompt: string; explanation: string; difficulty: 'easy' | 'medium' | 'hard'; choices: Choice[] }>({ prompt: question?.prompt ?? '', explanation: question?.explanation ?? '', difficulty: question?.difficulty ?? 'medium', choices: question?.choices ?? [{ content: '', is_correct: true }, { content: '', is_correct: false }] });
    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (question) {
            form.put(questions.update(question).url);
        } else {
            form.post(conceptQuestions.store(concept).url);
        }
    };
    const setChoice = (index: number, field: keyof Choice, value: string | boolean) => form.setData('choices', form.data.choices.map((choice, choiceIndex) => choiceIndex === index ? { ...choice, [field]: value } : field === 'is_correct' && value === true ? { ...choice, is_correct: false } : choice));

    return (
        <PageShell>
            <Head title={question ? 'Edit question' : 'New question'} />
            <PageHeader eyebrow={concept.title} title={question ? 'Edit question' : 'New question'} description="Create a multiple-choice question with exactly one correct answer." />
            <Card className="py-0">
                <CardContent className="py-6">
                    <form onSubmit={submit} className="grid w-full gap-5">
                        {Object.keys(form.errors).length > 0 && (
                            <div role="alert" className="space-y-1 rounded-md border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">
                                <p className="font-medium">The question could not be saved.</p>
                                {Object.values(form.errors).map((message, index) => <p key={index}>{message}</p>)}
                            </div>
                        )}
                        <div className="grid gap-2">
                            <label htmlFor="question-prompt" className="text-sm font-medium">Question prompt</label>
                            <textarea id="question-prompt" value={form.data.prompt} onChange={(event) => form.setData('prompt', event.target.value)} aria-invalid={Boolean(form.errors.prompt)} aria-describedby={form.errors.prompt ? 'question-prompt-error' : 'question-prompt-hint'} className="min-h-28 w-full rounded-md border border-input bg-background p-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive" required />
                            <p id="question-prompt-hint" className="text-sm text-muted-foreground">Write one clear question that checks understanding of this concept.</p>
                            <InputError id="question-prompt-error" message={form.errors.prompt} />
                        </div>
                        <div className="grid max-w-xs gap-2">
                            <label htmlFor="question-difficulty" className="text-sm font-medium">Difficulty</label>
                            <select id="question-difficulty" value={form.data.difficulty} onChange={(event) => form.setData('difficulty', event.target.value as 'easy' | 'medium' | 'hard')} aria-invalid={Boolean(form.errors.difficulty)} aria-describedby={form.errors.difficulty ? 'question-difficulty-error' : undefined} className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive">
                                <option value="easy">Easy</option>
                                <option value="medium">Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                            <InputError id="question-difficulty-error" message={form.errors.difficulty} />
                        </div>
                        <div className="grid gap-2">
                            <label htmlFor="question-explanation" className="text-sm font-medium">Explanation <span className="font-normal text-muted-foreground">(optional)</span></label>
                            <textarea id="question-explanation" value={form.data.explanation} onChange={(event) => form.setData('explanation', event.target.value)} aria-invalid={Boolean(form.errors.explanation)} aria-describedby={form.errors.explanation ? 'question-explanation-error' : 'question-explanation-hint'} className="min-h-24 w-full rounded-md border border-input bg-background p-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive" />
                            <p id="question-explanation-hint" className="text-sm text-muted-foreground">Explain why the correct answer is right. Learners will see this after submitting a quiz.</p>
                            <InputError id="question-explanation-error" message={form.errors.explanation} />
                        </div>
                        <fieldset className="grid gap-3">
                            <legend className="text-sm font-medium">Answer choices</legend>
                            <p className="text-sm text-muted-foreground">Add at least two choices and select the one correct answer.</p>
                            {form.errors.choices && <InputError message={form.errors.choices} />}
                            {form.data.choices.map((choice, index) => {
                                const contentError = form.errors[`choices.${index}.content`];

                                return (
                                    <div key={index} className="grid gap-3 rounded-lg border p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                                        <div className="grid gap-2">
                                            <label htmlFor={`question-choice-${index}`} className="text-sm font-medium">Choice {index + 1}</label>
                                            <input id={`question-choice-${index}`} value={choice.content} onChange={(event) => setChoice(index, 'content', event.target.value)} aria-invalid={Boolean(contentError)} aria-describedby={contentError ? `question-choice-${index}-error` : undefined} className="h-9 w-full min-w-0 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive" required />
                                            <InputError id={`question-choice-${index}-error`} message={contentError} />
                                        </div>
                                        <label className="flex min-h-9 items-center gap-2 text-sm">
                                            <input type="radio" name="correct-answer" checked={choice.is_correct} onChange={() => setChoice(index, 'is_correct', true)} className="size-4 accent-primary" />
                                            Correct answer
                                        </label>
                                    </div>
                                );
                            })}
                            <Button type="button" variant="outline" className="justify-self-start" onClick={() => form.setData('choices', [...form.data.choices, { content: '', is_correct: false }])}>
                                <Plus aria-hidden="true" />
                                Add answer choice
                            </Button>
                        </fieldset>
                        <div className="flex justify-end border-t pt-5">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Saving…' : question ? 'Save changes' : 'Create question'}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </PageShell>
    );
}
