import type { ComponentProps } from 'react';
import SubjectForm from './_form';
import subjects from '@/routes/subjects';

function CreateSubjectPage(props: ComponentProps<typeof SubjectForm>) {
    return <SubjectForm {...props} />;
}

CreateSubjectPage.layout = () => ({
    breadcrumbs: [{ title: 'Subjects', href: subjects.index() }, { title: 'New subject', href: subjects.create() }],
});

export default CreateSubjectPage;
