import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import study from '@/routes/study';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Lesson = { id: number; title: string; summary: string | null; content: string; concept: { id: number; title: string; subject: { id: number; name: string } } };

export default function StudyLesson({ lesson }: { lesson: Lesson }) {
    return <PageShell><Head title={lesson.title} /><Button asChild variant="ghost" className="-ml-3"><Link href={study.concepts.show(lesson.concept.id)}><ArrowLeft />{lesson.concept.title}</Link></Button><PageHeader eyebrow={lesson.concept.subject.name} title={lesson.title} description={lesson.summary} /><Card><CardContent className="pt-6"><article className="whitespace-pre-wrap text-base leading-8 text-foreground">{lesson.content}</article></CardContent></Card></PageShell>;
}
