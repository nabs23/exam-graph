# 07 — FAR Complete Worked Example: Syllabus → Concepts → Lessons → Objectives → Questions

## Purpose

This document is the first complete implementation example for the Reviewer App curriculum model.

It uses **Financial Accounting and Reporting (FAR)** from **PRBOA Resolution No. 30, Series of 2022** and demonstrates how the official CPALE syllabus and Table of Specifications (TOS) should be transformed into application data without confusing:

- official syllabus hierarchy;
- exam blueprint/TOS;
- atomic concepts;
- lessons;
- learning objectives;
- questions;
- standards and issuances.

The goal is not merely to copy the syllabus. The goal is to show the full data transformation pipeline that can later be repeated for AFAR, MS, Auditing, Taxation and RFBT.

---

# 1. Authoritative Board Source

**Subject:** Financial Accounting and Reporting  
**Exam:** Licensure Examination for Certified Public Accountants  
**Effective baseline:** October 2022  
**Official exam items:** 70 MCQs  
**Primary source:** PRBOA Resolution No. 30, Series of 2022, Annex A

The official syllabus defines FAR as testing understanding and application of accounting principles and standards involving recognition, measurement, valuation, subsequent events and transactions, impairment, related-party transactions, presentation and disclosures.

The Board also expressly states that newer laws, standards and issuances effective on the examination date supersede related syllabus topics unless the Board issues contrary guidance.

Therefore:

- the syllabus is a versioned scope baseline;
- standards are versioned separately;
- questions must reference applicable standards as of an effective date.

---

# 2. FAR Official Syllabus Hierarchy

## 1.0 Development of Financial Reporting Framework, Standard-Setting Bodies and Regulation of the Accountancy Profession

### 1.1 History, Development and Functions of Standard-Setting Bodies
- 1.1.1 IASB
- 1.1.2 IFRIC and SIC
- 1.1.3 FRSC
- 1.1.4 PIC

### 1.2 Regulation and Environment of the Accounting Profession in the Philippines
- 1.2.1 Professional Regulatory Board of Accountancy
- 1.2.2 Accredited professional organization
- 1.2.3 Sectors of practice and accreditation requirements

## 2.0 Conceptual Framework, Accounting Process and Presentation of Financial Statements

### 2.1 Conceptual Framework for Financial Reporting
- 2.1.1 Objective and status
- 2.1.2 Qualitative characteristics
- 2.1.3 Financial-statement elements, recognition, derecognition and measurement bases
- 2.1.4 Capital and capital maintenance

### 2.2 Accounting Process
- 2.2.1 Steps in the accounting process
- 2.2.2 Special/general journals, subsidiary/general ledgers
- 2.2.3 Worksheets, adjusting, closing and reversing entries

### 2.3 Presentation of Financial Statements
- 2.3.1 General features
- 2.3.2 Statement of Financial Position
  - 2.3.2.1 Definition of elements
  - 2.3.2.2 Classified Statement of Financial Position
- 2.3.3 Statement of Comprehensive Income
  - nature of expense
  - function of expense
  - continuing operations
  - discontinued operations
- 2.3.4 Statement of Changes in Equity
- 2.3.5 Statement of Cash Flows
  - sections
  - direct and indirect operating cash flows
- 2.3.6 Notes to Financial Statements
- 2.3.7 Earnings per Share
  - Basic EPS
  - Diluted EPS

## 3.0 Cash and Other Financial Assets

### 3.1 Cash
- nature and composition
- bank reconciliation
- petty cash fund

### 3.2 Other Financial Assets
- classification, recognition, subsequent measurement, reclassification and presentation
- FVTPL
- FVOCI
- amortized cost
- receivables
- allowance for doubtful accounts
- pledging, assignment and factoring
- bond investments
- investments in associates and joint ventures

## 4.0 Non-financial Assets

### 4.1 Inventories
- nature
- capitalizable cost
- cost-flow assumptions
- lower-of-cost measurement
- gross-profit and retail inventory methods

### 4.2 Property, Plant and Equipment
- nature
- initial capitalizable cost
- borrowing costs
- subsequent expenditures
- cost model
- depreciation
- depreciation methods
- useful-life/method changes
- revaluation
- impairment
- retirement/disposal

### 4.3 Investment Property
- nature
- initial recognition
- cost model
- fair-value model
- derecognition and reclassification

### 4.4 Intangible Assets
- nature
- initial recognition
- subsequent expenditures
- finite-life amortization
- indefinite-life assets
- impairment
- derecognition

