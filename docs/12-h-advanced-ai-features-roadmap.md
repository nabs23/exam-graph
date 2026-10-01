# 12-H — Advanced AI Features Roadmap

## Purpose

This document describes AI features that come after the foundational file-embedding work in 12-A through 12-C. Nothing in this roadmap is part of the basic embedding MVP. Each feature needs a separate implementation decision, evaluation, and rollout gate.

## Priority 1 — RAG for subject concept creation

### Goal

Help reviewers create a structured set of instructional concepts for a subject by retrieving relevant evidence from that subject's approved files and asking an LLM to propose concepts grounded in that evidence. The result is a draft for reviewer correction and approval; it never writes or publishes curriculum records automatically.

### Why this follows the embedding foundation

The basic MVP stores page-level text and text vectors, but its page-sized evidence is too broad for reliable RAG. RAG needs a reviewer-controlled chunking pipeline in addition to vector storage. It also needs a reviewer workflow and a structured-output contract. These are intentional later steps, not reasons to expand the basic embedding MVP.

### Proposed workflow

1. A reviewer selects one subject and the specific uploaded files that may be used as evidence.
2. The ingestion workflow reuses the stored PDF text layer page by page and runs OCR only for pages without usable text after OCR is approved. It retains the original wording, page number, file ID, file hash/version, extraction method, and warnings. Unsupported or low-quality pages are flagged for review.
3. The application divides approved extracted text into bounded, page-aware chunks. Each chunk remains traceable to its subject file and page range. A reviewer can exclude unsuitable material.
4. The application creates text embeddings for those chunks and stores them in pgvector with the subject/file/page filters needed for retrieval. Evaluate the chosen text model against the expected queries before building the feature.
5. For a subject and optional approved syllabus topic, the reviewer requests concept candidates. Retrieval is restricted to the selected subject and approved files; the response cannot draw evidence from another subject.
6. The LLM receives only the retrieved evidence and a versioned structured-output schema. Each proposed concept includes a stable draft identifier, title, concise definition, learning objectives, source chunk/page citations, and uncertainty notes. Prerequisite edges are excluded from the first RAG iteration.
7. Application validation verifies schema shape and that every cited chunk was in the retrieval result. The reviewer compares each proposal with the source pages and can edit, approve, reject, or request another draft.
8. Only explicit reviewer action creates or updates concept records. Persist the evidence IDs, source versions, prompt/schema version, provider/model, and reviewer decision for each approved result.

### First structured concept proposal

```text
subject_id
syllabus_topic_id nullable
concepts[]:
  draft_id
  title
  definition
  learning_objectives[]
  evidence[]:
    source_file_id
    source_version_or_hash
    page_number
    chunk_id
    supporting_excerpt
  uncertainty_notes[]
```

Do not ask the model to invent concept IDs, official syllabus codes, prerequisite edges, mastery rules, or publication status. The application owns identifiers and lifecycle state.

### Required safeguards and quality checks

- Enforce subject ownership and selected-file scope in the retrieval query itself.
- Preserve page-level provenance through extraction, chunking, retrieval, and generated output.
- Reject citations that do not point to retrieved evidence; show insufficient evidence instead of generating unsupported concepts.
- Evaluate retrieval relevance, subject-boundary isolation, page citation accuracy, structured-output validity, reviewer edit/rejection rate, latency, and cost on a small rights-cleared set.
- Keep all concept candidates private drafts until reviewed. Log metadata and safe failure categories, not complete source text or signed storage URLs.
- Make re-ingestion idempotent and invalidate chunks/vectors when source content changes.

### Explicitly out of scope for the first RAG iteration

Program-wide official syllabus extraction, automatic concept publication, graph/prerequisite generation, lesson generation, learner tutoring, practice-question generation, hybrid search, reranking, agent tools, and autonomous multi-step agents.

## Later advanced features

Consider these only after subject concept RAG is reviewed and stable:

1. Official program/syllabus extraction with page-cited reviewer approval.
2. Concept mapping and reviewer-controlled prerequisite edges.
3. Grounded lesson drafts from approved concepts and source evidence.
4. Low-stakes practice question drafts with reviewer approval.
5. Learner-facing tutor responses after privacy, retention, and evidence quality gates.

## Entry gate

Begin Priority 1 only after the basic file-embedding MVP is complete, PostgreSQL/pgvector is operating in the target environment, and the product approves PDF text extraction/OCR, chunk review, the LLM provider/model, and the reviewer workflow. Do not implement this roadmap as part of 12-A through 12-G.
