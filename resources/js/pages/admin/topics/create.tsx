import type { ComponentProps } from 'react';
import TopicForm from './_form';
import { subjects } from '@/lib/curriculum-routes';
import { subjectTopics } from '@/lib/curriculum-routes';

function CreateTopicPage(props: ComponentProps<typeof TopicForm>) {
    return <TopicForm {...props} />;
}

CreateTopicPage.layout = (props: { subject: { id: number } }) => ({
    breadcrumbs: [{ title: 'Subject', href: subjects.show(props.subject) }, { title: 'Syllabus topics', href: subjectTopics.index(props.subject) }, { title: 'New syllabus topic', href: subjectTopics.create(props.subject) }],
});

export default CreateTopicPage;