### 4.5 Biological Assets
- nature
- bearer plants vs agricultural produce
- initial recognition
- subsequent measurement

### 4.6 Non-current Assets Held for Sale / Disposal Group
- classification criteria
- initial recognition
- subsequent measurement
- reclassification
- derecognition

### 4.7 Prepaid Expenses and Other Assets

## 5.0 Financial Liabilities
- classification
- initial recognition
- debt issue cost
- effective-interest measurement
- troubled debt restructuring

## 6.0 Non-financial Liabilities, Provisions and Contingencies
- loyalty programs
- warranties/product guarantees
- unearned revenue
- other provisions and contingencies

## 7.0 Shareholders' Equity
- initial share issuance and issuance cost
- treasury shares
- retirement/conversion
- prior-period errors and accounting-policy changes
- dividends
- quasi-reorganization
- recapitalization
- cumulative OCI
- book value per share

## 8.0 Other Topics

### 8.1 Share-based Payments
- equity-settled
- cash-settled
- equity-settled with cash alternative

### 8.2 Leases
- lessee initial recognition
- lessee subsequent measurement
- financial-statement presentation
- exemptions
- lessor accounting
- direct finance lease
- manufacturer/dealer lease
- operating lease
- sale-leaseback

### 8.3 Income Tax
- accounting profit vs taxable profit
- book basis vs tax basis
- current tax
- deferred tax
- deferred tax asset/liability
- presentation and disclosure

### 8.4 Employee Benefits
- nature/classification
- defined contribution
- defined benefit
- presentation/disclosures

### 8.5 Interim Reporting

### 8.6 Operating Segments

## 9.0 Other Reporting Frameworks
- PFRS for SMEs
- PFRS for Small Entities
- reporting for microenterprises

---

# 3. FAR TOS / Exam Blueprint

The official TOS is a separate assessment blueprint.

| Area | Weight | Approx. items |
|---|---:|---:|
| Development / profession / standard-setting | 5.71% | 4 |
| Conceptual Framework, Accounting Process and Presentation | 12.86% | 9 |
| Cash and Other Financial Assets | 14.29% | 10 |
| Non-financial Assets | 20.00% | 14 |
| Financial Liabilities | 5.71% | 4 |
| Non-financial Liabilities / Provisions / Contingencies | 5.71% | 4 |
| Shareholders' Equity | 14.29% | 10 |
| Other Topics | 14.29% | 10 |
| Other Reporting Frameworks | 7.14% | 5 |
| **Total** | **100%** | **70** |

Global difficulty target:
- Easy: 30% = 21 items
- Moderate: 40% = 28 items
- Difficult: 30% = 21 items

The TOS also uses Bloom's Taxonomy dimensions.

## Data rule

Do not store these numbers directly in `topics`.

Use:

`exam_blueprints`
- id
- exam_program_id
- subject_id
- name
- effective_from
- effective_to
- source_reference

`blueprint_areas`
- id
- exam_blueprint_id
- syllabus_node_id
- weight_percent
- target_items

`blueprint_difficulty_targets`
- difficulty_id
- percentage
- target_items

---

# 4. Database-Ready Core Models

## 4.1 subjects

Example:

```text
id: 1
code: FAR
name: Financial Accounting and Reporting
exam_item_count: 70
```

## 4.2 syllabus_nodes

Use one adjacency-list table instead of separate physical tables for area/topic/subtopic unless there is a strong query reason to split them.

Suggested fields:

```text
id
subject_id
parent_id nullable
node_type        // area, topic, subtopic, detail
official_code    // e.g. "4.2.5.1.1"
title
description nullable
sort_order
source_version_id
is_official
```

Example:

```text
FAR
└── 4.0 Non-financial Assets
    └── 4.2 Property, Plant and Equipment
        └── 4.2.5 Subsequent Measurement
            └── 4.2.5.1 Cost Method
                └── 4.2.5.1.1 Depreciation
```

The official hierarchy must be preserved exactly enough to trace any curriculum object back to the board syllabus.

---

# 5. Why a Syllabus Topic Is Not Automatically a Concept

Take:

**4.2 Property, Plant and Equipment**

This is too broad to be one concept.

It contains many atomic or near-atomic concepts:

- definition of PPE;
- recognition criteria;
- directly attributable costs;
- dismantling/restoration obligation;
- borrowing-cost capitalization;
- component accounting;
- subsequent expenditure;
- cost model;
- residual value;
- useful life;
- depreciable amount;
- straight-line depreciation;
- diminishing-balance depreciation;
- units-of-production depreciation;
- change in estimate;
- revaluation increase;
- revaluation decrease;
- impairment indicator;
- recoverable amount;
- disposal gain/loss.

