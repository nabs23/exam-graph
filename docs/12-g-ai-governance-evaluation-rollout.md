# 12-G — AI Governance, Evaluation, and Rollout

## Goal

Measure and control AI features before and after release so they remain accurate, reviewable, private, and economically sustainable.

## Evaluation set and metrics

Maintain a small rights-cleared corpus of representative PRC documents and reviewer-approved expected records. Evaluate each relevant feature using:

- field-level curriculum extraction precision and recall;
- page-citation accuracy;
- concept-mapping agreement and prerequisite-graph validity;
- retrieval recall, source/version filtering, and insufficient-evidence accuracy;
- grounded-lesson claim and citation accuracy;
- reviewer correction/rejection rate;
- processing time, provider usage, and cost per document or response.

## Security and privacy review

Before a feature is enabled, review document upload, malware scanning, provider payloads, access control, logs, queue failures, tutor interactions, retention/deletion, and source rights. Verify the organization’s actual provider data settings, contractual terms, account limits, and approved budget.

## Test requirements

Use Laravel AI SDK fakes for agents, embeddings, and reranking. Cover schema and application-validation rejection, authorization, duplicate upload/idempotency, source-page provenance, version isolation, retrieval filtering, insufficient evidence, absent citations, provider failure, rate limits, stale generated assets, and publication controls.

## Release gates

1. Enable each workflow only after its configuration, feature flag, security review, evaluation threshold, and reviewer ownership are approved.
2. Pilot document intake and extraction with one representative resolution/specification.
3. Pilot embeddings and retrieval against the same rights-cleared corpus, including a PostgreSQL/pgvector deployment check.
4. Pilot lesson drafts with qualified reviewers and measure correction rate.
5. Enable learner-facing tutor or practice drafts only after the retrieval and lesson pilots meet documented thresholds.

## Non-goals

- AI does not publish official curriculum, prerequisite edges, lessons, or questions autonomously.
- Model knowledge does not replace an official resolution or approved instructional source.
- SDK file/vector storage does not replace the application’s authoritative source and provenance record.
