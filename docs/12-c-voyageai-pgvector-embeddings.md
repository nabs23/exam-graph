# 12-C — VoyageAI Text Embeddings in PostgreSQL

## Goal

Create and persist page-level text vectors for eligible program and subject PDFs. This is the first and only AI feature in the MVP. Search, retrieval, semantic chunking, and generation are later work.

## Infrastructure gate

- The current application configuration defaults to SQLite. pgvector requires PostgreSQL; explicitly configure and provision PostgreSQL for the target environment before implementing or enabling vector writes.
- Install/enable the pgvector extension in each target database and verify it during deployment. Do not assume that the PostgreSQL server includes the extension.
- Select the VoyageAI text model and output dimension before writing the vector migration. The current candidate is `voyage-4` at 1024 dimensions; treat model name and dimensions as configuration and validate that the database column matches them.
- Configure the VoyageAI API key as a secret, plus the model, dimensions, maximum page count, text-extractor binaries and timeouts, feature flag, and bounded job retry settings. The runtime checks the actual `file_page_embeddings.embedding` column type against the configured dimension before any vector write.

## MVP workflow

1. An authorized administrator requests embedding for an uploaded program or subject file.
2. The job validates file status, MIME/content, hash, and supported format. It reads the object from private storage.
3. The job extracts the PDF text layer page by page with `pdftotext`. It preserves the original page number, excludes empty pages, and fails safely when no usable text exists. Do not send the raw PDF to the embedding endpoint.
4. `FileEmbeddingService` calls the installed Laravel AI SDK `Embeddings` API with VoyageAI and the configured text model, supplying the extracted page text strings.
5. Validate response count and vector length, then store one vector and the corresponding extracted text per usable source page with file identity, content hash, provider/model, dimensions, and timestamps.
6. Clean up the temporary local PDF whether the request succeeds or fails. Mark the file complete only when every extracted page has a valid vector.
7. On content/configuration change, replace the file's previous vector set in one database transaction and re-embed. Keep the work idempotent across queue retries.

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
content text
embedding vector(1024)  # dimension follows the approved model configuration
embedded_at
created_at
updated_at
```

Exactly one of `program_file_id` and `subject_file_id` must be set. The source key, page, and model are unique, and re-embedding replaces that source's existing set atomically. Add indexes needed for file cleanup and status checks. Use the established Laravel/pgvector support available in the project; do not introduce a second vector database or provider-managed vector store.

Keep processing status on the existing file record if that fits its lifecycle conventions; add only the minimum fields needed to distinguish not requested, queued/processing, complete, unsupported, and failed. Avoid a generalized AI runs table for this MVP.

## Limits and failure behavior

- Process one file at a time and use conservative bounded batches; do not create a bulk-ingestion platform.
- A file is complete only when all its pages have valid embeddings. Persist only safe failure categories; never expose provider messages or source content.
- Retry transient provider/storage and temporary cleanup failures with bounded backoff. Invalid PDFs, extraction failures, page-limit violations, and malformed provider output fail without automatic retries and can be retried manually after review; unsupported content is not retried automatically.
- Never compare vectors from different models or dimensions. The MVP does not run similarity queries, so vector indexes can wait until retrieval is approved.
- Avoid approximate indexes, reranking, lexical/hybrid retrieval, similarity thresholds, and retrieval evaluation at this stage.

## Acceptance criteria

- A deployment check confirms PostgreSQL, pgvector, and the configured vector dimension.
- A multi-page text PDF produces the expected number of page vectors, retaining source file/page provenance and extracted text.
- Repeating a job does not create duplicate active vectors; changed file content or embedding configuration creates a clean replacement set.
- Unsupported input, malformed provider output, provider outage, and cleanup failure produce safe failure categories. Transient provider/cleanup errors retry with bounded backoff; malformed provider output fails without automatic retries and can be manually retried.
- Automated tests use Laravel AI SDK fakes and a PostgreSQL/pgvector integration environment for the vector-column path. Run `php artisan test --configuration=phpunit.pgvector.xml` only against the dedicated `exam_graph_testing` database after pgvector has been enabled and the database has been provisioned.

## Dependencies

Requires 12-A and 12-B. This MVP stores vectors only. Curriculum extraction, semantic search/retrieval, lesson generation, and tutor features remain deferred.
