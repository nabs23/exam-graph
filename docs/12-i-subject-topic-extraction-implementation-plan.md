# 12-I — Subject Topic Extraction Implementation Plan

## Status and relationship to 12-D

**Status: planned; implementation has not started.** This document turns the product and safety requirements in [12-D — Subject-Scoped Syllabus Topic Extraction](12-d-curriculum-extraction-subjects-concepts.md) into an implementation sequence. 12-D remains the normative scope for what extraction may propose and publish. This plan covers the application work needed to deliver it.

The current application still contains the earlier program-wide curriculum extraction flow. It proposes subjects and topics from program files. That workflow is superseded by 12-D and must not be extended as the implementation of subject topic extraction.

## Goal

Let a content administrator select completed source files belonging to one existing subject, request a structured topic hierarchy with verifiable source-page citations, review and edit that proposal, and publish selected topics into that subject. Provide both an extraction tab on the subject page and a dedicated route at:

```text
/programs/{program}/subjects/{subject}/topics-extraction
```

Use the Laravel AI SDK through a new subject-specific structured-output agent. Keep publication explicit, atomic, and reviewer-controlled.

## Product and data decisions

- Program subjects continue to be created and maintained manually. Extraction never creates, merges, renames, or edits a subject.
- Each extraction is scoped to one subject and an explicit selection of that subject's eligible source files. It never reads program files or files from another subject.
- Reuse the AI SDK, queueing, status, provider configuration, polling, and safe-failure patterns where useful. Do not reuse the current program-scoped extraction record, source selection, proposal schema, or publication behavior as-is.
- Add a dedicated `SubjectTopicExtractor` Laravel AI agent with structured output. Its contract is exhaustive extraction of source-supported topics, not open-ended curriculum research. The agent must preserve official codes and hierarchy and must not generate concepts, lessons, objectives, questions, or subject records.
- A topic proposal can cite one or more pairs of selected `subject_file_id` and original page number. Citations must resolve to pages in the extraction's immutable source snapshot.
- Preserve topic citations after publication in a durable topic-to-source-page mapping. Do not leave published provenance only in extraction JSON. A mapping row should include the topic, source file reference, source hash, title snapshot, and page number. Permit a deleted source file reference to become null while retaining the snapshot metadata, so file deletion does not silently erase the historical citation. Do not copy whole source pages into the mapping table.
- Reuse `file_page_embeddings` as the current page-text and file/page identity store: read only rows whose `subject_file_id`, `page_number`, and `source_hash` match the selected file snapshot. The embedding vector is not extraction input. Initially require selected files to have completed, usable page text; decide during implementation whether page-text extraction should be decoupled from vector generation so topic extraction does not need vectors.
- Keep source files private. Send bounded extracted page text to the configured provider, never storage keys, signed URLs, or source bytes.

## Target application design

### Records and provenance

1. Add a subject-scoped extraction record, provisionally `SubjectTopicExtraction`, with `subject_id`, requester/reviewer IDs, lifecycle status, source snapshot and hash, provider, model, prompt/schema version, proposal, reviewed proposal, safe error category, correlation ID, and review timestamps.
2. Snapshot each selected file's ID, title, content hash, and other non-secret identity needed for review. The ordered page text is resolved by the selected file IDs and matching hashes in `file_page_embeddings`; do not silently include files uploaded after the request.
3. Add a durable topic-source mapping table for published `SyllabusTopic` citations. Support multiple file/page citations per topic, store the cited file's snapshot hash/title and original page number, and retain citation metadata if the source file is later deleted.
4. Add model relationships and deletion behavior for extraction records and topic-source mappings. Define how subject deletion, source file deletion, and existing program extraction history are handled before dropping legacy tables or models.

### Extraction pipeline

