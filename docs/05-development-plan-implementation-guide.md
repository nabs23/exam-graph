# Reviewer App — Development Plan & Implementation Guide

## Development Strategy

Build the product as usable vertical slices. Do not begin with “full AI.” Establish the curriculum graph, assessment evidence, and learner state first; RAG and generation should attach to that stable domain model.

## Phase 0 — Project Foundation

**Goal:** Base Laravel/Inertia application and conventions.

Deliver:
- authentication;
- roles/authorization;
- MongoDB connection;
- queue configuration;
- tests;
- service conventions;
- AI provider configuration abstraction.

Exit: learner/admin authentication works, MongoDB persists data, and queued jobs execute.

## Phase 1 — Licensure Program and Curriculum Graph

Build:
- programs;
- subjects;
- curriculum nodes;
- prerequisite edges;
- cycle prevention;
- curriculum explorer.

Checklist:
- [ ] Program and subject models
- [ ] Curriculum node model
- [ ] Prerequisite edge model
- [ ] Cycle detection
- [ ] Node CRUD
- [ ] Relationship editor
- [ ] Board-level marker
- [ ] Flexible depth hints
- [ ] Multi-parent and cycle tests

Exit: one real PRC subject can be modeled from board level to foundations.

## Phase 2 — Manual Question Bank and Attempts

Build:
- questions;
- answers/rationales;
- question-node links;
- quizzes;
- attempts;
- response-time capture.

Exit: a learner can take a node quiz and create structured evidence tied to curriculum nodes.

## Phase 3 — Mastery and Remediation Logic

Build:
- user-node mastery;
- rules-based mastery;
- thresholds;
- remediation sessions;
- prerequisite recommendation;
- bottom-up progression.

Exit: using manually authored content, the app completes:
**fail → drill down → pass prerequisite → move upward → retry original topic**.

This is the first true product milestone.

## Phase 4 — Source Knowledge Library

Build:
- source registry;
- uploads;
- extraction;
- cleaning;
- semantic chunking;
- chunk browser;
- provenance.

Checklist:
- [ ] Validate uploads
- [ ] Store original file reference
- [ ] Queue extraction
- [ ] Clean repeated noise
- [ ] Preserve page/heading data
- [ ] Hash source versions
- [ ] Make ingestion idempotent

Exit: an administrator can ingest a real reviewer/classroom source and inspect clean chunks.

## Phase 5 — Taxonomy Mapping and Embeddings

Build:
- chunk-node links;
- embedding service;
- vector index;
- retrieval service;
- mapping review.

Exit: a curriculum node/query returns relevant approved chunks with provenance.

## Phase 6 — Source-Grounded Lesson Generation

Build:
- provider-neutral LLM interface;
- retrieval-to-generation pipeline;
- structured lesson schema;
- source references;
- generated-asset versioning and stale detection;
- admin review;
- learner rendering.

Exit: drill-down produces a node-specific lesson grounded only in approved retrieved evidence.

## Phase 7 — AI-Generated Practice

Build:
- grounded question generation;
- source linkage;
- review lifecycle;
- deduplication;
- difficulty targeting.

Exit: generated node practice can be reviewed and published safely.

## Phase 8 — Context-Aware AI Tutor

Build:
- tutor panel;
- active-node context;
- retrieval on substantive questions;
- bounded session history;
- streaming where supported;
- insufficient-evidence behavior.

Exit: tutor responses stay relevant to the active node and are traceable to approved evidence.

## Phase 9 — Initial Diagnostic and Placement

Build:
- diagnostic blueprint;
- representative board-level sampling;
- gap analysis;
- prerequisite signals;
- baseline recommendations;
- confidence-aware recalculation.

Exit: a learner receives an explainable starting plan after diagnostic assessment.

## Phase 10 — Analytics and Mastery UX

Build:
- mastery overview;
- weakness heatmap;
- node history;
- time-to-solve trends;
- remediation outcomes.

Important: distinguish **not assessed** from **weak** and avoid false precision in readiness scores.

## Phase 11 — Board-Style Mock Examination

Build:
- exam blueprint;
- timed sessions;
- coverage rules;
- result analysis;
- post-exam remediation.

Exit: mock-exam mistakes feed directly into the curriculum/remediation graph rather than ending as a score.

## Phase 12 — Content Operations and Governance

Build:
- draft/review/published lifecycle;
- source versions;
- stale generated-content detection;
- bulk regeneration;
- model/provider/prompt metadata;
- audit trail.

## Phase 13 — Performance, Reliability and Deployment Hardening

Address:
- MongoDB indexes from real query patterns;
- queue monitoring and retries;
- AI cost controls;
- caching;
- rate limiting;
- upload security;
- backups/restore;
- observability;
- CI/CD;
- deployment health checks.

Earlier project notes proposed Docker, Hostinger VPS, Coolify, Redis/Horizon, and Git-based deployment. These are reasonable defaults after the core learning loop is validated.

# Recommended MVP Boundary

A strong MVP includes:

1. one licensure program or one subject;
2. curriculum graph with at least three practical depths;
3. manual question bank;
4. attempts and mastery;
5. remediation sessions;
6. source ingestion;
7. chunk-to-node mapping;
8. vector retrieval;
9. source-grounded mini-lessons;
10. return-to-original-topic flow.

Defer full AI tutor breadth, fully automatic diagnostics, large-scale question generation, native mobile, and complex analytics until the core remediation loop proves useful.

# Suggested First Vertical Slice

Use one difficult board-level accounting topic:

```text
Board-level topic
  -> prerequisite A
    -> prerequisite B
      -> foundational node
```

Load a small authorized corpus for those nodes, create a small manual assessment set, and make the entire adaptive loop work end to end.

Do this before importing the full PRC syllabus.

# Architecture Validation Criterion

The architecture is not validated merely because the AI generates a lesson or vector search returns text.

It is validated when the application can reliably execute:

**assessment evidence → weak curriculum node → prerequisite path → source evidence → learning intervention → mastery evidence → return to original board objective.**

