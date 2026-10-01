# 12-C — VoyageAI Multimodal Embeddings in PostgreSQL

## Goal

Create and persist page-level multimodal vectors for eligible program and subject files. This is the first and only AI feature in the MVP. Search, retrieval, extraction, and generation are later work.

## Infrastructure gate

- The current application configuration defaults to SQLite. pgvector requires PostgreSQL; explicitly configure and provision PostgreSQL for the target environment before implementing or enabling vector writes.
- Install/enable the pgvector extension in each target database and verify it during deployment. Do not assume that the PostgreSQL server includes the extension.
- Select the VoyageAI multimodal model and output dimension before writing the vector migration. The current candidate is `voyage-multimodal-3.5` at 1024 dimensions; treat model name and dimensions as configuration and validate that the database column matches them.
- Configure the VoyageAI API key as a secret, plus the model, dimensions, maximum page size/pixels, feature flag, and bounded job retry settings.

## MVP workflow

1. An authorized administrator requests embedding for an uploaded program or subject file.
2. The job validates file status, MIME/content, hash, and supported format. It reads the object from private storage.
3. A raster image becomes one image input. A PDF is rendered to one image per page; each page is sent as an image input. Preserve the original page number. Do not send the raw PDF as if VoyageAI accepted it as a document input.
4. `FileEmbeddingService` calls the installed Laravel AI SDK `Embeddings` API with VoyageAI and the multimodal model. The installed SDK accepts image file inputs and routes the multimodal model to VoyageAI's multimodal endpoint; it does not currently expose an `input_type` setting, so do not assume document-mode prompting is applied.
5. Validate response count and vector length, then store one vector per source page with file identity, content hash, provider/model, dimensions, and timestamps.
6. Clean up temporary page images whether the request succeeds or fails. Mark the file's embedding state complete only when all required pages have vectors.
7. On content/configuration change, make old vectors inactive and re-embed. Keep the work idempotent across queue retries.

## Minimal storage design

Add a `file_page_embeddings` table with:

```text
id
program_file_id nullable FK
subject_file_id nullable FK
page_number unsigned integer
content_hash
provider
model
dimensions
embedding vector(1024)  # dimension follows the approved model configuration
embedded_at
created_at
updated_at
```

Exactly one of `program_file_id` and `subject_file_id` must be set. Add uniqueness for the active source-file/page/content-hash/model/dimension combination and indexes needed for file cleanup and status checks. Use the established Laravel/pgvector support available in the project; do not introduce a second vector database or provider-managed vector store.

Keep processing status on the existing file record if that fits its lifecycle conventions; add only the minimum fields needed to distinguish not requested, queued/processing, complete, unsupported, and failed. Avoid a generalized AI runs table for this MVP.

## Limits and failure behavior

- Process one file at a time and use conservative bounded batches; do not create a bulk-ingestion platform.
- A file is complete only when all its pages have valid embeddings. Report page-level failures without exposing provider messages or source content.
- Retry transient provider/storage failures with bounded backoff. Do not retry invalid or unsupported content automatically.
- Never compare vectors from different models or dimensions. The MVP does not run similarity queries, so vector indexes can wait until retrieval is approved.
- Avoid approximate indexes, reranking, lexical/hybrid retrieval, similarity thresholds, and retrieval evaluation at this stage.

## Acceptance criteria

- A deployment check confirms PostgreSQL, pgvector, and the configured vector dimension.
- An eligible image and a multi-page PDF each produce the expected number of page vectors, retaining source file/page provenance.
- Repeating a job does not create duplicate active vectors; changed file content or embedding configuration creates a clean replacement set.
- Unsupported input, malformed provider output, provider outage, and cleanup failure produce safe, recoverable states.
- Automated tests use Laravel AI SDK fakes and a PostgreSQL/pgvector integration environment for the vector-column path.

## Dependencies

Requires 12-A and 12-B. This MVP stores vectors only. Curriculum extraction, semantic search/retrieval, lesson generation, and tutor features remain deferred.
