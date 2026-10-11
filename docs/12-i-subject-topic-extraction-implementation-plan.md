# 12-I — Subject Topic Extraction Implementation Plan

## Status and relationship to 12-D

**Status: core subject topic-extraction implementation is complete; source-backed development acceptance remains. Production compliance work is deferred.** This document turns the product requirements in [12-D — Subject-Scoped Syllabus Topic Extraction](12-d-curriculum-extraction-subjects-concepts.md) into an implementation sequence. 12-D remains the normative scope for what subject topic extraction may propose and publish. This plan is limited to that feature. The existing program-wide extraction workflow and program-file management interfaces remain supported and are outside this plan. Development acceptance uses the approved source and criteria below; provider/legal compliance review is a later production-readiness task, not a feature-completion gate.

Subject topic extraction is an independent workflow. It does not replace or modify program-wide curriculum extraction or program-file management.

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
- For initial development acceptance, use the official PRC-hosted LECPA syllabus PDF effective October 2022 as the sole required source. Supporting questionnaires, textbooks, and notes are optional follow-up coverage, not blockers to feature completion.

## Target application design

### Records and provenance

1. Add a subject-scoped extraction record, provisionally `SubjectTopicExtraction`, with `subject_id`, requester/reviewer IDs, lifecycle status, source snapshot and hash, provider, model, prompt/schema version, proposal, reviewed proposal, safe error category, correlation ID, and review timestamps.
2. Snapshot each selected file's ID, title, content hash, and other non-secret identity needed for review. The ordered page text is resolved by the selected file IDs and matching hashes in `file_page_embeddings`; do not silently include files uploaded after the request.
3. Add a durable topic-source mapping table for published `SyllabusTopic` citations. Support multiple file/page citations per topic, store the cited file's snapshot hash/title and original page number, and retain citation metadata if the source file is later deleted.
4. Add model relationships and deletion behavior for subject extraction records and topic-source mappings. Define how subject deletion and source-file deletion behave. Existing program-wide extraction records and program-file records remain managed by their existing workflows and are outside this plan.

### Extraction pipeline

1. Add a focused subject topic extraction service and queued job. The request validates that the subject belongs to the route's program, every selected file belongs to the subject, files are complete and eligible, page text exists, and the selected source set is within explicit bounds.
2. Lock or otherwise consistently snapshot the subject and selected file versions. Prevent duplicate in-flight requests for the same subject, source hash, provider, model, and prompt version.
3. Build bounded input from the matching page-text rows and call the new `SubjectTopicExtractor` agent using the configured Laravel AI SDK provider/model. Keep the prompt/schema versioned.
4. Validate structured output before review: bounds, unique and valid codes as applicable, complete hierarchy, valid parent references and order, and every file/page citation against the source snapshot. Record validation status and reviewer-facing notes with the proposal. A failed automatic check must not hide the generated result from reviewers; publication still blocks invalid hierarchy and citations until corrected.
5. Keep provider interactions out of controllers. Queue work after commit, support safe retry/polling where the selected provider requires it, and record only actionable safe failure categories and correlation IDs.

### Review and publication

- Give reviewers a dedicated extraction detail view with source file/page citations and excerpts resolved from matching page-text rows. Reviewers can edit titles/codes, exclude topics, reorder nodes, change parentage, publish selected topics, or reject the proposal.
- Before publication, verify that the subject still exists, all source snapshot files and hashes are unchanged, the reviewed hierarchy is valid, and topic codes do not conflict with the subject's existing topics.
- Write selected `syllabus_topics` and their durable source-page mapping rows in a single database transaction. Set `parent_id` and `sort_order` from the final reviewed hierarchy. Excluded or rejected proposals create no topic rows.
- Preserve existing manually entered topics. At publication, skip and report only the conflicting proposal topic; continue with other valid selected topics. Also skip descendants whose proposed parent was skipped. Never overwrite, merge, or silently deduplicate existing topics.

### Routes and UI

