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

## Directory Contents

```text
docs/
├── 01–10*.md           Project documents
└── README.md           This index
```

## Document Index

| Area | Document | Purpose |
| --- | --- | --- |
| Product | [01 — Final Project Description & Product Definition](01-final-project-description-product-definition.md) | Defines the users, differentiator, learning loop, scope, and success criteria. |
| Product | [02 — Final Decisions & Architecture Decision Register](02-final-decisions-architecture-decision-register.md) | Canonical technical and product decisions, including the Laravel/Inertia/MongoDB direction. |
| Specifications | [03 — Consolidated Software Requirements Specification](03-consolidated-software-requirements-specification.md) | Functional requirements and the core acceptance scenario. |
| Architecture | [04 — Architecture, Curriculum Graph & RAG Design Guide](04-architecture-curriculum-graph-rag-design-guide.md) | Domain boundaries, storage model, retrieval pipeline, services, jobs, and UI surfaces. |
| Planning | [05 — Development Plan & Implementation Guide](05-development-plan-implementation-guide.md) | Original implementation sequencing and MVP rationale. |
| Curriculum | [06 — CPALE Official Syllabus & TOS Baseline](06-cpale-official-syllabus-tos-baseline-2022-2028.md) | Official syllabus/TOS baseline, versioning, and import rules. |
| Curriculum | [07 — FAR Complete Worked Example](07-far-complete-worked-example-syllabus-concepts-lessons-objectives-questions.md) | End-to-end example from FAR syllabus through concepts, lessons, objectives, questions, and mastery. |
| Planning | [08 — Executable Development Plan](08-executable-development-plan.md) | The current phased delivery plan, checklists, milestones, and release gates. |
| Planning | [09 — Bare-Bones CRUD MVP Development Plan](09-bare-bones-crud-mvp-development-plan.md) | The intentionally small CRUD MVP scope, data model, delivery phases, and completion status. |
| QA | [10 — Bare-Bones CRUD MVP Test Guide](10-bare-bones-crud-mvp-test-guide.md) | Automated, manual, negative-path, and database checks for verifying the finished MVP. |

## How to Use These Documents

- Use the product, requirements, and architecture documents to decide what the application must do and how its domain should work.
- Follow `08-executable-development-plan.md` for implementation order and completion checks.
- Use `09-bare-bones-crud-mvp-development-plan.md` for the reduced MVP scope and `10-bare-bones-crud-mvp-test-guide.md` for end-to-end verification.
- Treat the syllabus baseline and FAR example as the content-model and board-coverage authority; do not collapse official syllabus hierarchy into the instructional concept graph.
