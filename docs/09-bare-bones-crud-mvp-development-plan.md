# ExamGraph — Bare-Bones CRUD MVP Development Plan

## Purpose

This plan delivers a small, usable ExamGraph foundation: one administrator-managed curriculum, lesson, and question bank with a learner quiz flow and basic progress records. It is intentionally a CRUD application, not the adaptive, source-grounded platform described in `08-executable-development-plan.md`.

The objective is to validate that the core content model is pleasant to manage and that a learner can move from a concept to a lesson and quiz before investing in authorization, ingestion, AI, or operational infrastructure.

## Implementation Status

The bare-bones CRUD MVP is implemented in the repository. The database foundation, FAR/PPE seed, complete content CRUD surfaces, learner curriculum, quiz submission, immutable attempts, progress persistence, empty states, Wayfinder routes, and feature coverage are complete. The MVP deliberately stops before authorization, ingestion, AI, and adaptive remediation.

The final hardening pass also enforces same-subject syllabus relationships, prevents self-parent topics, and prevents quizzes from attaching questions owned by another concept.

The final usability pass adds direct management links from a concept to its lessons, objectives, questions, and quizzes, plus a learner-facing progress link from the study landing page.

The factory-quality pass replaces generated placeholders with usable domain factories and adds a regression test for relationship-backed test data.

The final request-smoke pass covers the public study landing page and progress entry point for the local learner.

### Verification Snapshot — 2026-09-24

- `php artisan test --compact`: 48 tests and 166 assertions passed.
- `npm run types:check`: passed.
- `vendor/bin/pint --dirty --format agent`: passed.
- `npm run build`: passed.
- `git diff --check`: passed.
- No unchecked implementation items remain in this plan.

## Explicit Scope

Build these capabilities:

- manage programs, subjects, syllabus topics, concepts, prerequisite links, lessons, learning objectives, questions, and answer choices;
- browse a subject curriculum and a concept's prerequisites;
- take a manually assembled concept quiz;
- save attempts, selected answers, scores, and simple per-concept progress;
- view a lightweight learner progress dashboard.

Use the FAR PPE example as the initial seed content, but keep every screen generic enough for another program or subject.

## Deliberate Exclusions

Do **not** build any of the following for this MVP:

- roles, permissions, admin gates, audit logs, content review, publication states, or approval workflows;
- source uploads, extraction, chunking, embeddings, vector search, RAG, LLMs, generated lessons, or generated questions;
- queues, Redis, Horizon, background jobs, malware scanning, or provider integrations;
- diagnostic placement, adaptive recommendations, mastery-policy configuration, remediation sessions, spaced repetition, analytics, mock exams, or notifications;
- curriculum/TOS versioning, legal overlays, source provenance, multi-environment operations, or pilot-release controls.

Authentication is not required for the first pass. During development, use a single local learner identity (for example, the first seeded user) for attempt and progress records. Keep `user_id` on learner-owned tables so a real login flow can be added later without redesigning the data.

## Working Product Flow

```text
Program → Subject → Syllabus topic → Concept
                              ↓
                    lesson(s) and quiz questions
                              ↓
              learner submits a concept quiz attempt
                              ↓
                   score and concept progress update
```

Prerequisites are displayed as navigable concept links. They are informational in this phase: a learner may take any available concept quiz, and no automatic remediation path is calculated.

## Minimal Database Design

All tables use normal Laravel timestamps. Use foreign keys and indexes for every relationship and lookup used below. Use nullable foreign keys only where the relationship is genuinely optional.

