import type { ComponentProps } from 'react';
import ObjectiveForm from './_form';
import concepts from '@/routes/concepts';
import conceptObjectives from '@/routes/concepts/objectives';

function CreateObjectivePage(props: ComponentProps<typeof ObjectiveForm>) {
    return <ObjectiveForm {...props} />;
}

CreateObjectivePage.layout = (props: { concept: { id: number } }) => ({
    breadcrumbs: [{ title: 'Concept', href: concepts.show(props.concept) }, { title: 'Learning objectives', href: conceptObjectives.index(props.concept) }, { title: 'New learning objective', href: conceptObjectives.create(props.concept) }],
});

export default CreateObjectivePage;
