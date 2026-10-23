import type { ComponentProps } from 'react';
import ProgramForm from './_form';
import programs from '@/routes/programs';

function EditProgramPage(props: ComponentProps<typeof ProgramForm>) {
    return <ProgramForm {...props} />;
}

EditProgramPage.layout = (props: {
    program: { id: number };
    returnTab: string | null;
}) => ({
    breadcrumbs: [
        { title: 'Programs', href: programs.index() },
        {
            title: 'Program',
            href: programs.show(
                props.program,
                props.returnTab
                    ? { query: { tab: props.returnTab } }
                    : undefined,
            ),
        },
    ],
});

export default EditProgramPage;