1. Add a focused subject topic extraction service and queued job. The request validates that the subject belongs to the route's program, every selected file belongs to the subject, files are complete and eligible, page text exists, and the selected source set is within explicit bounds.
2. Lock or otherwise consistently snapshot the subject and selected file versions. Prevent duplicate in-flight requests for the same subject, source hash, provider, model, and prompt version.
3. Build bounded input from the matching page-text rows and call the new `SubjectTopicExtractor` agent using the configured Laravel AI SDK provider/model. Keep the prompt/schema versioned.
4. Validate structured output before review: bounds, unique and valid codes as applicable, complete hierarchy, valid parent references and order, and every file/page citation against the source snapshot. Reject malformed or incomplete official outlines rather than presenting or publishing a partial tree.
5. Keep provider interactions out of controllers. Queue work after commit, support safe retry/polling where the selected provider requires it, and record only actionable safe failure categories and correlation IDs.

### Review and publication

- Give reviewers a dedicated extraction detail view with source file/page citations and excerpts resolved from matching page-text rows. Reviewers can edit titles/codes, exclude topics, reorder nodes, change parentage, publish selected topics, or reject the proposal.
- Before publication, verify that the subject still exists, all source snapshot files and hashes are unchanged, the reviewed hierarchy is valid, and topic codes do not conflict with the subject's existing topics.
- Write selected `syllabus_topics` and their durable source-page mapping rows in a single database transaction. Set `parent_id` and `sort_order` from the final reviewed hierarchy. Excluded or rejected proposals create no topic rows.
- Define conflict behavior for existing manually entered topics before implementation. Do not overwrite, merge, or silently deduplicate them. Publication should require an explicit reviewer resolution for collisions.

### Routes and UI

- Add routes inside the authenticated `manage-content` group and nested `programs/{program}` scoped-binding group. The requested index route is `GET /programs/{program}/subjects/{subject}/topics-extraction`; add a throttled `POST` request action plus nested extraction detail, publish, reject, and permitted delete/retry actions as appropriate.
- Ensure nested binding proves the subject belongs to the route's program. Apply the existing content-management authorization boundary to every action.
- Generate typed Wayfinder helpers and use them for all client links and form actions.
- Add a **Topic extraction** tab to `resources/js/pages/admin/subjects/show.tsx`. Extract its launcher/history UI into a dedicated reusable component, provisionally `SubjectTopicExtractionPanel`. The tab gives access to source-file selection and recent extraction status, with a Wayfinder link to the dedicated extraction route.
- Add a dedicated Inertia page for the route. It hosts the full extraction request/history/review workflow and uses generic resource breadcrumbs consistent with other admin pages. Keep reusable panel and detail components under `resources/js/components`; keep route-level title and breadcrumbs in the page module.
- Show only files owned by the current subject and eligible for extraction. Display file title, type, upload/embedding readiness, and page count; never show storage keys or signed URLs.

## Delivery phases and checks

### Phase 0 — Confirm source and retention contracts

- [ ] Confirm the selected file types and completion states supported by the first release.
- [ ] Choose the maximum selected files, total pages, and total characters; define safe behavior when limits are exceeded.
- [ ] Decide whether usable page text must currently have a completed vector, or whether text extraction will be separated from embedding status.
- [ ] Define existing-topic conflict handling and source-file deletion behavior for durable citations.
- [ ] Define retention, export, or retirement for existing `program_files` and `curriculum_extractions` data before destructive schema cleanup.
- [ ] Verify that the target database is PostgreSQL with pgvector enabled, since the current page-text records live in the pgvector-backed table. Record the target-environment check before enabling the feature.

### Phase 1 — Subject-scoped persistence and source mapping

- [ ] Add migrations, models, relationships, factories, and enums/status values for extraction records and durable topic citations.
- [ ] Add snapshot and ownership checks for selected subject files and page text.
- [ ] Define explicit delete/retention behavior for files, mappings, extractions, and subjects.
- [ ] Check: a selected page can be traced from a subject, through its source file and content hash, to its original page number; tampered, missing, cross-subject, or stale data is rejected.

### Phase 2 — Agent, queue, and validation

- [ ] Add `SubjectTopicExtractor`, versioned structured schema and prompt, extraction service, job, configuration, and bounded input builder.
- [ ] Add exhaustive hierarchy and citation validation, idempotency, safe failures, retry/polling behavior, and duplicate in-flight protection.
- [ ] Add focused tests using Laravel AI SDK fakes; provider tests must not make network calls.
- [ ] Check: unsupported or incomplete source sets fail safely, malformed structures never enter review, and every accepted citation resolves to the selected snapshot.

