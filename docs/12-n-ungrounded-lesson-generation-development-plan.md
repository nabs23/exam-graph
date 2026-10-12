# 12-N — LLM-Knowledge Lesson Generation Development Plan

## Status and relationship to existing plans

**Status: Initial development workflow implemented and locally verified; ready for author use when the existing generation feature switch is enabled.** Product direction: lesson drafts use the language model's learned knowledge and the selected curriculum context. They do not use program files, retrieved evidence, RAG, web search, or citations.

This plan continues the author-reviewed curriculum workflow in [12-K](12-k-program-source-library-and-reviewed-curriculum-generation-plan.md) and builds on the approved concepts in [12-M](12-m-topic-scoped-concept-extraction-development-plan.md). It allows authors to start lesson drafting from a concept, a syllabus topic, or a subject. Lessons remain associated with existing concepts, matching the current curriculum model. It defines the initial LLM-only lesson workflow; it does not require a reference-set study or policy-document reconciliation before development can begin.

## Goal

Let an authorized content author draft lessons from existing curriculum context at concept, topic, or subject scope. The author reviews and edits each proposal, then explicitly approves it. Drafts remain private until approval, and approved lessons are labeled as author-reviewed but not source-verified.

## Product decisions

- **Flexible curriculum context:** Start from one existing concept, syllabus topic, or subject. A concept request uses that concept and its available topic/subject context. A topic request uses that topic and its descendants as context. A subject request uses that subject's complete topic tree as context.
- **Lessons attach to concepts:** The current Lesson model belongs to a Concept. For topic or subject requests, the author selects the existing concepts that should receive a lesson. The model may assign a proposal only to one of those selected concepts; it may not create concepts or change their topic mapping.
- **Use context that exists:** Pass available subject, topic, concept, and learning-objective information. Missing descriptions or objectives may be omitted from the prompt and shown to the reviewer; they do not block drafting.
- **LLM knowledge only:** Do not select source files, retrieve text, invoke embeddings or RAG, browse the web, request citations, or claim source support. The model uses its learned knowledge and the supplied curriculum context.
- **Bounded proposals:** Generate at most one lesson per selected concept in a request. Each lesson has a title, summary, structured teaching sections, examples where useful, common misconceptions, and a recap. Learning objectives, quizzes, graded questions, and prerequisites are separate work.
- **Review is per item:** Authors can edit, reorder, approve, or reject individual lesson proposals. Duplicate or questionable items should not block unrelated approvals.
- **Approval is editorial:** Pending and rejected proposals remain private. Approval creates the canonical Lesson and makes it available in existing learner views. Label it **Author reviewed · not source-verified**; do not claim correctness, official completeness, currency, or PRC compliance.
- **Do not overwrite:** A new request creates a separate proposal. It never silently edits or replaces an existing lesson.
- **Feature availability:** Keep a configuration switch for lesson generation, with conservative request and output limits. The existing application default model is gpt-6-luna unless a lesson-specific override is configured.

## Data and request contract

Reuse curriculum_generations for request status, provider/model, prompt/schema version, request context, scope snapshot, proposal, reviewer edits, decisions, canonical lesson IDs, and timestamps. Add lesson generation as a generation kind and include the selected scope type and ID, subject ID, relevant topic hierarchy, and selected target concept IDs in the scope snapshot and request hash.

For a concept request, the target concept set contains only that concept. For a topic request, it contains the concepts the author selected from that topic and its descendants. For a subject request, it contains the concepts the author selected from that subject. Snapshot available titles, descriptions, codes, topic paths, and learning objectives so review can detect context changes. If relevant context changes before approval, mark the proposal stale or require explicit re-review.

Do not snapshot source files, store source citations, or include credentials, signed URLs, or learner data in the prompt or logs.

The initial structured-output contract is:

    items[]:
      draft_id
      concept_id             # must be one of the selected existing concepts
      title
      summary
      sections[]:
        heading
        explanation
        example: null | { scenario, steps[], takeaway }
      common_misconceptions[]:
        misconception
        correction
      recap

The application owns item limits, unique draft IDs, field bounds, concept membership, duplicate checks, safe rendering, and canonical lesson IDs. Convert approved sections into the existing Lesson summary and plain-text content fields. Do not execute or render model-supplied HTML.

## Author workflow

1. From a concept, topic, or subject, the author chooses **Draft lessons** and sees the selected curriculum context.
2. For topic or subject scope, the author selects existing concepts to receive lesson proposals. The form shows the selected count and configured limit.
3. The application checks authorization, program/subject ownership, concept membership, and request limits, then snapshots the relevant context before queueing.
4. A queued structured generation drafts one lesson for each selected concept, using only the context snapshot and optional author instructions.
5. The review page groups proposals by concept, explains that generation used LLM knowledge without source materials, and supports edits and per-item decisions.
6. On explicit approval, the application rechecks scope and proposal versions, writes the Lesson and decision transactionally, and exposes the approved lesson through existing learner views.

## Delivery phases and acceptance checks

### Phase 0 — Development fixture

