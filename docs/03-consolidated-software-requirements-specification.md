# Reviewer App — Consolidated Software Requirements Specification

## 1. Purpose

This SRS defines the functional and conceptual requirements for an adaptive PRC licensure examination reviewer focused on deep prerequisite remediation and source-grounded AI-assisted learning.

## 2. Product Goals

The system shall:

1. organize a PRC licensure curriculum from board-level topics down to foundational prerequisite concepts;
2. diagnose topic and prerequisite weaknesses;
3. provide learning materials, not only questions;
4. retrieve relevant material from an approved corpus;
5. use AI to adapt explanations, lessons, practice, and tutoring to the learner;
6. verify mastery at lower levels and guide the learner upward;
7. measure readiness at board-exam level.

## 3. User Roles

### 3.1 Learner
May:
- create and manage a review profile;
- choose or be assigned a licensure program;
- take diagnostics;
- navigate curriculum nodes;
- consume lessons;
- answer practice and assessment questions;
- drill down to prerequisites;
- interact with the AI tutor;
- view mastery and progress.

### 3.2 Content Administrator
May:
- manage exams, subjects, curriculum nodes, and relationships;
- upload and manage source materials;
- review extracted/chunked content;
- map content to curriculum nodes;
- manage questions;
- review AI-generated learning assets;
- publish/unpublish content.

### 3.3 System / AI Worker
Performs:
- extraction and cleaning;
- chunking;
- embedding;
- retrieval;
- generation;
- taxonomy suggestions;
- diagnostic calculations;
- mastery updates;
- queued processing.

## 4. Curriculum Requirements

### FR-CUR-001 — Curriculum Nodes
The system shall represent learning concepts as addressable curriculum nodes.

Each node should support:
- title;
- description;
- licensure program;
- subject/domain;
- difficulty or conceptual depth;
- status;
- learning objectives;
- mastery policy;
- metadata.

### FR-CUR-002 — Prerequisite Relationships
The system shall allow a node to have multiple prerequisite nodes and multiple dependent nodes.

### FR-CUR-003 — Cycle Prevention
The system shall prevent prerequisite relationships that create cycles.

### FR-CUR-004 — Board-Level Mapping
Board syllabus topics shall be identifiable as board-level or top-level review nodes.

### FR-CUR-005 — Foundational Depth
The system shall permit further prerequisite decomposition until a practical zero-base level is reached.

### FR-CUR-006 — Navigation
Learners shall be able to see the current node, understand its parent/dependent objective, view recommended prerequisites, manually choose a deeper prerequisite path when permitted, and return to the original objective.

## 5. Source Knowledge Requirements

### FR-KB-001 — Source Registry
Administrators shall be able to register approved learning sources with metadata such as title, author, edition, subject, rights/usage notes, and publication status.

### FR-KB-002 — Document Ingestion
The system shall accept approved source files and create ingestion jobs.

### FR-KB-003 — Extraction and Cleaning
The system shall extract usable text and remove predictable noise such as repeated headers, footers, and page artifacts.

### FR-KB-004 — Chunking
The system shall split source text into retrievable semantic chunks while preserving source location and surrounding context.

### FR-KB-005 — Embeddings
The system shall generate vector representations for retrievable chunks.

### FR-KB-006 — Taxonomy Mapping
Chunks shall be linkable to one or more curriculum nodes. Mapping may be manual, AI-suggested, or automatically proposed then human-approved.

### FR-KB-007 — Provenance
Every retrieved or generated learning asset shall be capable of retaining references to the source chunks used to create it.

## 6. Diagnostic and Placement Requirements

### FR-DIA-001 — Initial Diagnostic
The system shall provide an initial assessment capable of sampling board-level topics.

### FR-DIA-002 — Gap Mapping
Incorrect responses shall be mapped to relevant curriculum nodes and, where possible, likely prerequisite nodes.

### FR-DIA-003 — Baseline Placement
The system shall calculate a recommended starting level/depth for weak areas.

### FR-DIA-004 — Learner Persona
The system may capture a review persona such as first-time taker, retaker, or returning/gap-year professional to influence pacing and recommendations. Persona shall not override measured mastery evidence.

### FR-DIA-005 — Recalibration
Placement recommendations shall update as new assessment evidence is collected.

## 7. Learning and Remediation Requirements

### FR-LRN-001 — Node Learning View
Each curriculum node shall support learning objectives, explanation, examples, provenance, practice, and a next recommended action.

### FR-LRN-002 — Drill-Down Trigger
When performance falls below the node’s mastery policy, the system shall recommend or initiate prerequisite remediation according to configured rules.

### FR-LRN-003 — Dynamic Lesson Assembly
The system shall be able to retrieve relevant source chunks and produce a focused mini-lesson for a target node.

### FR-LRN-004 — Cached/Reused Assets
Generated lessons may be stored, versioned, and reused when they remain valid for the same curriculum/source version.

### FR-LRN-005 — Manual Drill-Down
The learner shall be able to request foundational review without waiting for automatic failure detection.

