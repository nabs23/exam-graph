# 14 — ExamGraph UI/UX Development Plan

## Status and relationship to existing plans

**Status: In progress; program and subject authoring are the current priority.** This plan defines a phased improvement of the ExamGraph web experience for learners and content administrators. It supports the learner and admin surfaces already identified in [08 — Executable Development Plan](08-executable-development-plan.md), including its dashboard, responsive, accessibility, and pilot-readiness work.

The plan keeps the current Laravel, Inertia React, Wayfinder, and shared UI component stack. It focuses on making common tasks easy to find and complete, making outcomes and errors clear, and giving the app a consistent modern visual language. It does not change curriculum or learning rules to accommodate the interface.

## Goal

Make the application easy to learn, predictable to navigate, friendly to use, and visually cohesive across learner study and administrator content workflows. A first-time user should understand where they are, what they can do next, and what happened after an action.

## Users and scope

- **Learners:** choose a program and subject, browse curriculum, open concepts and lessons, take quizzes, and understand progress or next steps.
- **Content administrators:** manage curriculum and materials, draft AI proposals, review items individually, resolve conflicts, and understand whether content is pending, approved, or unavailable.
- **Shared experience:** authentication entry points, navigation, page structure, responsive behavior, feedback, and reusable interaction patterns.

The work targets responsive web. Native mobile apps and new product capabilities such as tutoring, adaptive recommendations, or prerequisite editing are outside this plan unless a later product decision adds them.

## Product and design decisions

- **Improve in vertical slices:** Start with shared navigation and dashboards, then complete learner and administrator journeys rather than polishing disconnected screens.
- **Evolve the current UI:** Keep Inertia React, Wayfinder, Tailwind, and existing shadcn-style components. Define consistent use of existing components before adding new dependencies or replacing the visual system.
- **Prioritize clarity over novelty:** Use a calm, modern interface with clear page titles, concise supporting text, readable hierarchy, meaningful whitespace, and restrained status colors. Do not imitate a specific external product.
- **Keep interaction patterns predictable:** Reuse placement and wording for primary actions, form errors, confirmations, breadcrumbs, status labels, and back navigation.
- **Show state and recovery:** Make loading, empty, success, failure, permission, stale, and conflict states visible. Explain the problem and the next useful action in plain language.
- **Design for keyboard and small screens:** Keep navigation, forms, dialogs, tables, and review controls usable without a mouse and at common phone and tablet widths.
- **Preserve product authority:** Official syllabus topics, instructional concepts, and author-approved materials remain distinct in labels and navigation.

## Current experience baseline

- The app already has learner study pages, admin CRUD pages, generation create/review pages, shared navigation, page-shell helpers, and a shared component library.
- The signed-in dashboard now provides role-relevant next actions and empty-state guidance; learner navigation and administrator authoring links appear in separate groups.
- Program and subject workspaces provide setup actions, counts, and context for subjects, source material, official curriculum, and AI proposals. Subject pages separate official syllabus topics from instructional concepts.
- Main authoring forms and proposal review pages now use consistent page headers, inline validation, action placement, and visible state. Responsive and keyboard behavior still needs a manual walkthrough.
- [08 — Executable Development Plan](08-executable-development-plan.md) already calls for learner and admin dashboards and responsive/accessibility validation; this plan makes those UI/UX tasks actionable.

## Experience map and shared patterns

| Task | Entry and next actions | Success signal | Recovery path |
| --- | --- | --- | --- |
| Administrator creates curriculum structure | Dashboard → Programs → create a program → program workspace → create a subject → subject workspace. | Success feedback confirms each save; the new subject appears in its program. | Field errors are shown beside the input; the subject form explains when a program must be created first; breadcrumbs and return links preserve the program context. |
| Administrator adds source materials | Program workspace → Source library → select shared or subject scope → upload and monitor processing. | Upload progress and completion appear, then the file row shows its processing state. | Upload errors identify the failure and retry guidance; source scope and file constraints are described beside the controls. |
| Administrator establishes official curriculum | Program workspace → Official curriculum → prepare extraction from eligible program PDFs → inspect the extraction proposal. | Extraction history and its review state are visible in the program workspace. | Unavailable reasons and missing-source state are explained near the action; the administrator can return to the source library. |
| Administrator drafts and reviews instructional content | Program or subject workspace → curriculum proposals → choose scope and target items → review decisions and conflicts. | Each item shows its decision and, when approved, its canonical record ID; approval is labeled as author-approved, not source-verified. | Validation preserves entered proposal edits; stale scope explains why a new proposal is needed; duplicate conflicts can be resolved or rejected item by item. |
| Learner studies and checks progress | Dashboard → Study → subject → concept → lesson or quiz → result → progress. | The result shows score and curriculum context; progress provides a route back to the concept. | Empty subject, lesson, quiz, and history states explain what is unavailable and where to return. |

