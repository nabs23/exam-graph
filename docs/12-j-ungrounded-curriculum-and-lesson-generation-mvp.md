# 12-J — Ungrounded Curriculum and Lesson Generation MVP

## Status and relationship to existing plans

**Status: proposed implementation plan; product approval and implementation have not started.** This plan proposes a bounded MVP that uses an LLM to draft subjects, topic hierarchies, and lessons for a program without source retrieval or citations. Generated items remain private drafts until a content administrator reviews and explicitly approves them.

This is a temporary product-scope exception to the source-grounding principles in [01 — Product Definition](01-final-project-description-product-definition.md), [02 — Architecture Decisions](02-final-decisions-architecture-decision-register.md), [03 — Requirements](03-consolidated-software-requirements-specification.md), and [04 — Architecture](04-architecture-curriculum-graph-rag-design-guide.md). It does not change the broader target architecture. Before implementation, update those normative documents to say grounding is deferred for this MVP and prohibit unreviewed learner-facing instructional content. Keep [12-D](12-d-curriculum-extraction-subjects-concepts.md) and [12-I](12-i-subject-topic-extraction-implementation-plan.md) as the separate source-backed syllabus topic-extraction workflow; this plan does not replace or weaken its citation contract.

## Goal

Let an administrator create a content package for one selected licensure program by asking a configured LLM to propose subjects, a hierarchical topic outline for selected subjects, and draft lessons for selected topics. Reviewers can edit, approve, reject, and publish each item. Learners see only approved and published materials.

The MVP tests whether AI-assisted content setup and a basic lesson-consumption flow are useful. It does not establish that generated content is factually correct, complete against an official syllabus, or suitable for examination preparation without expert review.

## Product decisions and boundaries

- **Program:** The administrator selects an existing program. The model cannot create, rename, or merge programs.
- **Subjects:** The model proposes subject titles and short descriptions scoped to the program. A reviewer edits and selects proposals; explicit approval creates subjects. Existing subjects are never overwritten, merged, or silently deduplicated.
- **Topics:** The reviewer selects an existing or newly approved subject and requests a bounded hierarchy. Proposals include title, short description, learning objectives, and parent/child structure. The model does not assign official syllabus codes, claim official coverage, create prerequisite edges, or set lifecycle state.
- **Lessons:** A reviewer selects an approved topic and requests a draft with objectives, explanation, examples, misconceptions, and a short knowledge check. Lessons remain admin-only drafts until reviewed and published.
- **Ungrounded generation:** Prompts may use the program name, subject/topic context, and administrator-provided instructions, but no source files, retrieval, embeddings, or citations. Every proposal displays “AI-generated, not source-verified” in admin review. Published lessons may be marked reviewed, but never source-verified.
- **Human authority:** Only reviewers create or publish canonical curriculum and lesson records. Approval confirms editorial review, not factual correctness or official syllabus alignment.
- **Pilot scope:** Begin with one program and a bounded number of subjects, topics, and lessons. Set concrete limits before enabling requests; reject over-limit requests before queueing.
- **Learner access:** Learners can access only published lessons and approved curriculum context. Chat, tutoring, generated exams, high-stakes questions, and autonomous publication are excluded.
- **Existing workflows:** Do not alter program-wide extraction or subject-scoped source-backed topic extraction. Do not repurpose their records or provenance fields for ungrounded output.

## User workflow

1. An authorized administrator selects an existing program and requests a subject proposal.
2. The system creates a private generation record and queues structured output. The reviewer inspects, edits, selects, or rejects each proposed subject.
3. Explicit approval creates selected subjects. Possible duplicate titles are surfaced for reviewer resolution; they are never merged automatically.
4. For an approved subject, the reviewer requests a bounded topic hierarchy. The system validates shape, uniqueness, parent references, and size. The reviewer edits hierarchy/order and selects topics for publication.
5. For an approved topic, the reviewer requests one lesson draft. The system validates required fields and length. The reviewer edits, approves, publishes, or rejects it.
6. Learners browse published subjects/topics and open published lessons. Drafts, rejected records, provider details, and internal prompt data are not exposed to learner routes.

