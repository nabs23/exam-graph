# Reviewer App — Architecture, Curriculum Graph & RAG Design Guide

## 1. Architectural Center of Gravity

Design the system around four distinct but connected models:

1. **Curriculum model** — what must be learned and what depends on what.
2. **Knowledge-source model** — what approved evidence exists for teaching those concepts.
3. **Learning/assessment model** — what lessons, questions, quizzes, and tutoring interactions are delivered.
4. **Learner-state model** — what the learner appears to know, what they struggle with, and where they should go next.

These models should communicate through explicit identifiers and relationships. AI-generated text must not become the structure tying the system together.

## 2. Curriculum Graph

A hierarchy is useful for display, but prerequisites are not always a strict tree. A board topic may depend on multiple concepts, and a foundational concept may support several advanced topics. Use explicit edges.

### Suggested collections

#### curriculum_nodes

```text
_id
program_id
subject_id
code
title
slug
description
learning_objectives[]
depth_hint
board_level
status
mastery_policy_id
created_at
updated_at
```

`depth_hint` is for presentation; it should not define the graph.

#### prerequisite_edges

```text
_id
prerequisite_node_id
dependent_node_id
relationship_type
weight_or_importance
notes
status
created_at
updated_at
```

Primary invariant:

```text
prerequisite_node_id != dependent_node_id
AND insertion must not create a cycle
```

Materialized ancestors, common path labels, board-topic roots, and descendant counts may be cached for navigation but are derived data.

## 3. Source Knowledge Architecture

### source_documents

```text
_id
title
author
edition
publisher
program_ids[]
subject_ids[]
source_type
file_reference
rights_notes
content_hash
ingestion_status
publication_status
created_at
updated_at
```

### content_chunks

```text
_id
source_document_id
chunk_index
text
heading_path[]
page_start
page_end
token_count
curriculum_node_ids[]
embedding
embedding_model
embedding_version
content_hash
status
created_at
updated_at
```

If vector fields become operationally expensive in the same collection, isolate vector records behind a repository/service abstraction. The domain should reference chunk IDs, not a specific vector vendor.

### Chunking Rules

Prefer semantically coherent chunks over fixed-length slicing. A chunk should retain the concept boundary, sufficient explanatory context, page/heading provenance, and should avoid joining unrelated subtopics.

## 4. Taxonomy Mapping

Every chunk may map to one or more curriculum nodes.

Suggested mapping metadata:

```text
chunk_id
node_id
mapping_source = manual | ai_suggested | imported
confidence
review_status
reviewed_by
reviewed_at
```

Do not silently promote an AI-suggested mapping into authoritative curriculum structure.

## 5. RAG Retrieval Pipeline

### Retrieval Inputs
- target curriculum node;
- learner question or lesson objective;
- subject/program scope;
- optional current lesson context;
- source-publication filters;
- desired number of chunks.

### Retrieval Stages

1. identify the authoritative target node;
2. filter candidate corpus by program/subject/node;
3. run semantic/vector retrieval;
4. optionally combine lexical search;
5. rerank or score;
6. remove near-duplicates;
7. preserve provenance;
8. pass bounded evidence to generation.

### Controlled Retrieval Expansion

If evidence is insufficient:
1. search chunks directly mapped to the target node;
2. widen to closely related prerequisite/dependent nodes;
3. widen to the subject only when policy allows;
4. otherwise return an insufficient-evidence state.

Do not automatically fall back to unrestricted model knowledge for formal reviewer content.

## 6. Generation Architecture

Use provider-neutral services:

```text
LearningGenerationService
TutorService
AssessmentGenerationService
EmbeddingService
RetrievalService
```

A lesson generation request should include target node, learning objectives, learner context, retrieved chunks, generation policy, depth/difficulty, and output schema.

Prefer structured generation output with title, objectives, explanation sections, worked examples, misconceptions, practice prompts, source chunk IDs, and model metadata.

