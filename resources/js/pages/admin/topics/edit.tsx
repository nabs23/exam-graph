import type { ComponentProps } from 'react';
import TopicForm from './_form';
import topics from '@/routes/topics';
import subjects from '@/routes/subjects';
import subjectTopics from '@/routes/subjects/topics';

function EditTopicPage(props: ComponentProps<typeof TopicForm>) {
    return <TopicForm {...props} />;
}

EditTopicPage.layout = (props: { subject: { id: number }; topic: { id: number } }) => ({
    breadcrumbs: [{ title: 'Subject', href: subjects.show(props.subject) }, { title: 'Syllabus topics', href: subjectTopics.index(props.subject) }, { title: 'Syllabus topic', href: topics.edit(props.topic) }],
});

export default EditTopicPage;
