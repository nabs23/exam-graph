# 12-A — Laravel AI SDK Foundation for Text File Embeddings

## Goal

Establish only the foundation needed to create VoyageAI text embeddings from existing program and subject PDFs and persist them in PostgreSQL with pgvector.

## MVP scope

- Use the installed Laravel AI SDK (`laravel/ai` 1.x) as the VoyageAI provider boundary.
- Configure the VoyageAI credentials and one text embedding model through environment-backed configuration.
- Add the smallest application service and queued job needed to embed an explicitly selected `ProgramFile` or `SubjectFile`.
- Record file/page identity, model, dimensions, content hash, status, timestamps, and a safe failure category.
- Keep embedding disabled unless PostgreSQL, pgvector, provider configuration, and the embedding feature flag are available.
- Use Laravel AI SDK embedding fakes for deterministic tests; no provider request should be needed in automated tests.

## Explicitly deferred

Do not add AI agents, chat, text generation, curriculum extraction, lesson or question drafting, reranking, retrieval APIs, generalized AI run/audit infrastructure, per-provider rate-limit systems, or AI-specific feature flags beyond this embedding workflow.

## Design

Use one focused `FileEmbeddingService` plus a queued job. The service accepts an eligible uploaded PDF, extracts its text layer page by page, calls `Embeddings::for(...)->generate()` with the VoyageAI provider and configured text model, and persists validated vectors, extracted page text, and provenance. Controllers must not call the provider or persist raw provider responses.

Reuse the existing `ProgramFile` and `SubjectFile` records as the source of truth for ownership and S3 location. Store embedding rows separately so re-embedding can replace vectors without modifying uploaded files. Do not store source bytes, signed URLs, or provider credentials in embedding records or logs.

## Acceptance criteria

- An authorized administrator can request embedding for an uploaded eligible program or subject file.
- The job can be retried safely and does not create duplicate page embeddings for the same file version, page, model, and dimensions.
- A PDF without usable extracted text, or a provider failure, is visible as a safe processing failure and can be retried.
- Provider/model/dimension configuration is explicit and vectors cannot be mixed across incompatible configurations.
- The workflow refuses to run if the database is not PostgreSQL with pgvector enabled or required configuration is missing.

## Dependencies and gate

`config/database.php` falls back to SQLite when `DB_CONNECTION` is unset, and `.env.example` selects SQLite. Confirm the intended PostgreSQL environment and provision pgvector there before this workflow is implemented or enabled. Do not add unrelated Laravel AI SDK capabilities as part of this foundation.
