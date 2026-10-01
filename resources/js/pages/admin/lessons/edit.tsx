import type { ComponentProps } from 'react';
import LessonForm from './_form';
import lessons from '@/routes/lessons';
import concepts from '@/routes/concepts';
import conceptLessons from '@/routes/concepts/lessons';

function EditLessonPage(props: ComponentProps<typeof LessonForm>) {
    return <LessonForm {...props} />;
}

EditLessonPage.layout = (props: { concept: { id: number }; lesson: { id: number } }) => ({
    breadcrumbs: [{ title: 'Concept', href: concepts.show(props.concept) }, { title: 'Lessons', href: conceptLessons.index(props.concept) }, { title: 'Lesson', href: lessons.edit(props.lesson) }],
});

export default EditLessonPage;
