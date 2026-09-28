# ExamGraph — Laravel AI SDK Integration Plan

## Purpose

Integrate the first-party [Laravel AI SDK](https://laravel.com/framework/docs/13.x/ai-sdk) to accelerate curriculum administration and deliver source-grounded learning support. The SDK is an implementation boundary for providers; it does not become the authority for PRC curriculum, learner mastery, or publication.

This plan covers two primary workflows:

1. Extract a proposed **program**, **subjects**, official syllabus hierarchy, and concept candidates from an uploaded PRC Exam Board Resolution or specification.
2. Generate reviewable, source-grounded lesson content for an approved concept.

It also identifies additional AI uses that add value without compromising the existing review, provenance, and assessment safeguards.

## Current Baseline and Decisions

- The application currently uses Laravel 13.33 and does not yet include `laravel/ai`.
- Install the SDK with `composer require laravel/ai`, then publish its configuration and migrations using the provider command documented by Laravel. Do not add a provider SDK directly unless the selected SDK driver requires it.
- Use SDK agents, structured output, attachments, embeddings, reranking, queueing, failover, and built-in fakes where they fit. Keep the application-facing services (`CurriculumExtractionService`, `LearningGenerationService`, `EmbeddingService`, and `RetrievalService`) as the stable domain boundary.
- Select the LLM, embedding model, OCR strategy, storage, and vector implementation only after a representative PRC PDF evaluation. Model/provider configuration belongs in environment configuration, never curriculum records or committed secrets.
- Treat uploaded board documents as untrusted input. An administrator must review all proposed structure and explicitly publish it. A model must never directly create published programs, subjects, syllabus topics, concepts, prerequisite edges, lessons, or questions.

## Target Workflow A — PRC Board Document to Reviewable Curriculum Draft

### 1. Upload and register

An administrator uploads a PRC Board Resolution or examination specification and supplies the authoritative metadata that the model cannot reliably infer:

- issuing board and resolution number;
- publication/effective date and validity period;
- document type and source URL/reference;
- intended licensure program, when known;
- rights/usage record and reviewer identity.

Create a versioned `source_document` in `draft`/`processing` state. Validate MIME type, extension, size, checksum, upload authorization, malware scan result, and duplicate content hash before it can enter the pipeline. Store the original immutably and preserve page references.

### 2. Extract text before interpreting it

Queue an idempotent extraction job. Prefer deterministic PDF text extraction; use OCR only for image-only or materially incomplete pages. Persist the raw extraction, cleaned text, page boundaries, extraction method/version, warnings, and confidence/coverage signals.

Stop for review when text quality is too low, pages are missing, tables cannot be read, or the document is not a PRC curriculum/specification document. AI should not silently repair missing official text.

### 3. Classify and segment the document

Use a small, structured-output agent to identify document type and locate likely sections: title/authority, program name, subjects, syllabus/coverage, tables of specifications, references, validity/effectivity, amendments, and exclusions. Its output is a page-anchored extraction map, not a data write.

The job passes only relevant page text or a document attachment supported by the selected provider. Use page ranges and headings in every candidate result so an administrator can compare it to the original PDF.

### 4. Extract official curriculum structure

Run a dedicated `ExtractBoardCurriculum` structured-output agent over the identified syllabus sections. Its schema should distinguish facts from inferences and contain, at minimum:

```text
document_identity: resolution number, issuer, program, effectivity, confidence, source pages
subjects[]: official name, code when printed, ordering, source pages
syllabus_topics[]: subject reference, parent reference, official title/code, ordering, source pages
blueprint_areas[]: subject reference, coverage/weight/item targets when printed, source pages
unresolved_items[]: ambiguity, missing value, conflicting pages, recommended reviewer action
```

Require exact source excerpts or page/heading references for every extracted official fact. Validate the returned shape with the SDK schema and application validation; normalize whitespace and numbering only after retaining the original wording.

### 5. Propose instructional concepts separately

After the official hierarchy has been extracted, run `SuggestConceptCandidates` against one reviewed subject/topic at a time. It may propose:

- instructional concepts and concise definitions;
- learning-objective candidates;
- candidate mappings to official syllabus topics;
- prerequisite-edge candidates with rationale and source support;
- ambiguities and concepts that need subject-matter expertise.

The agent must not treat the official hierarchy as a prerequisite tree. It produces a separate draft concept graph, with each suggestion labeled `ai_suggested`; reviewers approve, edit, reject, or split it before it becomes authoritative.

### 6. Reconcile, validate, and publish

Present a diff-based review workspace showing the source page alongside the proposed program, subjects, topics, concepts, mappings, and unresolved items. The reviewer must be able to correct source references and reject an entire run.

Before publication, validate:

- official subject/topic codes are unique in the source version;
- parent references and ordering are valid;
- blueprint totals are internally consistent where applicable;
- every published official item has an authoritative page reference;
- concept-to-topic mappings are reviewed;
- prerequisite edges contain no self-reference or cycle;
- a new resolution creates a new version rather than overwriting the prior baseline.

Publication writes an audited, immutable curriculum version. It records the document hash, extraction run, agent/prompt/schema versions, provider/model, source pages, reviewer, and decision timestamps.

## Target Workflow B — Source-Grounded Lesson Generation

### Preconditions

Lesson generation is available only for a published program, subject, and concept, using published/approved source chunks with valid rights and versions. The system should return `insufficient evidence` when retrieval cannot support the requested lesson.

### Flow

1. The learner or administrator selects the concept, learning objective, desired depth, and context (for example, a failed quiz objective or prerequisite remediation).
2. `RetrievalService` filters approved chunks by program, subject, concept mapping, source version, and publication state before semantic search. It may then use SDK embeddings and reranking through the service boundary.
3. `GenerateLesson` receives only the bounded retrieval set, learner-safe context, lesson policy, and a strict structured-output schema.
4. The agent produces a draft with: title, learning objectives, explanation sections, worked example, common misconceptions, short practice prompts, evidence-backed claims, and cited chunk IDs/page ranges.
5. Application validation rejects citations outside the retrieved set, unsupported factual claims, malformed output, excessive duplication, or content that violates the policy.
6. Store the generated asset as `draft` with prompt/schema/model/version metadata, source-chunk IDs, source document versions, content hash, and cost/usage data.
7. An administrator or qualified reviewer approves it before learner-facing publication. Cache published assets only while their curriculum, source, and generation-policy versions remain valid; mark them stale when any dependency changes.

For on-demand learner explanations, streaming is acceptable, but the response must remain retrieval-bound, cite its evidence, respect rate limits, and retain only the minimum necessary study-session history. It should never assert mastery, diagnose a learner as having a disability, give legal/professional advice, or fabricate PRC policy.

## Laravel AI SDK Design

### Agents and contracts

Create focused agents rather than one general-purpose agent:

- `ClassifyBoardDocument`
- `ExtractBoardCurriculum`
- `SuggestConceptCandidates`
- `GenerateGroundedLesson`
- `TutorConceptQuestion`
- `SuggestChunkMappings`
- `GeneratePracticeQuestionDraft`

Use `HasStructuredOutput` JSON schemas for extraction and generated assets. Schemas ensure predictable transport, but application-level validation and human review remain mandatory. Keep prompts versioned as application resources/configuration and use explicit policy text: source hierarchy is authoritative, uncertainty must be surfaced, and no facts may be invented beyond the supplied evidence.

### Files, retrieval, and provider capability checks

The SDK supports document attachments, files/vector stores, embeddings, and reranking. Use an attachment only where the chosen provider/model supports the required PDF modality; retain the application's own extracted text and page-level provenance as the canonical audit record. Evaluate whether SDK vector stores or the application's selected vector backend better satisfies filtering, version isolation, auditability, cost, and portability requirements before committing to either.

Create a provider capability matrix for document attachments, structured output, embeddings, reranking, streaming, data residency, rate limits, and cost. Configure failover only between providers/models proven to meet the same task contract; do not downgrade a formal curriculum-extraction job to a model that cannot preserve structured output or document fidelity.

### Queues and observability

Run upload processing, OCR, extraction, concept suggestion, embeddings, reranking, lesson generation, stale regeneration, and retries in queues. Each job must be idempotent and use a document/version/run idempotency key.

Track run state (`queued`, `running`, `needs_review`, `failed`, `completed`, `superseded`), attempt count, latency, token/usage/cost where available, provider/model, prompt/schema version, input and output hashes, failure category, and reviewer disposition. Never log source text or personal data unnecessarily.

### Testing

Use the SDK's fakes for agents, embeddings, reranking, files, and vector stores. Cover deterministic behaviors:

- schema and application-validation rejection;
- low-quality/OCR-required document routing;
- page-level provenance preservation;
- duplicate upload and job idempotency;
- reviewer-only publish authorization;
- version isolation and stale generated assets;
- retrieval filters and insufficient-evidence response;
- rejection of citations absent from the retrieval set;
- failover and provider failure paths.

Maintain a small, rights-cleared gold set of PRC documents and reviewer-approved expected extraction records. Measure field-level precision/recall, page-citation accuracy, reviewer correction rate, cost/document, processing time, and concept-mapping agreement before widening the workflow.

## Delivery Phases

| Phase | Deliverable | Exit criteria |
| --- | --- | --- |
| 1 — Foundation | Install/configure SDK; provider capability matrix; encrypted secrets; AI run/audit records; queue and rate-limit policies | A fake agent run is observable and no provider response shape reaches controllers or domain records. |
| 2 — Document intake | Secure upload, versioned source registry, text extraction/OCR fallback, page-aware cleaned text | A PRC PDF can be processed idempotently and inspected page by page. |
| 3 — Official extraction | Classification and structured program/subject/syllabus/blueprint draft, review diff, validation/publish workflow | A reviewer publishes a traced curriculum version from one resolution without manual re-keying of the whole document. |
| 4 — Concept proposals | Draft concept/objective/mapping/edge suggestions and graph validation | Suggestions are reviewable, no AI proposal bypasses graph or publication controls. |
| 5 — RAG foundation | Approved chunk mappings, embeddings, retrieval evaluation, evidence controls | Retrieval returns only in-scope approved chunks or an explicit insufficient-evidence result. |
| 6 — Lesson drafts | Grounded structured lesson generation, citation validation, review/publish/staleness lifecycle | A reviewer can publish a source-traced lesson for one concept. |
| 7 — Learner AI | Retrieval-bound tutor, streaming where appropriate, practice drafts, usage limits and evaluation dashboard | Learner AI improves a measured learning workflow without autonomous high-stakes decisions. |

## Additional AI Opportunities

Prioritize these after the two primary workflows are dependable:

| Use | AI role | Guardrail |
| --- | --- | --- |
| Chunk-to-concept mapping | Suggest likely mappings and confidence | Reviewer approval; retain source pages and mapping rationale. |
| Board-resolution change detection | Compare a newly uploaded version with the active baseline | Human confirms legal/effectivity interpretation and publishes a new version. |
| Lesson adaptation | Rephrase approved, grounded lessons for depth, language, or time available | Preserve claims/citations; do not alter the canonical source-backed asset silently. |
| Tutor | Answer questions within the active concept and retrieved evidence | Cite sources; say when evidence is insufficient; bounded session memory. |
| Practice-question drafts | Draft low-stakes questions, distractors, and rationales from approved chunks | Duplicate detection, SME review, calibration; never autonomous high-stakes publication. |
| Feedback explanations | Explain why an answer is wrong and direct the learner to prerequisites | Do not use the LLM as the mastery decision-maker. |
| Content quality checks | Flag missing objectives, weak provenance, duplicate lessons/questions, or terminology inconsistency | Flags are review tasks, not automatic content mutations. |
| Accessibility aids | Generate glossary entries, plain-language versions, audio scripts, and translations | Review technical accuracy and preserve the original lesson and evidence. |
| Admin search and summaries | Search/summarize approved sources and extraction-review queues | Keep access controls and source-version filters intact. |
| Operations | Classify failed jobs and cluster recurring content-review issues | Do not expose source or learner data to tools/providers outside approved policy. |

## Explicit Non-Goals and Guardrails

- Do not let AI publish official curriculum, prerequisite edges, lessons, or questions without human approval.
- Do not use model knowledge as a substitute for the board resolution or approved instructional source material.
- Do not automatically infer that a learner has mastered a concept based on chat, lesson completion, or model judgment; mastery remains evidence-based assessment logic.
- Do not send learner PII, hidden answers, or unnecessary source material to a provider.
- Do not make eligibility, licensure, legal, or high-stakes readiness claims solely from AI output.
- Do not make SDK vector/file storage the only copy of authoritative source text or provenance.

## Decisions Required Before Implementation

1. Initial LLM and embedding provider/model, including acceptable data handling and monthly budget.
2. PDF extraction/OCR service and its accuracy threshold for escalation.
3. Vector backend after testing with the first rights-cleared PRC corpus.
4. Content reviewers and approval SLA for official extraction, concept graph, and lessons.
5. Retention/deletion policy for uploaded documents, raw extraction, AI runs, tutor history, and generated drafts.
6. The first representative PRC resolution/specification and a reviewer-approved gold extraction set.

## References

- [Laravel AI SDK documentation](https://laravel.com/framework/docs/13.x/ai-sdk)
- [Laravel AI SDK introduction](https://laravel.com/blog/introducing-the-laravel-ai-sdk)
- Existing project foundations: [architecture and RAG guide](04-architecture-curriculum-graph-rag-design-guide.md), [SRS](03-consolidated-software-requirements-specification.md), and [executable development plan](08-executable-development-plan.md).
