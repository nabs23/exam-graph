# ExamGraph — AI Integration Plan

## Basic MVP

The only implementation scope in this plan's first delivery is VoyageAI multimodal embeddings for uploaded program and subject files. The application will store page-level vectors in PostgreSQL with pgvector. It will not extract text, build RAG, generate concepts, generate lessons, or expose AI to learners.

The existing application currently defaults to SQLite. PostgreSQL with pgvector is a prerequisite for implementation and deployment of vector storage, not an assumed current capability.

## Delivery sequence

| Order | Document | MVP role |
| --- | --- | --- |
| 1 | [A — Laravel AI SDK foundation](12-a-laravel-ai-sdk-foundation.md) | Configure the installed Laravel AI SDK and one controlled VoyageAI embedding workflow. |
| 2 | [B — Program and subject file embedding inputs](12-b-source-documents-content-mapping.md) | Reuse existing private file records; define supported input and page provenance. |
| 3 | [C — VoyageAI multimodal embeddings in PostgreSQL](12-c-voyageai-pgvector-embeddings.md) | Render PDF pages/images, generate vectors, and store them with pgvector. |
| 4 | [G — Embedding MVP governance and rollout](12-g-ai-governance-evaluation-rollout.md) | Evaluate, secure, and pilot this single workflow. |

## Later feature notes

- [D — Curriculum extraction](12-d-curriculum-extraction-subjects-concepts.md)
- [E — Grounded lesson drafts](12-e-grounded-lesson-drafts.md)
- [F — Learner tutor and practice drafts](12-f-learner-tutor-practice-drafts.md)

These documents are deferred placeholders. Detailed advanced AI scope, starting with RAG-assisted subject concept creation, is in [H — Advanced AI features roadmap](12-h-advanced-ai-features-roadmap.md). Do not implement these features as part of the basic MVP.

## Shared MVP rules

- Keep uploaded program and subject files as the source records; do not introduce generalized source-document/chunk/mapping models yet.
- Store one embedding per supported source page/image with the original file ID and page number.
- Keep originals in private object storage and vectors in the application PostgreSQL database.
- Do not build text extraction, RAG/retrieval, concept generation, lesson generation, reranking, chat, or learner-facing AI into this delivery.
- Do not add dependencies or SDK capabilities that are not required for this workflow.
- Use the installed Laravel AI SDK version and verify its supported multimodal input classes before implementation.

## MVP completion definition

An administrator can request embeddings for an eligible program or subject file, observe completion or a safe failure, retry idempotently, and verify that every stored vector belongs to the correct source file/page and configured model. PostgreSQL and pgvector are verified in the target environment.

## Advanced AI roadmap

The full AI feature set is intentionally separate from this basic delivery. Its first proposed capability is subject-scoped RAG that retrieves approved subject-file evidence and asks an LLM for structured concept drafts, as described in [12-H](12-h-advanced-ai-features-roadmap.md). It requires page-aware text extraction/OCR and chunk provenance; the page vectors created by the basic MVP are not sufficient by themselves.
