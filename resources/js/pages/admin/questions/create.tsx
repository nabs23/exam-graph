import type { ComponentProps } from 'react';
import QuestionForm from './_form';
import { concepts } from '@/lib/curriculum-routes';
import { conceptQuestions } from '@/lib/curriculum-routes';

function CreateQuestionPage(props: ComponentProps<typeof QuestionForm>) {
    return <QuestionForm {...props} />;
}

CreateQuestionPage.layout = (props: { concept: { id: number } }) => ({
    breadcrumbs: [{ title: 'Concept', href: concepts.show(props.concept) }, { title: 'Questions', href: conceptQuestions.index(props.concept) }, { title: 'New question', href: conceptQuestions.create(props.concept) }],
});

export default CreateQuestionPage;
