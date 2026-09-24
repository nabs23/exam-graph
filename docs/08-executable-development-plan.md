# Reviewer App — Executable Development Plan

## Purpose

This is the delivery plan for the Reviewer App. It converts the product definition, SRS, architecture guide, CPALE baseline, and FAR worked example into sequenced, verifiable phases.

The first release must prove one complete learning loop, not merely demonstrate AI text generation:

```text
board-level assessment failure
  → identified weak concept
  → prerequisite remediation path
  → evidence-based learning intervention
  → prerequisite mastery
  → return to and reassess the original objective
```

## Current Starting Point

The repository already provides a Laravel 13 application with Inertia React 3, Fortify authentication (including 2FA and passkeys), Wayfinder, Pest, and the standard Laravel job queue migration. It does not yet contain Reviewer App domain models, MongoDB integration, Redis/Horizon, curriculum content, or AI/RAG integrations.

## Delivery Principles

- Build working vertical slices before broad platform features.
- Keep **official syllabus scope**, the **instructional concept graph**, the **assessment blueprint**, source evidence, generated assets, and learner state as separate models.
- Start with explainable rules for mastery and remediation; do not start with opaque ML recommendations.
- Formal instructional AI output must be grounded in approved source chunks and retain provenance.
- Treat generated assets and high-stakes questions as draft/review/published content; AI must not publish them autonomously.
- Keep AI and vector implementations behind interfaces so providers can change without rewriting the learning domain.
- Use FAR 4.2 Property, Plant and Equipment (PPE) as the first full vertical slice.

## MVP Boundary

The MVP covers one licensure program and the FAR PPE slice:

- the official CPALE syllabus and TOS baseline needed for FAR;
- a board-level PPE target, at least two prerequisite depths, and a cycle-free concept graph;
- manually curated lessons and questions first;
- attempts, configurable mastery policies, remediation sessions, and return-to-target reassessment;
- one approved, rights-cleared source corpus ingested into chunks and mapped to the PPE concepts;
- retrieval and a source-grounded mini-lesson with provenance;
- basic learner progress and admin content-management surfaces.

Defer broad AI tutor chat, automated question publication, full adaptive diagnostics, all-subject curriculum enrichment, native mobile, psychometrics, and advanced recommendation models until this loop is validated.

## Milestones at a Glance

| Milestone | Outcome | Depends on |
| --- | --- | --- |
| M0 | Product and delivery guardrails agreed | — |
| M1 | Development platform ready | M0 |
| M2 | CPALE/FAR curriculum truth is importable and editable | M1 |
| M3 | PPE concept graph is usable and validated | M2 |
| M4 | Manual adaptive remediation loop works | M3 |
| M5 | Approved source evidence is retrievable | M4 |
| M6 | Grounded learning intervention works end-to-end | M5 |
| M7 | MVP can be piloted and operated safely | M6 |

## Phase 0 — Scope, Authority, and Delivery Setup

**Goal:** Make the first vertical slice, data authority, and operating constraints explicit before implementation begins.

### Checklist

- [ ] Confirm the MVP program is CPALE and the first subject/slice is FAR 4.2 PPE.
- [ ] Select the exact board-level PPE objective that will act as the initial remediation target.
- [ ] Define two or more prerequisite depths for that objective using the FAR worked example.
- [ ] Obtain and record rights/usage approval for the initial reviewer or classroom source corpus.
- [ ] Identify an accountable content administrator and subject-matter reviewer.
- [ ] Define the initial mastery policy (for example, threshold, minimum question count, and retry behavior) as configurable data, not code constants.
- [ ] Define draft, review, published, and archived lifecycle states for curriculum, questions, sources, chunks, and generated assets.
- [ ] Establish a development/staging/production environment strategy and secrets ownership.
- [ ] Record success measures for the pilot: completion of remediation paths, reassessment improvement, source coverage, and reviewer approval rate.

### Exit criteria

- The PPE vertical-slice map, authorized source list, initial mastery policy, and accountable reviewers are agreed.
- No source is ingested without recorded usage rights and lifecycle status.

## Phase 1 — Platform and Domain Foundation

**Goal:** Prepare the existing Laravel/Inertia application for Reviewer App development without committing business logic to a temporary infrastructure choice.

### Checklist

- [ ] Confirm and document the MongoDB hosting option, backup approach, and environment-specific connection settings.
- [ ] Obtain approval and add the Laravel-compatible MongoDB driver; verify normal application persistence against MongoDB.
- [ ] Configure queue workers for local, staging, and production environments.
- [ ] Add Redis and Horizon when the chosen deployment supports them; otherwise document the interim queue backend and operational limits.
- [ ] Configure role/permission authorization for learner and content administrator capabilities.
- [ ] Establish domain service conventions and keep controllers, queue jobs, and Inertia pages thin.
- [ ] Add provider-neutral configuration contracts for embeddings and LLM generation, without selecting a provider in the domain model.
- [ ] Create shared lifecycle, audit, and publication-state conventions.
- [ ] Add baseline automated checks for authentication, authorization boundaries, queue dispatching, and domain-service behavior.
- [ ] Configure error reporting, structured logs, and queue failure visibility.

