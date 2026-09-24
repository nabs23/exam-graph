# Reviewer App — Final Project Description & Product Definition

## 1. Project Identity

The Reviewer App is an AI-assisted adaptive learning and review platform for Philippine Professional Regulation Commission (PRC) licensure examinations.

Its purpose is broader than mock examinations. The system combines board-exam review, structured learning materials, adaptive diagnostics, prerequisite remediation, AI-assisted tutoring, and mastery tracking in one learning loop.

The defining problem is that many review systems assume the examinee still possesses the prerequisite knowledge expected by the board syllabus. This app is designed specifically to handle cases where that assumption is false.

## 2. Primary Users

The system is designed for:

- **Retakers** who need to identify weak areas, rebuild foundations, and correct persistent misconceptions.
- **Gap-year candidates and working professionals** who completed their degrees years earlier and may need substantial foundational refreshers before returning to board-level material.
- **First-time examinees with uneven foundations** who discover during review that some advanced topics depend on concepts they never fully mastered.
- **Self-directed reviewees** who need a structured path rather than a flat question bank.

## 3. Core Product Concept

The core differentiator is the **Deep Drill-Down learning architecture**.

Instead of organizing the curriculum only as subjects, chapters, and board-level questions, the system models knowledge as a graph of concepts and prerequisites.

A typical path can descend from:

1. **Board / syllabus level**
2. **Intermediate concepts**
3. **Core mechanics**
4. **Foundational concepts**
5. **Zero-base concepts**, when necessary

Example for Financial Accounting and Reporting:

- Consolidated Financial Statements
- Business Combinations / Equity Method
- Adjusting Entries and Trial Balance
- Accounting Cycle, T-Accounts, Debits and Credits
- Basic meaning of assets, liabilities, equity, revenue, and expenses

The exact depth is not fixed globally. Different subjects may require different numbers of prerequisite levels.

## 4. Adaptive Learning Loop

The product should continuously run the following loop:

**Assess → Detect Gap → Map Prerequisite → Drill Down → Teach → Reassess → Progress Upward → Return to Board-Level Application**

The system should not merely label a topic as weak. It should attempt to locate the prerequisite concept most likely causing the difficulty and provide a path to repair it.

### Typical “Struggle and Rescue” Flow

1. The learner attempts a board-level lesson, quiz, or exam item.
2. The system records incorrect answers, response time, and topic linkage.
3. The learner’s performance is mapped to the associated curriculum node and prerequisite nodes.
4. If mastery is below threshold, the learner is offered or assigned a deeper prerequisite node.
5. The system assembles or retrieves a focused lesson from the approved knowledge corpus.
6. The learner completes a small assessment.
7. Passing the lower-level node allows upward progression.
8. The learner is eventually returned to the original board-level topic.

## 5. Knowledge and Content Model

The app uses approved reviewer books, classroom materials, and other authorized learning sources as its knowledge corpus.

The content pipeline is:

**Source document → extraction → cleaning → semantic chunking → metadata → embedding → taxonomy linkage → retrieval → AI-assisted lesson or assessment generation**

AI-generated content should be grounded in retrieved source material rather than generated from unrestricted model memory whenever the feature is presenting instructional content as part of the formal reviewer.

The source corpus remains the authority. AI is primarily an orchestration, explanation, adaptation, and generation layer over that corpus.

## 6. Major Product Capabilities

### Curriculum and Knowledge Structure
- Hierarchical and graph-based curriculum taxonomy
- Board-level topics linked to prerequisite concepts
- Multiple depths of foundational review
- Manual “go back to basics” navigation
- Prerequisite and dependency mapping

### Diagnostic and Placement
- Initial diagnostic assessment
- Topic-level gap analysis
- Starting-depth recommendation
- Persona-aware review behavior
- Ongoing recalibration based on performance

### Learning Experience
- Generated or assembled mini-lessons
- Explanations grounded in the approved corpus
- Examples and practice exercises
- Node-level quizzes
- Bottom-up progression back to the original topic

### AI Tutor
- Context-aware chat alongside lessons or quizzes
- Retrieval-bound answers
- Current-topic awareness
- Clear handling of insufficient or out-of-scope evidence

### Assessment
- Board-style questions
- Foundational mini-quizzes
- Time-to-solve tracking
- Mastery thresholds
- Question-to-topic and question-to-prerequisite mappings

### Analytics
- Mastery by topic/node
- Weakness heatmap
- Review history
- Progress through prerequisite paths
- Time and accuracy trends

### Administration / Content Operations
- Upload source materials
- Manage source metadata
- Parse and chunk documents
- Generate embeddings
- Map chunks to curriculum nodes
- Review AI-suggested taxonomy links
- Manage curriculum and prerequisite relationships
- Curate generated learning assets where required

## 7. Final Technology Direction

The project’s settled progressive-development direction is:

- **Backend:** Laravel
- **Web application:** Inertia.js
- **Frontend adapter:** React is the expected implementation when following the developer’s standard stack; the architectural requirement is Inertia.js rather than a separate SPA API boundary.
- **Primary data store:** MongoDB
- **Vector retrieval:** MongoDB vector search where available, with the retrieval layer designed so the vector implementation can be replaced if needed
- **Background work:** Laravel queues; Redis/Horizon is an appropriate worker-management path
- **AI layer:** provider-agnostic LLM and embedding interfaces rather than hard-coding the product to one model vendor

## 8. Product Principles

1. **Foundation before repetition.** Repeated board questions are not enough when the root problem is missing prerequisite knowledge.
2. **The curriculum graph is a first-class domain model.** It is not merely a navigation tree.
3. **Source-grounded AI.** Formal instructional outputs should remain traceable to the approved corpus.
4. **Adaptive depth.** Learners should descend only as far as necessary.
5. **Return to the original objective.** Remediation is successful only when the learner can return to and handle the board-level topic.
6. **Progressive development.** The system should be built in usable vertical slices rather than attempting the entire AI architecture at once.
7. **Human-curated authority.** AI may propose curriculum links, lessons, and questions, but administrators must be able to inspect and correct them.

## 9. Scope Boundary

The initial product is a reviewer and adaptive learning platform, not a general-purpose LMS. Features should be evaluated by whether they improve licensure-exam learning, remediation, assessment, or content operations.

Native mobile applications, broad social/community features, unrelated classroom management, and generalized education administration are not required to validate the core product.

## 10. Definition of Success

The product succeeds when it can take a learner who fails an advanced board-level topic, identify the likely prerequisite gap, guide the learner through an evidence-grounded foundational path, verify mastery, and return the learner to the original board-level objective with measurable improvement.

