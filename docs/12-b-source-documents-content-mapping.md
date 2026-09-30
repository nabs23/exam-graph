# 12-B — Source Documents and Content Mapping

## Goal

Turn uploaded PRC board resolutions, examination specifications, and related authoritative documents into reviewed, page-aware source content that can safely support extraction and retrieval.

## Workflow

1. An administrator uploads a document and supplies issuer, resolution/reference number, publication/effectivity information, document type, source reference, intended program when known, rights/usage record, and reviewer identity.
2. The system validates authorization, MIME type, extension, size, checksum, duplicate content hash, and malware scan result. It stores the original immutably.
3. A versioned source-document record tracks the original file, metadata, source version, status, and audit history.
4. An idempotent job extracts the existing PDF text layer by page. It records extraction tool/version, raw page text, cleaned text, page boundaries, warnings, coverage, and missing or unusable pages.
5. A reviewer approves, rejects, or requests correction for the extracted source before it is available to downstream AI features.
6. The system splits approved pages into bounded source chunks. A reviewer maps each chunk to the relevant program, subject, syllabus topic, and concept where applicable, with a rationale and status.

## Content mapping rules

- A source chunk always belongs to one source document and page; chunks preserve the source document version and rights metadata.
- Mapping a chunk to a subject, topic, or concept requires that target to belong to the document’s program and current reviewed hierarchy.
- Rejected chunks retain no approved mappings or embeddings.
- A new source version never overwrites existing chunks, mappings, or published content. It creates a new review and publication path.
- Low-quality, image-only, incomplete, or table-heavy pages go to human review. The system does not silently repair official wording with AI.

## Acceptance criteria

- A reviewer can inspect the original document alongside every extracted page and chunk.
- Only approved, rights-cleared chunks may enter embedding, extraction, lesson, or tutor workflows.
- Every mapped chunk can be traced to a source document version and page number.

## Dependencies

Requires 12-A. Feature 12-C embeds only approved chunks; 12-D extracts official hierarchy from reviewed pages.
