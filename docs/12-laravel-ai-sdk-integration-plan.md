# ExamGraph — AI Curriculum Features Index

## Purpose

This index replaces the single Laravel AI SDK integration plan with small development features that can be designed, implemented, reviewed, and released independently. The Laravel AI SDK remains an implementation boundary; it is never the authority for official PRC curriculum, learner mastery, or publication.

## Feature sequence

| Order | Feature | Outcome |
| --- | --- | --- |
| A | [Laravel AI SDK foundation](12-a-laravel-ai-sdk-foundation.md) | Providers, configuration, audit records, queues, rate limits, and test fakes are ready for controlled AI work. |
| B | [Source documents and content mapping](12-b-source-documents-content-mapping.md) | Uploaded resolutions and exam specifications become versioned, reviewed, page-aware source content. |
| C | [VoyageAI embeddings and pgvector retrieval](12-c-voyageai-pgvector-embeddings.md) | Approved source chunks can be embedded, filtered, and retrieved with page-level provenance. |
| D | [Curriculum extraction: subjects, topics, and concepts](12-d-curriculum-extraction-subjects-concepts.md) | Reviewers can turn an uploaded official document into a published, traced curriculum draft. |
| E | [Grounded lesson drafts](12-e-grounded-lesson-drafts.md) | Reviewers can publish lessons that cite approved source chunks. |
| F | [Learner tutor and practice drafts](12-f-learner-tutor-practice-drafts.md) | Learners receive bounded, cited support and reviewers receive low-stakes practice drafts. |
| G | [AI governance, evaluation, and rollout](12-g-ai-governance-evaluation-rollout.md) | Quality, privacy, security, operating limits, and pilot gates are measurable and enforced. |

## Shared rules

- Administrators explicitly review and publish all official curriculum, concept graph, lesson, and question changes.
- Every official fact and generated claim retains page- and source-version provenance.
- Provider credentials, selected models, limits, and budgets live in environment configuration. They are never committed or stored in curriculum records.
- The application retains the canonical extracted text and provenance. Provider file or vector storage cannot be the sole record.
- AI workflows remain disabled until the related feature’s rollout gate is met.

## Decisions that apply to every feature

1. Approve each provider, model, data-handling terms, and monthly budget before it sends production content externally.
2. Define the retention and deletion policy for originals, extracted text, AI runs, generated drafts, and learner interactions.
3. Maintain rights-cleared evaluation documents and reviewer-approved expected results.
4. Run each feature first with a limited reviewer pilot before enabling it more broadly.

## References

- [Laravel AI SDK documentation](https://laravel.com/framework/docs/13.x/ai-sdk)
- [Architecture and RAG design](04-architecture-curriculum-graph-rag-design-guide.md)
- [Software requirements specification](03-consolidated-software-requirements-specification.md)
- [Executable development plan](08-executable-development-plan.md)
