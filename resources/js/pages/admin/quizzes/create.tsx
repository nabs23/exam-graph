import type { ComponentProps } from 'react';
import QuizForm from './_form';
import concepts from '@/routes/concepts';
import conceptQuizzes from '@/routes/concepts/quizzes';

function CreateQuizPage(props: ComponentProps<typeof QuizForm>) {
    return <QuizForm {...props} />;
}

CreateQuizPage.layout = (props: { concept: { id: number } }) => ({
    breadcrumbs: [{ title: 'Concept', href: concepts.show(props.concept) }, { title: 'Quizzes', href: conceptQuizzes.index(props.concept) }, { title: 'New quiz', href: conceptQuizzes.create(props.concept) }],
});

export default CreateQuizPage;
