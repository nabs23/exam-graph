# Subject Sources and Topic Extraction Handoff

## Purpose

Use this document as the ongoing handoff and working note for subject source files and topic extraction. Add current observations, decisions, blockers, and the next action in the section below when stopping or resuming work. Mark checklist items complete only after their acceptance checks have been performed in the target environment.

## Manual notes and immediate next actions

Add dated notes here. Keep the newest entry first.

### 2026-10-11 — Recheck subject-wide concept and topic-extraction plans

- 12-M subject-wide concept generation phases 1–3 and Phase 4 workflow checks are verified. Tests cover recursive snapshots through multiple nested levels and separate roots, no-file requests, per-item author approval, scope ownership, duplicate conflicts, and stale-topic rejection; the UI discloses LLM-knowledge generation without selected program files. Latest validation: 216 tests / 1,147 assertions and Pint pass. FAR (subject ID 1) is the development evaluation scope; the local database currently has 47 topics and 26 concepts across 12 topics, leaving 35 topics without concepts. Those concepts are only a partial set, not an author-reviewed reference fixture. A proposed metric rubric is recorded in 12-M but awaits product-owner/SME approval. The author-reviewed full-tree reference set and live-model quality evaluation remain open.
- Local development now enables `AI_CURRICULUM_GENERATION_ENABLED=true` with OpenAI model `gpt-6.1-sol` in the ignored `.env`; `CurriculumGenerationService::unavailableReason()` returns `null`. Config's default remains disabled. No concept-generation provider call has been made; wait for the reference set and rubric decision before the evaluation pilot.
- The approved PRC syllabus source is already present in the local database, and a live extraction record (`subject_topic_extractions.id=3`) records OpenAI `gpt-6-luna`, prompt `subject-topics-v1`, and the source hash `d11428585fc47e456fe5b63ab089f9799719735ff1f4aac2b243a50dac42048b`.
- The first 12-I live extraction (`id=3`) proposed the branch but published zero topics because existing `4.0` and `4.2` conflicts caused its descendants to be skipped. The review flow now supports an explicit exact-match selection and existing-topic parent anchors without changing existing topic rows.
- 12-I source-backed development acceptance is now complete in live record `id=5`: OpenAI `gpt-6-luna`, `subject-topics-v1`, file hash `d11428585fc47e456fe5b63ab089f9799719735ff1f4aac2b243a50dac42048b`; all seven target nodes were published or linked, with verified original PDF page 4 mappings (printed page 2). Reviewer corrections fixed the parent chain, matched existing IDs 114/116, added five rows (IDs 369–373), and skipped none. Details are recorded in [12-I](12-i-subject-topic-extraction-implementation-plan.md).
- Next: complete 12-M's author-reviewed reference set and evaluation-threshold decision before its Phase 4 pilot. The existing FAR concepts remain only a partial reference set for the full subject tree.

### 2026-10-11 — Approve initial development source set

- Decision: use the official PRC-hosted LECPA syllabus effective October 2022 as the sole required development acceptance source. The target is the seven-node FAR PPE branch on printed page 2, from `4.0` through `4.2.5.1.3`; the exact outline and acceptance rules are recorded in [12-I](12-i-subject-topic-extraction-implementation-plan.md).
- Scope: no supporting questionnaire, reviewer textbook, or notes are required to complete this feature. The existing “Main Reviewer” Hospitality PDF is excluded as irrelevant to FAR. “Accounting Succinctly” is deferred until it belongs to the selected FAR subject and has usable page text.
- Decision: source-rights/provider-terms, retention, budget, and broader compliance review are production-readiness work for later. They do not block development completion. Keep production default-off; local/test may explicitly enable the feature.
- The approved-source development acceptance was completed in record `subject_topic_extractions.id=5`, recorded above. The earlier failed zero-topic result (`id=3`) remains in the database as its original audit history.

### 2026-10-11 — Verify extraction-route fix and identify pilot-source candidate

- Fixed the subject topic-extraction pages' missing `route_parameters` props used by contextual subject breadcrumbs and back links. The focused feature suite passes (7 tests, 61 assertions), TypeScript passes, Pint passes, and PHP syntax validation passes.
- Read-only target database review found two subject files and no subject-topic-extraction records. “Main Reviewer” has completed PDF page text, but its processing rights are undocumented. “Test: Accounting Succinctly” has no content hash and is not embedding-complete, so it is not currently eligible. The extraction feature flag is false.
- Located the PRC-hosted LECPA syllabus PDF and drafted the FAR PPE outline on printed page 2. At the time of this note, source selection and processing-rights decisions were still open; the newer decision above supersedes that status. No source was uploaded to the app or sent to an AI provider.
- Next action at the time: confirm the approved pilot source. This has since been decided as recorded above. Program-file and program-wide extraction retention decisions remain separate from this plan.

### 2026-10-11 — Implement subject topic extraction alongside existing workflows

- Implemented the subject-scoped topic extraction record, structured agent, queued workflow, nested routes, subject tab, review/publish UI, source-page mappings, and regression coverage. PostgreSQL 18.4 with pgvector is verified and the new migrations are applied.
- Kept program-wide extraction and program-file management interfaces active and separate from this subject-scoped feature. Their agents, services, jobs, requests, routes, UI, records, and storage remain outside the 12-I scope.
- Validation: full test suite passed (104 tests, 533 assertions), TypeScript check passed, and Pint passed.
- Next action at the time: hold rollout pending source, evaluation, and compliance decisions. This is superseded by the newer development-source approval above: compliance and expanded evaluation are deferred; only the approved-source end-to-end development acceptance remains.

### 2026-10-03 — Add subject-scoped sources for topic extraction

