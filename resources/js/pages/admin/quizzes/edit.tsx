import type { ComponentProps } from 'react';
import QuizForm from './_form';
import { quizzes } from '@/lib/curriculum-routes';
import { concepts } from '@/lib/curriculum-routes';
import { conceptQuizzes } from '@/lib/curriculum-routes';

function EditQuizPage(props: ComponentProps<typeof QuizForm>) {
    return <QuizForm {...props} />;
}

EditQuizPage.layout = (props: { concept: { id: number }; quiz: { id: number } }) => ({
    breadcrumbs: [{ title: 'Concept', href: concepts.show(props.concept) }, { title: 'Quizzes', href: conceptQuizzes.index(props.concept) }, { title: 'Quiz', href: quizzes.edit(props.quiz) }],
});

export default EditQuizPage;
