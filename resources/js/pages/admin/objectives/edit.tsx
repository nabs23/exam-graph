import type { ComponentProps } from 'react';
import ObjectiveForm from './_form';
import { objectives } from '@/lib/curriculum-routes';
import { concepts } from '@/lib/curriculum-routes';
import { conceptObjectives } from '@/lib/curriculum-routes';

function EditObjectivePage(props: ComponentProps<typeof ObjectiveForm>) {
    return <ObjectiveForm {...props} />;
}

EditObjectivePage.layout = (props: { concept: { id: number }; objective: { id: number } }) => ({
    breadcrumbs: [{ title: 'Concept', href: concepts.show(props.concept) }, { title: 'Learning objectives', href: conceptObjectives.index(props.concept) }, { title: 'Learning objective', href: objectives.edit(props.objective) }],
});

export default EditObjectivePage;