| Table | Required columns | Purpose |
| --- | --- | --- |
| `users` | existing Laravel user columns | Holds the temporary local learner identity and supports future authentication. |
| `programs` | `id`, `name`, `code`, `description` | A licensure or review program, such as CPALE. |
| `subjects` | `id`, `program_id`, `name`, `code`, `description`, `sort_order` | Subjects within a program, such as FAR. |
| `syllabus_topics` | `id`, `subject_id`, `parent_id`, `code`, `title`, `description`, `sort_order` | A simple nested official/topic outline. `parent_id` enables sections and subsections. |
| `concepts` | `id`, `subject_id`, `syllabus_topic_id`, `code`, `title`, `description`, `sort_order` | The teachable and assessable units. A concept belongs to one subject and may be assigned to one syllabus topic. |
| `concept_prerequisites` | `id`, `concept_id`, `prerequisite_concept_id` | Directed prerequisite links between concepts. Enforce unique pairs and reject self-links. |
| `lessons` | `id`, `concept_id`, `title`, `summary`, `content`, `sort_order` | Manually authored lesson content for a concept. |
| `learning_objectives` | `id`, `concept_id`, `description`, `sort_order` | Short, observable objectives displayed with the concept and lesson. |
| `questions` | `id`, `concept_id`, `prompt`, `explanation`, `difficulty`, `sort_order` | Manually authored multiple-choice questions for one concept. |
| `question_choices` | `id`, `question_id`, `content`, `is_correct`, `sort_order` | Choices for a question. Require exactly one correct choice in application validation. |
| `quizzes` | `id`, `concept_id`, `title`, `description`, `passing_score` | A named quiz for a concept. A default quiz can be created with the concept. |
| `quiz_questions` | `id`, `quiz_id`, `question_id`, `sort_order` | Explicitly determines which questions appear on a quiz. Enforce unique quiz/question pairs. |
| `quiz_attempts` | `id`, `user_id`, `quiz_id`, `started_at`, `submitted_at`, `score`, `correct_answers`, `total_questions`, `passed` | An immutable submitted-attempt summary. |
| `attempt_answers` | `id`, `quiz_attempt_id`, `question_id`, `question_choice_id`, `is_correct` | The learner's selected answer and its result at submission time. Enforce one answer per question per attempt. |
| `concept_progress` | `id`, `user_id`, `concept_id`, `last_score`, `best_score`, `attempts_count`, `last_attempted_at`, `is_completed` | A denormalized convenience record for the learner dashboard; update it when an attempt is submitted. |

### Database Rules

- `programs.code` is unique; `subjects` are unique by `(program_id, code)`; concepts are unique by `(subject_id, code)`.
- `syllabus_topics.parent_id` must refer to a topic in the same subject.
- `concept_prerequisites` must not contain self-links or duplicate pairs. Cycle detection is deferred; display links safely and seed an acyclic graph.
- A question must have at least two choices and exactly one correct choice before it may be placed on a quiz.
- Submitted attempts and attempt answers are never edited through CRUD screens. Incorrect entries are corrected by creating a new attempt.
- Store score as a decimal percentage (for example, `80.00`) and compute it server-side from stored answer results.

## Pages and CRUD Surfaces

Create conventional index, create, edit, and detail views for the administrative data. Since authorization is excluded, these routes are simply development-facing application pages.

| Area | Required screens/actions |
| --- | --- |
| Programs | List, create, edit, view subjects, delete only when no dependent data exists. |
| Subjects | List within program, create, edit, view syllabus and concepts. |
| Syllabus topics | Nested list within subject; create, edit, reorder, and delete empty topics. |
| Concepts | List/filter within subject; create, edit, view lessons, objectives, questions, quizzes, and prerequisites. |
| Prerequisites | Add and remove prerequisite concept links from a concept detail page. |
| Lessons | List/create/edit/delete within a concept; render lesson content in learner view. |
| Learning objectives | Add, edit, reorder, and remove within a concept. |
| Questions and choices | List/create/edit/delete questions; manage choices inline; show correct-answer explanation only after submission. |
| Quizzes | Create/edit/delete quiz metadata; add, remove, and reorder its questions. |
| Learner curriculum | Browse program, subject, topic, concept, prerequisites, objectives, lessons, and available quizzes. |
| Quiz taking | Start a quiz, select one answer per question, submit once, and view score plus explanations. |
| Progress | Show attempted concepts, best/latest score, completion status, and link back to each concept. |

