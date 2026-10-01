import type { ComponentProps } from 'react';
import ProgramForm from './_form';
import programs from '@/routes/programs';

function EditProgramPage(props: ComponentProps<typeof ProgramForm>) {
    return <ProgramForm {...props} />;
}

EditProgramPage.layout = (props: { program: { id: number } }) => ({
    breadcrumbs: [{ title: 'Programs', href: programs.index() }, { title: 'Program', href: programs.edit(props.program) }],
});

export default EditProgramPage;
