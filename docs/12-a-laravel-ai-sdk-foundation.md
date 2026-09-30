# 12-A — Laravel AI SDK Foundation

## Goal

Install and configure Laravel AI SDK so AI capabilities are observable, bounded, and testable before they operate on official curriculum sources.

## Scope

- Add `laravel/ai` using Composer and publish only the configuration and migrations required by the installed SDK version.
- Define named, environment-driven provider configuration for generation and embeddings. Keep provider selection behind application services rather than controllers.
- Establish focused agent classes for classification, curriculum extraction, concept suggestions, grounded lessons, tutor answers, and practice-question drafts.
- Define `ai_runs` with state, idempotency key, attempt count, provider, model, prompt/schema version, latency, usage/cost, hashes, failure category, reviewer disposition, and timestamps.
- Configure queues, bounded retries/backoff, per-provider rate limits, and a workflow feature flag for every external call.
- Add Laravel AI fakes to support deterministic feature tests without provider traffic.

## Design

Use the SDK interfaces that match the task: `Promptable` agents for generation, `HasStructuredOutput` for reviewable records, `Embeddings` for vectors, and `Reranking` only after confirming provider support. Application services remain the domain boundary:

- `CurriculumExtractionService`
- `ConceptCandidateService`
- `EmbeddingService`
- `RetrievalService`
- `LearningGenerationService`

Each service receives a versioned input, records an idempotent AI run, and returns a validated application result. A controller must never persist an unvalidated provider response directly.

## Acceptance criteria

- A fake structured-output run can be queued, observed, retried, and reviewed without reaching a provider.
- Disabled workflow flags prevent external calls.
- Provider credentials and model names come only from configuration.
- Logs and failures exclude source text and personal data unless operationally required and approved.

## Dependencies and rollout gate

This is the prerequisite for all remaining 12-series features. Select a provider/model and approve its data handling, rate limits, and budget before enabling any real workflow.