### Exit criteria

- Learner and administrator accounts can sign in and access only authorized areas.
- MongoDB persistence and a queued test job work in every development environment.
- No provider-specific AI response shape leaks into controllers or domain records.

## Phase 2 — Official Curriculum and Assessment Blueprint

**Goal:** Import the board-authoritative structure separately from the instructional graph.

### Checklist

- [ ] Implement exam program and subject records.
- [ ] Implement versioned official syllabus nodes with code, title, parent, sort order, source reference, and validity dates.
- [ ] Import the six CPALE subjects and their official syllabus hierarchy from the 2022 baseline.
- [ ] Implement versioned exam blueprints, blueprint areas, weights, target item counts, cognitive targets, and difficulty targets.
- [ ] Import FAR's nine blueprint areas, 70-item total, and 30/40/30 difficulty distribution.
- [ ] Keep laws, standards, and issuance overlays as separately versioned records linked to affected syllabus nodes.
- [ ] Add admin tools to inspect imported hierarchy and blueprint data without altering the official baseline accidentally.
- [ ] Validate official codes are unique within their source version and hierarchy parentage is valid.
- [ ] Validate that each active blueprint's weights total 100% and target item counts total correctly.
- [ ] Add regression tests for import validation and version isolation.

### Exit criteria

- FAR scope and its TOS can be browsed and traced to the authoritative baseline.
- Changing a future board blueprint creates a new version rather than overwriting the 2022–2028 baseline.

## Phase 3 — Concept Graph and Content Administration

**Goal:** Build the instructional graph beneath the authoritative syllabus tree.

### Checklist

- [ ] Implement addressable concepts/curriculum nodes with subject, title, description, depth hint, objectives, lifecycle, and mastery-policy references.
- [ ] Implement explicit prerequisite edges with relationship type, importance, notes, and lifecycle.
- [ ] Implement server-side cycle prevention, including self-reference prevention and multi-hop cycle detection.
- [ ] Implement many-to-many syllabus-node-to-concept mappings.
- [ ] Seed the PPE concept set, including recognition, initial measurement, borrowing costs, depreciation, revaluation, impairment, and derecognition.
- [ ] Seed the prerequisite relationships and validate the worked-example graph.
- [ ] Add an administrator graph editor and a learner-friendly curriculum explorer; the UI may be hierarchical, but the model must remain a DAG.
- [ ] Support manual “go back to basics” navigation while retaining the learner's original target.
- [ ] Add admin review and publication controls for concepts and edges.
- [ ] Add tests for cycle prevention, multi-parent concepts, mapping validity, and authorization.

### Exit criteria

- An administrator can create and publish the PPE graph without introducing a cycle.
- A learner can see the board target, its prerequisites, and a clear path back upward.

## Phase 4 — Manual Learning, Assessment, and Remediation Loop

**Goal:** Prove the core product using curated lessons and questions before adding RAG or generation.

### Checklist

- [ ] Implement lessons, learning objectives, examples, and publication lifecycle.
- [ ] Implement questions, answer choices, rationales, difficulty, cognitive level, and provenance placeholders.
- [ ] Implement many-to-many links from questions to concepts, objectives, and syllabus nodes, with primary/prerequisite-signal/supporting roles and weights.
- [ ] Create a small reviewed PPE question set covering the board target and prerequisite nodes.
- [ ] Implement node quizzes, attempt capture, correctness, response-time measurement, and immutable attempt records.
- [ ] Implement per-learner concept mastery with state, score, confidence, attempt count, evidence summary, and timestamps.
- [ ] Implement configurable, explainable mastery policies.
- [ ] Implement remediation sessions that preserve the trigger attempt, original target, chosen prerequisite path, completed nodes, and reassessment result.
- [ ] Implement the initial rules engine: failed target → inspect prerequisite evidence → recommend/test prerequisite → remediate → progress upward → retry target.
- [ ] Build learner pages for a node, quiz, feedback, remediation path, and return-to-target action.
- [ ] Add feature tests for passing, failing, insufficient evidence, manual drill-down, progression, and reassessment.

### Exit criteria — M4: First Product Proof

- With only manually reviewed content, a learner can fail a board-level PPE question, complete prerequisite remediation, pass the prerequisite quiz, and be directed back to reassess the original PPE target.
- The system records evidence and mastery changes without treating lesson completion as mastery.

## Phase 5 — Source Knowledge Library and Ingestion

**Goal:** Make approved source material inspectable, versioned, and safe for retrieval.

