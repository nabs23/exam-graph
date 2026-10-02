import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import study from '@/routes/study';
import { PageHeader, PageShell, EmptyState } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Concept = { id: number; code: string; title: string; description: string | null; quizzes: { id: number }[] };
type Subject = { name: string; code: string | null; concepts: Concept[] };

export default function StudySubject({ subject }: { subject: Subject }) {
    return <PageShell><Head title={subject.name} /><Button asChild variant="ghost" className="-ml-3"><Link href={study.index()}><ArrowLeft />All subjects</Link></Button><PageHeader eyebrow={subject.code ?? undefined} title={subject.name} description="Select a concept to review its objectives, lessons, prerequisites, and quizzes." />{subject.concepts.length === 0 ? <EmptyState>No concepts have been added to this subject yet.</EmptyState> : <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{subject.concepts.map((concept) => <Card key={concept.id} className="group h-full transition-colors hover:border-primary/50"><Link href={study.concepts.show(concept)} className="flex h-full flex-col"><CardContent className="flex h-full flex-col gap-3 pt-6"><div className="flex items-center justify-between"><Badge variant="outline">{concept.code}</Badge><ArrowRight className="size-4 text-muted-foreground transition-transform group-hover:translate-x-1" /></div><h2 className="text-lg font-semibold">{concept.title}</h2><p className="text-sm text-muted-foreground">{concept.description}</p><p className="mt-auto text-sm font-medium text-primary">{concept.quizzes.length} quiz{concept.quizzes.length === 1 ? '' : 'zes'} available</p></CardContent></Link></Card>)}</div>}</PageShell>;
}
