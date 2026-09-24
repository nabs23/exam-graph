import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, CircleDashed } from 'lucide-react';
import study from '@/routes/study';
import { EmptyState, PageHeader, PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';

type Progress = { id: number; last_score: string | null; best_score: string | null; attempts_count: number; is_completed: boolean; concept: { id: number; code: string; title: string; subject: { name: string } } };
export default function ProgressPage({ progress }: { progress: Progress[] }) {
    return <PageShell><Head title="Progress" /><PageHeader eyebrow="ExamGraph learning workspace" title="Your progress" description="Review your latest and best quiz performance across completed concepts." />{progress.length === 0 ? <EmptyState>No quiz attempts yet.</EmptyState> : <div className="grid gap-4 md:grid-cols-2">{progress.map((item) => <Card key={item.id} className="group transition-colors hover:border-primary/50"><Link href={study.concepts.show(item.concept.id)}><CardContent className="space-y-4 pt-6"><div className="flex items-start justify-between gap-4"><div><p className="text-sm text-muted-foreground">{item.concept.subject.name} · {item.concept.code}</p><h2 className="text-lg font-semibold">{item.concept.title}</h2></div><Badge variant={item.is_completed ? 'default' : 'secondary'}>{item.is_completed ? <CheckCircle2 /> : <CircleDashed />}{item.is_completed ? 'Completed' : 'In progress'}</Badge></div><div className="flex items-center justify-between text-sm"><span>Best <strong>{item.best_score}%</strong> · Latest <strong>{item.last_score}%</strong></span><span className="text-muted-foreground">{item.attempts_count} attempt{item.attempts_count === 1 ? '' : 's'}</span></div><ArrowRight className="size-4 text-primary transition-transform group-hover:translate-x-1" /></CardContent></Link></Card>)}</div>}</PageShell>;
}
