# 12-G — Embedding MVP Governance and Rollout

## Status recheck — 2026-10-11

The PostgreSQL and pgvector target environment is provisioned, as confirmed by the project owner. The repository also contains a dedicated `phpunit.pgvector.xml` configuration and PostgreSQL integration tests for the vector column and embedding workflow. This satisfies the infrastructure prerequisite; a checked-in configuration or test file alone does not establish a recent passing run in the target environment.

12-G is **not yet complete** based on the available project evidence. The project has code-level tests for deletion cleanup, but a passing run of the dedicated pgvector suite in the target environment is not recorded here. Rights and provider-term review for every pilot document, a manually inspected representative corpus run, and review of operational failures and usage before expanding the pilot are still open. The handoff also says processing rights and provider terms remain to be confirmed for each pilot document. The embedding deletion behavior is specified in [12-B](12-b-source-documents-content-mapping.md); verify it in the target-like environment as part of the rollout. Since the handoff moves to subject-owned source files, use that source set for the remaining evaluation and rollout checks.

## Goal

Enable one bounded AI workflow safely: create VoyageAI text page embeddings for selected program and subject PDFs and store them in PostgreSQL with pgvector.

## MVP evaluation

Use a small rights-cleared set of representative program and subject files. Verify:

- supported file/page detection and PDF text-extraction completeness;
- expected page/vector count and source file/page provenance;
- provider/model/dimension consistency;
- idempotency, replacement after content changes, and retry behavior;
- processing time and provider usage/cost per file;
- safe handling of unsupported files, provider errors, and temporary-file cleanup.

Do not define retrieval recall, extraction accuracy, citation accuracy, lesson quality, or tutor quality metrics yet; those workflows are not in the MVP.

## Security and privacy

- Confirm rights to send each selected file to VoyageAI and review the provider account’s current data handling and retention terms before production use.
- Keep S3 private and authorize every embedding request against the owning program or subject.
- Do not log source bytes, extracted page text, signed URLs, API keys, or full provider error payloads.
- Delete the temporary local PDF after processing, including on job failure.
- Keep provider credentials and model settings in managed environment configuration.
- Document the retention/deletion behavior for embeddings when a source file is deleted or archived.

## MVP test scope

Use Laravel AI SDK embedding fakes for job/service behavior. Cover authorized and unauthorized requests, unsupported file types, multi-page text extraction and completion, PDFs without usable text, duplicate dispatch, changed content/model, provider failure, safe failure state, and deletion cleanup. Exercise the pgvector migration and dimension path against PostgreSQL with pgvector; SQLite is not a substitute for that deployment check.

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