Generation stages are independently retryable and versioned. A later generation never mutates previously reviewed or published records.

## Proposed content contracts

The application owns primary keys, relationships, ordering, status, and timestamps. The model returns content only:

```text
subject proposal:
  subjects[]: { draft_id, title, description }

topic proposal:
  topics[]: { draft_id, parent_draft_id nullable, title, description, objectives[] }

lesson proposal:
  title
  summary
  objectives[]
  sections[]: { heading, explanation, examples[] }
  common_misconceptions[]
  knowledge_check[]: { prompt, expected_answer, explanation }
```

For each generation record program and selected curriculum context, requester, provider/model, prompt/schema version, request limits, status, safe failure category, correlation ID, proposal, reviewer edits, reviewer, decision, and timestamps. Do not store secrets, raw provider payloads, or unnecessary learner data.

## Application design

### Persistence and lifecycle

- Add a durable generation record for generation kind (`subjects`, `topics`, `lesson`), scoped parent IDs, provider/model and prompt/schema version, bounded request context, private proposal, review outcome, requester/reviewer, status, and safe error/correlation metadata.
- Inspect the current subject/topic/lesson schema before implementation. Reuse canonical tables only if they preserve the distinction between official syllabus topics and instructional topics. Do not overload `SyllabusTopic` to mean an AI-authored instructional concept.
- Store approved content in canonical tables transactionally with the approval decision. Preserve the generation record and reviewer edits as audit history. Define deletion/retention behavior before implementation.
- Use explicit lifecycle states such as queued, generating, draft, approved/published, rejected, and failed. Enforce allowed transitions in application services.
- Record and display that the content is ungrounded. This status must not imply evidence or citation relationships.

### Generation pipeline

1. Authorize the administrator and validate program/subject/topic ownership and configured size limits.
2. Snapshot generation context and prompt/schema version; reject empty or invalid requests before dispatch.
3. Queue a provider-neutral Laravel AI SDK structured-output agent. Keep provider calls out of controllers and use existing queue and safe-failure patterns.
4. Validate output shape, required text, field/total-size limits, unique draft IDs, topic parent references, hierarchy depth, and item counts. Reject malformed output safely; never store a partial tree as reviewable content.
5. Keep accepted output private in the generation record. Do not create canonical curriculum or learner-facing records until explicit review.
6. On approval, recheck permissions, parent scope, duplicate conflicts, proposal version, and lifecycle state; atomically write selected content and approval audit data.
7. Make retries idempotent and prevent duplicate in-flight requests for the same scope and generation version. Regeneration creates a new proposal/version and never overwrites an approved asset.

### Routes and UI

- Add routes only inside the existing authenticated content-management boundary and scope nested resources to their parent program/subject/topic.
- Provide generation launch/history and dedicated review experiences for subjects, topic trees, and lessons. Keep prompts and requests bounded.
- Show generation status, safe failure explanations, administrator-appropriate provider/model metadata, and clear draft/ungrounded labels. Do not display raw provider errors.
- Reviewer controls include edit, reorder, include/exclude, approve/publish, reject, and regenerate where valid. Show duplicate candidates before subject/topic approval.
- Learner pages query only published records and never include private generation data or draft fields. Label approved ungrounded lessons as reviewed, not source-verified.
- Use existing components, route naming, Wayfinder helpers, and page conventions.

## Safeguards and limitations

- Treat model output as untrusted input. Escape rendered content and validate supported rich text against an allowlist; never execute generated markup or code.
- Enforce authorization and parent ownership at every request, review, publish, retry, and delete action.
- Apply request throttles, configurable token/output bounds, provider timeouts, retry limits, budget/usage tracking, an administrator kill switch, and a default-off feature flag outside explicitly enabled development/pilot environments.
- Log correlation IDs, generation IDs, duration, usage/cost metadata if available, and safe failure categories. Do not log full completions, sensitive prompt contents, provider payloads, credentials, or signed URLs.
- Preserve reviewer identity and edits. Review does not prove correctness, official compliance, or source verification.
- Source upload, RAG, citations, generated questions, learner tutor/chat, adaptive diagnosis, automatic prerequisite generation, and automatic publication are out of scope.
- Before using the workflow with real examinees, the accountable subject-matter reviewer must approve the pilot content and the product owner must accept the ungrounded-content limitation.

