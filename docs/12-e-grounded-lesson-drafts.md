# 12-E — Grounded Lesson Drafts: Deferred

## Status

Lesson generation is outside the first AI MVP. The MVP creates VoyageAI text embeddings for program and subject PDFs and stores them in PostgreSQL with pgvector; it does not retrieve evidence or call a text-generation model.

## Future prerequisite

Revisit lesson drafts only after the application has approved source content, page-level provenance, retrieval with program/subject filters, and a reviewer workflow for checking citations. A vector alone is not a citation and cannot support a grounded lesson. Subject-scoped retrieval and structured concept drafts are the first advanced AI priority in [12-H](12-h-advanced-ai-features-roadmap.md).

## Deferred design notes

- Future lesson generation must use only approved evidence and return insufficient evidence when support is missing.
- Every learner-facing claim needs a verifiable source page and source version.
- A reviewer must edit and approve drafts before publication.

## Not included in the MVP

Retrieval, chunk mapping, lesson prompts or agents, structured lesson output, claim validation, citation verification, draft storage, publishing, staleness tracking, and lesson quality evaluation.