- Add routes inside the authenticated `manage-content` group and nested `programs/{program}` scoped-binding group. The requested index route is `GET /programs/{program}/subjects/{subject}/topics-extraction`; add a throttled `POST` request action plus nested extraction detail, publish, reject, and permitted delete/retry actions as appropriate.
- Ensure nested binding proves the subject belongs to the route's program. Apply the existing content-management authorization boundary to every action.
- Generate typed Wayfinder helpers and use them for all client links and form actions.
- Add a **Topic extraction** tab to `resources/js/pages/admin/subjects/show.tsx`. Extract its launcher/history UI into a dedicated reusable component, provisionally `SubjectTopicExtractionPanel`. The tab gives access to source-file selection and recent extraction status, with a Wayfinder link to the dedicated extraction route.
- Add a dedicated Inertia page for the route. It hosts the full extraction request/history/review workflow and uses generic resource breadcrumbs consistent with other admin pages. Keep reusable panel and detail components under `resources/js/components`; keep route-level title and breadcrumbs in the page module.
- Show only files owned by the current subject and eligible for extraction. Display file title, type, upload/embedding readiness, and page count; never show storage keys or signed URLs.

## Delivery phases and checks

### Phase 0 — Confirm source and retention contracts

- [x] First release supports uploaded PDFs with completed page text and embedding status.
- [x] Bounds are configurable: 10 files, 300 pages, and 250,000 characters by default; over-limit requests are rejected before dispatch. The approved 68-page LECPA source has 195,074 extracted characters and therefore fits as one source while preserving original page numbers.
- [x] Page text currently comes from `file_page_embeddings`, so completed embedding status and matching content hashes are required. Separating text extraction from vector generation is deferred.
- [x] Existing code/title conflicts block publication until the reviewer edits or excludes the colliding proposal. Source-file deletion nulls the live FK while preserving source ID, title, hash, and page.
- [x] Keep the existing program-wide extraction and program-file workflows outside this feature; do not retire or alter their interfaces, records, tables, or source objects as part of subject topic extraction.
- [x] The configured target database is PostgreSQL 18.4 with pgvector enabled. Both new migrations are applied there. Keep the production default disabled; local and test environments may enable the feature explicitly for development acceptance.
- [x] Approve the PRC-hosted 2022 LECPA syllabus PDF as the initial development evaluation source. Use it for the selected FAR subject; do not use the current “Main Reviewer” upload because its filename identifies a Hospitality Management document, and defer “Accounting Succinctly” because it belongs to another subject and has no page text yet.

### Phase 1 — Subject-scoped persistence and source mapping

- [x] Add migrations, models, relationships, factories, and enums/status values for extraction records and durable topic citations.
- [x] Add snapshot and ownership checks for selected subject files and page text.
- [x] Define delete/retention behavior for subject files, topic mappings, subject extractions, and subjects. Topic provenance survives subject-file deletion; subjects with topic-extraction history cannot be deleted. Program-wide extraction and program-file retention behavior is outside this plan.
- [x] Check: tests trace selected pages through subject, file, content hash, and original page number; cross-subject and stale data is rejected.

### Phase 2 — Agent, queue, and validation

- [x] Add `SubjectTopicExtractor`, versioned structured schema and prompt, extraction service, job, configuration, and bounded input builder.
- [x] Add hierarchy and citation validation, safe failures, retry/polling behavior, and duplicate in-flight protection. Validation findings accompany proposals for review rather than blocking access to the result.
- [x] Add focused tests using Laravel AI SDK fakes; provider tests do not make network calls.
- [x] Check: unsupported or incomplete source sets fail safely and every accepted citation resolves to the selected snapshot.

### Phase 3 — Scoped routes and reviewer UI

- [x] Add nested, scoped routes and controller actions for listing/requesting, viewing, publishing, rejecting, and allowed retry/delete operations.
- [x] Generate and use Wayfinder helpers.
- [x] Add the subject show tab and `SubjectTopicExtractionPanel` component.
- [x] Add a dedicated route page and reviewer detail UI with source page evidence, edit/exclude/reorder controls, and publish/reject actions.
- [x] Check: nested program/subject scope and selected-file ownership are covered by feature tests; all routes remain in the content-management authorization group.

### Phase 4 — Atomic publication and subject-workflow isolation

- [x] Publish reviewed topics and source mappings atomically after revalidating the subject, source snapshot, hierarchy, and code collisions.
- [x] Verify rejection, exclusion, changed/deleted sources, and transaction failures leave no partial topic tree.
- [x] Keep publication inside the subject-scoped service and transaction. Publish only reviewer-selected syllabus topics and their topic-source mappings.
- [x] Check: subject manual data is preserved; code/title collisions skip only the conflicting proposal topic, dependent descendants are reported, and unrelated selected topics publish with their source-page provenance.
- [x] Check: subject extraction routes and file selection operate only on the selected subject. Program-wide extraction and program-file interfaces remain outside the subject workflow and are not retired by this plan.