## Evaluation and acceptance

Run a small pilot in one program with a named content administrator and subject-matter reviewer. Record subject proposal usefulness and duplicate/conflict rate; topic hierarchy edit/exclusion/rejection rates; lesson edit time and reviewer accept/reject rate; malformed-output and provider-failure rates; latency and estimated cost per approved asset; and reviewer-reported factual or instructional errors. Agree on pilot thresholds with the product owner and reviewer before interpreting results. Fluency or reviewer acceptance alone is not evidence of factual accuracy. Require review of every lesson before publication and maintain a correction path for published material.

Acceptance requires:

- generated assets remain private until authorized explicit review and approval;
- scope, hierarchy, lifecycle, duplicate, authorization, failure, retry, and size-limit behavior is enforced;
- approvals create canonical records atomically and rejected drafts create none;
- learner routes expose published content only and preserve the reviewed/ungrounded distinction;
- reviewer actions and generation metadata are auditable;
- the bounded pilot is completed, outcomes and costs are recorded, and the product owner decides whether to continue, revise, or stop before expanding scope.

## Delivery phases

### Phase 0 — Product and schema alignment

- [ ] Approve this ungrounded MVP as a time-bounded exception to the source-grounding target.
- [ ] Update the product definition, decision register, SRS, architecture guide, README, and AI roadmap to distinguish MVP behavior from the grounded target.
- [ ] Confirm the distinction between official syllabus topics and instructional topics; inspect current schema and lifecycle support.
- [ ] Name the pilot program, accountable content administrator, subject-matter reviewer, and concrete generation limits.
- [ ] Confirm provider/model, data handling, budget limit, feature-flag defaults, and kill-switch owner.

### Phase 1 — Generation records and provider foundation

- [ ] Confirm installed Laravel AI SDK/provider versions and project conventions; reuse existing AI configuration and queueing patterns.
- [ ] Add generation persistence, status transitions, relationships, retention rules, and safe metadata.
- [ ] Implement subject, topic, and lesson structured-output contracts with versioned prompts/schemas and configurable limits.
- [ ] Implement provider-neutral queued generation services/jobs, idempotency, safe failures, retries, and usage/correlation tracking.
- [ ] Verify output validation rejects malformed, oversized, duplicate, or invalidly nested proposals.

### Phase 2 — Subject proposal and review

- [ ] Add program-scoped subject generation request/history/review UI.
- [ ] Add editable proposal review, duplicate resolution, rejection, and explicit atomic subject creation.
- [ ] Verify existing subjects are never overwritten, merged, or duplicated without explicit resolution.

### Phase 3 — Topic hierarchy proposal and review

- [ ] Add subject-scoped topic generation request/history/review UI.
- [ ] Support editing titles/descriptions/objectives, hierarchy, order, include/exclude, rejection, and explicit publication.
- [ ] Verify topic records map to the correct instructional domain and do not alter official syllabus topics or prerequisite edges.

### Phase 4 — Lesson drafting and learner delivery

- [ ] Add topic-scoped lesson generation and review/publish UI.
- [ ] Add learner views that expose published lessons and approved context only.
- [ ] Verify draft/rejected lesson data and internal generation metadata never reach learner responses.
- [ ] Add reviewed-but-ungrounded labeling and a correction/unpublish path.

### Phase 5 — Bounded pilot and decision

- [ ] Run the pilot within agreed subject/topic/lesson limits.
- [ ] Record reviewer edits, factual issues, approval times, provider failures, latency, and costs.
- [ ] Decide whether the content is useful enough to continue; revise scope or stop if review burden or error levels are unacceptable.
- [ ] Before broader learner rollout, decide when source ingestion, retrieval, and citation validation will replace or supplement ungrounded drafts.

## Explicitly deferred

Source-grounded generation, retrieval/chunking, citations, official syllabus completeness claims, concept-to-syllabus mapping, prerequisite graph generation, generated assessments/exams, learner-facing free-form tutoring, autonomous approval/publication, multi-program bulk generation, and model-based mastery or recommendations.
