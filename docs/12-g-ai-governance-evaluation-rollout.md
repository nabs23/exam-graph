# 12-G — Embedding MVP Governance and Rollout

## Goal

Enable one bounded AI workflow safely: create VoyageAI multimodal page embeddings for selected program and subject files and store them in PostgreSQL with pgvector.

## MVP evaluation

Use a small rights-cleared set of representative program and subject files. Verify:

- supported file/page detection and PDF page-rendering completeness;
- expected page/vector count and source file/page provenance;
- provider/model/dimension consistency;
- idempotency, replacement after content changes, and retry behavior;
- processing time and provider usage/cost per file;
- safe handling of unsupported files, provider errors, and temporary-file cleanup.

Do not define retrieval recall, extraction accuracy, citation accuracy, lesson quality, or tutor quality metrics yet; those workflows are not in the MVP.

## Security and privacy

- Confirm rights to send each selected file to VoyageAI and review the provider account’s current data handling and retention terms before production use.
- Keep S3 private and authorize every embedding request against the owning program or subject.
- Do not log source bytes, page images, signed URLs, API keys, or full provider error payloads.
- Delete temporary rendered pages after processing, including on job failure.
- Keep provider credentials and model settings in managed environment configuration.
- Document the retention/deletion behavior for embeddings when a source file is deleted or archived.

## MVP test scope

Use Laravel AI SDK embedding fakes for job/service behavior. Cover authorized and unauthorized requests, unsupported file types, multi-page completion, duplicate dispatch, changed content/model, provider failure, safe failure state, and deletion cleanup. Exercise the pgvector migration and dimension path against PostgreSQL with pgvector; SQLite is not a substitute for that deployment check.

## Release gates

1. Confirm target PostgreSQL availability, pgvector installation, model/dimension choice, provider terms, and budget.
2. Run the workflow on a small rights-cleared reviewer corpus and manually inspect page coverage and provenance.
3. Verify idempotent retries, cleanup, and deletion behavior in the target-like PostgreSQL environment.
4. Enable the embedding feature for administrators only, behind its configuration/feature gate.
5. Review operational failures and usage before increasing the pilot corpus.

## Deferred gates

Subject-concept RAG is the first proposed advanced feature, described in [12-H](12-h-advanced-ai-features-roadmap.md). Curriculum extraction, lessons, tutor, and practice generation each require their own product decision, evaluation set, security review, and release gate. None is implied by approval of this MVP.

## Non-goals

- Embeddings do not establish curriculum facts, concepts, mappings, or citations.
- No learner-facing AI is enabled.
- No autonomous publication, vector search endpoint, reranker, or provider-managed vector store is part of the first release.
