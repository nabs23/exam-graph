# Reviewer App Documentation

This directory contains the product, curriculum, architecture, requirements, and execution material for the Reviewer App: an adaptive PRC licensure-review platform built around prerequisite remediation and source-grounded learning.

## Recommended Reading Order

1. [Product definition](01-final-project-description-product-definition.md)
2. [Architecture decisions](02-final-decisions-architecture-decision-register.md)
3. [Software requirements specification](03-consolidated-software-requirements-specification.md)
4. [Architecture, curriculum graph, and RAG design](04-architecture-curriculum-graph-rag-design-guide.md)
5. [Executable development plan](08-executable-development-plan.md)
6. [Original development and implementation guide](05-development-plan-implementation-guide.md)
7. [CPALE syllabus and TOS baseline](06-cpale-official-syllabus-tos-baseline-2022-2028.md)
8. [FAR worked example](07-far-complete-worked-example-syllabus-concepts-lessons-objectives-questions.md)
9. [Bare-bones CRUD MVP development plan](09-bare-bones-crud-mvp-development-plan.md)
10. [Bare-bones CRUD MVP test guide](10-bare-bones-crud-mvp-test-guide.md)
11. [Program and subject files S3 development plan](11-program-subject-files-s3-development-plan.md)
12. [AI curriculum feature index](12-laravel-ai-sdk-integration-plan.md), with its ordered foundation, source-document, embedding, extraction, lesson, learner, and governance features
13. [CI/CD setup guide](13-ci-cd-setup-guide.md)

## Directory Contents

```text
docs/
├── 01–13*.md           Project documents
└── README.md           This index
```

## Document Index

| Area | Document | Purpose |
| --- | --- | --- |
| Product | [01 — Final Project Description & Product Definition](01-final-project-description-product-definition.md) | Defines the users, differentiator, learning loop, scope, and success criteria. |
| Product | [02 — Final Decisions & Architecture Decision Register](02-final-decisions-architecture-decision-register.md) | Canonical technical and product decisions, including the Laravel/Inertia/MongoDB direction. |
| Specifications | [03 — Consolidated Software Requirements Specification](03-consolidated-software-requirements-specification.md) | Functional requirements and the core acceptance scenario. |
| Architecture | [04 — Architecture, Curriculum Graph & RAG Design Guide](04-architecture-curriculum-graph-rag-design-guide.md) | Domain boundaries, storage model, retrieval pipeline, services, jobs, and UI surfaces. |
| File management | [11 — Program and Subject Files S3 Development Plan](11-program-subject-files-s3-development-plan.md) | Private S3 direct upload, signed download, CRUD, AWS setup, and production configuration. |
| Planning | [05 — Development Plan & Implementation Guide](05-development-plan-implementation-guide.md) | Original implementation sequencing and MVP rationale. |
| Curriculum | [06 — CPALE Official Syllabus & TOS Baseline](06-cpale-official-syllabus-tos-baseline-2022-2028.md) | Official syllabus/TOS baseline, versioning, and import rules. |
| Curriculum | [07 — FAR Complete Worked Example](07-far-complete-worked-example-syllabus-concepts-lessons-objectives-questions.md) | End-to-end example from FAR syllabus through concepts, lessons, objectives, questions, and mastery. |
| Planning | [08 — Executable Development Plan](08-executable-development-plan.md) | The current phased delivery plan, checklists, milestones, and release gates. |
| Planning | [09 — Bare-Bones CRUD MVP Development Plan](09-bare-bones-crud-mvp-development-plan.md) | The intentionally small CRUD MVP scope, data model, delivery phases, and completion status. |
| QA | [10 — Bare-Bones CRUD MVP Test Guide](10-bare-bones-crud-mvp-test-guide.md) | Automated, manual, negative-path, and database checks for verifying the finished MVP. |
| Planning | [12 — AI Curriculum Features Index](12-laravel-ai-sdk-integration-plan.md) | Entry point for independently deliverable Laravel AI SDK curriculum features. |
| Planning | [12-A — Laravel AI SDK Foundation](12-a-laravel-ai-sdk-foundation.md) | SDK installation, provider configuration, audit runs, queues, limits, and fakes. |
| Planning | [12-B — Source Documents and Content Mapping](12-b-source-documents-content-mapping.md) | Reviewable source intake, extraction, chunks, and mapping. |
| Planning | [12-C — VoyageAI Embeddings and pgvector Retrieval](12-c-voyageai-pgvector-embeddings.md) | Approved-chunk embeddings, vector storage, filtered retrieval, and provenance. |
| Planning | [12-D — Curriculum Extraction: Subjects, Topics, and Concepts](12-d-curriculum-extraction-subjects-concepts.md) | Review and publication of official hierarchy plus concept proposals. |
| Planning | [12-E — Grounded Lesson Drafts](12-e-grounded-lesson-drafts.md) | Evidence-bounded lesson generation, review, publication, and staleness. |
| Planning | [12-F — Learner Tutor and Practice Drafts](12-f-learner-tutor-practice-drafts.md) | Cited learner support and reviewer-approved question drafts. |
| Planning | [12-G — AI Governance, Evaluation, and Rollout](12-g-ai-governance-evaluation-rollout.md) | Evaluation, security/privacy review, testing, and release gates. |
| Operations | [13 — CI/CD Setup Guide](13-ci-cd-setup-guide.md) | GitHub Actions, Docker Hub, and Coolify setup for testing and deployment. |

## How to Use These Documents

- Use the product, requirements, and architecture documents to decide what the application must do and how its domain should work.
- Follow `08-executable-development-plan.md` for implementation order and completion checks.
- Use `09-bare-bones-crud-mvp-development-plan.md` for the reduced MVP scope and `10-bare-bones-crud-mvp-test-guide.md` for end-to-end verification.
- Treat the syllabus baseline and FAR example as the content-model and board-coverage authority; do not collapse official syllabus hierarchy into the instructional concept graph.
