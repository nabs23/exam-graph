import type { ComponentProps } from 'react';
import QuizForm from './_form';
import { concepts } from '@/lib/curriculum-routes';
import { conceptQuizzes } from '@/lib/curriculum-routes';

function CreateQuizPage(props: ComponentProps<typeof QuizForm>) {
    return <QuizForm {...props} />;
}

CreateQuizPage.layout = (props: { concept: { id: number } }) => ({
    breadcrumbs: [{ title: 'Concept', href: concepts.show(props.concept) }, { title: 'Quizzes', href: conceptQuizzes.index(props.concept) }, { title: 'New quiz', href: conceptQuizzes.create(props.concept) }],
});

export default CreateQuizPage;
