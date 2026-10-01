# 12-B — Program and Subject File Embedding Inputs

## Goal

Use the existing private program-file and subject-file library as the input boundary for the first AI MVP. This stage adds no new source-document, extracted-text, chunk-mapping, or curriculum model.

## Input contract

1. An administrator uploads or selects an already uploaded `ProgramFile` or `SubjectFile`.
2. The application authorizes access to the owning program or subject and verifies the file is in its completed upload state.
3. The application computes or verifies a content hash, validates the actual content type, and dispatches an idempotent embedding job.
4. The job reads the private object through its configured storage disk. It never accepts a browser-supplied storage key or public URL.
5. The resulting page images and vectors are associated with the source file record and original page number.

## MVP file support

- Raster image files supported by VoyageAI multimodal embeddings may be embedded directly as one page/image.
- PDF files may be rendered to one image per page and embedded page by page. Keep the original PDF in private object storage; temporary rendered images are processing artifacts and must be removed after use.
- DOCX, EPUB, OCR, text extraction, tables-to-text conversion, semantic chunking, and video are out of scope for this MVP. Leave those uploads available for ordinary file management, but show embedding as unsupported for them.
- Apply provider image size/pixel constraints before sending a page. Oversized or unrenderable pages fail visibly and can be retried after correction; do not silently skip them.

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

Requires the existing program/subject file CRUD and the narrow SDK foundation in 12-A. This stage ends at validated source-file and page-image inputs; it does not produce extracted text or curriculum mappings.
