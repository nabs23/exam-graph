import type { ComponentProps } from 'react';
import QuestionForm from './_form';
import { questions } from '@/lib/curriculum-routes';
import { concepts } from '@/lib/curriculum-routes';
import { conceptQuestions } from '@/lib/curriculum-routes';

function EditQuestionPage(props: ComponentProps<typeof QuestionForm>) {
    return <QuestionForm {...props} />;
}

EditQuestionPage.layout = (props: { concept: { id: number }; question: { id: number } }) => ({
    breadcrumbs: [{ title: 'Concept', href: concepts.show(props.concept) }, { title: 'Questions', href: conceptQuestions.index(props.concept) }, { title: 'Question', href: questions.edit(props.question) }],
});

export default EditQuestionPage;