Use the existing `PageShell` and `PageHeader` for route pages. Keep page titles and descriptions short, put the primary action in the header, and use generic resource names in breadcrumbs. Forms use one column on small screens and split into two columns where fields are independent; labels, short hints, inline errors, and a consistent footer keep save and cancel actions together. Use text with status colors, `role="status"` for progress or success, and `role="alert"` for blocking errors. Program and subject workspaces use responsive cards and URL-backed sections; the section picker becomes a sheet on small screens. Preserve Wayfinder links and the current component library.

The highest-priority usability risks are losing program context while creating subjects, confusing official topics with author-created concepts, and mistaking proposal approval for source verification. These are addressed in the authoring work above. Manual responsive and keyboard review remains a release check.

## Delivery phases and acceptance checks

### Phase 0 — Experience inventory and shared patterns

- [x] Inventory existing learner and administrator routes, shared layouts, page headers, navigation, forms, tables, dialogs, and status messages.
- [x] Map the common tasks for each audience and identify the current entry point, expected next action, success state, and recovery path.
- [x] Define a concise UI pattern reference using the existing component library: page width and spacing, typography hierarchy, action placement, status treatment, form layout, and responsive breakpoints.
- [x] Record the most visible usability problems to address in Phases 1–4; avoid turning this phase into a broad product redesign.
- **Check:** A learner and an administrator can follow a one-page task map for the flows in scope, and the first implementation tasks are prioritized by task blockage and confusion.

### Phase 1 — Shared navigation and dashboards

- [x] Replace placeholder dashboard content with useful, role-relevant entry points and clear next actions.
- [x] Organize navigation so learner destinations and content-management destinations are easy to distinguish, while keeping authorized access rules unchanged.
- [x] Standardize page titles, descriptions, primary actions, breadcrumbs, active navigation, and return paths on the main route pages.
- [x] Add useful dashboard empty states that explain what the user can do when no curriculum, study history, or review work exists.
- **Check:** A first-time learner can find a subject to study; a first-time administrator can reach curriculum management and proposal review; both can identify their current section and return to a sensible starting point.

### Phase 2 — Learner study journey

- [x] Make the path from Study to program, subject, topic/concept, lesson, quiz, result, and progress clear and consistent.
- [x] Keep curriculum context visible as learners move deeper, and label official topics separately from instructional concepts and lessons.
- [x] Clarify lesson actions and quiz state, including unanswered/answered questions, submission, result, retry or return actions, and progress summaries.
- [x] Provide helpful empty and unavailable states when a subject has no concepts, lessons, or quizzes.
- **Check:** A learner unfamiliar with the interface can locate a subject, open a concept lesson, complete a quiz, understand the result, and find progress or a next study action without coaching.

### Phase 3 — Administrator content workflows

- [x] Standardize program, subject, topic, concept, lesson, objective, quiz, question, and source-file management pages around consistent page and form patterns.
- [x] Make create/edit/delete consequences, validation errors, pending work, and success feedback visible near the relevant controls.
- [x] Improve curriculum generation setup and review: show scope, target items, generation state, per-item decisions, duplicate conflicts, and the next action clearly.
- [x] Make author approval distinct from source verification or official endorsement wherever generated content is displayed.
- [x] Keep long proposal reviews scannable with clear item grouping, decision state, and preserved edits after validation errors.
- **Check:** An administrator can create or update a curriculum item and review a proposal, identify a duplicate conflict, resolve or reject it, and tell which items have become canonical without relying on hidden status or generic error text.

### Phase 4 — Responsive, accessibility, and consistency pass

