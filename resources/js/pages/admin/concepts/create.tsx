import type { ComponentProps } from 'react';
import ConceptForm from './_form';
import concepts from '@/routes/concepts';

function CreateConceptPage(props: ComponentProps<typeof ConceptForm>) {
    return <ConceptForm {...props} />;
}

CreateConceptPage.layout = () => ({
    breadcrumbs: [{ title: 'Concepts', href: concepts.index() }, { title: 'New concept', href: concepts.create() }],
});

export default CreateConceptPage;
