import type { ComponentProps } from 'react';
import TopicForm from './_form';
import { topics } from '@/lib/curriculum-routes';
import { subjects } from '@/lib/curriculum-routes';
import { subjectTopics } from '@/lib/curriculum-routes';

function EditTopicPage(props: ComponentProps<typeof TopicForm>) {
    return <TopicForm {...props} />;
}

EditTopicPage.layout = (props: { subject: { id: number }; topic: { id: number } }) => ({
    breadcrumbs: [{ title: 'Subject', href: subjects.show(props.subject) }, { title: 'Syllabus topics', href: subjectTopics.index(props.subject) }, { title: 'Syllabus topic', href: topics.edit(props.topic) }],
});

export default EditTopicPage;
