import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import lessons from '@/routes/concepts/lessons';
import objectives from '@/routes/concepts/objectives';
import questions from '@/routes/concepts/questions';
import quizzes from '@/routes/concepts/quizzes';
import concepts from '@/routes/concepts';

type Concept = {
    id: number;
    code: string;
    title: string;
    description: string | null;
    lessons: { id: number; title: string }[];
    learning_objectives: { id: number; description: string }[];
    questions: {
        id: number;
        prompt: string;
        choices: { id: number; content: string; is_correct: boolean }[];
    }[];
    quizzes: { id: number; title: string; questions: { id: number }[] }[];
    prerequisites: { id: number; code: string; title: string }[];
};
export default function Concept({ concept }: { concept: Concept }) {
    const lesson = useForm({
        title: '',
        summary: '',
        content: '',
        sort_order: 0,
    });
    const objective = useForm({ description: '', sort_order: 0 });
    return (
            <main className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Head title={concept.title} />
            <header>
                <p className="text-sm font-medium uppercase tracking-wide text-primary">{concept.code}</p>
                <h1 className="text-3xl font-bold">{concept.title}</h1>
                <p className="mt-2 text-slate-600">{concept.description}</p>
            </header>
            <section>
                <h2 className="font-semibold">Prerequisites</h2>
                <p className="mt-1 text-sm text-slate-600">
                    {concept.prerequisites
                        .map((item) => `${item.code}: ${item.title}`)
                        .join(', ') || 'None yet.'}
                </p>
            </section>
            <nav className="flex flex-wrap gap-2">
                <Link
                    href={lessons.index(concept)}
                    className="rounded border px-3 py-2 text-sm"
                >
                    Manage lessons
                </Link>
                <Link
                    href={objectives.index(concept)}
                    className="rounded border px-3 py-2 text-sm"
                >
                    Manage objectives
                </Link>
                <Link
                    href={questions.index(concept)}
                    className="rounded border px-3 py-2 text-sm"
                >
                    Manage questions
                </Link>
                <Link
                    href={quizzes.index(concept)}
                    className="rounded border px-3 py-2 text-sm"
                >
                    Manage quizzes
                </Link>
            </nav>
            <section className="grid gap-4 md:grid-cols-2">
                    <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        lesson.post(lessons.store(concept).url, {
                            onSuccess: () => lesson.reset(),
                        });
                    }}
                        className="space-y-3 rounded-xl border bg-card p-5 shadow-sm"
                >
                    <h2 className="font-semibold">Add lesson</h2>
                    <input
                        placeholder="Title"
                        value={lesson.data.title}
                        onChange={(event) =>
                            lesson.setData('title', event.target.value)
                        }
                        className="w-full rounded border p-2"
                        required
                    />
                    <textarea
                        placeholder="Summary"
                        value={lesson.data.summary}
                        onChange={(event) =>
                            lesson.setData('summary', event.target.value)
                        }
                        className="w-full rounded border p-2"
                    />
                    <textarea
                        placeholder="Lesson content"
                        value={lesson.data.content}
                        onChange={(event) =>
                            lesson.setData('content', event.target.value)
                        }
                        className="min-h-28 w-full rounded border p-2"
                        required
                    />
                    <Button type="submit" disabled={lesson.processing}>
                        Save lesson
                    </Button>
                </form>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        objective.post(objectives.store(concept).url, {
                            onSuccess: () => objective.reset(),
                        });
                    }}
                        className="space-y-3 rounded-xl border bg-card p-5 shadow-sm"
                >
                    <h2 className="font-semibold">Add learning objective</h2>
                    <textarea
                        placeholder="Observable objective"
                        value={objective.data.description}
                        onChange={(event) =>
                            objective.setData('description', event.target.value)
                        }
                        className="min-h-28 w-full rounded border p-2"
                        required
                    />
                    <Button type="submit" disabled={objective.processing}>
                        Save objective
                    </Button>
                </form>
            </section>
            <section>
                <h2 className="font-semibold">Lessons</h2>
                {concept.lessons.length === 0 ? (
                    <p className="mt-2 text-sm text-slate-500">
                        No lessons yet.
                    </p>
                ) : (
                    <ul className="mt-2 list-disc pl-5">
                        {concept.lessons.map((item) => (
                            <li key={item.id}>{item.title}</li>
                        ))}
                    </ul>
                )}
            </section>
            <section>
                <h2 className="font-semibold">Questions</h2>
                <ul className="mt-2 list-disc pl-5">
                    {concept.questions.map((item) => (
                        <li key={item.id}>
                            {item.prompt} ({item.choices.length} choices)
                        </li>
                    ))}
                </ul>
            </section>
            <section>
                <h2 className="font-semibold">Quizzes</h2>
                <ul className="mt-2 list-disc pl-5">
                    {concept.quizzes.map((item) => (
                        <li key={item.id}>
                            {item.title} ({item.questions.length} questions)
                        </li>
                    ))}
                </ul>
            </section>
        </main>
    );
}

Concept.layout = (props: { concept: Concept }) => ({
    breadcrumbs: [
        { title: 'Concepts', href: concepts.index() },
        {
            title: 'Concept',
            href: concepts.show(props.concept.id),
        },
    ],
});