### Phase 5 — Development acceptance

- [x] Select the official PRC-hosted LECPA syllabus PDF effective October 2022 as the initial development source. Its printed page 2 contains the target FAR PPE branch.
- [x] Set the acceptance scope to that branch; questionnaires, reviewer textbooks, and notes are optional follow-up coverage and do not block feature completion.
- [x] Define a reviewer-correctable acceptance criterion: after review, the published branch must match the codes, titles, parentage, sibling order, and printed-page citations below. The AI draft may need edits; record corrections and review time without blocking the initial feature release on an arbitrary percentage or time target.
- [ ] Run the approved source through the full request, review, and publication flow and record its source hash, model/prompt version, reviewer edits, and final page mappings. This is the only remaining feature-development acceptance item; source selection and the gold outline are approved below.
- [x] Confirm duplicate/collision, ownership, stale-source, and atomic-publication failure handling through the feature suite.

#### Approved initial source and gold outline

Source: [official PRC LECPA syllabus PDF](https://www.prc.gov.ph/sites/default/files/2022-30%20Annexes%20Syllabi%20Effective%20October%202022.pdf), effective October 2022. The accepted development slice is on printed page 2:

```text
4.0 Non-financial Assets
└── 4.2 Property, plant, and equipment
    └── 4.2.5 Subsequent measurement
        └── 4.2.5.1 Cost method
            ├── 4.2.5.1.1 Depreciation
            ├── 4.2.5.1.2 Depreciation methods
            └── 4.2.5.1.3 Changes in useful life and depreciation methods
```

Initial acceptance requires all seven nodes, exact official codes, the hierarchy and sibling order shown above, and a valid citation to printed page 2 for each node. The reviewer may edit the draft before publishing. No further model-quality threshold is required for the first development release; log corrections, citation adjustments, and review time to inform later evaluation.

### Deferred production-compliance work

- [ ] Before production rollout, review provider terms, source rights, data retention, budget, and failure diagnostics for every source category enabled in production.
- [ ] Add representative questionnaires, reviewer textbooks, and notes to a later evaluation set when suitable files are available in the same subject and have usable page text.
- [x] Keep the environment default disabled; local and test environments may enable the feature explicitly for development acceptance.

## Acceptance criteria

- The topic-extraction tab on a subject page and the dedicated nested route both operate on that same subject; the route cannot cross program/subject ownership boundaries.
- Administrators choose one or more eligible files owned by the subject. Each request records an immutable file/hash snapshot; later file changes block publication.
- Every proposal topic cites one or more selected original source pages, and every published topic keeps durable file/page provenance.
- Official numbered outlines are checked for completeness, order, parentage, codes, and citations. Failed checks appear as notes on the review result; reviewers can inspect and correct the proposal, while publication still blocks invalid hierarchy or citations.
- Reviewers can edit, exclude, reorder, publish, or reject. Publication writes only reviewer-selected topic rows and citation mappings in one transaction.
- No subject, concept, concept mapping, prerequisite, lesson, question, or learner-facing output is generated by this workflow.
- Provider failures, invalid citations, duplicate requests, source changes, code conflicts, and unsupported inputs fail safely without exposing source text, storage credentials, or provider payloads.
- The initial development acceptance branch matches the approved official outline and page citations after reviewer edits. Production compliance and expanded source coverage are tracked separately under deferred work.

## Out of scope

Subject generation or merging, concept generation or mapping, prerequisite generation, lessons, questions, learner tutoring, semantic retrieval, OCR, automatic publication, source-document version management, and official syllabus version management. These require separate product decisions and evaluation plans.

## Related documents

- [12-D — Subject-scoped syllabus topic extraction](12-d-curriculum-extraction-subjects-concepts.md) — normative feature behavior and safeguards.
- [12-B — Program and subject file embedding inputs](12-b-source-documents-content-mapping.md) — current supported page-text and file/page identity foundation.
- [12-G — Embedding MVP governance and rollout](12-g-ai-governance-evaluation-rollout.md) — embedding MVP gates; topic extraction also needs its own evaluation above.
- [Subject sources and topic extraction handoff](next-tasks-handoff.md) — current application direction and operational follow-ups.