Use named Laravel routes and Wayfinder-generated route helpers in the Inertia React client. Keep controllers thin; use Form Requests for create/update validation and a focused service/action for quiz submission and progress updates.

## Delivery Phases

### Phase 1 — Data Foundation

**Goal:** Establish the complete basic schema and seed one coherent FAR PPE slice.

- [x] Confirm installed package versions and existing Laravel/Inertia conventions before implementation.
- [x] Create migrations, models, factories, and relationships for all tables in the minimal database design.
- [x] Add appropriate foreign keys, unique constraints, indexes, and deletion behavior.
- [x] Create a development seeder with CPALE, FAR, PPE topics, a small acyclic concept graph, lessons, objectives, and questions.
- [x] Seed one local learner user for attempt records.
- [x] Add tests for key constraints, relationships, and quiz-ready question validation.

**Exit criteria:** A fresh local database can be migrated and seeded into a browsable FAR PPE dataset with at least one quiz-ready concept.

### Phase 2 — Curriculum and Content CRUD

**Goal:** Make the entire content model manageable through the web application.

- [x] Build Program, Subject, Syllabus Topic, Concept, Lesson, Learning Objective, Question, Choice, and Quiz CRUD routes and Inertia pages.
- [x] Build inline prerequisite and quiz-question management on the appropriate detail pages.
- [x] Validate all create and update inputs server-side with clear errors.
- [x] Prevent deletion when it would orphan records; use a clear validation message rather than cascading learner data away.
- [x] Add empty states and navigation between parent and child records.
- [x] Add feature tests for the principal CRUD paths and invalid submissions.

**Exit criteria:** A developer can create a new concept, attach a lesson and objective, create a valid multiple-choice question, place it in a quiz, and find it again from the subject curriculum.

### Phase 3 — Learner Browse and Quiz Flow

**Goal:** Turn curated content into a basic usable study experience.

- [x] Build learner-facing subject, topic, and concept pages.
- [x] Display prerequisites, objectives, ordered lessons, and available quizzes.
- [x] Implement quiz start, answer selection, submission, server-side scoring, and result feedback.
- [x] Persist immutable attempt and answer records using the local learner identity.
- [x] Show question explanations only after a submitted attempt.
- [x] Add tests for complete, incomplete, and invalid quiz submissions and correct score calculation.

**Exit criteria:** A learner can study a concept, finish a quiz, and see a persisted score and answer feedback.

### Phase 4 — Basic Progress and Polish

**Goal:** Make the MVP demonstrable without introducing adaptive behavior.

- [x] Update `concept_progress` transactionally after every submitted quiz attempt.
- [x] Build a progress dashboard with last score, best score, attempt count, and completed/not-completed status.
- [x] Define completion simply: the learner has at least one passing attempt for a concept's quiz.
- [x] Handle empty curriculum, no quizzes, no attempts, validation errors, and submitted-quiz revisits gracefully.
- [x] Run formatting and the focused Pest suite; manually verify the seeded FAR PPE flow.

**Exit criteria — Bare-Bones MVP:** A developer can manage the complete basic content set and a learner can browse it, take a quiz, receive feedback, and see simple progress—without authorization gates, ingestion, or AI.

## Definition of Done

- [x] Migrations run cleanly on a new database and the development seeder works.
- [x] Every CRUD form has server-side validation and understandable error feedback.
- [x] Foreign keys, unique constraints, and required indexes protect the basic data model.
- [x] Quiz scoring is calculated server-side, and submitted attempts cannot be edited.
- [x] The seeded FAR PPE happy path works from curriculum browsing through visible progress.
- [x] Meaningful feature tests cover CRUD validation, quiz submission, score calculation, and progress updates.
- [x] PHP changes are formatted with Pint; affected Pest tests pass.

## Next Upgrade Path

After the CRUD MVP is proven, add capability in this order: authentication and authorization; publication/review states; stronger graph validation and remediation rules; source ingestion and provenance; retrieval; then AI features. The full delivery plan in `08-executable-development-plan.md` remains the reference for those later phases.