### Checklist

- [ ] Implement the source-document registry with author, edition, publisher, scope, rights notes, content hash, ingestion state, and publication state.
- [ ] Implement secure source upload/storage with file-type, size, malware, and authorization controls appropriate to the hosting environment.
- [ ] Implement idempotent ingestion jobs for extraction, cleaning, semantic chunking, and metadata preservation.
- [ ] Preserve original file reference, source version, page range, heading path, chunk index, and content hash for every chunk.
- [ ] Prefer direct text extraction; use OCR only when a document is scanned and the output is reviewable.
- [ ] Remove predictable headers, footers, and page artifacts without corrupting source meaning.
- [ ] Build admin views for ingestion status, failures, raw/clean text comparison, and chunk inspection.
- [ ] Implement manual chunk-to-concept mappings with reviewer, confidence, source, and review status.
- [ ] Prevent duplicate chunks and duplicate processing for the same source version.
- [ ] Add tests for upload authorization, ingestion idempotency, failure handling, lifecycle filters, and provenance.

### Exit criteria

- An administrator can upload one authorized PPE source, inspect its clean semantic chunks, and publish reviewed mappings to PPE concepts.
- Every chunk used later can be traced to a source document and location.

## Phase 6 — Retrieval and Source-Grounded Lessons

**Goal:** Replace the manually authored intervention for selected nodes with a provenance-preserving retrieval and lesson workflow.

### Checklist

- [ ] Implement an `EmbeddingService` behind a provider-neutral interface.
- [ ] Generate embeddings asynchronously and store the embedding model/version with each vector record.
- [ ] Configure MongoDB vector search or another approved vector implementation behind a retrieval interface.
- [ ] Implement retrieval filtering by program, subject, active node, publication status, and source version before semantic ranking.
- [ ] Add optional lexical search, deduplication, and reranking only when evaluation data shows they are needed.
- [ ] Implement controlled expansion: target node → related prerequisite/dependent nodes → subject scope only when policy permits.
- [ ] Return an explicit insufficient-evidence result rather than unrestricted model knowledge when evidence is weak.
- [ ] Implement structured lesson generation with target node, learner context, objectives, source chunk IDs, prompt version, provider/model metadata, and output schema.
- [ ] Implement generated-asset hashes, review/publication lifecycle, caching, and stale marking when source or curriculum versions change.
- [ ] Add source references to the learner lesson view and an admin review queue.
- [ ] Create retrieval and grounding evaluation cases from the PPE corpus, including unsupported questions and near-miss concepts.

### Exit criteria — M6: Evidence-Grounded Intervention

- A failed PPE assessment can open a focused prerequisite lesson assembled only from approved retrieved evidence.
- The learner and administrator can inspect which source chunks supported the lesson.
- Unsupported requests return a clear limitation instead of invented citations or content.

## Phase 7 — MVP Pilot, Operations, and Release Readiness

**Goal:** Operate the complete vertical slice safely with real pilot learners and content reviewers.

### Checklist

- [ ] Build a learner dashboard showing current remediation session, recommended next step, basic concept mastery, and return-to-target status.
- [ ] Build an admin dashboard for curriculum publication, ingestion failures, mapping review, question review, and generated-asset review.
- [ ] Record audit events for publication, source replacement, curriculum-edge changes, and question/asset approval.
- [ ] Add basic analytics: mastery by concept, unanswered/not-assessed distinction, response-time trend, and remediation outcome.
- [ ] Add rate limits, AI usage limits, upload limits, and retry/idempotency policies.
- [ ] Add queue monitoring, failed-job handling, backup/restore validation, and health checks.
- [ ] Validate accessibility, responsive web behavior, empty states, loading/progress states, and permission-denied flows.
- [ ] Run a content-review pass for every published PPE node, source mapping, lesson, and question.
- [ ] Recruit a small representative pilot group: retakers, returning learners, or first-time candidates with uneven foundations.
- [ ] Define pilot feedback questions and success thresholds before inviting participants.
- [ ] Fix defects that break provenance, graph integrity, assessment recording, or the remediation loop before expanding content.

### Exit criteria — M7: Pilot-Ready MVP

- Pilot learners can complete the full PPE loop without administrator intervention.
- Administrators can diagnose ingestion, content, and queue problems from the application and operational tooling.
- Every published instructional asset and assessment item has the appropriate provenance and review state.

## Phase 8 — Broaden Curriculum and Diagnostic Placement

**Goal:** Expand from the validated FAR slice while keeping imports and recommendations explainable.

### Checklist