### Phase 3 — Scoped routes and reviewer UI

- [ ] Add nested, scoped routes and controller actions for listing/requesting, viewing, publishing, rejecting, and allowed retry/delete operations.
- [ ] Generate and use Wayfinder helpers.
- [ ] Add the subject show tab and `SubjectTopicExtractionPanel` component.
- [ ] Add a dedicated route page and reviewer detail UI with source page evidence, edit/exclude/reorder controls, and publish/reject actions.
- [ ] Check: a subject from another program, a file from another subject, and an unauthorized user cannot access or mutate the extraction.

### Phase 4 — Atomic publication and legacy retirement

- [ ] Publish reviewed topics and source mappings atomically after revalidating the subject, source snapshot, hierarchy, and code collisions.
- [ ] Verify rejection, exclusion, changed/deleted sources, and transaction failures leave no partial topic tree.
- [ ] Retire program-wide extraction routes, UI, agent, job, service, config, and tests after the approved history-retention/migration path is implemented.
- [ ] Remove or migrate program-file functionality only as directed by the current handoff and after confirming no remaining application references or data-retention dependency.
- [ ] Check: subject manual data is preserved; no proposal silently overwrites existing topics; every published topic retains its file/page provenance.

### Phase 5 — Evaluation and gated rollout

- [ ] Evaluate a rights-cleared official hierarchical curriculum, questionnaire, reviewer textbook, and notes.
- [ ] Measure hierarchy completeness, citation precision, duplicate/collision handling, page coverage, reviewer corrections, and time-to-review. Agree thresholds before the pilot.
- [ ] For the LECPA FAR official source, verify the `4.2` PPE branch through `4.2.5.1.1`–`4.2.5.1.3`, including codes, parentage, order, and page mappings.
- [ ] Confirm provider processing rights, provider terms, retention, budget, and failure diagnostics for the selected pilot sources.
- [ ] Keep the feature disabled until source rights, target PostgreSQL/pgvector, evaluation, and reviewer pilot gates are passed.

## Acceptance criteria

- The topic-extraction tab on a subject page and the dedicated nested route both operate on that same subject; the route cannot cross program/subject ownership boundaries.
- Administrators choose one or more eligible files owned by the subject. Each request records an immutable file/hash snapshot; later file changes block publication.
- Every proposal topic cites one or more selected original source pages, and every published topic keeps durable file/page provenance.
- Official numbered outlines are complete, ordered, correctly parented, and retain their official codes. Invalid or incomplete outlines cannot be reviewed or published.
- Reviewers can edit, exclude, reorder, publish, or reject. Publication writes only reviewer-selected topic rows and citation mappings in one transaction.
- No subject, concept, concept mapping, prerequisite, lesson, question, or learner-facing output is generated by this workflow.
- Provider failures, invalid citations, duplicate requests, source changes, code conflicts, and unsupported inputs fail safely without exposing source text, storage credentials, or provider payloads.
- The pilot passes agreed source coverage, citation, hierarchy, reviewer-workload, privacy, and target-environment gates before enablement.

## Out of scope

Subject generation or merging, concept generation or mapping, prerequisite generation, lessons, questions, learner tutoring, semantic retrieval, OCR, automatic publication, source-document version management, and official syllabus version management. These require separate product decisions and evaluation plans.

## Related documents

- [12-D — Subject-scoped syllabus topic extraction](12-d-curriculum-extraction-subjects-concepts.md) — normative feature behavior and safeguards.
- [12-B — Program and subject file embedding inputs](12-b-source-documents-content-mapping.md) — current supported page-text and file/page identity foundation.
- [12-G — Embedding MVP governance and rollout](12-g-ai-governance-evaluation-rollout.md) — embedding MVP gates; topic extraction also needs its own evaluation above.
- [Subject sources and topic extraction handoff](next-tasks-handoff.md) — current application direction and operational follow-ups.
