import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, BookOpen, CircleHelp, ListChecks } from 'lucide-react';
import study from '@/routes/study';
import { PageHeader, PageShell, EmptyState } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Concept = {
    id: number;
    code: string;
    title: string;
    description: string | null;
    subject: { id: number; name: string };
    prerequisites: { id: number; title: string; code: string }[];
    learning_objectives: { id: number; description: string }[];
    lessons: { id: number; title: string; summary: string | null }[];
    quizzes: { id: number; title: string; description: string | null; questions: { id: number }[] }[];
};

export default function StudyConcept({ concept }: { concept: Concept }) {
    return (
        <PageShell>
            <Head title={concept.title} />
            <Button asChild variant="ghost" className="-ml-3"><Link href={study.subjects.show(concept.subject.id)}><ArrowLeft />{concept.subject.name}</Link></Button>
            <PageHeader eyebrow={concept.code} title={concept.title} description={concept.description} />
            <div className="grid gap-5 xl:grid-cols-[1.35fr_1fr]">
                <div className="space-y-5">
                    <Card><CardHeader><CardTitle className="flex items-center gap-2"><ListChecks className="size-5 text-primary" />Learning objectives</CardTitle></CardHeader><CardContent>{concept.learning_objectives.length === 0 ? <p className="text-sm text-muted-foreground">No objectives yet.</p> : <ul className="grid gap-3">{concept.learning_objectives.map((objective) => <li key={objective.id} className="flex gap-3 text-sm"><span className="mt-1 size-2 shrink-0 rounded-full bg-primary" />{objective.description}</li>)}</ul>}</CardContent></Card>
                    <section className="space-y-3"><div className="flex items-center justify-between"><h2 className="text-lg font-semibold">Lessons</h2><Badge variant="secondary">{concept.lessons.length}</Badge></div>{concept.lessons.length === 0 ? <EmptyState>No lessons yet.</EmptyState> : <div className="grid gap-3">{concept.lessons.map((lesson) => <Card key={lesson.id} className="group transition-colors hover:border-primary/50"><Link href={study.lessons.show(lesson)}><CardContent className="flex items-center justify-between gap-4 pt-6"><div><h3 className="font-semibold">{lesson.title}</h3>{lesson.summary && <p className="mt-1 text-sm text-muted-foreground">{lesson.summary}</p>}<p className="mt-3 text-sm font-medium text-primary">Read lesson</p></div><BookOpen className="size-5 shrink-0 text-primary transition-transform group-hover:scale-110" /></CardContent></Link></Card>)}</div>}</section>
                </div>
                <div className="space-y-5">
                    {concept.prerequisites.length > 0 && <Card><CardHeader><CardTitle>Prerequisites</CardTitle></CardHeader><CardContent className="flex flex-wrap gap-2">{concept.prerequisites.map((item) => <Link key={item.id} href={study.concepts.show(item)}><Badge variant="outline" className="h-auto whitespace-normal px-3 py-1.5 text-left">{item.code}: {item.title}</Badge></Link>)}</CardContent></Card>}
                    <Card><CardHeader><CardTitle className="flex items-center gap-2"><CircleHelp className="size-5 text-primary" />Check your understanding</CardTitle></CardHeader><CardContent className="space-y-3">{concept.quizzes.length === 0 ? <p className="text-sm text-muted-foreground">No quizzes yet.</p> : concept.quizzes.map((quiz) => <Link key={quiz.id} href={study.quizzes.show(quiz)} className="group flex items-center justify-between rounded-lg border p-4 transition-colors hover:border-primary/50 hover:bg-accent"><span><span className="block font-semibold">{quiz.title}</span><span className="mt-1 block text-sm text-muted-foreground">{quiz.questions.length} question{quiz.questions.length === 1 ? '' : 's'}</span></span><ArrowRight className="size-4 text-primary transition-transform group-hover:translate-x-1" /></Link>)}</CardContent></Card>
                </div>
            </div>
        </PageShell>
    );
}