- Decision: Administrators create program subjects manually. Existing program-file uploads and program-wide curriculum extraction remain supported.
- Decision: Use `SubjectFile` for the separate subject topic-extraction workflow. A subject file may be an official curriculum, questionnaire, reviewer textbook, note, or another relevant source. It continues to support PDF text extraction and embeddings.
- Direction: Extract recursive syllabus topics only from the selected subject’s source files. The reviewer edits and publishes topics into that existing subject; extraction never creates or merges subjects.
- Why: The official resolution has a deep numbered hierarchy that benefits from a focused subject-scoped extraction path. That path preserves manual control of subjects and ties source evidence to the subject it supports, while the existing program-wide workflow remains available independently.
- Follow-up: The implementation in [12-I — Subject Topic Extraction Implementation Plan](12-i-subject-topic-extraction-implementation-plan.md) has superseded this immediate action. Continue to preserve the direction and safeguards in [12-D](12-d-curriculum-extraction-subjects-concepts.md).

<!--
### YYYY-MM-DD — Short title

- Context or observation:
- Decision or question:
- Next action:
-->

## Existing program-wide implementation (out of scope for 12-I)

- [x] Program-file uploads and program-wide curriculum extraction were implemented as an administrator-only, reviewer-controlled workflow.
- [x] Subject files already have independent upload, download, deletion, PDF text extraction, and embedding support.
- [x] The program-wide workflow and program-file interfaces remain supported. Subject topic extraction is separate and does not replace or modify them.

## Implementation plan: subject-first sources and topic extraction

Use [12-I — Subject Topic Extraction Implementation Plan](12-i-subject-topic-extraction-implementation-plan.md) for the phased implementation sequence, including the subject-page tab, dedicated nested route, new Laravel AI agent, and durable topic-to-source-page mappings. Program-wide extraction and program-file operations remain separate and out of scope. The checklist below records the product direction and remains useful as a high-level completion summary.

- [x] Keep `SubjectFile` as the source-document model used by subject topic extraction.
  - Retain upload, download, deletion, page text extraction, and embeddings. Present its supported use as source material, including curricula, questionnaires, reviewer textbooks, and notes.
  - Check: topic extraction reads only the selected subject-file page text; existing program-file behavior remains outside this workflow.
- [x] Design and implement a subject-scoped topic-extraction record and workflow.
  - Snapshot selected subject-file IDs, titles, hashes, provider, model, prompt version, requester, proposal, review outcome, and lifecycle state. Cite `subject_file_id` and page number for each proposed topic.
  - Check: only files owned by the chosen subject are included and a changed or removed snapshot file prevents publication.
- [x] Change the AI contract from subject generation to exhaustive topic extraction.
  - Require every source-supported numbered outline item, preserve official codes, derive parent relationships from the supplied hierarchy, and reject an incomplete or invalid hierarchy before review. Allow non-outline source files to yield relevant topics without invented official codes.
  - Check: [ ] the approved LECPA FAR source produces the `4.2` PPE branch through `4.2.5.1.1`–`4.2.5.1.3`, with correct parents and citations after reviewer correction. The source and reviewer gold outline are approved; only the end-to-end run remains.
- [x] Build the reviewer experience on the subject page.
  - The reviewer may edit, exclude, reorder, publish, or reject proposed topics. Publication writes only `syllabus_topics` to the existing subject in one transaction.
  - Check: manual subjects remain unchanged; excluded or rejected proposals create no topics.
- [x] Define the initial development source set and acceptance target.
  - Use one official hierarchical curriculum: the approved PRC LECPA syllabus PDF. Questionnaires, reviewer textbooks, and notes are optional later coverage.
  - Check: the seven-node branch, exact codes/titles/parents/order, and printed-page citations are specified in 12-I. Record reviewer corrections and workload during the remaining end-to-end run; no arbitrary model-quality threshold blocks the first feature release.

## Known operational follow-ups

- [ ] Define retention and migration for `program_files` and `curriculum_extractions` only if separately requested; this is outside the subject-topic-extraction plan.
- [x] Define behavior when selected subject page text exceeds the configured extraction character limit: reject before dispatch and prompt reviewers to select fewer files.
- [x] Record safe correlation identifiers and actionable failure categories without exposing provider payloads or source text.
- [ ] Deferred to production readiness: review provider terms, retention, and rights for source categories enabled in production. This is not a development feature blocker; keep the production default disabled until the review is complete.

## Next product work

- [ ] Decide whether source versioning is required before supporting revisions to published subject topics.
- [x] Design extraction of instructional concepts from approved subject topics. Concepts remain separate from syllabus-topic records and require independent author approval; see [12-M](12-m-topic-scoped-concept-extraction-development-plan.md).
- [x] Implement phases 1–3 of [12-M — Subject-Wide Instructional Concept Extraction](12-m-topic-scoped-concept-extraction-development-plan.md); its reference set, evaluation, and rollout gates remain open.
- [ ] Plan source-grounded lesson drafts after concept extraction is established. See [12-E](12-e-grounded-lesson-drafts.md).
- [ ] Define retrieval, evaluation, governance, and rollout requirements for later learner-facing AI features. See [12-F](12-f-learner-tutor-practice-drafts.md) and [12-G](12-g-ai-governance-evaluation-rollout.md).

## Useful references

- [Current subject-scoped topic-extraction design](12-d-curriculum-extraction-subjects-concepts.md)
- [AI integration plan](12-laravel-ai-sdk-integration-plan.md)
- [Advanced AI features roadmap](12-h-advanced-ai-features-roadmap.md)
- [Executable development plan](08-executable-development-plan.md)
