# 12-K — Program Source Library and Author-Reviewed Curriculum Generation

## Status and relationship to existing plans

**Status: implementation complete.** Phases 1–4 acceptance checks are complete. Phase 2 retired the subject-file product surface while preserving the legacy model and table for archive compatibility. This plan changes the source-file ownership model and defines a reviewer-controlled workflow for drafting subjects, syllabus topics, and concepts from program files. Program files become the sole supported source-file product surface. Each program file may optionally be associated with one subject.

This plan relaxes the earlier requirement to reproduce the PRC syllabus exactly. The PRC syllabus remains a useful reference source, but generated subjects, topics, and concepts are author proposals. An authorized author decides what becomes canonical curriculum. Citations are optional supporting metadata and are not a generation or publication gate. This does not claim PRC compliance or source verification.

The plan supersedes the subject-file ownership and mandatory-citation assumptions in [12-D](12-d-curriculum-extraction-subjects-concepts.md) and [12-I](12-i-subject-topic-extraction-implementation-plan.md) for the future author-reviewed curriculum workflow. Preserve the existing program-wide extraction and program-file interfaces. Do not silently remove historical extraction records, source objects, or reviewer decisions. The separate ungrounded generation proposal in [12-J](12-j-ungrounded-curriculum-and-lesson-generation-mvp.md) should be reconciled with this plan before overlapping generation work is implemented.

## Goal

Maintain a single program-owned library of source files and let an author choose relevant files when asking the system to propose curriculum content. The author may generate candidate subjects for a program, topics for a selected subject, and concepts for a selected topic or subject. Reviewers can edit, approve, reject, and publish individual candidates. No generated item becomes canonical without explicit author approval.

## Product decisions

- **One file library:** `ProgramFile` is the only supported source-file model and management interface. Retire `SubjectFile` upload, update, download, deletion, and listing support after any required data migration and compatibility period.
- **Optional subject association:** Add nullable `subject_id` to program files. A file with no subject is shared program material; a file associated with a subject is relevant to that subject. A selected subject must belong to the file's program.
- **File type:** Use the existing `App\SubjectFileType` enum for program files; do not introduce a second enum or free-form type field. The supported values are `reviewer_ebook`, `reviewer_notes`, `lecture_material`, `official_reference`, `practice_material`, and `other`. Classify official syllabi, board resolutions, and similar authoritative documents as `official_reference`. Keep the original filename and descriptive metadata separate from this classification.
- **Source selection:** Generation starts from a program and selects explicit program files. For subject-scoped generation, eligible choices may include files associated with that subject plus unassigned program-wide files. The author sees the file type and optional subject association before selecting sources.
- **Author proposals:** The AI may propose subjects, hierarchical syllabus topics, and concepts based on selected material and the author's instructions. It may suggest a structure that differs from the PRC outline. It must not label output “official,” “complete,” or “PRC-compliant” unless an author explicitly supplies and approves that assertion outside the generation result.
- **Approval is required:** Each proposed subject, topic, and concept remains a private draft until an authorized author reviews and explicitly approves it. Approval can be per item; a collision or quality concern on one item must not discard unrelated accepted items.
- **Citations are optional:** A proposal can include file/page citations when the model can provide them. Citation absence, uncertainty, or a failed citation check is shown as a review note and does not prevent editing or approval. Never present optional citations as verified evidence unless they have been checked against the selected source snapshot.
- **No automatic publishing:** Generation and source selection never write directly to canonical curriculum tables. The application records the author, selected sources, model/provider and prompt version, proposal, review decisions, and approval timestamps.
- **Scope:** This plan covers source-file ownership and subject/topic/concept proposal workflows. Lesson generation, learner-facing AI, questions, and automatic curriculum publication remain separate decisions.

## Proposed data model and migration

### Program files

Retain `program_files` and extend it with nullable `subject_id` referencing `subjects`. Keep `program_id` as the owning scope. Enforce that a non-null subject belongs to the same program, in validation and, if practical for the existing database design, a database constraint.

Use the existing `file_type` column and cast it to `App\SubjectFileType`, matching `SubjectFile`. Add `file_type` to `ProgramFile`'s fillable attributes and implement the optional `subject()` relationship; keep its existing `program()` ownership relationship. Do not create a new enum or duplicate type column. Preserve private storage, content hashes, upload state, and page text/embedding lifecycle. During migration, map existing program-file types into the six supported enum values; map authoritative curriculum documents to `official_reference` and retain their more specific distinctions in metadata when needed.

