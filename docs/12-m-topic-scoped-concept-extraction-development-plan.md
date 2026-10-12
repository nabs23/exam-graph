# 12-M — Subject-Wide Instructional Concept Extraction Development Plan

## Status and relationship to existing plans

**Status: Current development complete; Phases 1–3 implemented and verified. Evaluation and rollout gates are deferred for a future decision.** This plan defines how to use an LLM to propose author-approved instructional concepts across all topics in one existing subject. It builds on the review workflow in [12-K](12-k-program-source-library-and-reviewed-curriculum-generation-plan.md), while keeping the syllabus-topic extraction boundary in [12-D](12-d-curriculum-extraction-subjects-concepts.md): topics describe curriculum coverage; concepts describe teachable ideas within that coverage.

The first delivery uses the existing `CurriculumGeneration` workflow with `kind=concepts` and one selected `subject_id`. It does not create a second extraction record or an independent publishing path. The subject's complete syllabus-topic tree, including every nested descendant, is the scope context. Generation relies on the LLM's knowledge and the supplied topic codes, titles, descriptions, and hierarchy; program files are not required or selected as generation sources. This is an explicitly ungrounded-by-program-files draft and must be disclosed as such. No RAG or file retrieval is required.

## Goal

Help a content author create a useful, non-duplicative set of instructional concepts for one existing subject, covering its full recursive syllabus-topic hierarchy. The author reviews each draft against its assigned topic and the subject's topic tree, edits or rejects candidates, and explicitly approves only the concepts that belong under that topic.

## Product decisions

- **Subject is the scope anchor:** Start from one canonical `Subject`. Include every `SyllabusTopic` in that subject recursively, preserving parent-child relationships, codes, titles, descriptions, and deterministic ordering. Do not include topics from another subject.
- **Concepts remain distinct and mapped:** A syllabus topic is not a concept. Keep official or author-approved topic wording and hierarchy intact; create separate `Concept` records with the subject's ID and `syllabus_topic_id` set to the specific existing topic each concept teaches.
- **LLM-only initial evidence:** Do not require or select `ProgramFile` records. Generate from the complete subject topic tree and the LLM's learned knowledge. Clearly label proposals as not grounded in selected program files; do not present citations or source verification that did not occur.
- **Concept codes are app-level:** Do not reuse or invent official syllabus codes for concepts. Any proposed concept code is an app-local identifier that the author can edit; it must be unique within the subject and must not imply Board authority.
- **Bounded, atomic proposals:** Propose concepts that represent one teachable idea. Each has a stable draft ID, title, concise description/definition, app-local code, a reference to the existing topic it belongs under, and review notes. Learning objectives, prerequisite edges, lessons, and questions are separate future work.
- **Review is per item:** Keep proposals private. Authors may edit, reorder, approve, or reject each item. Only explicit approval creates a canonical concept. A duplicate or questionable candidate must not block unrelated approved candidates.
- **No fabricated citations:** The initial workflow has no selected-file citation contract. Ignore or reject model-generated source citations; do not store them as verified provenance.
- **No correctness claims:** Show the subject and complete topic tree during review. Approval means an author accepted the concept into this curriculum; it does not certify official coverage, factual correctness, or PRC compliance.

## Data and request contract

Reuse `curriculum_generations` for request, status, provider/model, prompt/schema version, scope snapshot, proposal, reviewer edits, decisions, and timestamps. Add a `scope_snapshot` JSON value for concept requests containing the selected subject ID and an ordered recursive snapshot of every topic's ID, parent ID, code, title, description, and position. Include this snapshot in the request hash. If a topic is added, removed, moved, or edited before review approval, mark the proposal stale and require regeneration or an explicit re-review against the updated tree.

Do not snapshot program files or accept model-supplied citations as evidence in this delivery. Keep provider/model, prompt/schema version, and the exact subject/topic context snapshot for auditability; do not log secrets or credentials.

The initial structured-output contract is:

```text
items[]:
  draft_id
  title
  code                    # app-local; author-editable, never an official syllabus code
  description             # concise teachable definition/scope
  syllabus_topic_id       # must resolve to a topic in the subject snapshot
  parent_draft_id: null   # concepts are flat in this delivery
```

Application validation owns item limits, unique draft IDs, field bounds, topic membership, duplicate checks, and canonical ID assignment. The model may reference only existing topic IDs included in the scope snapshot; it must not return concept database IDs, create prerequisites, or publish records.

## Author workflow

