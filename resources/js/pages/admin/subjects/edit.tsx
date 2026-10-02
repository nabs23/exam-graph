import type { ComponentProps } from 'react';
import SubjectForm from './_form';
import { subjects } from '@/lib/curriculum-routes';

function EditSubjectPage(props: ComponentProps<typeof SubjectForm>) {
    return <SubjectForm {...props} />;
}

EditSubjectPage.layout = (props: { subject: { id: number } }) => ({
    breadcrumbs: [{ title: 'Subjects', href: subjects.index() }, { title: 'Subject', href: subjects.edit(props.subject) }],
});

export default EditSubjectPage;
