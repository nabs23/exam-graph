import rawSubjects from '@/routes/subjects';
import rawTopics from '@/routes/topics';
import rawConcepts from '@/routes/concepts';
import rawLessons from '@/routes/lessons';
import rawQuestions from '@/routes/questions';
import rawQuizzes from '@/routes/quizzes';
import rawObjectives from '@/routes/objectives';
import rawProgramSubjects from '@/routes/programs/subjects';
import rawSubjectConcepts from '@/routes/subjects/concepts';
import rawTopicConcepts from '@/routes/topics/concepts';
import rawConceptLessons from '@/routes/concepts/lessons';
import rawConceptQuestions from '@/routes/concepts/questions';
import rawConceptQuizzes from '@/routes/concepts/quizzes';
import rawConceptObjectives from '@/routes/concepts/objectives';
import unassignedConcepts from '@/routes/unassigned/concepts';
import unassignedLessons from '@/routes/unassigned/lessons';
import unassignedQuestions from '@/routes/unassigned/questions';
import unassignedQuizzes from '@/routes/unassigned/quizzes';
import unassignedObjectives from '@/routes/unassigned/objectives';
import unassignedConceptLessons from '@/routes/unassigned/concepts/lessons';
import unassignedConceptQuestions from '@/routes/unassigned/concepts/questions';
import unassignedConceptQuizzes from '@/routes/unassigned/concepts/quizzes';
import unassignedConceptObjectives from '@/routes/unassigned/concepts/objectives';
import type { RouteQueryOptions } from '@/wayfinder';

export type CurriculumResource = { id: number; route_parameters?: Record<string, number> };

type GeneratedRoute<R, F> = ((args: never, options?: RouteQueryOptions) => R) & {
    form: (args: never, options?: RouteQueryOptions) => F;
};

function contextualRoute<R extends { url: string }, F>(assigned: GeneratedRoute<R, F>, unassigned?: GeneratedRoute<R, F>) {
    function context(resource: CurriculumResource) {
        if (!resource.route_parameters) {
            throw new Error('Curriculum route parameters are missing from the resource.');
        }
        return {
            route: unassigned && !('topic' in resource.route_parameters) ? unassigned : assigned,
            parameters: resource.route_parameters as never,
        };
    }
    const visit = (resource: CurriculumResource, options?: RouteQueryOptions): R => {
        const { route, parameters } = context(resource);
        return route(parameters, options);
    };
    visit.url = (resource: CurriculumResource, options?: RouteQueryOptions): string => visit(resource, options).url;
    visit.form = (resource: CurriculumResource, options?: RouteQueryOptions): F => {
        const { route, parameters } = context(resource);
        return route.form(parameters, options);
    };
    return visit;
}

export const subjects = { ...rawSubjects,
    show: contextualRoute(rawSubjects.show), edit: contextualRoute(rawSubjects.edit),
    update: contextualRoute(rawSubjects.update), destroy: contextualRoute(rawSubjects.destroy),
};
export const subjectTopics = {
    index: contextualRoute(rawTopics.index), create: contextualRoute(rawTopics.create), store: contextualRoute(rawTopics.store),
};
export const topics = { ...subjectTopics,
    show: contextualRoute(rawTopics.show), edit: contextualRoute(rawTopics.edit),
    update: contextualRoute(rawTopics.update), destroy: contextualRoute(rawTopics.destroy),
};
export const concepts = { ...rawConcepts,
    show: contextualRoute(rawConcepts.show, unassignedConcepts.show), edit: contextualRoute(rawConcepts.edit, unassignedConcepts.edit),
    update: contextualRoute(rawConcepts.update, unassignedConcepts.update), destroy: contextualRoute(rawConcepts.destroy, unassignedConcepts.destroy),
};

export const lessons = {
    show: contextualRoute(rawLessons.show, unassignedLessons.show),
    edit: contextualRoute(rawLessons.edit, unassignedLessons.edit),
    update: contextualRoute(rawLessons.update, unassignedLessons.update),
    destroy: contextualRoute(rawLessons.destroy, unassignedLessons.destroy),
};
export const conceptLessons = {
    index: contextualRoute(rawConceptLessons.index, unassignedConceptLessons.index),
    create: contextualRoute(rawConceptLessons.create, unassignedConceptLessons.create),
    store: contextualRoute(rawConceptLessons.store, unassignedConceptLessons.store),
};

export const questions = {
    show: contextualRoute(rawQuestions.show, unassignedQuestions.show),
    edit: contextualRoute(rawQuestions.edit, unassignedQuestions.edit),
    update: contextualRoute(rawQuestions.update, unassignedQuestions.update),
    destroy: contextualRoute(rawQuestions.destroy, unassignedQuestions.destroy),
};
export const conceptQuestions = {
    index: contextualRoute(rawConceptQuestions.index, unassignedConceptQuestions.index),
    create: contextualRoute(rawConceptQuestions.create, unassignedConceptQuestions.create),
    store: contextualRoute(rawConceptQuestions.store, unassignedConceptQuestions.store),
};

export const quizzes = {
    show: contextualRoute(rawQuizzes.show, unassignedQuizzes.show),
    edit: contextualRoute(rawQuizzes.edit, unassignedQuizzes.edit),
    update: contextualRoute(rawQuizzes.update, unassignedQuizzes.update),
    destroy: contextualRoute(rawQuizzes.destroy, unassignedQuizzes.destroy),
};
export const conceptQuizzes = {
    index: contextualRoute(rawConceptQuizzes.index, unassignedConceptQuizzes.index),
    create: contextualRoute(rawConceptQuizzes.create, unassignedConceptQuizzes.create),
    store: contextualRoute(rawConceptQuizzes.store, unassignedConceptQuizzes.store),
};

export const objectives = {
    show: contextualRoute(rawObjectives.show, unassignedObjectives.show),
    edit: contextualRoute(rawObjectives.edit, unassignedObjectives.edit),
    update: contextualRoute(rawObjectives.update, unassignedObjectives.update),
    destroy: contextualRoute(rawObjectives.destroy, unassignedObjectives.destroy),
};
export const conceptObjectives = {
    index: contextualRoute(rawConceptObjectives.index, unassignedConceptObjectives.index),
    create: contextualRoute(rawConceptObjectives.create, unassignedConceptObjectives.create),
    store: contextualRoute(rawConceptObjectives.store, unassignedConceptObjectives.store),
};

export const programSubjects = rawProgramSubjects;
export const subjectConcepts = {
    index: contextualRoute(rawSubjectConcepts.index),
    create: contextualRoute(rawSubjectConcepts.create),
    store: contextualRoute(rawSubjectConcepts.store),
};
export const topicConcepts = {
    index: contextualRoute(rawTopicConcepts.index),
    create: contextualRoute(rawTopicConcepts.create),
    store: contextualRoute(rawTopicConcepts.store),
};
