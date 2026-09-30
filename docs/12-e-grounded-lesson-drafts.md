# 12-E — Grounded Lesson Drafts

## Goal

Produce reviewer-approved lesson drafts for published concepts using only approved, mapped, and retrievable source evidence.

## Preconditions

Lesson generation is available only when the program, subject, concept, source chunks, source versions, and rights status are published or approved. The workflow returns insufficient evidence when it cannot support a requested claim.

## Workflow

1. An administrator chooses the concept, objective, depth, and teaching context.
2. `RetrievalService` applies the filters defined in 12-C and returns a bounded evidence set with chunk IDs and page/version provenance.
3. `GenerateGroundedLesson` receives only that evidence set, the learning context, lesson policy, and a strict structured-output schema.
4. It proposes title, objectives, explanatory sections, worked example, misconceptions, practice prompts, claims, and citations.
5. Application validation rejects citations outside the retrieved set, unsupported claims, malformed output, excessive duplication, and policy violations.
6. The system stores the result as a draft with prompt/schema/provider/model versions, evidence IDs, source versions, content hash, and usage/cost data.
7. A reviewer edits, approves, and publishes the lesson. Source, curriculum, policy, or model dependencies changing marks the published lesson stale.

## Acceptance criteria

- Every learner-facing claim has valid evidence from the generation run.
- Reviewers can inspect each lesson draft alongside its cited source pages.
- Published lessons are invalidated when dependent curriculum or source evidence changes.

## Dependencies

Requires 12-A through 12-C and a published concept from 12-D.
