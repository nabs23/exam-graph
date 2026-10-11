# 12-D — Subject-Scoped Syllabus Topic Extraction

## Status and direction

This is the current design for official syllabus-topic extraction. It supersedes the previously implemented program-wide workflow that proposed subjects and topics from program files. Administrators create program subjects manually; extraction works only within one existing subject and never creates, merges, or renames subjects.

The subject-first workflow is planned and is not yet implemented. See [the current handoff](next-tasks-handoff.md) for implementation and operational follow-ups.

## Goal

Help a reviewer turn selected source files attached to an existing subject into a complete, editable hierarchy of syllabus topics, preserving official codes and page citations. Only explicit reviewer publication writes topics to the subject.

## Scope

- Start an extraction for one existing subject using an explicit selection of that subject's source files.
- Reuse the selected files' stored page text and preserve source-file identity, content hash/version, and original page number. Do not accept files owned by another subject.
- Support official hierarchical curricula as well as non-outline sources. For a numbered official outline, extract every source-supported item, preserve its official code and order, and derive each parent from the supplied hierarchy. For non-outline material, propose relevant topics without inventing official codes.
- Keep each proposal private and reviewer-controlled. The reviewer can edit, exclude, reorder, publish, or reject proposed topics.
- Publish only `syllabus_topics` under the existing subject, in one transaction. Do not create or merge subjects, instructional concepts, concept mappings, or prerequisite relationships.

## Proposal record and provenance

Each extraction records the subject, selected source-file snapshot (file IDs, titles, and hashes), snapshot hash, provider, model, prompt/schema version, requester, proposal, review outcome, lifecycle state, and reviewer/timestamps. Every proposed topic cites one or more `subject_file_id` and page-number pairs from the selected snapshot. Any displayed supporting excerpt must come from those cited pages.

If a selected file changes or is removed after extraction, publication is rejected and the reviewer must create a new extraction. Files added after extraction do not silently enter the snapshot. Limit handling for source sets that exceed the configured input bound must be decided before implementation; see the handoff's operational follow-ups.

## Extraction and review safeguards

1. The content administrator selects an existing subject and one or more completed subject source files.
2. The application verifies ownership and eligibility, snapshots the source versions, and sends only bounded page text from that snapshot to the configured provider/model.
3. Validate the structured proposal, hierarchy, official code preservation, bounds, and every cited file/page pair against the snapshot. Reject incomplete or malformed official hierarchies before review; do not silently publish a partial outline.
4. Present each proposal with its source citations for reviewer comparison. The reviewer may edit, exclude, reorder, publish selected topics, or reject the extraction.
5. Before publication, recheck the subject, snapshot versions, proposal state, hierarchy, and topic-code uniqueness. Write the selected hierarchy atomically to the existing subject. Rejection or exclusion creates no corresponding topic records.

Use safe failure categories and a correlation identifier for provider failures. Do not log provider payloads, source text, credentials, or signed storage URLs. Keep extraction gated until processing rights, provider terms, and the reviewer pilot are approved for the selected source set.

## Evaluation

Evaluate at least one official hierarchical curriculum, questionnaire, reviewer textbook, and set of notes. Measure hierarchy completeness, citation accuracy, duplicate handling, and reviewer workload against thresholds agreed before the pilot. For the LECPA FAR official source, verify that the `4.2` PPE branch is reproduced through `4.2.5.1.1`–`4.2.5.1.3`, with correct parents, ordering, and citations.

## Acceptance criteria

- Only authorized content administrators can create, review, publish, or reject an extraction.
- Selected files must belong to the existing subject; proposals cannot create or merge subjects.
- Every proposed topic is traceable to a selected source file and page; invalid citations cannot be reviewed or published.
- Numbered official outlines retain all source-supported items, codes, order, and correct parentage; incomplete or invalid hierarchies cannot be published.
- Changed or removed snapshot files prevent publication; newly added files do not alter an existing snapshot.
- Reviewer edits, exclusions, ordering, and rejection are respected. Publication writes selected topics to the existing subject in one transaction.
- No instructional concepts, concept mappings, prerequisites, or AI-authored official facts are created by this workflow.
- The source-set evaluation passes its agreed completeness, citation, duplicate, and reviewer-workload thresholds before rollout.

## Explicitly out of scope

Subject creation or merging, concept extraction, concept-to-topic mapping, prerequisite generation, OCR without a separate approval, lesson/question generation, learner-facing AI, semantic retrieval, autonomous publication, and official syllabus version management.
