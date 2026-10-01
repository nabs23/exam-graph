import type { ComponentProps } from 'react';
import ProgramForm from './_form';
import programs from '@/routes/programs';

function CreateProgramPage(props: ComponentProps<typeof ProgramForm>) {
    return <ProgramForm {...props} />;
}

CreateProgramPage.layout = () => ({
    breadcrumbs: [{ title: 'Programs', href: programs.index() }, { title: 'New program', href: programs.create() }],
});

export default CreateProgramPage;
