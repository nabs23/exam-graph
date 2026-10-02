import type { ComponentProps } from 'react';
import ConceptForm from './_form';
import { concepts, subjectConcepts, topicConcepts } from '@/lib/curriculum-routes';

function CreateConceptPage(props: ComponentProps<typeof ConceptForm>) {
    return <ConceptForm {...props} />;
}

CreateConceptPage.layout = (props: ComponentProps<typeof ConceptForm>) => ({
    breadcrumbs: [{ title: 'Concepts', href: props.contextTopic ? topicConcepts.index(props.contextTopic) : props.contextSubject ? subjectConcepts.index(props.contextSubject) : concepts.index() }, { title: 'New concept', href: props.contextTopic ? topicConcepts.create(props.contextTopic) : props.contextSubject ? subjectConcepts.create(props.contextSubject) : concepts.create() }],
});

export default CreateConceptPage;
