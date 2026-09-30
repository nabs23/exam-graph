# 12-F — Learner Tutor and Practice Drafts

## Goal

Provide constrained, source-cited learner help and reviewer-approved practice-question drafts without making autonomous mastery, licensure, or high-stakes decisions.

## Tutor flow

`TutorConceptQuestion` accepts a single question scoped to the active concept. It requires a learner confirmation that the prompt contains no personal information, applies per-user and provider limits, retrieves only eligible evidence, and returns a cited answer or an insufficient-evidence response.

Streaming may be added after the non-streaming evidence and citation path is proven. Retain only the minimum study-session data required by the approved retention policy; do not create persistent chat history by default.

## Practice-draft flow

`GeneratePracticeQuestionDraft` uses bounded approved evidence to propose low-stakes questions, answer options, rationale, objective mapping, difficulty rationale, and citations. A reviewer validates duplicates, factual accuracy, calibration, answer keys, and source support before publication.

## Guardrails

- Do not send learner PII, hidden answers, or unnecessary source material to providers.
- Do not assert learner mastery, diagnose disability, give legal or professional advice, or claim licensure readiness.
- Do not publish questions or answer keys without review.
- Keep answers tied to retrieved evidence and name uncertainty when evidence is insufficient.

## Acceptance criteria

- Responses display source citations that are valid for the active program, concept, and source version.
- Limits and feature flags block use before a provider or learner data policy is approved.
- Tutor and draft-generation failures do not expose source text, private answers, or provider errors to learners.

## Dependencies

Requires 12-A through 12-E. Enable only after retrieval and lesson quality meet the rollout criteria in 12-G.