- [ ] Import the remaining FAR syllabus areas, concepts, objectives, and reviewed assessment content in the documented sequence.
- [ ] Complete TOS coverage reports: unmapped questions, orphan concepts, missing outcomes, and blueprint-weight gaps.
- [ ] Repeat the import pattern for AFAR, MS, AUD, TAX, and RFBT.
- [ ] Build a representative board-level diagnostic blueprint using subject/TOS weights and difficulty targets.
- [ ] Implement baseline placement recommendations using rules and confidence, not opaque classification.
- [ ] Implement ongoing recalibration as learners create new assessment evidence.
- [ ] Capture learner persona only as a pacing/recommendation input; never let it override measured mastery.
- [ ] Add release checks for current legal/standards overlays and blueprint applicability dates.

### Exit criteria

- The application can produce an explainable initial plan and expand curriculum without conflating official syllabus scope with the concept graph.

## Phase 9 — AI Practice, Tutor, Analytics, and Mock Exams

**Goal:** Add high-leverage adaptive features after the content, evidence, and review processes are dependable.

### Checklist

- [ ] Implement grounded AI practice generation with source links, prompt/model metadata, duplicate detection, and mandatory review before high-stakes publication.
- [ ] Implement a context-aware tutor with active-node context, bounded study-session history, retrieval grounding, and insufficient-evidence behavior.
- [ ] Add streaming/progress feedback for long AI work where the selected provider supports it.
- [ ] Expand analytics with weakness heatmaps, readiness indicators based on coverage and recency, and remediation-path outcomes.
- [ ] Implement blueprint-aware timed mock examinations and post-exam remediation recommendations.
- [ ] Add question quality review, item statistics, and calibration workflows before relying on generated questions for readiness claims.
- [ ] Evaluate tutor, retrieval, and generated-question quality against a reviewed subject-matter test set.

### Exit criteria

- AI features improve the validated learning loop while retaining human review, evidence boundaries, and operational cost controls.

## Phase 10 — Scale, Reliability, and Future Capabilities

**Goal:** Harden proven workflows before pursuing advanced learning science or new client platforms.

### Checklist

- [ ] Add indexes from measured MongoDB query patterns and monitor retrieval latency/cost.
- [ ] Add cache policies for published curriculum, retrieval results where safe, and reusable generated assets.
- [ ] Load-test quizzes, concurrent attempts, ingestion, embedding, and queued generation.
- [ ] Define retention, export, deletion, and privacy policies for learner data and tutor histories.
- [ ] Establish observability for failed retrievals, insufficient-evidence rates, queue latency, provider failures, costs, and content staleness.
- [ ] Automate deployment checks, backup verification, rollback, and disaster-recovery exercises.
- [ ] Consider spaced repetition, psychometrics, Bayesian knowledge tracing, or graph-based recommendation only after sufficient validated attempt data exists.
- [ ] Consider PWA/native mobile only after the responsive web experience and pilot usage justify it.

## Cross-Phase Definition of Done

A deliverable is complete only when all applicable items are true:

- [ ] Product behavior meets its stated acceptance criteria.
- [ ] Authorization and lifecycle rules are enforced server-side.
- [ ] Meaningful feature and failure-path tests pass.
- [ ] UI states cover loading, empty, error, and no-permission conditions.
- [ ] Background work is idempotent and observable.
- [ ] New instructional content retains traceable source/provenance data.
- [ ] Domain records distinguish draft, review, published, stale, and archived states where required.
- [ ] Required indexes and validation rules exist for the new query/write path.
- [ ] Documentation or operational runbooks are updated when the user explicitly requests them.

## Key Decisions to Resolve Before Their Dependent Phase

| Decision | Required before | Owner | Decision criterion |
| --- | --- | --- | --- |
| MongoDB hosting and Laravel driver | Phase 1 | Technical owner | Managed service/VPS fit, backups, cost, and vector-search availability |
| Queue backend and worker supervision | Phase 1 | Technical owner | Reliability for ingestion and generation jobs |
| Initial source corpus rights | Phase 0 / Phase 5 | Content owner | Written permission or demonstrably authorized use |
| LLM and embedding providers | Phase 6 | Product + technical owner | Quality, cost, privacy, streaming, and SDK fit behind interfaces |
| Source storage and malware scanning | Phase 5 | Technical owner | File size, access controls, retention, and operating cost |
| Mastery thresholds and retry policy | Phase 0 / Phase 4 | Subject-matter reviewer | Pilot evidence and pedagogical suitability |
| Pilot population and success metrics | Phase 7 | Product owner | Representative learners and measurable remediation outcomes |

## Source Documents

- `01-final-project-description-product-definition.md`
- `02-final-decisions-architecture-decision-register.md`
- `03-consolidated-software-requirements-specification.md`
- `04-architecture-curriculum-graph-rag-design-guide.md`
- `05-development-plan-implementation-guide.md`
- `06-cpale-official-syllabus-tos-baseline-2022-2028.md`
- `07-far-complete-worked-example-syllabus-concepts-lessons-objectives-questions.md`