### FR-LRN-006 — Re-integration
After prerequisite mastery, the system shall guide the learner back toward the original higher-level node.

## 8. Assessment Requirements

### FR-ASMT-001 — Question Bank
Questions shall support question stem, choices where applicable, correct answer, rationale, difficulty, source/provenance, curriculum-node mappings, and lifecycle/version.

### FR-ASMT-002 — Board-Level Questions
The system shall support questions representing board-exam style and difficulty.

### FR-ASMT-003 — Node Mini-Quizzes
The system shall support small assessments attached to prerequisite nodes.

### FR-ASMT-004 — AI Question Generation
The system may generate practice questions from retrieved source material. Generated questions must retain generation/provenance metadata and support human review.

### FR-ASMT-005 — Attempt Recording
The system shall record user, question, answer, correctness, response time, timestamp, and active curriculum context.

### FR-ASMT-006 — Mastery Calculation
The system shall update node mastery from assessment evidence using configurable rules.

## 9. Adaptive Progression Requirements

### FR-ADP-001 — Node State
The system shall maintain per-user state for curriculum nodes. Recommended states include not started, recommended, in progress, needs review, and mastered.

### FR-ADP-002 — Threshold Policy
Mastery thresholds shall be configurable.

### FR-ADP-003 — Upward Progression
When a learner masters a prerequisite node, the system shall recommend the next dependent node in the remediation path.

### FR-ADP-004 — Original Goal Tracking
A remediation session shall preserve the original weak board-level target so the learner can be returned to it.

### FR-ADP-005 — Reassessment
The system shall verify improvement at the original target after remediation is completed.

## 10. AI Tutor Requirements

### FR-TUT-001 — Context Awareness
The tutor shall receive the active node, current learning/assessment context, and retrieved evidence relevant to the question.

### FR-TUT-002 — Retrieval Grounding
Answers intended as formal subject instruction shall rely on retrieved approved material.

### FR-TUT-003 — Insufficient Evidence
If the retrieved corpus does not support an answer, the tutor shall indicate the limitation or perform a controlled broader retrieval.

### FR-TUT-004 — Scope Control
The tutor may redirect unrelated questions when the active experience is intentionally constrained to a topic.

### FR-TUT-005 — Conversation History
Tutor history may be preserved per study session with appropriate limits.

## 11. Analytics Requirements

### FR-AN-001 — Mastery Dashboard
The learner shall be able to view mastery by subject and curriculum node.

### FR-AN-002 — Weakness Heatmap
The system shall provide a visual representation of weak, developing, and mastered areas.

### FR-AN-003 — Time-to-Solve
The system shall record and summarize response time for assessment items.

### FR-AN-004 — Remediation History
The system shall show which prerequisite paths were used and whether the learner successfully returned to the board-level target.

### FR-AN-005 — Readiness Indicators
Readiness indicators shall derive from recent assessment evidence and coverage rather than a single global percentage alone.

## 12. Administration Requirements

### FR-ADM-001 — Curriculum Management
Administrators shall manage nodes and prerequisite edges.

### FR-ADM-002 — Source Management
Administrators shall manage source documents, ingestion status, and chunk indexing.

### FR-ADM-003 — AI Review Queue
Administrators shall be able to inspect AI-generated or AI-suggested assets that require approval.

### FR-ADM-004 — Version Awareness
Changes to curriculum, source material, and generated content shall be version-aware enough to identify stale generated assets.

### FR-ADM-005 — Publication State
Curriculum nodes, questions, sources, and generated assets shall support draft/review/published or equivalent lifecycle states.

## 13. Functional Quality Requirements

- Normal Inertia navigation and non-AI interactions should feel immediate.
- AI operations should stream or expose progress when processing is perceptibly long.
- Extraction, embedding, bulk generation, and indexing shall run asynchronously.
- Generated assets and questions should retain model/provider/version and provenance where practical.
- Core domain logic shall not be coupled to one AI vendor.
- Curriculum graph operations shall preserve acyclic prerequisite relationships.
- Administrative ingestion and publication actions shall require appropriate authorization.
- Uploaded files shall be treated as untrusted input.
- The system shall support progressive delivery in vertical slices.

## 14. Out of Scope for Core Validation

Unless later promoted into scope:
- native mobile applications;
- generic school/classroom administration;
- social networking/community features;
- a dedicated graph database;
- unrestricted general-purpose AI chat;
- fully autonomous publication of unreviewed high-stakes learning content.

## 15. Core Acceptance Scenario

A release demonstrates the central product concept when:

1. an administrator creates a board-level curriculum node and at least two levels of prerequisites;
2. approved source content is ingested and linked to those nodes;
3. a learner answers a board-level assessment incorrectly;
4. the system identifies the weak node and recommends a prerequisite;
5. a source-grounded lesson is shown for the prerequisite;
6. the learner passes a prerequisite quiz;
7. the system advances the learner upward;
8. the learner is returned to the original board-level topic;
9. the system records the new assessment evidence and updated mastery.

