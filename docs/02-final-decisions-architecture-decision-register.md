# Reviewer App — Final Decisions & Architecture Decision Register

This document records the project decisions that should be treated as canonical unless a later project decision explicitly replaces them.

## Decision Status Legend

- **Final** — use as the working project direction.
- **Provisional** — valid default, but implementation may change without altering the product concept.
- **Deferred** — intentionally not fixed yet.
- **Superseded** — appeared in earlier exploration but should not drive current implementation.

## D-001 — Product Type

**Status: Final**

Build an **AI-assisted adaptive learning and PRC review platform**, not merely a mock-exam/question-bank application.

The system must contain actual learning pathways and learning materials, including foundational remediation.

## D-002 — Primary Differentiator

**Status: Final**

Use a **Deep Drill-Down prerequisite architecture**.

Advanced PRC syllabus topics must be linked to increasingly fundamental prerequisite concepts so the learner can descend to the level required to repair the knowledge gap.

## D-003 — Curriculum Shape

**Status: Final**

Model the curriculum as a **directed prerequisite graph / DAG**, even if parts of the user interface present it as levels or a tree.

A simple parent-child tree is insufficient for the domain because a concept may depend on multiple prerequisites and may support multiple higher concepts.

Cycles must be prevented in prerequisite relationships.

## D-004 — Curriculum Levels

**Status: Final concept; flexible depth**

The system may expose levels such as:

- Level 1 — board/syllabus level
- Level 2 — intermediate abstraction
- Level 3 — core mechanics
- Level 4 — foundational / ground zero
- Level 5+ — zero-base concepts when required

The number of levels is not a hard schema constraint. The graph should support arbitrary practical depth.

## D-005 — Primary Application Stack

**Status: Final**

- Laravel backend
- Inertia.js web application
- MongoDB primary data store

React is the expected Inertia adapter when aligning with the developer’s standard stack, but the essential architectural decision is Laravel + Inertia rather than a separately deployed frontend application.

## D-006 — Earlier Backend Alternatives

**Status: Superseded**

Earlier exploration mentioned Node.js/Express, Python/Django, or Go.

These should not be treated as current architecture choices. The project later explicitly selected Laravel.

## D-007 — Earlier Relational/Graph Database Alternatives

**Status: Superseded**

Earlier exploration mentioned PostgreSQL plus Neo4j or recursive SQL.

The progressive implementation direction later selected MongoDB. Graph semantics should therefore be implemented through explicit node and edge/reference collections rather than requiring a dedicated graph database for the initial product.

A future graph database is an optimization option, not an initial dependency.

## D-008 — MongoDB Modeling Strategy

**Status: Final principle; implementation details provisional**

Use referenced documents rather than deeply nested monolithic course documents.

Recommended domain collections include:

- exams / licensure programs
- subjects
- curriculum_nodes
- prerequisite_edges
- source_documents
- content_chunks
- embeddings or vector-enabled content chunks
- questions
- question_node_links
- user_node_mastery
- assessment_attempts
- generated_learning_assets
- tutoring_sessions

Ancestor arrays/materialized paths may be used for navigation and efficient hierarchy queries, but prerequisite edges remain explicit.

Avoid designs that approach MongoDB document-size limits by embedding large trees, lessons, attempts, or source text into a single document.

## D-009 — RAG as the Formal Learning Content Foundation

**Status: Final**

Formal AI-assisted lessons and tutor responses should be grounded in retrieved chunks from authorized reviewer/classroom content.

The LLM must not be treated as the primary source of truth for course facts.

## D-010 — AI Provider

**Status: Provisional / provider-agnostic**

The project may use OpenAI, Anthropic, Gemini, DeepSeek, or another suitable provider, but the application architecture must place LLM and embedding access behind service interfaces.

No curriculum, mastery, or content model should depend on one provider’s proprietary response format.

## D-011 — Vector Storage

**Status: Provisional**

MongoDB vector search is the preferred first implementation when it satisfies deployment and cost constraints.

The retrieval service should remain swappable so another vector store can be adopted later without rewriting the learning domain.

## D-012 — Dynamic Lesson Generation

**Status: Final concept**

When a learner needs remediation:

1. identify the target curriculum node;
2. retrieve high-relevance source chunks mapped to the node;
3. construct a constrained generation prompt;
4. produce a focused lesson or explanation;
5. preserve provenance to the retrieved chunks;
6. assess the learner at that node.

Generated content may be cached and reused when appropriate rather than regenerated on every view.

## D-013 — AI-Generated Assessment

**Status: Final concept with review controls**

AI may generate node-level practice and mini-quizzes from grounded content.

High-stakes or canonical question banks should support human review, approval status, versioning, and source linkage.

## D-014 — Diagnostic Behavior

**Status: Final**

The system should use an initial assessment and ongoing performance to estimate where a learner should begin.

Incorrect answers at advanced levels should be mapped to prerequisite nodes rather than treated only as wrong questions.

## D-015 — Upward Progression

**Status: Final**

The system must support a bottom-up remediation loop.

Mastering a lower-level node should advance the learner toward the parent/dependent concept until the learner returns to the original board-level objective.

## D-016 — Mastery Thresholds

**Status: Provisional**

The source design uses example thresholds such as 75%.

Thresholds should be configurable by subject/node or policy rather than hard-coded globally.

## D-017 — AI Tutor Scope

**Status: Final**

The study assistant must be context-aware and retrieval-bound.

It should know the active curriculum node, current lesson/assessment context, and relevant source chunks.

When evidence is insufficient, the system should say so or widen retrieval according to controlled rules rather than fabricate an answer.

## D-018 — Content Ingestion

**Status: Final**

Administrative content ingestion must support:

- source registration
- file ingestion
- extraction
- cleaning
- semantic chunking
- embedding
- taxonomy mapping
- validation/review
- indexing and publication status

OCR is a fallback for scanned material, not the preferred extraction path when text is directly available.

## D-019 — Background Processing

**Status: Final principle**

Long-running ingestion, embedding, generation, and re-indexing work must run asynchronously through queues.

Laravel queues are the implementation basis; Redis/Horizon is the preferred operational pattern when available.

## D-020 — Deployment

**Status: Provisional / secondary**

Earlier project material proposed Docker, Hostinger VPS, Coolify, Git-based CI/CD, and persistent workers.

These remain reasonable deployment defaults but are not part of the core learning architecture and should not constrain the domain model.

## D-021 — Native Mobile

**Status: Deferred**

Earlier exploration mentioned Flutter or React Native.

The progressive project direction should validate the web/PWA experience first. Native mobile should be added only when product requirements justify it.

## D-022 — Non-Negotiable Domain Rule

**Status: Final**

The system must preserve the distinction between:

- curriculum truth,
- source evidence,
- generated learning assets,
- assessment evidence,
- learner mastery state.

Do not collapse these into one AI-generated content collection. They have different authority, lifecycle, and audit requirements.

