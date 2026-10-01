# 12-B — Program and Subject File Embedding Inputs

## Goal

Use the existing private program-file and subject-file library as the input boundary for the first AI MVP. This stage extracts the existing PDF text layer page by page; it adds no source-document, semantic-chunk, curriculum, or OCR model.

## Input contract

1. An administrator uploads or selects an already uploaded `ProgramFile` or `SubjectFile`.
2. The application authorizes access to the owning program or subject and verifies the file is in its completed upload state.
3. The application computes or verifies a content hash, validates the actual content type, and dispatches an idempotent embedding job.
4. The job reads the private object through its configured storage disk. It never accepts a browser-supplied storage key or public URL.
5. The extracted page text and resulting vectors are associated with the source file record and original page number.

## MVP file support

- PDFs with a usable text layer are extracted page by page and embedded as text. Keep the original PDF in private object storage; the local copy used for extraction is a temporary processing artifact and must be removed after use.
- DOCX, EPUB, image-only PDFs, OCR, tables-to-text conversion, semantic chunking, and video are out of scope for this MVP. Leave those uploads available for ordinary file management, but show embedding as unsupported or failed with a safe error category.
- Preserve original page numbers. Skip pages that have no usable text; fail the file when no page yields usable text.

## Provenance and lifecycle

- Each page embedding retains the source file ID, page number, content hash, model, dimensions, and processing timestamps.
- An uploaded file is immutable. A replacement upload is a new file record and receives its own embeddings.
- Editing display metadata does not invalidate vectors. Replacing file bytes or changing the configured model/dimensions requires re-embedding.
- Deleting or archiving a file must also delete or deactivate its page embeddings.
- Do not approve, publish, or map source content to curriculum concepts in this stage.

## Acceptance criteria

- Administrators can distinguish supported, queued, processing, embedded, unsupported, and failed states.
- Every stored vector can be traced to one source file and page, without exposing S3 keys or signed URLs in the UI.
- Duplicate dispatches for the same unchanged file and embedding configuration do not create duplicate active vectors.

## Dependencies

Requires the existing program/subject file CRUD and the narrow SDK foundation in 12-A. This stage ends at validated source-file, page-text, and vector inputs; it does not produce semantic chunks or curriculum mappings.
