# 12-C — VoyageAI Embeddings and pgvector Retrieval

## Goal

Generate embeddings for approved source chunks with VoyageAI and store them in PostgreSQL through the pgvector extension, while enforcing program, version, and content-mapping filters before similarity search.

## Infrastructure requirements

- PostgreSQL is the application’s primary connection.
- pgvector must be installed in the PostgreSQL server and enabled for the application database with `CREATE EXTENSION IF NOT EXISTS vector`. PostgreSQL alone does not include vector types or vector indexes.
- The chosen VoyageAI embedding model and its native or configured dimension count determine the `vector(n)` column dimension. Do not ship migrations with a placeholder dimension that differs from the selected model.
- Configuration includes the VoyageAI API key, embedding model, dimensions, feature flag, batch size, request limit, minimum similarity, and maximum candidate chunks.

## Workflow

1. A reviewer approves a source chunk and its content mappings.
2. `EmbeddingService` checks the chunk status, rights, source version, configured model, and content hash.
3. It sends only the approved chunk text to VoyageAI through Laravel AI SDK embeddings, records the provider/model/dimensions/input hash, and stores the vector on the chunk or its immutable embedding record.
4. A content change, rejection, source supersession, or embedding-model change invalidates the prior vector and queues replacement work only when approved.
5. `RetrievalService` first filters by publication state, program, subject, concept mapping, source version, chunk status, and rights. It then runs pgvector similarity on the filtered candidates.
6. If the result set cannot satisfy the configured evidence threshold, it returns an explicit insufficient-evidence result.

## Retrieval and database design

- Use pgvector distance operators and an index suitable for the selected metric and corpus size. Evaluate exact search first; add an approximate index only after measuring recall and latency on the gold set.
- Store embedding provider, model, dimensions, input hash, generated timestamp, and source-chunk identifier with each vector.
- Never compare vectors produced by incompatible models or dimensions.
- Keep chunk IDs, page numbers, document versions, and mapping status in every retrieval result so citations can be validated later.
- Reranking is a separate, disabled-by-default capability. Enable it only after selecting a provider that supports the SDK reranking contract and after approving cost and data handling.

## Acceptance criteria

- A search can return only approved, in-scope chunks and includes page/source-version provenance.
- Cross-program, rejected, stale, unmapped, or superseded chunks cannot appear in results.
- A migration and deployment check fail clearly when pgvector is unavailable.
- Retrieval quality, latency, and cost are measured against a rights-cleared corpus before enabling production use.

## Dependencies

Requires 12-A and 12-B. It enables the evidence layer used by 12-D, 12-E, and 12-F.
