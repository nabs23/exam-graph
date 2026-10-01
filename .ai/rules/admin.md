---
paths:
  - 'resources/js/pages/admin/**/*.tsx'
---

# Admin

## Place concise breadcrumbs on route pages
Define breadcrumbs on route page modules such as index, show, create, and edit, not shared presentation components like `_form.tsx`. Use concise generic resource labels (for example, Program, Subject, Concept) instead of instance names, and build breadcrumb links with Wayfinder route helpers.