These concepts can be reused across lessons, questions and prerequisite graphs.

---

# 6. Complete Worked Slice: FAR 4.2 Property, Plant and Equipment

This section shows the transformation from official syllabus node all the way to assessment questions.

## 6.1 Official syllabus nodes

```text
4.2 Property, Plant and Equipment
4.2.1 Nature
4.2.2 Capitalizable cost at initial recognition
4.2.3 Borrowing costs
4.2.4 Subsequent expenditures
4.2.5 Subsequent measurement
4.2.5.1 Cost method
4.2.5.1.1 Depreciation
4.2.5.1.2 Depreciation methods
4.2.5.1.3 Changes in useful life and depreciation methods
4.2.5.2 Revaluation
4.2.6 Impairment
4.2.7 Retirement and disposals
```

## 6.2 TOS outcomes covering PPE

Board outcomes include the ability to:

- describe the nature of PPE;
- determine capitalizable cost at initial recognition;
- measure borrowing costs;
- identify subsequent expenditures;
- apply cost-model measurement;
- apply depreciation principles and methods;
- account for changes in useful life and depreciation method;
- determine revaluation;
- determine impairment;
- account for retirement and disposals.

These should populate `learning_outcomes` as board-authored outcomes.

---

# 7. Atomic Concept Extraction for PPE

Suggested concept records:

| Concept code | Concept |
|---|---|
| PPE-C01 | Definition and scope of PPE |
| PPE-C02 | PPE recognition criteria |
| PPE-C03 | Initial measurement at cost |
| PPE-C04 | Directly attributable costs |
| PPE-C05 | Dismantling/restoration cost |
| PPE-C06 | Borrowing-cost capitalization |
| PPE-C07 | Component accounting |
| PPE-C08 | Subsequent expenditure: capitalize vs expense |
| PPE-C09 | Cost model |
| PPE-C10 | Depreciable amount |
| PPE-C11 | Residual value |
| PPE-C12 | Useful life |
| PPE-C13 | Depreciation commencement/cessation |
| PPE-C14 | Straight-line method |
| PPE-C15 | Diminishing-balance method |
| PPE-C16 | Units-of-production method |
| PPE-C17 | Review of useful life/residual value |
| PPE-C18 | Change in accounting estimate |
| PPE-C19 | Revaluation model |
| PPE-C20 | Revaluation surplus |
| PPE-C21 | Revaluation decrease |
| PPE-C22 | Impairment indicators |
| PPE-C23 | Recoverable amount |
| PPE-C24 | Impairment loss |
| PPE-C25 | Derecognition |
| PPE-C26 | Gain/loss on disposal |

These are app-derived instructional concepts, not official Board syllabus lines.

---

# 8. Concept Relationships / Prerequisite Graph

Example directed edges:

```text
PPE-C01 → PPE-C02
PPE-C02 → PPE-C03
PPE-C03 → PPE-C04
PPE-C03 → PPE-C05
PPE-C03 → PPE-C06

PPE-C03 → PPE-C10
PPE-C10 → PPE-C11
PPE-C10 → PPE-C12
PPE-C11 → PPE-C14
PPE-C12 → PPE-C14
PPE-C14 → PPE-C17
PPE-C17 → PPE-C18

PPE-C03 → PPE-C19
PPE-C19 → PPE-C20
PPE-C19 → PPE-C21

PPE-C03 → PPE-C22
PPE-C22 → PPE-C23
PPE-C23 → PPE-C24

PPE-C03 → PPE-C25
PPE-C25 → PPE-C26
```

Suggested pivot:

`concept_dependencies`
- prerequisite_concept_id
- dependent_concept_id
- dependency_type
- strength
- rationale

The graph should remain a DAG where possible.

---

# 9. Mapping Official Syllabus Nodes to Concepts

Use many-to-many mapping.

`syllabus_node_concept`

Examples:

```text
4.2.1 → PPE-C01
4.2.2 → PPE-C02, C03, C04, C05
4.2.3 → PPE-C06
4.2.4 → PPE-C07, C08
4.2.5.1 → PPE-C09
4.2.5.1.1 → PPE-C10, C11, C12, C13
4.2.5.1.2 → PPE-C14, C15, C16
4.2.5.1.3 → PPE-C17, C18
4.2.5.2 → PPE-C19, C20, C21
4.2.6 → PPE-C22, C23, C24
4.2.7 → PPE-C25, C26
```