- [x] Apply the shared page shell and actionable empty state to learner progress.
- [x] Apply the shared patterns to authentication, settings, and remaining secondary pages.
- [x] Source-audit the shared header, navigation, page shell, dialog/sheet primitives, key forms, and primary learner/admin pages for accessible names, focus treatment, headings, status semantics, and responsive layout patterns; fix issues found in the shared header.
- [ ] Verify keyboard navigation, visible focus, labels, headings, dialog behavior, and status announcements for important interactions.
- [ ] Review common desktop, tablet, and phone widths; fix clipped controls, difficult tables, and actions that become hard to reach.
- [ ] Review loading, empty, error, permission-denied, stale, and success states across the main learner and administrator tasks.
- **Check:** The core task flows remain understandable and operable at supported widths and with keyboard-only navigation; no high-impact state depends on color alone.

#### Phase 4 implementation review — 2026-10-23

Source review confirms that shared buttons, links, form controls, and mobile navigation include visible focus styling or accessible names; key forms connect validation errors to controls, and reviewed pages use headings and status/alert semantics. The shared page shell and main dashboard, study, and generation-review pages use responsive width, wrapping, and breakpoint patterns. Existing validation, stale-proposal, conflict, permission, empty, and success handling is represented across the relevant learner and administrator workflows.

The source audit found that the shared header exposed an unlabeled, nonfunctional search button and its mobile navigation links had no explicit focus treatment or current-page announcement. The inert search control was removed; the mobile menu trigger and account menu now have accessible names, and navigation links expose `aria-current` and visible focus styling. These changes improve keyboard and assistive-technology support but still require an interactive check.

Automated verification on this worktree passed: `npm run types:check`, `npm run build`, the frontend lint check (`npx vp check --no-fmt resources/js/pages resources/js/components`), and the full Pest suite (266 tests, 1,801 assertions). `git diff --check` also passed. The broader `npm run check` reports formatting differences across 70 files, including pre-existing and out-of-scope files; no repository-wide formatter was applied.

The interactive keyboard-only walkthrough and visual review at desktop, tablet, and phone widths remain open. Chrome is available, but the browser origin-approval gate rejected opening the local app and disallows alternate browser routes or raw CDP. Launching the installed headless Chrome also fails because its crash-report socket is denied by the sandbox. Source inspection and build/test results do not establish that focus order, dialog behavior, announcements, or actual viewport rendering work correctly, so the Phase 4 acceptance check and rollout completion remain pending that walkthrough.

## Implementation and review approach

- Reuse the existing page shell, shared UI components, and route helpers. Introduce a shared pattern only when it removes visible inconsistency across multiple pages.
- Keep backend changes out of scope by default. If a page lacks information needed to explain scope, state, or next action, make the smallest data/interface change that supports that need.
- Review each phase as a working task flow, not only as isolated screenshots. Include at least one learner and one content administrator in walkthroughs when available; record where they hesitate, misunderstand state, or need help.
- Use focused feature tests for changed server behavior and existing frontend type/build checks for page changes. Manually inspect responsive and keyboard behavior; do not add a browser-testing dependency solely for this plan.

## Acceptance criteria

- Learners and administrators can identify the correct starting point and the next useful action for their role.
- Primary actions, navigation, page structure, and feedback behave consistently across the main workflows.
- Common task outcomes and failure states are visible and understandable, including validation and duplicate conflicts.
- The learner study journey and administrator content/review journey can be completed without coaching in a walkthrough.
- Core pages remain usable on phone, tablet, and desktop widths and with keyboard navigation.
- Existing curriculum ownership, review authorization, and publishing rules remain unchanged by UI/UX work.

## Out of scope

Native mobile applications; a frontend framework replacement; new learning, assessment, or AI product capabilities; automatic curriculum approval; changes to curriculum data authority; a new design-system dependency; broad backend redesign; a formal visual-brand rebrand.

## Related documents

- [01 — Final Project Description & Product Definition](01-final-project-description-product-definition.md) — intended users and the learning loop.
- [03 — Consolidated Software Requirements Specification](03-consolidated-software-requirements-specification.md) — user roles, navigation, and functional requirements.
- [08 — Executable Development Plan](08-executable-development-plan.md) — overall milestones, including learner/admin dashboards and release readiness.
- [12-M — Subject-Wide Instructional Concept Extraction](12-m-topic-scoped-concept-extraction-development-plan.md) — concept review workflow and author decisions.
- [12-N — LLM-Knowledge Lesson Generation](12-n-ungrounded-lesson-generation-development-plan.md) — lesson drafting and review workflow.
