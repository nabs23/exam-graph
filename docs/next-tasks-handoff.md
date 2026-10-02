# Curriculum Extraction Handoff Checklist

## Purpose

Use this document as the ongoing handoff and working note for the official-curriculum extraction work. Add current observations, decisions, blockers, and the next action in the section below when stopping or resuming work. Mark checklist items complete only after their acceptance checks have been performed in the target environment.

## Manual notes and immediate next actions

Add dated notes here. Keep the newest entry first.

<!--
### YYYY-MM-DD — Short title

- Context or observation:
- Decision or question:
- Next action:
-->

## Current state

- [x] Program PDFs are uploaded, page text is extracted, and embeddings provide the readiness gate.
- [x] An administrator can create a separate extraction proposal from all eligible files in one program.
- [x] Each proposal records the exact source-file IDs, titles, content hashes, provider, model, prompt version, requester, and lifecycle state.
- [x] The extraction proposes official subjects and recursive syllabus topics with file/page citations. It does not create instructional concepts.
- [x] A reviewer can inspect, edit, publish, or reject a proposal. Publishing creates the selected `subjects` and `syllabus_topics` records in one transaction after the source snapshot is rechecked.
- [x] The program extraction history lists all proposals and links to each proposal detail page.
- [x] Failed proposals and proposals without a `reviewed_proposal` can be deleted. Published proposals with a reviewed proposal are retained.
- [x] A source snapshot can be extracted again when the configured provider, model, or prompt version changes.

## Resume here: operational verification

- [ ] Configure the intended provider, model, API key, and queue worker in the target environment.
  - Check: an extraction reaches `reviewing` from a program with completed PDF embeddings.
- [ ] Run an extraction against a small, rights-cleared multi-PDF program.
  - Check: every cited file/page pair exists in the proposal snapshot and the reviewer can identify the supporting text.
- [ ] Publish a small reviewed selection.
  - Check: the intended subject and full nested topic hierarchy appear once, the parent relationships are correct, and the extraction is `published`.
- [ ] Change a source PDF after an extraction reaches review.
  - Check: publishing stops with `source_changed`; create a new extraction for the new file snapshot.
- [ ] Retry with a changed configured model.
  - Check: a new proposal queues while a prior proposal for the same source snapshot remains available in history.
- [ ] Verify the feature against the target PostgreSQL instance with pgvector installed.
  - Check: the pgvector-specific extraction tests run there and page embedding readiness behaves as expected.

## Known operational follow-ups

- [ ] Improve provider-failure diagnostics without exposing provider payloads or source text. Record a safe correlation identifier and retain the actionable failure category shown to administrators.
- [ ] Decide how to handle programs whose combined page text exceeds `AI_OFFICIAL_CURRICULUM_MAX_CHARACTERS`.
  - Options to evaluate: increase the environment limit for a bounded pilot, choose an explicit subset of program files, or use a staged extraction design that preserves file/page evidence.
  - Check: the chosen approach has documented cost, latency, citation, and reviewer implications.
- [ ] Define a reviewer evaluation set and acceptance thresholds for subject completeness, topic hierarchy, and citation accuracy before wider rollout.
- [ ] Confirm provider terms, retention, and rights to process every pilot document; keep the extraction feature gated until this review is complete.

## Next product work

- [ ] Decide whether official curriculum versioning is required before supporting revisions to a published program curriculum.
- [ ] Design extraction of instructional concepts from approved subjects and syllabus topics. Concepts must remain separate from official curriculum records and require independent reviewer approval.
- [ ] Plan source-grounded lesson drafts after concept extraction is established. See [12-E](12-e-grounded-lesson-drafts.md).
- [ ] Define retrieval, evaluation, governance, and rollout requirements for later learner-facing AI features. See [12-F](12-f-learner-tutor-practice-drafts.md) and [12-G](12-g-ai-governance-evaluation-rollout.md).

## Useful references

- [Official curriculum extraction design](12-d-curriculum-extraction-subjects-concepts.md)
- [AI integration plan](12-laravel-ai-sdk-integration-plan.md)
- [Advanced AI features roadmap](12-h-advanced-ai-features-roadmap.md)
- [Executable development plan](08-executable-development-plan.md)