This allows the board hierarchy to remain stable while the concept graph can evolve pedagogically.

---

# 10. Lesson Design

A lesson is a teaching unit, not a Board node.

Recommended PPE lesson sequence:

### Lesson PPE-L01 — What Qualifies as PPE?
Concepts:
- C01 Definition/scope
- C02 Recognition criteria

### Lesson PPE-L02 — Initial Measurement of PPE
Concepts:
- C03 Initial measurement
- C04 Directly attributable costs
- C05 Dismantling/restoration cost

### Lesson PPE-L03 — Borrowing Costs
Concept:
- C06 Borrowing-cost capitalization

### Lesson PPE-L04 — Subsequent Expenditure and Components
Concepts:
- C07 Component accounting
- C08 Capitalize vs expense

### Lesson PPE-L05 — Depreciation Foundations
Concepts:
- C10 Depreciable amount
- C11 Residual value
- C12 Useful life
- C13 Commencement/cessation

### Lesson PPE-L06 — Depreciation Methods
Concepts:
- C14 Straight line
- C15 Diminishing balance
- C16 Units of production

### Lesson PPE-L07 — Changes in Estimates
Concepts:
- C17 Periodic review
- C18 Change in estimate

### Lesson PPE-L08 — Revaluation
Concepts:
- C19 Revaluation model
- C20 Revaluation surplus
- C21 Revaluation decrease

### Lesson PPE-L09 — Impairment
Concepts:
- C22 Indicators
- C23 Recoverable amount
- C24 Impairment loss

### Lesson PPE-L10 — Derecognition and Disposal
Concepts:
- C25 Derecognition
- C26 Gain/loss

---

# 11. Learning Objectives

Objectives must be more granular than Board outcomes and measurable.

Examples:

## PPE-L02 objectives
- **OBJ-PPE-001:** Identify expenditures that form part of the initial cost of PPE.
- **OBJ-PPE-002:** Distinguish directly attributable costs from period expenses.
- **OBJ-PPE-003:** Compute the initial carrying amount of PPE from a transaction fact pattern.
- **OBJ-PPE-004:** Recognize the initial estimate of dismantling/restoration obligation as part of PPE cost when applicable.

## PPE-L05 objectives
- **OBJ-PPE-005:** Compute depreciable amount.
- **OBJ-PPE-006:** Determine the date depreciation begins.
- **OBJ-PPE-007:** Determine the date depreciation ceases.
- **OBJ-PPE-008:** Explain the role of useful life and residual value.

## PPE-L06 objectives
- **OBJ-PPE-009:** Compute straight-line depreciation.
- **OBJ-PPE-010:** Compute diminishing-balance depreciation.
- **OBJ-PPE-011:** Compute units-of-production depreciation.
- **OBJ-PPE-012:** Select a depreciation method consistent with the expected consumption pattern.

## PPE-L08 objectives
- **OBJ-PPE-013:** Compute a revalued carrying amount.
- **OBJ-PPE-014:** Determine whether a revaluation movement is recognized in OCI or profit/loss given prior balances.
- **OBJ-PPE-015:** Compute depreciation after revaluation.

---

# 12. Objective–Concept Mapping

Use:

`learning_objective_concept`

Examples:

```text
OBJ-PPE-001 → C03, C04
OBJ-PPE-002 → C04
OBJ-PPE-003 → C03, C04, C05
OBJ-PPE-004 → C05

OBJ-PPE-005 → C10, C11
OBJ-PPE-006 → C13
OBJ-PPE-008 → C11, C12

OBJ-PPE-009 → C14
OBJ-PPE-010 → C15
OBJ-PPE-011 → C16
OBJ-PPE-012 → C14, C15, C16

OBJ-PPE-013 → C19
OBJ-PPE-014 → C20, C21
OBJ-PPE-015 → C19, C10
```

---

# 13. Question Model

Suggested `questions` core fields:

```text
id
subject_id
stem
question_type
difficulty_id
status
source_type
source_reference nullable
explanation
effective_from nullable
effective_to nullable
```

Supporting pivots:

`question_concept`
- question_id
- concept_id
- relevance_weight
- role: primary / secondary

`question_learning_objective`
- question_id
- learning_objective_id

`question_syllabus_node`
- question_id
- syllabus_node_id

`question_blueprint_tags`
- question_id
- bloom_level
- difficulty
- blueprint_area_id

---

# 14. Example Questions