### Existing subject files

Before retiring `subject_files`, inventory file rows and their downstream page text, embeddings, topic citations, and extraction snapshots. Plan an idempotent migration that moves eligible subject-file records into `program_files`, preserving object keys, hashes, metadata, upload state, and a mapping from old IDs to new IDs. Set the migrated row's `program_id` from its subject and `subject_id` to the former subject. Preserve page text and vector identity or rebuild them deliberately; do not orphan vectors or rewrite historical citations without a documented mapping.

Historical subject-extraction snapshots and published topic-source citations must remain interpretable after migration. Keep legacy identifiers or a durable old-to-new source mapping for archive reads. Do not delete an S3 object during row migration. Retire subject-file routes and UI only after the migrated files can be listed, downloaded, and selected through program-file interfaces.

### Generation and review records

Use a generation record capable of representing `subjects`, `topics`, and `concepts` proposals. Snapshot the program, optional subject/topic scope, selected file IDs and hashes, requester, provider/model, prompt/schema version, proposal, reviewer edits, decisions, timestamps, and safe failure details. Store optional citations with file/page identity and a verification state such as `unverified`, `valid`, or `invalid`; citation validation informs review but is not a publication precondition.

Keep draft proposals separate from canonical `subjects`, `syllabus_topics`, and `concepts`. Approval writes only selected, author-edited items and their valid parent relationships. Preserve approved/rejected proposal history according to an explicit retention policy.

## Generation and approval workflow

1. The author chooses a program and a generation stage: propose subjects, topics for an existing subject, or concepts for an existing subject/topic.
2. The author selects one or more program files. Subject-scoped requests can use files tagged to that subject and shared program files. Validate program ownership and any subject association.
3. Snapshot selected file IDs, hashes, titles, types, and optional subject association. Use extracted page text when available; do not require embeddings solely to read source text. If the selected files have no usable text, explain that and allow an ungrounded request only when the author explicitly chooses that mode.
4. Generate bounded structured candidate content. The model may return optional source/page references. Validate schema, size, unique draft IDs, parent references, and item counts. Validation findings are visible notes; malformed structures that cannot be safely rendered may fail generation.
5. Present candidates with source selection, optional citations and their verification state, and clear disclosure that generation is not an official-compliance determination. The author edits, reorders, selects, approves, or rejects each item.
6. On approval, re-check authorization, target ownership, proposal version, and duplicate conflicts. Surface conflicts for author resolution and permit unrelated items to be approved in the same operation. Write approved items and review audit data transactionally.
7. Show approved content as author-approved curriculum. Do not imply that optional or unchecked citations establish correctness or official coverage.

## Application design

### Source files and embeddings

- Update `ProgramFile` relationships to expose its program and optional subject; expose subject-associated and shared files to the author in the correct scope.
- Keep source files private and preserve current upload, download, update, delete, page-text, and embedding behavior for program files.
- Preserve historical extraction pages and citations when managing files; a program file with historical extraction or citation references cannot be deleted because deleting it would cascade its page text.
- Change extraction/source selection queries to use program files and their optional subject association.
- Move or reconcile page text and `file_page_embeddings` rows while preserving original file/page identity. The embedding vector is not a substitute for readable page text or citation verification.
- Remove subject-file routes and UI after migration and compatibility acceptance. Do not retire program-file management or the old program-wide extraction workflow.

### Curriculum authoring

- Add separate generation actions and review experiences for subject, topic, and concept proposals, or use a shared generation record with explicit stage and scoped parent IDs.
- Keep provider calls in queued services/agents, use the configured Laravel AI SDK, bound prompts and output sizes, and avoid logging source text, raw provider payloads, credentials, or signed URLs.
- Enforce nested program/subject/topic ownership on every request, review, approval, retry, and deletion.
- Use typed Wayfinder routes for frontend calls. Keep generated proposals private to authorized content authors.
- Add source-file selection components that show title, type, optional subject, page-text readiness, and page count. Never expose storage keys.

## Delivery phases and acceptance checks

### Phase 0 — Inventory and migration design

- [x] Inventory existing program and subject files, storage objects, page text, vectors, extraction snapshots, and published topic citations.
- [x] Choose and document the old-to-new ID and archive compatibility strategy before changing foreign keys or removing models.
- [x] Confirm every existing program-file type maps to one of the `SubjectFileType` enum values; map official syllabus and board-resolution values to `official_reference`.
- [x] Check: every existing subject-file source and downstream reference has a defined migration, retention, or archive outcome.