Generation rules:
- use supplied evidence for factual course content;
- distinguish evidence-backed explanation from analogy;
- do not invent citations/references;
- return insufficient evidence when needed;
- preserve formulas and technical terminology accurately.

## 7. Generated Asset Lifecycle

Suggested fields:

```text
_id
node_id
asset_type
title
structured_content
rendered_content
source_chunk_ids[]
source_document_versions[]
prompt_version
model_provider
model_name
model_version
generation_parameters
review_status
publication_status
content_hash
stale
created_at
updated_at
```

Mark assets stale when source content, curriculum semantics, or relevant generation policy changes materially.

## 8. Assessment Architecture

### questions

```text
_id
question_type
stem
choices[]
answer
rationale
difficulty
source_chunk_ids[]
generation_metadata
review_status
publication_status
created_at
updated_at
```

### question_node_links

```text
question_id
node_id
role = primary | prerequisite_signal | supporting
weight
```

A question may provide evidence for multiple nodes with different weights.

## 9. Learner Mastery Architecture

### user_node_mastery

```text
user_id
node_id
state
mastery_score
confidence
attempt_count
last_assessed_at
last_mastered_at
needs_review_at
evidence_summary
updated_at
```

Mastery should be derived from assessment evidence, not from simply viewing or completing a lesson.

### assessment_attempts

Store immutable attempt evidence:

```text
user_id
assessment_id
question_id
node_context_id
answer
correct
response_time_ms
attempted_at
remediation_session_id
```

### remediation_sessions

Retain:
- original target node;
- trigger attempt;
- selected prerequisite path;
- completed nodes;
- current status;
- return-to-target result.

This prevents deep remediation from losing the learner’s original goal.

## 10. Diagnostic Logic

Begin with explainable rules.

Example:
1. board-level question fails;
2. question maps strongly to node A and moderately to prerequisite B;
3. if A is weak and B lacks evidence, test B;
4. if B fails, recommend B or a deeper prerequisite;
5. stop descending when mastery is adequate, no deeper prerequisite exists, or confidence is too low and more diagnostic evidence is required.

Do not begin with opaque ML classification; the curriculum graph already provides a strong explainable model.

## 11. Suggested Laravel Service Boundaries

```text
CurriculumGraphService
PrerequisiteValidationService
CurriculumNavigationService

SourceDocumentService
IngestionPipelineService
ChunkingService
TaxonomyMappingService
EmbeddingService
RetrievalService

DiagnosticService
MasteryService
RemediationService

LearningGenerationService
AssessmentGenerationService
TutorService

GeneratedAssetService
ContentPublicationService
```

Keep controllers thin. Queue jobs call services rather than owning the domain logic.

## 12. Likely Queue Jobs

```text
ExtractSourceDocument
CleanExtractedText
ChunkSourceDocument
GenerateChunkEmbeddings
SuggestChunkTaxonomyMappings
ReindexDocument
GenerateNodeLesson
GenerateNodePracticeQuestions
RegenerateStaleAsset
RecalculateUserMastery
```

Use idempotent job design. Re-running ingestion should not duplicate chunks or embeddings for identical source versions.

## 13. UI Surfaces

### Learner
- Dashboard
- Diagnostic
- Subject overview
- Curriculum explorer
- Node learning page
- Practice/quiz
- Remediation path
- AI tutor panel
- Mastery analytics
- Mock exam

### Admin
- Curriculum graph editor
- Source library
- Ingestion job detail
- Chunk viewer
- Taxonomy mapping review
- Question bank
- Generated asset review
- Publication queue

## 14. Architectural Anti-Patterns

Avoid:
- one nested MongoDB document containing the entire course;
- hard-coded Level 1–4 columns;
- vector similarity without curriculum filtering;
- generated lessons without source provenance;
- letting the LLM decide mastery directly;
- unreviewed publication of high-stakes generated content;
- business rules hidden only inside prompts;
- introducing a graph database before evidence of need;
- discarding source chunks after generating summaries.

