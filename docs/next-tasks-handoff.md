# Subject Sources and Topic Extraction Handoff

## Purpose

Use this document as the ongoing handoff and working note for subject source files and topic extraction. Add current observations, decisions, blockers, and the next action in the section below when stopping or resuming work. Mark checklist items complete only after their acceptance checks have been performed in the target environment.

## Manual notes and immediate next actions

Add dated notes here. Keep the newest entry first.

### 2026-10-03 — Move to subject-first source files

- Decision: Remove program-file uploads and program-wide generation of subjects. Administrators create program subjects manually.
- Decision: Retain `SubjectFile` as the source-document workflow. A subject file may be an official curriculum, questionnaire, reviewer textbook, note, or another relevant source. It continues to support PDF text extraction and embeddings.
- Direction: Extract recursive syllabus topics only from the selected subject’s source files. The reviewer edits and publishes topics into that existing subject; extraction never creates or merges subjects.
- Why: The official resolution has a deep numbered hierarchy that the program-wide proposal summarized inconsistently. Subject-first extraction narrows the input and review surface, preserves manual control of subjects, and makes source evidence belong to the subject it supports.
- Next action: Replace the program-file/curriculum-extraction implementation with the subject-scoped topic-extraction workflow described below.

<!--
### YYYY-MM-DD — Short title

- Context or observation:
- Decision or question:
- Next action:
-->

## Superseded implementation

- [x] Program-file uploads and program-wide curriculum extraction were implemented as an administrator-only, reviewer-controlled workflow.
- [x] Subject files already have independent upload, download, deletion, PDF text extraction, and embedding support.
- [x] The program-wide workflow is superseded by the subject-first direction below. Do not extend it or run its remaining operational-verification checklist.

## Implementation plan: subject-first sources and topic extraction

- [ ] Remove the `program_files` product surface and program-wide curriculum-extraction feature.
  - Remove its routes, controllers, models, jobs, services, agent, UI pages/components, Wayfinder output, factories, tests, configuration, and database tables after preserving or explicitly retiring existing extraction history.
  - Check: no application route, relation, queue job, migration dependency, or frontend import refers to `ProgramFile` or `CurriculumExtraction`.
- [ ] Keep `SubjectFile` as the only owned source-document model.
  - Retain upload, download, deletion, page text extraction, and embeddings. Present its supported use as source material, including curricula, questionnaires, reviewer textbooks, and notes.
  - Check: a manually created subject can upload and embed each supported source type without a program-file dependency.
- [ ] Design a subject-scoped topic-extraction record and workflow.
  - Snapshot selected subject-file IDs, titles, hashes, provider, model, prompt version, requester, proposal, review outcome, and lifecycle state. Cite `subject_file_id` and page number for each proposed topic.
  - Check: only files owned by the chosen subject are included and a changed or removed snapshot file prevents publication.
- [ ] Change the AI contract from subject generation to exhaustive topic extraction.
  - Require every source-supported numbered outline item, preserve official codes, derive parent relationships from the supplied hierarchy, and reject an incomplete or invalid hierarchy before review. Allow non-outline source files to yield relevant topics without invented official codes.
  - Check: the LECPA FAR source produces the `4.2` PPE branch through `4.2.5.1.1`–`4.2.5.1.3`, with correct parents and citations.
- [ ] Build the reviewer experience on the subject page.
  - The reviewer may edit, exclude, reorder, publish, or reject proposed topics. Publication writes only `syllabus_topics` to the existing subject in one transaction.
  - Check: manual subjects remain unchanged; excluded or rejected proposals create no topics.
- [ ] Define and run a source-set evaluation.
  - Include one official hierarchical curriculum, questionnaire, reviewer textbook, and notes. Measure hierarchy completeness, citation accuracy, duplicate handling, and reviewer workload.
  - Check: published topics are traceable to their subject files and match the accepted source-set thresholds.

## Known operational follow-ups

- [ ] Define the retention and migration policy for existing `program_files` and `curriculum_extractions` rows before their tables are removed.
- [ ] Decide how to handle a selected subject’s source set when its combined page text exceeds the extraction character limit.
  - Options to evaluate: choose an explicit subset of subject files, stage extraction per file while preserving citations, or use a bounded higher limit.
  - Check: the chosen approach has documented cost, latency, citation, and reviewer implications.
- [ ] Improve provider-failure diagnostics without exposing provider payloads or source text. Record a safe correlation identifier and retain the actionable failure category shown to administrators.
- [ ] Confirm provider terms, retention, and rights to process every pilot document; keep the extraction feature gated until this review is complete.

## Next product work

- [ ] Decide whether source versioning is required before supporting revisions to published subject topics.
- [ ] Design extraction of instructional concepts from approved subjects and syllabus topics. Concepts must remain separate from subject-source topic records and require independent reviewer approval.
- [ ] Plan source-grounded lesson drafts after concept extraction is established. See [12-E](12-e-grounded-lesson-drafts.md).
- [ ] Define retrieval, evaluation, governance, and rollout requirements for later learner-facing AI features. See [12-F](12-f-learner-tutor-practice-drafts.md) and [12-G](12-g-ai-governance-evaluation-rollout.md).

## Useful references

- [Superseded official curriculum extraction design](12-d-curriculum-extraction-subjects-concepts.md)
- [AI integration plan](12-laravel-ai-sdk-integration-plan.md)
- [Advanced AI features roadmap](12-h-advanced-ai-features-roadmap.md)
- [Executable development plan](08-executable-development-plan.md)