#### Inventory and migration decision — 2026-10-11

Read-only inspection of the configured PostgreSQL database found one program file (`program_files.id=2`) and two subject files (`subject_files.id=2,3`). All three S3 objects exist. The program file and subject file 3 have the same SHA-256 (`d11428585fc47e456fe5b63ab089f9799719735ff1f4aac2b243a50dac42048b`) but are separate S3 objects. Each has 68 page-text/vector rows; all 68 page texts and vectors match exactly, while their `source_key` values differ. The program file remains the canonical shared file so the existing program-wide source remains available without a duplicate listing.

Subject file 2 (`Accounting_Succinctly.pdf`) belongs to subject 2, has `reviewer_ebook` type, no content hash, no page text/vector rows, and no extraction or topic-citation references. Migrate it to a new program-file row associated with subject 2, retaining its existing private object, metadata, and current upload/embedding states. Do not claim it has usable text or vectors.

Subject file 3 (`Specs`) belongs to subject 1 and is the same official PRC 2022 syllabus as program file 2. It currently has `other` type, but its verified filename/content identify it as an official syllabus; classify the canonical program file as `official_reference`. Keep program file 2 shared (`subject_id = null`) to preserve its program-wide scope. Map legacy subject-file ID 3 to program-file ID 2. Do not copy or delete the duplicate S3 object during row migration; retain its private disk/key in the legacy-source mapping so historical records and later retention work can resolve it.

`program_files` currently has no `file_type` or `subject_id` column; an earlier migration removed its `file_type`. The single existing program-file type therefore has no stored value to translate. Restore `file_type` using the existing `SubjectFileType` enum, classify the PRC syllabus as `official_reference` only by its verified content hash, and leave other unclassified program files as `other`. Preserve the subject file 2 type as `reviewer_ebook` when creating its program-file row.

There are 68 program-file page rows and 68 matching subject-file page rows for the duplicate syllabus. Keep the program-file rows as canonical. The legacy ID map must let archive reads for subject file 3 resolve page text/vectors through program file 2 using the unchanged source hash and original page numbers; do not retain a second active embedding set. `file_page_embeddings.subject_file_id` currently cascades on subject-file deletion, and `syllabus_topic_sources.subject_file_id` currently becomes null. Migrate those references through the ID map before retiring either table; do not let the current delete rules erase vectors or live citation links during the transition. Program-wide extractions 4, 10, and 11 have null `program_file_id` values but their immutable `source_files` snapshots identify program file 2, so keep those snapshots unchanged. Subject extraction 3 snapshots subject file 3 and has no published topic citations; preserve that record verbatim and resolve its source through the legacy ID map. `syllabus_topic_sources` is empty, so there are no existing published citation rows to rewrite. Any citations created before retirement must carry the legacy source ID snapshot and resolve their live program-file reference through the same mapping. No extraction records or S3 objects are deleted as part of this migration.

The map must be durable and keyed by legacy source kind and ID, with a nullable program-file foreign key and retained legacy title, type, content hash, storage disk/key, and metadata needed for archive reads. Source reconciliation must be idempotent: the existing program file is reused for subject file 3 by verified hash and identical page content/vector, while subject file 2 receives one mapped program-file row. Before removing subject-file tables or routes, verify program-file access and historical extraction rendering through these mappings.

### Phase 1 — Unified program-file model

- [x] Add nullable `subject_id` to program files with same-program validation and relationships.
- [x] Migrate subject-file metadata and storage references to program files without deleting source objects; preserve/reconcile page text, vectors, and provenance.
- [x] Add program-file filters for shared files and subject-associated files.
- [x] Check: source rows, page text, embeddings, and historical provenance resolve after migration.
- [x] Check: rerunning source reconciliation does not duplicate files or reset migrated file types.

#### Phase 1 verification — 2026-10-11

Migration `2026_10_11_054054_unify_subject_files_into_program_files` is recorded in batch 11. Direct read-only PostgreSQL inspection confirms program file 2 remains shared and classified `official_reference`, subject file 3 maps to it, and subject file 2 maps to newly created subject-scoped program file 4 classified `reviewer_ebook`. All 68 page embeddings now belong to program files; no subject-file embedding rows remain. The citation table remains empty. Program-wide extractions 1, 4, 10, and 11 and published subject extraction 3 remain present with their prior source references/snapshots. Focused tests cover legacy-to-canonical page resolution, citations, shared/subject filters, the two-legacy-ID-to-one-canonical-file case, and repeated reconciliation without duplicate rows or changed migrated types.


