import type { ComponentProps } from 'react';
import LessonForm from './_form';
import concepts from '@/routes/concepts';
import conceptLessons from '@/routes/concepts/lessons';

function CreateLessonPage(props: ComponentProps<typeof LessonForm>) {
    return <LessonForm {...props} />;
}

CreateLessonPage.layout = (props: { concept: { id: number } }) => ({
    breadcrumbs: [{ title: 'Concept', href: concepts.show(props.concept) }, { title: 'Lessons', href: conceptLessons.index(props.concept) }, { title: 'New lesson', href: conceptLessons.create(props.concept) }],
});

export default CreateLessonPage;