## Q-PPE-001 — Initial Cost

A company purchases equipment for ₱1,000,000. Freight is ₱40,000, installation is ₱60,000, employee training is ₱25,000 and the present value of the expected dismantling obligation is ₱75,000.

What amount should initially be recognized as the cost of the equipment?

**Answer:** ₱1,175,000

Mapped to:
- syllabus: 4.2.2
- concepts: C03, C04, C05
- objectives: OBJ-PPE-001, OBJ-PPE-003, OBJ-PPE-004
- Bloom: Applying
- suggested difficulty: Moderate

Explanation:
Purchase price, freight, installation and qualifying dismantling cost are included. Training is expensed.

---

## Q-PPE-002 — Depreciable Amount

Equipment cost is ₱2,000,000, residual value is ₱200,000, and estimated useful life is 6 years.

Under straight-line depreciation, annual depreciation is:

**Answer:** ₱300,000

Mapped to:
- syllabus: 4.2.5.1.1 and 4.2.5.1.2
- concepts: C10, C11, C12, C14
- objectives: OBJ-PPE-005, OBJ-PPE-009
- Bloom: Applying
- difficulty: Easy

---

## Q-PPE-003 — Change in Useful Life

An asset was originally depreciated over 10 years. After three years, the remaining useful life is reassessed to four years.

The reassessment is generally treated as:

**Answer:** Change in accounting estimate applied prospectively.

Mapped to:
- syllabus: 4.2.5.1.3
- concepts: C17, C18
- Bloom: Understanding / Applying
- difficulty: Moderate

---

## Q-PPE-004 — Revaluation

A building has a carrying amount of ₱8,000,000 before revaluation. Fair value is ₱9,500,000 and there is no previous revaluation decrease relating to the asset.

The ₱1,500,000 increase is generally recognized in:

**Answer:** Other comprehensive income and accumulated in revaluation surplus, subject to the applicable standard.

Mapped to:
- syllabus: 4.2.5.2
- concepts: C19, C20
- objective: OBJ-PPE-014
- Bloom: Applying
- difficulty: Moderate

---

## Q-PPE-005 — Disposal

An asset has cost of ₱1,500,000 and accumulated depreciation of ₱1,100,000 when sold for ₱450,000.

**Answer:** Gain on disposal = ₱50,000.

Mapped to:
- syllabus: 4.2.7
- concepts: C25, C26
- Bloom: Applying
- difficulty: Easy

---

# 15. Question Generation Constraints

AI-generated questions must not be accepted merely because they sound plausible.

Each generated item should pass validation against:

1. official syllabus mapping;
2. concept mapping;
3. learning objective;
4. current applicable accounting standard;
5. numerical recomputation if computational;
6. distractor plausibility;
7. single-best-answer requirement;
8. difficulty calibration;
9. explanation correctness;
10. duplicate/similarity checks.

Generated questions should initially have status:

`draft_ai`

and require either human review or a defined validation pipeline before becoming:

`approved`.

---

# 16. Example Full Record Flow

For Q-PPE-001:

```text
Subject
FAR

Syllabus Node
4.2.2 Capitalizable cost at initial recognition

Board Outcome
Determine capitalizable cost at initial recognition

Concepts
PPE-C03 Initial measurement at cost
PPE-C04 Directly attributable costs
PPE-C05 Dismantling/restoration cost

Lesson
PPE-L02 Initial Measurement of PPE

Objectives
OBJ-PPE-001
OBJ-PPE-003
OBJ-PPE-004

Question
Q-PPE-001

Blueprint tags
FAR / Non-financial Assets
Applying
Moderate

Applicable Standard
PAS 16, current applicable version
```

This is the fundamental Reviewer App chain.

---

# 17. Student Progress Model

Do not store mastery only at syllabus-topic level.

Recommended:

`student_concept_mastery`
- user_id
- concept_id
- mastery_score
- confidence
- attempts
- correct_attempts
- last_assessed_at

`student_objective_mastery`
- user_id
- learning_objective_id
- mastery_score

Syllabus progress can be computed upward from concept/objective mastery.

Example:

```text
PPE syllabus progress
= aggregate mastery of mapped PPE concepts/objectives
```

This is preferable to manually marking “4.2 PPE = 70% complete.”

---

# 18. Diagnostic / Adaptive Workflow Example

Student answers Q-PPE-001 incorrectly.

The system sees:
- question primary concept: C04 directly attributable costs
- secondary concepts: C03 initial measurement, C05 dismantling cost
- objective failures: OBJ-PPE-001 and OBJ-PPE-003

