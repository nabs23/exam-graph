# ExamGraph — Bare-Bones CRUD MVP Test Guide

## Purpose

This guide is the verification companion to [`09-bare-bones-crud-mvp-development-plan.md`](09-bare-bones-crud-mvp-development-plan.md). It describes how to verify the finished CRUD MVP from a clean local database through administration, learner study, quiz submission, and progress reporting.

The guide deliberately tests only the shipped MVP. Authentication, authorization, source ingestion, AI, adaptive remediation, analytics, notifications, and mock exams are outside its acceptance scope.

## 1. Prerequisites

Use a local development environment with:

- PHP and Composer installed;
- Node.js and npm installed;
- a database configured in `.env`;
- the repository dependencies installed;
- a browser available for manual request checks.

The MVP uses the seeded local learner identity. No login or email-verification step is required for this test pass.

## 2. Reset to a Known Dataset

Run these commands from the project root:

```bash
composer install
npm install
php artisan migrate:fresh --seed --no-interaction
```

`migrate:fresh` deletes local database tables. Never run it against a database containing data you need to keep.

The seed should provide a CPALE program, FAR subject, PPE syllabus topics, concepts and prerequisite links, lessons, learning objectives, questions, quizzes, and one local learner user.

## 3. Automated Verification

Run the checks below after resetting the database and after any implementation change:

```bash
php artisan test --compact
npm run types:check
vendor/bin/pint --dirty --format agent
npm run build
git diff --check
```

The completed MVP currently records 48 tests and 166 assertions. Test counts may increase as regression coverage is added; the required result is that the suite passes with no failures, TypeScript has no errors, Pint reports clean formatting, the production build succeeds, and `git diff --check` reports no whitespace errors.

To run a focused feature file while iterating:

```bash
php artisan test --compact tests/Feature/CrudMvpTest.php
```

## 4. Start the Application

Use the repository's normal development process:

```bash
composer run dev
```

If the project is being run in separate processes, start Laravel and Vite independently:

```bash
php artisan serve
npm run dev
```

Open the URL printed by the development server. The main manual entry points are `/study`, `/progress`, `/programs`, `/subjects`, and `/concepts`.

## 5. Route and Navigation Smoke Check

Confirm the named routes exist before browser testing:

```bash
php artisan route:list --except-vendor
```

Verify that the application exposes these learner routes:

- `study.index` — study landing page;
- `study.subjects.show` — subject curriculum;
- `study.concepts.show` — concept detail;
- `study.lessons.show` — full lesson content;
- `study.quizzes.show` — quiz form;
- `study.quizzes.attempts.store` — quiz submission;
- `attempts.result` — submitted attempt result;
- `progress.index` — learner progress dashboard.

Also verify that the sidebar links reach the dashboard, study area, progress, programs, subjects, and concepts pages without a client-side route error.

## 6. Content CRUD Verification

Perform each operation through the development-facing pages. Every successful create/update should redirect to a useful parent or detail page, and every invalid submission should return understandable field errors without losing entered data.

### Programs

1. Open the program list.
2. Create a program with a unique code and description.
3. Open it, confirm its subject list is empty, then edit its name and description.
4. Attempt to delete a program with dependent subjects; confirm deletion is refused.
5. Delete an empty program and confirm it leaves the list.

### Subjects

1. Create a subject under the seeded program.
2. Edit its name, code, description, and sort order.
3. Confirm the subject page links to its syllabus topics and concepts.
4. Submit a duplicate subject code within the same program; confirm validation fails.

### Syllabus topics

1. Create a root topic and a child topic under the same subject.
2. Edit their titles, descriptions, and sort order.
3. Confirm nested topics render in the subject view.
4. Attempt to use a parent topic from another subject; confirm validation fails.
5. Attempt to make a topic its own parent; confirm validation fails.
6. Delete an empty topic, then attempt to delete a topic with concepts; confirm dependent deletion is refused.

### Concepts

1. Create a concept under the subject and, optionally, one of its topics.
2. Edit its code, title, description, and sort order.
3. Confirm the concept detail page exposes lessons, objectives, questions, quizzes, and prerequisites.
4. Submit a duplicate concept code within the subject; confirm validation fails.
5. Assign a syllabus topic from another subject; confirm validation fails.

### Lessons and learning objectives