1. From a subject, the author chooses **Draft concepts**. The subject and recursive topic tree are the fixed request scope and visible before submission.
2. The application validates the subject's program ownership, loads every topic recursively, and snapshots the full tree before queueing. No program files are required.
3. A queued structured generation receives the complete topic tree and asks the LLM for concise, atomic concepts distributed across its topics. It must not alter topics or claim exhaustive or officially verified coverage.
4. The review page shows each proposed concept under its assigned topic, the full topic context, and a clear disclosure that generation used LLM knowledge without selected program-file grounding. The author edits, reorders, approves, or rejects individual candidates.
5. Before approval, the application rechecks authorization, proposal hash, subject/topic-tree snapshot, subject ownership, and duplicate conflicts. It writes only approved concepts, each linked to an existing topic in the selected subject, and records the decision transactionally.
6. The result page labels approved items **Author approved** and preserves rejected and pending decisions in the generation record.

## Delivery phases and acceptance checks

### Phase 0 — Scope and evaluation fixture

- [x] Use the existing development subject Financial Accounting and Reporting (FAR; subject ID 1) and its complete current topic tree as the first evaluation scope.
- [ ] Prepare an author-reviewed reference set of instructional concepts mapped across that subject's topics, including boundary cases and likely duplicates.
- [ ] Agree on evaluation measures before running generation: useful concept coverage, duplicate rate, unsupported-claim rate, topic-assignment accuracy, and reviewer edit/rejection workload.

#### Phase 0 scope record — 2026-10-11

The local development database contains 47 FAR topic records and 26 existing concepts mapped to 12 topics; 35 topics currently have no concepts. This confirms the development scope, but the existing concepts are not an author-reviewed reference set for the full topic tree. Reference-set review and evaluation thresholds remain open, so Phase 4's comparative pilot must wait for those inputs.

#### Proposed evaluation rubric — pending author/SME approval

Use one-to-one matching between reference concepts and proposals, with a subject-matter reviewer deciding whether a proposal is an acceptable equivalent and whether its assigned topic is correct. Calculate concept coverage as the macro-average of reference-concept recall across topics, so a topic with many reference items does not dominate. Report the other rates over all reviewed proposals or factual claims as named below. The following are starting thresholds for review, not approved release gates:

| Measure | Proposed calculation | Proposed starting threshold |
| --- | --- | --- |
| Useful concept coverage | Reference concepts matched to an acceptable proposal, macro-averaged by topic | At least 80% |
| Duplicate rate | Proposals marked redundant with an existing concept, another proposal, or a reference concept ÷ all proposals | At most 10% |
| Unsupported/incorrect claims | Reviewer-flagged unsupported or incorrect factual claims ÷ factual claims reviewed | 0 critical errors; at most 5% other flagged claims |
| Topic-assignment accuracy | Proposals assigned to the reviewer-confirmed topic ÷ proposals with a confirmed assignment | At least 95%, with zero cross-subject assignments |
| Reviewer workload | Proposals needing major edits or rejection; active review time per proposal | At least 70% usable with no or minor edits and median review time at most 2 minutes per proposal |

Boundary cases and duplicate traps should be scored separately as pass/fail reviewer checks, not counted as ordinary reference coverage. The product owner and subject-matter reviewer must accept or revise this rubric before the live-model pilot; no pilot result should be judged against these suggested values until that agreement is recorded.

#### Deferred evaluation and rollout gates — future reference

The current development work is complete. The unchecked Phase 0 reference-set and measure decisions, and the Phase 4 comparison and threshold checks, are intentionally deferred; no decision is being made on them in this iteration. Keep these checklist items open. Before a comparative quality evaluation or enabling the workflow outside development, a product owner and subject-matter reviewer need to prepare the reference set and approve or revise the proposed measures and thresholds. The rubric above is a proposal only.

### Phase 1 — Subject-scoped launch and authorization

- [x] Add a concept-draft entry point for a selected subject and show its complete topic hierarchy.
- [x] Require one existing subject; load all its topics recursively and reject cross-program or cross-subject scope.
- [x] Reuse existing generation records, queueing, and reviewer authorization without requiring source-file selection.
- [x] Check: a subject from another program cannot be used, and no records/jobs are created on rejected requests.

### Phase 2 — Immutable scope and LLM generation

- [x] Persist the recursive subject/topic `scope_snapshot` for concept generations and include it in request idempotency and stale-review checks.
- [x] Pass the full topic tree and existing subject concepts for duplicate context to the structured agent; bound input and output sizes.
- [x] Validate concept item bounds, app-local codes, topic membership, and duplicate candidates; attach uncertainty notes where useful.
- [x] Check: topic-tree edits, malformed output, and oversized input are handled without silently widening scope or publishing content.

