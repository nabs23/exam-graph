# 12-D — Official Curriculum Extraction

## Status

Implemented as an administrator-only, reviewer-controlled workflow for proposing official subjects and syllabus topics from a program PDF. Instructional concepts remain a separate feature in [12-H](12-h-advanced-ai-features-roadmap.md).

## Scope

- Select an existing, uploaded program PDF after its page text and VoyageAI embeddings have completed.
- Reuse the stored PDF text page by page. This workflow does not download or reprocess the original file and does not add OCR, document classification, semantic chunks, or new extraction models.
- Ask the configured Laravel AI SDK text provider for a structured proposal of official subject and syllabus-topic names, optional codes, descriptions, hierarchy, and source-page references. The model must not invent codes. If a description is absent from the source, it drafts a concise description from the cited passage and curriculum context, and labels it AI-generated for reviewer verification.
- Preserve the source content hash, provider, model, prompt version, original proposal, final reviewed selection and edits, lifecycle status, requester, reviewer, and review timestamp for each extraction.
- Keep the proposal private until a content administrator reviews it. The reviewer may edit details, exclude proposals, publish selected subjects/topics, or reject the entire proposal.
- Publish only to the existing `subjects` and `syllabus_topics` tables. Do not create instructional concepts or prerequisite relationships.

## Workflow and safeguards

1. An administrator starts extraction from an uploaded program PDF with complete page embeddings whose text belongs to the current content hash.
2. The job sends bounded page-numbered text to the configured provider/model. The feature is disabled by default and requires explicit provider, model, and API-key configuration; PostgreSQL and pgvector must be available.
3. Validate the structured response, bounds, hierarchy, and every cited page against the supplied pages. Invalid proposals fail with a safe error category; provider messages and source text are not written to logs or error fields.
4. A reviewer sees each proposed subject/topic next to its source-page excerpt. Subject and topic codes are optional; reviewers can publish accurate names and hierarchy without inventing or supplying codes. AI-drafted descriptions are visibly labeled and must be checked against the cited source before publication.
5. Publishing rechecks the source version and proposal state, validates the selected hierarchy and uniqueness of any supplied codes, and creates the selected records in one transaction. The extraction record retains the full proposal and provenance. Rejection creates no curriculum records.

Statuses are `queued`, `processing`, `reviewing`, `published`, `rejected`, and `failed`. Re-running after rejection/failure creates a separate record; an active or reviewable extraction for the same file version prevents duplicate dispatch.

## Not included

OCR, image-only PDFs, DOCX/EPUB extraction, semantic chunking, official curriculum versioning, blueprint validation, prerequisite suggestions, subject-concept extraction, concept mapping, and evaluation of extraction precision/recall remain out of scope. Embeddings are used only as an ingestion readiness gate; they are not evidence for official facts. Source-page text is the evidence and the reviewer is the only publisher.

## Configuration

Set `AI_OFFICIAL_CURRICULUM_EXTRACTION_ENABLED=true`, an explicit `AI_OFFICIAL_CURRICULUM_PROVIDER`, `AI_OFFICIAL_CURRICULUM_MODEL`, and the provider API key. Configure the character bound, timeout, and queue retry/backoff values through the matching `AI_OFFICIAL_CURRICULUM_*` variables in `.env.example`. Keep this feature off until provider terms, rights to process the selected files, and a small reviewer pilot are approved.

## Acceptance criteria

- Only authorized content administrators can create, review, publish, or reject proposals.
- Only completed text embeddings matching the current program-file content hash can be submitted.
- Every proposed subject/topic cites a page supplied to the model; invalid citations and malformed hierarchies cannot be published.
- No subject/topic is created without explicit reviewer selection; reviewer publication is atomic and source-version checked.
- Concepts remain untouched and no AI proposal is treated as an official fact until reviewed and published.
