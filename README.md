# DX-Engine — Complete Framework Documentation

DX-Engine is a PHP-first, Server-Driven UI (SDUI) framework for building dynamic forms, multi-step business flows, and role-aware workflow processing.  
The backend controls flow metadata, validation, routing, and persistence; the frontend interpreter renders and executes that metadata with Bootstrap-based UI.

This document is the **single canonical documentation** for the project.

---

## Table of Contents

1. [What DX-Engine Is](#1-what-dx-engine-is)  
2. [Core Principles](#2-core-principles)  
3. [Repository Structure and Safe Modification Zones](#3-repository-structure-and-safe-modification-zones)  
4. [Architecture Overview](#4-architecture-overview)  
5. [End-to-End Request Lifecycle](#5-end-to-end-request-lifecycle)  
6. [Metadata Bridge (JSON Contract)](#6-metadata-bridge-json-contract)  
7. [Backend Core Components](#7-backend-core-components)  
8. [Frontend Runtime Components](#8-frontend-runtime-components)  
9. [Built-in UI Components and Behavior](#9-built-in-ui-components-and-behavior)  
10. [Validation, Visibility, and Security Model](#10-validation-visibility-and-security-model)  
11. [Workflow + RBAC Extension](#11-workflow--rbac-extension)  
12. [API Reference](#12-api-reference)  
13. [Developer Implementation Guides](#13-developer-implementation-guides)  
14. [Practical Integration Examples](#14-practical-integration-examples)  
15. [Testing and Verification Guide](#15-testing-and-verification-guide)  
16. [Performance and Stability Guidance](#16-performance-and-stability-guidance)  
17. [Troubleshooting Guide](#17-troubleshooting-guide)  
18. [Deployment and Operations Checklist](#18-deployment-and-operations-checklist)  
19. [Glossary](#19-glossary)

---

## 1) What DX-Engine Is

DX-Engine lets you define UI behavior in backend PHP, return that as JSON, and let a lightweight JS interpreter build and run the experience in-browser.

It supports:
- Dynamic component rendering
- Multi-step forms
- Conditional field visibility
- Per-step validation
- Backend-driven transitions (`next_step`)
- Embeddable queue/worklist and RBAC administration extensions
- Session-based security controls for operational APIs

---

## 2) Core Principles

1. **Backend as source of truth**  
   UI schema and behavior originate from PHP controllers/models, not hardcoded frontend templates.

2. **Contract-driven rendering**  
   Frontend only interprets the Metadata Bridge contract.

3. **Symmetric validation**  
   Fast client checks + authoritative server validation.

4. **Composable extension model**  
   Add DX flows, models, API endpoints, and frontend components without forking framework core.

5. **Operational safety**  
   Session-aware endpoints, predictable error envelopes, controlled routing rules.

---

## 3) Repository Structure and Safe Modification Zones

## Current project layout (high level)

```text
dx-engine/
├── config/
├── database/
│   └── migrations/
├── docs/
├── examples/
├── public/
│   ├── api/
│   ├── css/
│   └── js/
├── src/
│   ├── Core/
│   └── App/
└── README.md
```

## Safe modification policy

### Core-sensitive files (modify carefully)
- `src/Core/**`
- `public/js/dx-interpreter.js`
- `public/js/dx-engine.js`
- `public/api/dx.php`
- `public/css/dx-engine.css`

### Safe extension zones (recommended for app-level work)
- `src/App/**`
- `database/migrations/**`
- `public/api/worklist.php`
- `public/api/rbac_admin.php`
- `public/api/workflow_seed_demo.php`
- `public/js/dx-worklist.js`
- `public/js/dx-rbac-admin.js`
- `examples/**`

### Environment/config zones
- `config/app.php`
- `config/database.php`

---

## 4) Architecture Overview

DX-Engine has two major planes:

1. **Framework Plane (Core Engine)**
   - Class loading
   - DataModel contract
   - DX controller contract
   - Router dispatch and API entrypoint
   - Frontend interpreter pipeline

2. **Application Plane (Business Extensions)**
   - Business-specific models (`src/App/Models`)
   - Business case controllers (`src/App/DX`)
   - Workflow/RBAC APIs and UI modules
   - SQL migrations for workflow entities

---

## 5) End-to-End Request Lifecycle

1. Browser mounts interpreter (`DXInterpreter` or `DXEngine` compatibility wrapper).
2. Interpreter requests `GET /public/api/dx.php?dx=<case_id>`.
3. `dx.php` bootstraps autoloader/config/session/db/router.
4. Router resolves DX class and returns flow JSON.
5. Frontend renders current step components and applies visibility rules.
6. User edits fields; state updates; visibility re-evaluates.
7. On submit, client validates and POSTs payload.
8. Backend validates/persists and returns:
   - `status: success` + `next_step`  
   - or `validation_error`
   - or `error`
9. Frontend transitions to next step or completion state.

---

## 6) Metadata Bridge (JSON Contract)

A flow response typically contains:

- `dx_id`
- `title`
- `steps[]`
- `initial_state`
- `post_endpoint`
- optional `context`

### Step descriptor essentials

Each step includes:
- `step_id`
- `title`
- `components[]`
- optional button labels and flags

### Component descriptor essentials

Each component may include:
- `component_type`
- `field_key`
- `label`
- `required`
- `validation_rules`
- `visibility_rule`
- `options`
- `attrs`
- `col_span`
- default `value`

---

## 7) Backend Core Components

### `src/Core/Autoloader.php`
- Registers namespace-to-path resolution.

### `src/Core/DataModel.php`
- Base ORM-like model contract.
- Requires app models to implement:
  - `table(): string`
  - `fieldMap(): array`
- Optional `primaryKey()` override.

### `src/Core/DXController.php`
- Abstract orchestration:
  - `preProcess(array $context): array`
  - `getFlow(array $context): array`
  - `postProcess(string $step, array $payload, array $context): array`

### `src/Core/Router.php`
- Maps `dx` key to DX controller class.

### `public/api/dx.php`
- Main entrypoint for DX flow GET/POST.
- Handles bootstrap + dispatch + JSON errors.

---

## 8) Frontend Runtime Components

### `public/js/dx-interpreter.js`
Primary class-based interpreter runtime:
- registry
- validator
- visibility engine
- stepper
- fetch/render/submit pipeline

### `public/js/dx-engine.js`
Compatibility/legacy runtime alias layer with similar behavior.

---

## 9) Built-in UI Components and Behavior

Common built-ins include:
- `text_input`
- `email_input`
- `number_input`
- `date_input`
- `textarea`
- `select`
- `radio`
- `checkbox_group`
- `file_upload`
- `heading`
- `paragraph`
- `divider`
- `alert`
- `hidden`

All are rendered through component registry lookups keyed by `component_type`.

---

## 10) Validation, Visibility, and Security Model

## Validation model
- Client validates for fast UX.
- Server re-validates for security/integrity.

## Visibility model
- `visibility_rule` controls dynamic show/hide.
- Rules evaluate against current form state.
- Operators include equality and numeric comparisons.

## Security model
- Session-based identity on operational APIs.
- JSON response hardening headers.
- Method restrictions where implemented.
- Safe text rendering for user-facing error messages.
- CSRF support through payload/token conventions.

---

## 11) Workflow + RBAC Extension

Added foundation includes:

### Models (`src/App/Models`)
- `DxUserModel`
- `DxGroupModel`
- `DxUserGroupModel`
- `DxCaseTypeModel`
- `DxRoutingRuleModel`
- `DxCaseInstanceModel`
- `DxAssignmentModel`
- `DxCaseEventModel`

These have been aligned to `DataModel` abstract contract (method-based definitions).

### Service (`src/Core`)
- `DxWorklistService.php`
  - queue retrieval
  - claim/release/process actions
  - event logging and routing support

### APIs (`public/api`)
- `worklist.php`
- `rbac_admin.php`
- `workflow_seed_demo.php`

### Frontend modules (`public/js`)
- `dx-worklist.js`
- `dx-rbac-admin.js`

### Migration
- `database/migrations/004_dx_workflow_rbac.sql`

---

## 12) API Reference

## 12.1 `public/api/dx.php`
DX flow and submission endpoint:
- `GET ?dx=<id>`
- `POST ?dx=<id>`

## 12.2 `public/api/worklist.php`
Queue and assignment operations:
- `GET ?action=queues`
- `POST ?action=claim`
- `POST ?action=release`
- `POST ?action=process`

## 12.3 `public/api/rbac_admin.php`
RBAC/workflow admin operations:
- `GET ?action=summary`
- POST actions for creating users/groups/memberships/routing entries.

## 12.4 `public/api/workflow_seed_demo.php`
Seed endpoint for demo workflow entities and sample runtime records.

---

## 13) Developer Implementation Guides

## A) Implement a new DX case type

1. Create a class in `src/App/DX/YourCaseDX.php` extending `DXController`.
2. Implement `preProcess`, `getFlow`, `postProcess`.
3. Register it in `public/api/dx.php` router.
4. Mount via interpreter on a page with `dx_id` matching router key.

## B) Implement a new model

1. Add class in `src/App/Models`.
2. Extend `DataModel`.
3. Implement:
   - `table()`
   - `fieldMap()`
   - optional `primaryKey()`
4. Add migration if schema changes are needed.

## C) Add a custom frontend component

1. Register renderer via `DXInterpreter.registry.register('your_type', fn)`.
2. Emit component in backend flow using `component_type: 'your_type'`.
3. Ensure field naming and state behavior align with existing collection logic.

## D) Add routing logic for workflow transitions

1. Add/update route config in workflow entities.
2. Extend service logic in `DxWorklistService`.
3. Ensure event logging + assignment transition consistency.

## E) Extend RBAC administration

1. Add action handlers in `public/api/rbac_admin.php`.
2. Use existing `Dx*Model` classes for persistence.
3. Update `dx-rbac-admin.js` to expose new controls.

---

## 14) Practical Integration Examples

## Basic DX mount

```html
<div id="dx-root" class="dx-root"></div>
<script src="/dx-engine/public/js/dx-interpreter.js"></script>
<script>
new DXInterpreter('#dx-root', {
  dx_id: 'admission_case',
  endpoint: '/dx-engine/public/api/dx.php',
  onComplete(data) { console.log('Complete', data); }
}).load();
</script>
```

## Worklist mount

```html
<div id="worklist-root"></div>
<script src="/dx-engine/public/js/dx-worklist.js"></script>
<script>
new DXWorklist('#worklist-root', {
  endpoint: '/dx-engine/public/api/worklist.php'
}).load();
</script>
```

## RBAC admin mount

```html
<div id="rbac-root"></div>
<script src="/dx-engine/public/js/dx-rbac-admin.js"></script>
<script>
new DXRbacAdmin('#rbac-root', {
  endpoint: '/dx-engine/public/api/rbac_admin.php'
}).load();
</script>
```

## Seed demo data

```bash
curl.exe -i "http://localhost/dx-engine/public/api/workflow_seed_demo.php"
```

---

## 15) Testing and Verification Guide

## Backend/API validation (curl)

### DX flow GET
```bash
curl.exe -i "http://localhost/dx-engine/public/api/dx.php?dx=admission"
curl.exe -i "http://localhost/dx-engine/public/api/dx.php?dx=admission_case"
```

### Seed workflow demo
```bash
curl.exe -i "http://localhost/dx-engine/public/api/workflow_seed_demo.php"
```

### Worklist unauthenticated edge case
```bash
curl.exe -i "http://localhost/dx-engine/public/api/worklist.php?action=queues"
```
Expected: error response indicating missing authenticated session.

### RBAC summary (session required)
```bash
curl.exe -i "http://localhost/dx-engine/public/api/rbac_admin.php?action=summary"
```

## Frontend checks

Verify:
- step rendering
- field interactions
- visibility transitions
- validation messages
- submit transitions
- completion rendering
- worklist/RBAC module load and refresh behavior

---

## 16) Performance and Stability Guidance

- Keep metadata payloads concise.
- Avoid very large option lists in initial flow response.
- Prefer paged server retrieval for admin-heavy datasets.
- Use abort/cancel behavior for overlapping in-flight requests.
- Keep UI updates minimal and state-driven.
- Avoid unnecessary direct DOM reflow loops in custom renderers.

---

## 17) Troubleshooting Guide

## Symptom: abstract method fatal in Dx* models
Cause: property-style table map used instead of required method overrides.  
Fix: implement required `table()` and `fieldMap()` methods.

## Symptom: worklist returns auth error
Cause: no session user.  
Fix: ensure authenticated `$_SESSION` identity is set before calling queue endpoints.

## Symptom: unknown DX route
Cause: route not registered in `dx.php`.  
Fix: register router key to class mapping.

## Symptom: frontend shows unknown component type
Cause: renderer not registered for emitted `component_type`.  
Fix: register custom renderer or correct backend type string.

---

## 18) Deployment and Operations Checklist

1. Configure production DB credentials in `config/database.php`.
2. Configure production app settings in `config/app.php`.
3. Run SQL migrations in order.
4. Lock down CORS and debug settings.
5. Ensure HTTPS and secure session cookie policies in production.
6. Validate all enabled endpoints behind intended auth model.
7. Run smoke + edge tests for DX, worklist, RBAC, and seeding endpoints.

---

## 19) Glossary

- **DX**: Digital Experience (a backend-defined flow)
- **Metadata Bridge**: JSON contract returned by backend for frontend rendering
- **Case Type**: Workflow/business definition for a flow
- **Assignment**: Work item ownership state
- **RBAC**: Role-Based Access Control
- **Queue**: Worklist partition by user/group/status
- **Interpreter**: Frontend runtime that renders and executes flow metadata

---

DX-Engine provides a complete backend-governed SDUI platform with workflow and RBAC extensibility while remaining embeddable in legacy or modern PHP applications.
