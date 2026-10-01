import type { ComponentProps } from 'react';
import ConceptForm from './_form';
import concepts from '@/routes/concepts';

function EditConceptPage(props: ComponentProps<typeof ConceptForm>) {
    return <ConceptForm {...props} />;
}

EditConceptPage.layout = (props: { concept: { id: number } }) => ({
    breadcrumbs: [{ title: 'Concepts', href: concepts.index() }, { title: 'Concept', href: concepts.edit(props.concept) }],
});

export default EditConceptPage;
