# 12-D — Official Curriculum Extraction: Deferred

## Status

This feature is outside the first AI MVP. The current delivery is limited to VoyageAI multimodal embeddings for program and subject files, stored in PostgreSQL with pgvector.

## Future goal

After file embeddings are operating reliably, a later phase may propose official subjects and syllabus structure from approved program files. Subject concept candidates are covered separately by the RAG roadmap in [12-H](12-h-advanced-ai-features-roadmap.md). A reviewer must remain the only publisher of curriculum records.

## Deferred design notes

- Preserve official wording and cite source page numbers when extraction is eventually designed.
- Keep the official syllabus hierarchy separate from the instructional concept graph.
- Label all AI proposals as suggestions until explicitly reviewed.
- Do not use embeddings as proof of an official fact or as a substitute for source-page provenance.

## Not included in the MVP

Document classification, OCR/text extraction, subject/topic/concept extraction, structured agent schemas, reviewer publication workflows, curriculum versioning, blueprint validation, prerequisite graph suggestions, and evaluation of extraction precision/recall.

## Dependency

Revisit only after the 12-A through 12-C file-embedding MVP is complete, the product explicitly prioritizes official curriculum extraction, and the separate subject-concept RAG design has been evaluated.