1. Create a lesson under the concept with a title, summary, and multi-paragraph content.
2. Edit the lesson and change its order.
3. From the concept page, click the lesson title and confirm the learner lesson page displays the complete content, summary, concept link, and back navigation.
4. Delete the lesson and confirm it disappears from the concept.
5. Add, edit, reorder, and remove at least one learning objective.

### Questions, choices, and quizzes

1. Create a question with an explanation and difficulty.
2. Add at least two choices and mark exactly one as correct.
3. Confirm a question with zero correct choices or multiple correct choices cannot be saved or placed on a quiz.
4. Create a quiz for the concept and set its passing score.
5. Add questions to the quiz, reorder them, remove one, and add it again.
6. Attempt to attach a question owned by another concept; confirm validation fails.
7. Confirm the quiz detail page shows the intended question order and answer choices.

### Prerequisites

1. Add a prerequisite link from one concept to another concept in the same subject.
2. Confirm the link appears on the concept detail and learner concept pages.
3. Remove the link and confirm it disappears.
4. Attempt a self-link or duplicate link; confirm validation fails.

## 7. Learner Happy Path

Run this flow with the seeded FAR/PPE content:

1. Open `/study` and confirm the program and subject cards render.
2. Open FAR and confirm syllabus topics and concepts are listed in order.
3. Open a PPE concept and verify its description, prerequisites, objectives, lessons, and available quizzes.
4. Open a lesson from its lesson card. Confirm the dedicated lesson page renders the full stored content rather than only a summary.
5. Return to the concept and open an available quiz.
6. Start the quiz, select one answer for every question, and submit once.
7. Confirm the result shows the server-calculated score, pass/fail state, selected answers, correctness, and explanations only after submission.
8. Revisit the submitted result and confirm it is read-only; no attempt-edit action should be available.
9. Open `/progress` and confirm the concept shows latest score, best score, attempt count, last-attempted time, and completion status.

Repeat the quiz with at least one incorrect answer. Confirm a new immutable attempt is created and that the best score is retained while the latest score changes.

## 8. Validation and Failure Cases

Verify these cases explicitly:

- submitting a quiz without answering every question is rejected;
- an answer choice belonging to another question is rejected;
- a question with fewer than two choices is not quiz-ready;
- a quiz cannot contain a question from another concept;
- duplicate program, subject, concept, prerequisite, and quiz-question pairs are rejected;
- deleting a parent with dependent records is refused with a clear message;
- empty curriculum, empty concept, no-lesson, no-quiz, and no-attempt states render a useful empty state;
- refreshing a submitted result does not duplicate the attempt or answers.

## 9. Read-Only Database Spot Checks

Use these checks when investigating a failed browser flow. They do not mutate data:

```bash
php artisan tinker --execute='dump(App\\Models\\Program::with("subjects")->count());'
php artisan tinker --execute='dump(App\\Models\\Lesson::with("concept")->get(["id", "concept_id", "title", "summary", "content"])->toArray());'
php artisan tinker --execute='dump(App\\Models\\QuizAttempt::with("answers")->latest()->first()?->toArray());'
php artisan tinker --execute='dump(App\\Models\\ConceptProgress::latest()->get()->toArray());'
```

The lesson query should include non-empty stored content. A submitted attempt should include one answer per quiz question, and progress should reflect the attempt score.

## 10. Acceptance Checklist

The MVP is fully verified when all of the following are true:

- migrations and seeding succeed on a clean local database;
- all automated checks pass;
- every CRUD area can create, view, edit, and remove records within its dependency rules;
- same-subject, self-link, duplicate, and quiz-ownership validations behave as expected;
- a learner can browse FAR/PPE curriculum and open complete lesson content;
- a learner can submit complete quizzes and see server-scored feedback;
- attempts are immutable and progress updates after each submission;
- empty states and validation failures are understandable;
- no feature outside the bare-bones scope is required for acceptance.

Record the verification date, commit or branch, commands run, browser paths checked, and any known defects alongside the release or handoff.

## Troubleshooting

- **Missing Vite manifest:** run `npm run build`, or keep `npm run dev` running locally.
- **Stale or missing seed records:** run `php artisan migrate:fresh --seed --no-interaction` against the local database.
- **Missing generated route helpers:** run `php artisan wayfinder:generate --with-form --no-interaction`.
- **Unexpected route behavior:** inspect `php artisan route:list --except-vendor` and confirm the route name used by the page.
- **Type errors after route changes:** regenerate Wayfinder helpers, then run `npm run types:check`.
- **Progress appears empty:** submit a quiz using the seeded learner, then refresh `/progress`.

