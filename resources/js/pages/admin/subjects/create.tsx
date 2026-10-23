import type { ComponentProps } from 'react';
import SubjectForm from './_form';
import { programSubjects, subjects } from '@/lib/curriculum-routes';
import programs from '@/routes/programs';

function CreateSubjectPage(props: ComponentProps<typeof SubjectForm>) {
    return <SubjectForm {...props} />;
}

CreateSubjectPage.layout = (props: ComponentProps<typeof SubjectForm>) => ({
    breadcrumbs: [
        ...(props.contextProgram
            ? [{ title: 'Program', href: programs.show(props.contextProgram) }]
            : []),
        {
            title: 'Subjects',
            href: props.contextProgram
                ? programSubjects.index(props.contextProgram)
                : subjects.index(),
        },
        {
            title: 'New subject',
            href: props.contextProgram
                ? programSubjects.create(props.contextProgram)
                : subjects.create(),
        },
    ],
});

export default CreateSubjectPage;
