# 12-D — Curriculum Extraction: Subjects, Topics, and Concepts

## Goal

Generate a reviewable curriculum draft from an uploaded PRC examination specification, board resolution, or other approved official source. The workflow proposes subjects, official syllabus structure, and instructional concept candidates; reviewers remain the only publishers.

## Stage 1: classify the reviewed document

`ClassifyBoardDocument` identifies document type and page ranges for title/authority, program, subjects, syllabus coverage, tables of specifications, references, effectivity, amendments, and exclusions. Its structured result is an extraction map with page references. It does not write curriculum records.

## Stage 2: extract official structure

`ExtractBoardCurriculum` runs against the identified reviewed pages and returns structured, page-anchored proposals:

```text
document_identity: issuer, resolution/reference number, program, effectivity, confidence, source pages
subjects[]: official name, code when printed, ordering, source pages
syllabus_topics[]: subject reference, parent reference, official title/code, ordering, source pages
blueprint_areas[]: subject reference, coverage/weight/item targets when printed, source pages
unresolved_items[]: ambiguity, missing value, conflicting pages, recommended reviewer action
```

Every official fact needs a source page and excerpt or heading reference. Application validation retains original wording before whitespace or numbering normalization.

## Stage 3: suggest concepts and learning objectives

After the official hierarchy is reviewed, `SuggestConceptCandidates` processes one subject or topic at a time. It may propose instructional concepts, concise definitions, learning-objective candidates, topic mappings, prerequisite-edge candidates, evidence, and uncertainty.

The official syllabus hierarchy is not a prerequisite graph. All proposed concepts and edges are labeled `ai_suggested` until a reviewer explicitly edits, approves, rejects, splits, or publishes them.

## Review and publication

The reviewer workspace displays the source page next to the proposed program, subjects, topics, blueprint areas, concepts, mappings, edges, and unresolved items. It supports correcting a source reference and rejecting a complete run.

Before publishing, validate:

- subject and topic codes are unique within the source version;
- parent references and ordering are valid;
- blueprint totals are internally consistent where applicable;
- every published official item cites an authoritative page;
- concept-to-topic mappings have reviewer approval;
- prerequisite edges have no self-reference, cross-subject violation, or cycle; and
- a new resolution creates a new version without overwriting the existing baseline.

Publication creates an immutable curriculum version with the source hash, extraction run, prompt/schema/provider/model versions, cited pages, reviewer, and decision timestamps.

## Acceptance criteria

- A reviewer can publish a fully traced curriculum version from one source document without manual re-entry of the complete hierarchy.
- Invalid citations, unresolved references, invalid hierarchy, or graph cycles block publication.
- No agent directly creates published programs, subjects, syllabus topics, concepts, or prerequisite edges.

## Dependencies

Requires 12-A and 12-B. Source chunk mappings from 12-B and vectors from 12-C strengthen concept evidence but do not replace official page provenance.