### Phase 2 — Retire subject-file product surface

- [x] Move subject page file management to the program-file interface filtered by subject association.
- [x] Update upload, metadata editing, download, deletion, and embedding operations to use program-file ownership.
- [x] Remove subject-file management routes, requests, and pages. Keep `SubjectFile` and `subject_files` as an internal compatibility layer for historical subject-topic extraction snapshots, merge rollback data, and already-serialized queue payloads; retire them only after those consumers have a durable replacement and the compatibility period is closed.
- [x] Check: program-file CRUD works for shared and subject-associated files; subject-file URLs no longer accept new operations; historical extraction pages still resolve their source details.

### Phase 3 — Author-reviewed generation

- [x] Implement structured proposal generation for subjects, topics, and concepts from explicitly selected program files. This supersedes 12-J's ungrounded subject/topic proposal scope; lesson generation remains deferred and out of scope.
- [x] Snapshot selected sources and show optional citations with verification notes; do not block a proposal or approval only because citations are missing or invalid.
- [x] Add item-level edit, reorder, approve, reject, and conflict handling. Approval is explicit and audit-recorded.
- [x] Check: generated items stay private before approval; only selected approved items enter canonical curriculum; one conflicting item does not block independent items.

### Phase 4 — End-to-end acceptance

- [x] Demonstrate generation of candidate subjects from shared program files, topics from subject-associated/shared files, and concepts from selected sources.
- [x] Demonstrate author edits and approval for each stage, including a source with no citations and a proposal with citation notes.
- [x] Confirm content is labeled author-approved and not automatically labeled PRC-compliant or source-verified.
- [x] Confirm the legacy program-wide extraction and program-file management interfaces remain supported.

#### Phase 4 verification — 2026-10-11

The targeted acceptance run passed: `php artisan test --compact tests/Feature/CurriculumGenerationServiceTest.php tests/Feature/CurriculumGenerationControllerTest.php tests/Feature/CurriculumExtractionTest.php tests/Feature/SourceFilesTest.php` completed with 69 tests and 417 assertions. The generation tests exercise subjects, topics, concepts, citations and citation notes, author edits, approval, and persistence; the legacy feature tests cover program-wide extraction and program-file listing/management. The review screen labels approved items “Author approved” and states that approval does not certify PRC compliance or source correctness. This verifies the application workflow with a fake AI agent; no live-provider call or assessment of real generated-content quality was performed.

## Acceptance criteria

- Program files are the sole supported file-management surface; each file belongs to a program and may optionally be associated with a subject in that program.
- Authors can select shared program files and subject-associated files as sources for the appropriate generation stage.
- Source type is recorded in `ProgramFile.file_type`, cast to `SubjectFileType`, and visible during selection and review.
- Subject, topic, and concept output remains a proposal until an author explicitly approves it.
- Citations are optional. Missing, unverified, or invalid citations are disclosed as notes and do not alone block review or approval.
- PRC syllabus structure can inform a proposal, but exact PRC specification compliance is not required or claimed by this workflow.
- Migration preserves file access, text/embedding references, historical extraction records, and topic provenance, or provides a documented archive mapping before subject-file support is removed.
- Program-wide extraction and program-file management remain intact.

## Out of scope

Automatic or silent publication; claiming official compliance without review; mandatory citations; learner-facing generation; lesson/question/tutor generation; source-rights certification; production compliance review; deleting historical source objects or extraction records without a retention decision.

## Related documents

- [12-I — Subject Topic Extraction Implementation Plan](12-i-subject-topic-extraction-implementation-plan.md) — existing implementation and historical behavior to reconcile during transition.
- [12-J — Ungrounded Curriculum and Lesson Generation MVP](12-j-ungrounded-curriculum-and-lesson-generation-mvp.md) — overlaps with author-reviewed ungrounded curriculum proposals and must be consolidated before implementation.
- [12-B — Program and Subject File Embedding Inputs](12-b-source-documents-content-mapping.md) — existing page-text and embedding identity model to adapt for program-owned files.
- [11 — Program and Subject Files S3 Development Plan](11-program-subject-files-s3-development-plan.md) — current two-scope file library plan; revise its subject-file assumptions when implementing this plan.