Adaptive action:
1. reduce mastery confidence for C04;
2. check prerequisite C03;
3. queue short remediation lesson PPE-L02;
4. present a simpler classification question;
5. then another computational question;
6. retest after delay.

This is more useful than simply recording “FAR question wrong.”

---

# 19. Standards and Issuances Mapping

Suggested table:

`standards`
- id
- code
- title
- authority
- effective_from
- effective_to
- supersedes_standard_id nullable
- source_url

Pivot:

`standard_syllabus_node`

Example mappings:
- PAS 16 → 4.2 PPE
- PAS 23 → 4.2.3 Borrowing Costs
- PAS 36 → 4.2.6 Impairment
- PFRS 5 → 4.6 Held for Sale
- PAS 40 → 4.3 Investment Property
- PAS 38 → 4.4 Intangibles

Questions should be capable of recording the applicable standard version so that future rule changes do not silently invalidate historical questions.

---

# 20. MVP vs Advanced Implementation

## MVP

Required now:
- subjects
- syllabus_nodes
- exam_blueprints
- blueprint_areas
- concepts
- syllabus_node_concept
- lessons
- lesson_concept
- learning_objectives
- objective_concept
- questions
- choices
- question_concept
- question_objective
- question_syllabus_node
- attempts
- student_concept_mastery

Enough for:
- structured review;
- question bank;
- syllabus coverage;
- concept-based remediation;
- basic adaptive recommendations.

## Advanced

Add later:
- prerequisite-strength scoring
- misconception graph
- semantic concept embeddings
- standards-version inference
- automatic question calibration
- item-response theory
- spaced repetition
- Bayesian knowledge tracing
- graph-based recommendation
- multi-stage AI curriculum extraction
- source/RAG provenance
- psychometric item statistics

---

# 21. Recommended Import Sequence for FAR

### Phase 1 — Official Board Structure
Import:
- subject
- 9 official syllabus areas
- all official numbered topics/subtopics
- official TOS outcomes
- blueprint weights and item counts

### Phase 2 — Concept Extraction
For each syllabus area:
- identify atomic concepts
- merge duplicates
- establish prerequisites
- map concepts to official nodes

### Phase 3 — Instructional Layer
Create:
- lessons
- explanations
- examples
- worked problems
- objectives

### Phase 4 — Assessment Layer
Create/import:
- questions
- choices
- explanations
- cognitive level
- difficulty
- concept/objective mappings

### Phase 5 — Validation
Check:
- full TOS coverage
- no unmapped questions
- no orphan concepts
- no cyclic prerequisite dependencies
- standards are current
- total blueprint weights = 100%

---

# 22. Generalization to the Other CPALE Subjects

The same architecture should be reused.

The important distinction is that subject content changes, but the structural model does not:

```text
Official Board Syllabus
        ↓
Normalized Syllabus Nodes
        ↓
Atomic Concept Graph
        ↓
Lessons + Objectives
        ↓
Questions
        ↓
Assessment / Mastery
```

TOS remains parallel:

```text
Official TOS
    ↓
Exam Blueprint
    ↓
Coverage + Cognitive + Difficulty Targets
```

Standards/laws remain another parallel versioned layer.

This makes FAR a reusable template rather than a special-case implementation.

---

# 23. Architecture Decision from This Example

For the Reviewer App, the recommended central domain objects are:

1. **Subject**
2. **SyllabusNode**
3. **ExamBlueprint**
4. **BlueprintArea**
5. **Concept**
6. **ConceptDependency**
7. **Lesson**
8. **LearningObjective**
9. **Question**
10. **Assessment**
11. **Standard / LegalIssuance**
12. **StudentConceptMastery**

The syllabus tree is authoritative for exam scope.

The concept graph is authoritative for instructional understanding.

The TOS blueprint is authoritative for exam-distribution targets.

No single hierarchy should be forced to perform all three roles.

---

# 24. Implementation Conclusion

FAR demonstrates why the Reviewer App should not model the CPALE curriculum as a simple:

`Subject → Topic → Question`

That loses the distinction between Board scope, concepts, learning outcomes and exam weighting.

The minimum robust model is:

`Subject → SyllabusNode ↔ Concept ↔ LearningObjective ↔ Question`

with:

`ExamBlueprint → SyllabusNode`

and:

`Standard/Issuance ↔ SyllabusNode / Concept / Question`

This worked example should be treated as the canonical template for extracting and implementing the other five CPALE subjects.