- [x] Use LLM knowledge and selected curriculum context only; no source materials, retrieval, RAG, web search, or citations.
- [x] Use Financial Accounting and Reporting (FAR; subject ID 1), which has 47 topics and 26 existing concepts with descriptions.
- [x] Exercise the concept entry point with concept ID 1, the topic entry point with topic ID 2 and concepts 2 and 3, and the subject entry point with selected concepts 1, 2, and 3.
- [x] Set configurable starter limits: up to 5 target concepts per request, 5 sections per lesson, and 10,000 generated text characters per lesson.

#### Phase 0 development fixture and starter limits — 2026-10-12

The development database contains a usable FAR fixture for all entry points: concept 1 (PPE-C01), topic 2 (Initial recognition and measurement) with concepts 2 (PPE-C02) and 3 (PPE-C03), and subject 1 with concepts 1–3 selected for the subject-scoped request. All 26 FAR concepts currently have non-empty descriptions.

The initial limits are deliberately small and configurable: no more than 5 target concepts per request, 5 sections per lesson, and 10,000 characters of generated text per lesson. Treat these as practical development bounds; adjust them after using the workflow. They are not content-quality thresholds or rollout gates.

### Phase 1 — Scope selection and authorization

- [x] Add a lesson-draft entry point from a concept, syllabus topic, and subject.
- [x] For topic and subject requests, allow the author to select existing target concepts and show their curriculum paths.
- [x] Validate that every selected concept belongs to the requested topic subtree or subject and program.
- [x] Check: invalid or unauthorized requests create no generation record or job.

### Phase 2 — Context snapshot and generation

- [x] Persist the selected scope, relevant topic tree, concepts, and available objectives in the generation scope snapshot and request hash.
- [x] Add a structured lesson agent using the current AI SDK patterns, configured model, feature switch, queueing, and safe failure handling.
- [x] Validate output shape, item and field limits, unique draft IDs, and target concept membership.
- [x] Check: malformed, oversized, or out-of-scope output cannot create canonical lessons.

### Phase 3 — Review and approval

- [x] Show lesson proposals under their target concepts and disclose LLM-knowledge generation without source materials.
- [x] Support editing, ordering, per-item approval/rejection, duplicate conflict notes, and optimistic proposal-version checks.
- [x] Create canonical Lessons only for explicitly approved items and preserve the generation review record.
- [x] Show approved lesson labels in author and learner views; keep pending and rejected proposals private.
- [x] Check: approving one lesson does not approve its siblings, and context changes prevent stale approval.

### Phase 4 — Development acceptance

- [x] Exercise concept-, topic-, and subject-scoped requests with the development fixture.
- [x] Verify topic/subject requests can target only selected existing concepts in their scope.
- [x] Verify lesson content is stored as plain text and canonical lessons are created only after explicit approval.
- [x] Record generation failure, selected-topic scope validation, reviewer edits, and approval behavior in focused feature tests.

#### Development verification — 2026-10-12

Implemented lessons as a new `CurriculumGeneration` kind with concept-, topic-, and subject-scoped entry points, a maximum of five target concepts per request, and a structured lesson proposal capped at five sections and 10,000 characters per lesson. Prompts contain the selected curriculum snapshot and optional author instructions only. Approval creates canonical lessons and links them to their generation for the **Author reviewed · not source-verified** label.

The focused curriculum-generation feature tests pass (25 tests, 188 assertions), and the full Laravel suite passes (222 tests, 1,198 assertions). TypeScript checking, targeted changed-file linting, the frontend build, and the migration also pass. These checks cover scope snapshots, topic-descendant validation, the absence of source materials in lesson prompts, private proposals, stale context, duplicate conflicts, approval-time creation, and label provenance. The repository-wide formatting check still reports issues across existing files. No live-model content quality review has been recorded; use the workflow and adjust its limits or prompts as needed.

## Acceptance criteria

- Authors can draft from one concept, one topic subtree, or one subject's topic tree.
- Every proposed lesson targets an existing selected concept in the requested scope.
- Generation uses LLM knowledge and the provided curriculum context only; no source materials, retrieval, or citations are used.
- Only explicit per-item author approval creates a canonical learner-visible lesson.
- Approved lessons are labeled **Author reviewed · not source-verified**.
- Invalid scope, malformed output, and failed requests do not create canonical lesson records.

## Out of scope

Program-file selection; embeddings, retrieval, RAG, web search, or citations; creating or remapping concepts or syllabus topics; generating learning objectives, questions, quizzes, exams, or prerequisites; learner-specific personalization; tutoring/chat; automatic approval; automatic replacement of existing lessons; claims of official completeness, source verification, factual certification, or PRC compliance.

## Related documents

- [12-M — Subject-Wide Instructional Concept Extraction](12-m-topic-scoped-concept-extraction-development-plan.md) — approved concept workflow and topic-tree scope.
- [12-K — Program Source Library and Author-Reviewed Curriculum Generation](12-k-program-source-library-and-reviewed-curriculum-generation-plan.md) — shared generation records and author review patterns.
- [12-J — Ungrounded Curriculum and Lesson Generation MVP](12-j-ungrounded-curriculum-and-lesson-generation-mvp.md) — earlier proposal for LLM-generated curriculum and lessons.
- [12-E — Grounded Lesson Drafts](12-e-grounded-lesson-drafts.md) — prior grounded lesson proposal.
- [07 — FAR Complete Worked Example](07-far-complete-worked-example-syllabus-concepts-lessons-objectives-questions.md) — curriculum-to-lesson illustration.