### Phase 3 — Topic-mapped review and approval

- [x] Show the assigned topic path and subject-wide scope disclosure beside each concept.
- [x] Support author edits, sort order, per-item approval/rejection, conflict resolution, and optimistic proposal-version checks.
- [x] Write approved concepts with the selected `subject_id` and `syllabus_topic_id`; keep pending/rejected items out of canonical curriculum and preserve review audit history.
- [x] Check: approving one item does not approve its siblings; a duplicate conflict does not block independent items; the author-approved label is shown for approved items during both partial and final review.

### Phase 4 — Evaluation and acceptance

- [x] Exercise one complete subject tree with LLM-generated proposals and verify structural topic assignments and duplicate handling.
- [ ] Compare the reviewed result with the agreed reference set and record coverage, duplicates, unsupported claims, topic-assignment accuracy, and reviewer edits/rejections.
- [x] Verify the absence of selected-file grounding is explicit and visibly disclosed; verify no concept is created before author approval.
- [x] Verify every approved concept belongs to an existing topic in the chosen subject, and topic-tree changes prevent stale approval.
- [ ] Check: the evaluation meets thresholds agreed in Phase 0 before enabling the workflow outside development.

#### Phase 4 workflow checks — 2026-10-11

The curriculum-generation feature tests verify that concept requests snapshot the subject topic tree through multiple nested levels and separate roots with no source files, malformed or out-of-scope proposals create no canonical concepts, per-item author approval controls canonical creation, duplicate conflicts do not block independent items, approved concepts link to topics in the selected subject, and edits to the topic tree stale an existing proposal. The create and review pages visibly state that concepts use the full topic tree and LLM knowledge without selected program files. These checks validate workflow behavior; they do not evaluate live-model content quality.

#### Live proposal exercise — 2026-10-11

Generation `curriculum_generations.id=1` used OpenAI `gpt-6-luna` for the AUDITING subject (subject ID 5), which was not the planned FAR evaluation fixture. Its scope snapshot contains 136 topics and the proposal has 136 items assigned across 136 distinct topics; every assigned topic belongs to subject 5. This verifies structural scope and topic membership for this run, not whether each concept is pedagogically assigned to the best topic.

The proposal contains an exact repeated title, “Attestation and Related Services,” under topics 184 and 304. The review workflow preserved the first item as approved and isolated the second as a conflict; 135 items are marked approved and have canonical concepts, while one conflict remains unresolved. The generation is still in `reviewing` status with no `reviewed_at` timestamp.

All 136 generated items have a null description. As a result, this run does not satisfy the requested concise concept-definition contract and cannot support a meaningful unsupported-claim or instructional-usefulness evaluation. It also has no author-reviewed reference set or accepted thresholds. Treat the run as workflow and failure-case evidence only; do not use it to close the comparative evaluation or broader rollout gates. Resolve the missing-description generation issue and review the proposal content before another quality evaluation.

## Acceptance criteria

- A concept proposal is scoped to one existing subject and its complete recursive topic tree; it cannot create, alter, or publish syllabus topics.
- Program-file selection is not required, and the UI clearly discloses that proposals are not grounded in selected program files.
- Every approved concept links to an existing topic in the selected subject, and only explicit author decisions create canonical records.
- Isolated duplicate conflicts remain visible without discarding unrelated candidates.
- A changed subject topic tree cannot be approved as if it were the original request.
- Concepts are presented as app-authored instructional items, separate from official syllabus codes and topic hierarchy.
- Evaluation evidence and reviewer workload are recorded before broader rollout.

## Out of scope

Concept generation across multiple subjects; automatic syllabus-topic creation or alteration; prerequisite generation; learning objectives; lessons, questions, or learner-facing AI; OCR; required program-file grounding, embeddings, or RAG/chunk review; automatic publication; claims of official completeness or PRC compliance; provider-terms production review.

## Related documents

- [12-K — Program Source Library and Author-Reviewed Curriculum Generation](12-k-program-source-library-and-reviewed-curriculum-generation-plan.md) — shared source and proposal/review implementation.
- [12-D — Subject-Scoped Syllabus Topic Extraction](12-d-curriculum-extraction-subjects-concepts.md) — separation of syllabus topics from instructional concepts.
- [12-H — Advanced AI Features Roadmap](12-h-advanced-ai-features-roadmap.md) — later RAG and concept-drafting architecture.
- [07 — FAR Complete Worked Example](07-far-complete-worked-example-syllabus-concepts-lessons-objectives-questions.md) — illustrative topic-to-concept breakdown.
