import type { ComponentProps } from 'react';
import SubjectForm from './_form';
import { programSubjects, subjects } from '@/lib/curriculum-routes';
import programs from '@/routes/programs';

function EditSubjectPage(props: ComponentProps<typeof SubjectForm>) {
    return <SubjectForm {...props} />;
}

EditSubjectPage.layout = (props: {
    subject: { id: number; program_id: number };
    programs: { id: number }[];
    returnTab: string | null;
}) => {
    const program = props.programs.find(
        (candidate) => candidate.id === props.subject.program_id,
    );

    return {
        breadcrumbs: [
            ...(program
                ? [{ title: 'Program', href: programs.show(program) }]
                : []),
            {
                title: 'Subjects',
                href: program
                    ? programSubjects.index(program)
                    : subjects.index(),
            },
            {
                title: 'Subject',
                href: subjects.show(
                    props.subject,
                    props.returnTab
                        ? { query: { tab: props.returnTab } }
                        : undefined,
                ),
            },
            { title: 'Edit subject', href: subjects.edit(props.subject) },
        ],
    };
};

export default EditSubjectPage;
