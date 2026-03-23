# DX-Engine — Server-Driven UI Framework
## Complete Documentation v1.0

---

## Table of Contents

1. [What Is DX-Engine?](#1-what-is-dx-engine)
2. [Directory Structure](#2-directory-structure)
3. [Core Concepts](#3-core-concepts)
   - [The Metadata Bridge (JSON Schema)](#31-the-metadata-bridge-json-schema)
   - [The DataModel (ORM-lite)](#32-the-datamodel-orm-lite)
   - [The DXController (Digital Experience)](#33-the-dxcontroller-digital-experience)
   - [The JS Interpreter](#34-the-js-interpreter)
4. [The Metadata Bridge — JSON Schema Specification](#4-the-metadata-bridge--json-schema-specification)
   - [Envelope keys](#41-envelope-keys)
   - [Flow keys](#42-flow-keys)
   - [StepDescriptor](#43-stepdescriptor)
   - [ComponentDescriptor](#44-componentdescriptor)
   - [Built-in Component Types](#45-built-in-component-types)
   - [ValidationRules Object](#46-validationrules-object)
   - [VisibilityRule Object](#47-visibilityrule-object)
5. [PHP Backend Reference](#5-php-backend-reference)
   - [DataModel base class](#51-datamodel-base-class)
   - [DXController base class](#52-dxcontroller-base-class)
   - [Router](#53-router)
   - [Autoloader](#54-autoloader)
6. [JS Interpreter Reference](#6-js-interpreter-reference)
   - [DXInterpreter constructor](#61-dxinterpreter-constructor)
   - [DXInterpreter.load()](#62-dxinterpreterload)
   - [ComponentRegistry](#63-componentregistry)
   - [Validator](#64-validator)
   - [VisibilityEngine](#65-visibilityengine)
7. [Request / Response Handshake](#7-request--response-handshake)
   - [GET — fetch the Metadata Bridge](#71-get--fetch-the-metadata-bridge)
   - [POST — submit a step](#72-post--submit-a-step)
   - [Success response](#73-success-response)
   - [Validation error response](#74-validation-error-response)
8. [Admission Case Type — Implementation Walkthrough](#8-admission-case-type--implementation-walkthrough)
9. [Legacy Integration Guide](#9-legacy-integration-guide)
10. [Scalability Guide](#10-scalability-guide)
    - [Adding a new UI Component](#101-adding-a-new-ui-component)
    - [Adding a new Database Entity (Model)](#102-adding-a-new-database-entity-model)
    - [Adding a new Digital Experience (DX)](#103-adding-a-new-digital-experience-dx)
    - [Adding a new step to an existing DX](#104-adding-a-new-step-to-an-existing-dx)
11. [Database Setup](#11-database-setup)
12. [Configuration Reference](#12-configuration-reference)

---

## 1. What Is DX-Engine?

DX-Engine is a **Server-Driven UI (SDUI) framework** built on PHP 8, Bootstrap 5, and Vanilla JS. Its core idea is that the **PHP backend is the single source of truth** for every UI decision:

- What fields to show and in what order
- What validation rules apply to each field
- What the form looks like (labels, placeholders, column widths, conditional visibility)
- What happens after each step (advance, stay, complete)

The frontend interpreter is a dumb renderer. It receives a JSON payload (the **Metadata Bridge**) from PHP and constructs a fully interactive Bootstrap 5 form from it — with no hardcoded HTML, no separate field definitions, and no duplicated validation logic.

### Why SDUI?

| Concern | Traditional approach | DX-Engine approach |
|---|---|---|
| Field list | Defined in both PHP and HTML | Defined once in PHP; JS renders |
| Validation rules | Duplicated FE + BE | Defined in PHP; JS mirrors them |
| Flow control | Hardcoded JS state machine | PHP returns `next_step` ID |
| Adding a field | Edit PHP + HTML + JS | Edit PHP model only |
| A/B test a flow | Release new code | Change `getFlow()` return value |

---

## 2. Directory Structure

```
dx-engine/
│
├── config/
│   ├── app.php                  # App-level settings (debug, timezone, base URL)
│   └── database.php             # PDO connection factory
│
├── src/                         # Framework core — never edit these
│   └── Core/
│       ├── Autoloader.php       # PSR-4 class loader (no Composer required)
│       ├── DataModel.php        # ORM-lite base class — all models extend this
│       ├── DXController.php     # DX base class — all experiences extend this
│       ├── Helpers.php          # CSRF, sanitise, etc.
│       └── Router.php           # Maps ?dx=key → DXController subclass
│
├── app/                         # Your application code — edit freely
│   ├── Models/
│   │   ├── PatientModel.php     # Extends DataModel → maps to `patients` table
│   │   ├── AdmissionModel.php   # Extends DataModel → maps to `admissions` table
│   │   ├── DepartmentModel.php  # Extends DataModel → maps to `departments` table
│   │   └── InsuranceModel.php   # Extends DataModel → maps to `insurance_details` table
│   └── DX/
│       └── AdmissionDX.php      # Extends DXController — the Admission experience
│
├── database/
│   └── migrations/
│       └── 001_create_tables.sql  # All table DDL statements
│
├── public/                      # Web-accessible files
│   ├── api/
│   │   └── dx.php               # Single API endpoint — all SDUI traffic hits this
│   ├── css/
│   │   └── dx-engine.css        # Scoped Bootstrap skin + design tokens
│   ├── js/
│   │   └── dx-interpreter.js    # The JS Interpreter (the only file the browser needs)
│   └── admission.php            # Standalone full-page demo
│
├── examples/
│   ├── index.php                # Complete legacy integration example
│   ├── legacy-embed.php         # Simpler embed snippet
│   └── custom-component.js      # Registry extension example
│
└── docs/
    ├── DOCUMENTATION.md         # This file
    └── admission_flow.example.json  # Annotated JSON schema example
```

---

## 3. Core Concepts

### 3.1 The Metadata Bridge (JSON Schema)

On a `GET ?dx=admission_case` request, the PHP backend returns a single JSON object called the **Metadata Bridge**. It contains everything the JS interpreter needs:

- The ordered list of steps
- Every component in every step (type, label, value, rules, visibility)
- The endpoint to POST submissions to
- The pre-hydrated initial state (for edit mode)

The JS interpreter is stateless between page loads — if you change the JSON, the UI changes automatically.

### 3.2 The DataModel (ORM-lite)

`DataModel` is an abstract PHP base class. You extend it once per database table/view and declare a **field map** — a PHP array that describes every column:

```php
protected function fieldMap(): array
{
    return [
        'first_name' => [
            'column'   => 'first_name',   // physical column name
            'type'     => 'string',
            'label'    => 'First Name',
            'required' => true,
            'rules'    => ['min:2', 'max:80', 'regex:/^[a-zA-Z\s\-\']+$/'],
        ],
        // …
    ];
}
```

The base class provides:
- `find($id)` — SELECT by primary key
- `where($conditions)` — SELECT with WHERE clause
- `all()` — SELECT *
- `insert($data)` — INSERT
- `update($id, $data)` — UPDATE
- `delete($id)` — DELETE
- `validate($data)` — validate a payload against the field map
- `frontendSchema()` — export field map in a JS-safe format
- `relatedOne()` / `relatedMany()` — relationship resolution

### 3.3 The DXController (Digital Experience)

`DXController` is an abstract PHP base class. You extend it once per "Digital Experience" (e.g. `AdmissionDX`). You implement three methods:

```php
protected function preProcess(array $context): array   // hydrate data before rendering
protected function getFlow(array $context): array       // return the Metadata Bridge
protected function postProcess(string $step, array $payload, array $context): array  // save
```

The Router calls `dispatch()` which routes GET → `preProcess` + `getFlow`, and POST → `preProcess` + `postProcess`.

### 3.4 The JS Interpreter

`dx-interpreter.js` exports a single class: `DXInterpreter`. It:

1. Fetches the Metadata Bridge JSON from the PHP API on `load()`
2. Renders each step's components using the **Component Registry**
3. Runs client-side validation (mirrors the PHP rules) before every POST
4. POSTs the step payload to `post_endpoint`
5. Reads `next_step` from the response to advance the flow
6. Applies `visibility_rule` on every field change (show/hide conditional fields)
7. Renders a built-in completion screen when `next_step` is null

---

## 4. The Metadata Bridge — JSON Schema Specification

See `docs/admission_flow.example.json` for the complete annotated example.

### 4.1 Envelope keys

Injected automatically by `DXController::wrapFlow()`:

| Key | Type | Description |
|---|---|---|
| `_dx_version` | string | Framework version |
| `_status` | string | Always `"ok"` on successful GET |
| `_timestamp` | string | ISO-8601 server time |

### 4.2 Flow keys

| Key | Type | Description |
|---|---|---|
| `dx_id` | string | Unique identifier; must match Router key |
| `title` | string | Top-level heading for the form |
| `description` | string | Subtitle shown on Step 1 |
| `version` | string | Business-logic version for auditing |
| `post_endpoint` | string | URL the JS POSTs every step to |
| `initial_state` | object | Pre-hydrated key→value map (edit mode) |
| `context` | object | Opaque data merged into every POST body |
| `steps` | array | Ordered array of StepDescriptor objects |

### 4.3 StepDescriptor

| Key | Type | Description |
|---|---|---|
| `step_id` | string | Snake-case ID; echoed as `_step` in POST body |
| `title` | string | Shown in the Stepper bar |
| `submit_label` | string | Primary button text |
| `cancel_label` | string\|null | Back button text; null hides the button |
| `is_final` | boolean | True on the last step |
| `components` | array | Ordered array of ComponentDescriptor objects |

### 4.4 ComponentDescriptor

| Key | Type | Default | Description |
|---|---|---|---|
| `component_type` | string | — | Registry key (see §4.5) |
| `field_key` | string\|null | null | DOM `name`; POST body key; state key |
| `label` | string | `""` | Human-readable label |
| `placeholder` | string | `""` | Input placeholder |
| `required` | boolean | false | Drives FE and BE validation |
| `readonly` | boolean | false | Renders as disabled |
| `value` | mixed | `""` | Pre-filled value |
| `options` | array | `[]` | `[{value, label, css_class?}]` for select/radio |
| `validation_rules` | object | `{}` | See §4.6 |
| `visibility_rule` | object\|null | null | See §4.7 |
| `col_span` | integer | 12 | Bootstrap grid columns (1–12) → `col-md-N` |
| `css_class` | string | `""` | Extra classes added to the input element |
| `help_text` | string | `""` | Small hint below the input |
| `attrs` | object | `{}` | Arbitrary HTML attributes merged onto the input |

### 4.5 Built-in Component Types

| `component_type` | Renders as | Notes |
|---|---|---|
| `text_input` | `<input type="text">` | General-purpose text |
| `email_input` | `<input type="email">` | Built-in e-mail format check |
| `number_input` | `<input type="number">` | |
| `date_input` | `<input type="date">` | Use `attrs.max` to cap future dates |
| `textarea` | `<textarea>` | Use `attrs.rows` for height |
| `select` | `<select class="form-select">` | Requires `options` array |
| `radio` | Radio button group | Requires `options`; each option can have `css_class` |
| `checkbox_group` | Checkbox group | `value` can be an array for multi-select |
| `file_upload` | `<input type="file">` | Use `attrs.accept` to restrict MIME types |
| `heading` | `<h6 class="dx-section-heading">` | Non-interactive section divider |
| `paragraph` | `<p class="text-muted small">` | Non-interactive text |
| `divider` | `<hr class="dx-divider">` | Visual separator |
| `alert` | `<div class="alert alert-N">` | Use `attrs.variant` for Bootstrap colour |
| `hidden` | `<input type="hidden">` | Carries server context; not shown to user |

### 4.6 ValidationRules Object

```json
{
  "min"    : 2,
  "max"    : 80,
  "pattern": "^[a-zA-Z\\s\\-']+$",
  "message": "Custom error message when pattern fails."
}
```

| Key | Type | Description |
|---|---|---|
| `min` | integer | Minimum character length |
| `max` | integer | Maximum character length |
| `pattern` | string | JavaScript `RegExp` source string (no delimiters) |
| `message` | string | Error shown when `pattern` fails |

Both the JS Validator and the PHP `DataModel::validate()` evaluate the same rules. The JS check runs first (before the POST), and the PHP check runs again server-side for security.

### 4.7 VisibilityRule Object

```json
{ "field": "has_insurance", "operator": "eq", "value": "1" }
```

| Key | Type | Description |
|---|---|---|
| `field` | string | The `field_key` of another component in the same step |
| `operator` | string | `eq` `neq` `gt` `lt` `gte` `lte` `in` `nin` `empty` `not_empty` |
| `value` | string | Comparison value (for `in`/`nin`, comma-separated list) |

The VisibilityEngine evaluates this rule on every `change`/`input` event and sets `display: none` on hidden components. Hidden components are also excluded from validation.

---

## 5. PHP Backend Reference

### 5.1 DataModel base class

**Location:** `src/Core/DataModel.php`

**Boot the connection once** (in `public/api/dx.php`):
```php
DataModel::boot($pdo);
```

**Subclass contract:**
```php
protected function table(): string;      // return 'table_name';
protected function fieldMap(): array;    // return field definitions
protected function primaryKey(): string; // default 'id'
```

**Field map entry shape:**
```php
'logical_key' => [
    'column'   => 'physical_column',   // required
    'type'     => 'string',            // string|int|float|bool|date|datetime|email|phone|text
    'label'    => 'Human Label',
    'required' => true,
    'rules'    => ['min:2', 'max:80', 'regex:/pattern/'],
    'default'  => null,
    'readonly' => false,               // excluded from INSERT/UPDATE
    'relation' => [                    // optional
        'model'       => OtherModel::class,
        'foreign_key' => 'other_id',
        'type'        => 'belongs_to', // or 'has_many'
    ],
]
```

**CRUD methods:**
```php
$model->find($id)              // → array|null
$model->where($conditions)     // → array[]
$model->all($orderBy)          // → array[]
$model->insert($data)          // → int|string (lastInsertId)
$model->update($id, $data)     // → int (rowsAffected)
$model->delete($id)            // → int (rowsAffected)
$model->validate($data)        // → ['valid'=>bool, 'errors'=>['field'=>'msg']]
$model->frontendSchema()       // → array (JS-safe field map)
$model->relatedOne($row, $key) // → array|null (belongs_to)
$model->relatedMany($row, $key)// → array[] (has_many)
```

### 5.2 DXController base class

**Location:** `src/Core/DXController.php`

**Abstract methods you implement:**
```php
protected function preProcess(array $context): array;
protected function getFlow(array $context): array;
protected function postProcess(string $step, array $payload, array $context): array;
```

**Context array keys:**
```php
$context['method']   // 'GET' or 'POST'
$context['params']   // merged GET + POST + JSON body
$context['session']  // $_SESSION slice
$context['files']    // $_FILES
```

**Helper methods available inside your DX:**
```php
$this->component($type, $options)               // build a ComponentDescriptor
$this->step($stepId, $title, $components, $opts)// build a StepDescriptor
$this->optionsFromModel($model, $valCol, $lblCol)// build options from DB query
$this->success($message, $data, $nextStep)       // success response
$this->fail($errors, $message)                   // validation_error response
```

### 5.3 Router

**Location:** `src/Core/Router.php`

Register DX controllers in `public/api/dx.php`:
```php
$router = new \DXEngine\Core\Router();
$router->register('admission_case',  \App\DX\AdmissionDX::class);
$router->register('discharge_case',  \App\DX\DischargeDX::class);
$router->dispatch($_GET['dx'] ?? '');
```

### 5.4 Autoloader

**Location:** `src/Core/Autoloader.php`

PSR-4 loader (no Composer required):
```php
\DXEngine\Core\Autoloader::register(
    DX_ROOT . '/src',   // DXEngine\ namespace
    DX_ROOT . '/app'    // App\ namespace
);
// Add more namespaces:
\DXEngine\Core\Autoloader::addNamespace('Vendor\\', '/path/to/vendor/');
```

---

## 6. JS Interpreter Reference

**Location:** `public/js/dx-interpreter.js`

No build step, no bundler, no dependencies. Include it with a `<script>` tag.

### 6.1 DXInterpreter constructor

```js
const app = new DXInterpreter(target, options);
```

| Parameter | Type | Description |
|---|---|---|
| `target` | string \| HTMLElement | CSS selector or DOM node |
| `options.dx_id` | string | Required. Matches a Router key in `dx.php` |
| `options.endpoint` | string | URL to `dx.php`. Default: `/dx-engine/public/api/dx.php` |
| `options.params` | object | Extra GET params appended to the fetch URL |
| `options.csrf` | string | CSRF token included in every POST body as `_csrf` |
| `options.onComplete` | function | Called with `response.data` on final step success |
| `options.successTitle` | string | Heading on the built-in completion screen |
| `options.resetLabel` | string | If set, shows a "start over" button on completion |
| `options.completionTemplate` | function | `(response) → HTMLElement` for a fully custom screen |

### 6.2 DXInterpreter.load()

Fetches the Metadata Bridge JSON and renders Step 1. Safe to call again to reset.

```js
app.load();
```

### 6.3 ComponentRegistry

The Registry pattern allows new components to be added **without modifying the interpreter core**.

```js
// Add a new component type
DXInterpreter.registry.register('star_rating', function(descriptor, instance) {
  const wrap = document.createElement('div');
  // build your component DOM…
  return wrap;  // return the root HTMLElement
});

// Query the registry
DXInterpreter.registry.get('text_input');   // → renderer function | null
DXInterpreter.registry.types();             // → ['text_input', 'select', …]
```

The renderer receives:
- `descriptor` — the full ComponentDescriptor object from the JSON
- `instance` — the live `DXInterpreter` instance (access `instance._state`, etc.)

### 6.4 Validator

```js
// Validate a single value
const err = DXInterpreter.validator.field(value, descriptor);
// → null (valid) or "Error message string"

// Validate all visible fields in a step
const result = DXInterpreter.validator.step(stepDescriptor, formState, isVisibleFn);
// → { valid: boolean, errors: { fieldKey: 'message' } }
```

### 6.5 VisibilityEngine

```js
// Evaluate one rule
VisibilityEngine.evaluate(rule, formState);  // → boolean

// Walk a container and show/hide all [data-dx-vis] wrappers
VisibilityEngine.applyAll(containerElement, formState);
```

---

## 7. Request / Response Handshake

### 7.1 GET — fetch the Metadata Bridge

```
GET /dx-engine/public/api/dx.php?dx=admission_case
Accept: application/json
X-Requested-With: XMLHttpRequest
```

Response: the full Metadata Bridge JSON (see §4).

For edit mode, append `&admission_id=42` — `preProcess()` detects it and hydrates `initial_state`.

### 7.2 POST — submit a step

```
POST /dx-engine/public/api/dx.php?dx=admission_case
Content-Type: application/json
Accept: application/json
X-Requested-With: XMLHttpRequest
```

Body (assembled by the JS interpreter):
```json
{
  "_step"   : "patient_info",
  "_dx_id"  : "admission_case",
  "_csrf"   : "token",
  "first_name"    : "Jane",
  "last_name"     : "Doe",
  "date_of_birth" : "1990-06-15",
  "gender"        : "female",
  "contact_phone" : "+1 555-234-5678",
  "contact_email" : "jane@example.com",
  "address"       : "123 Main St"
}
```

The body always contains the **entire accumulated `formState`** — all fields from all completed steps plus the current step. This means `postProcess` on Step 2 can read `patient_id` that was returned from Step 1.

### 7.3 Success response

```json
{
  "status"   : "success",
  "message"  : "Patient information saved.",
  "data"     : { "patient_id": 42 },
  "errors"   : [],
  "next_step": "clinical_data"
}
```

- `data` is merged into `formState` (JS picks up `patient_id` for the Step 2 POST)
- `next_step` is the `step_id` to advance to; `null` means the flow is complete

### 7.4 Validation error response

```json
{
  "status"   : "validation_error",
  "message"  : "Please correct the highlighted fields.",
  "data"     : null,
  "errors"   : {
    "first_name"   : "First Name is required.",
    "contact_phone": "Phone Number format is invalid."
  },
  "next_step": null
}
```

The JS interpreter marks each field with Bootstrap's `.is-invalid` and appends `.invalid-feedback` nodes. The user stays on the current step.

---

## 8. Admission Case Type — Implementation Walkthrough

The Admission DX covers the two-step flow: **Patient Info → Clinical Data**.

### Step 1: Patient Information (`patient_info`)

**PHP pre-process:** no DB queries needed (new patient).

**PHP post-process (`handlePatientInfo`):**
1. Validate payload against `PatientModel::fieldMap()`
2. If `patient_id` exists in payload → `PatientModel::update()`; else → `PatientModel::insert()`
3. Return `success(message, ['patient_id' => $id], 'clinical_data')`

**JS receives:** `next_step: "clinical_data"` → advances to Step 2, merges `patient_id` into state.

### Step 2: Clinical Data (`clinical_data`)

**PHP pre-process:** loads `DepartmentModel::where(['is_active' => 1])` → populates the Department `<select>` options.

**PHP post-process (`handleClinicalData`):**
1. Validate against `AdmissionModel::fieldMap()`
2. Insert/update `admissions` row (reads `patient_id` from payload)
3. If `has_insurance === '1'` → validate and insert `insurance_details` row
4. Return `success(message, ['admission_id' => $id], null)` (null → done)

**JS receives:** `next_step: null` → renders completion screen and calls `onComplete({ admission_id: 15 })`.

### Conditional Insurance Block

The `has_insurance` `<select>` drives 6 insurance fields via `visibility_rule`:

```json
"visibility_rule": { "field": "has_insurance", "operator": "eq", "value": "1" }
```

When the user switches to "Has Insurance", the VisibilityEngine instantly shows those fields. When they switch back, the fields hide and are excluded from validation.

---

## 9. Legacy Integration Guide

To embed a DX-Engine form into any existing `.php` page, add exactly **three things**:

**Step 1 — Add the CSS** (after Bootstrap, in `<head>`):
```html
<link rel="stylesheet" href="/dx-engine/public/css/dx-engine.css">
```

The stylesheet is fully scoped to `.dx-root` — it will not interfere with Bootstrap or any existing styles.

**Step 2 — Add the mount point** (anywhere in `<body>`):
```html
<div id="dx-entry" class="dx-root" data-case="admission"></div>
```

- `id` is the JS selector target — pick any value you like.
- `class="dx-root"` scopes all DX-Engine CSS to this element.
- `data-case` is optional metadata; it has no functional role.

**Step 3 — Load the interpreter and initialise** (before `</body>`):
```html
<script src="/dx-engine/public/js/dx-interpreter.js"></script>
<script>
  const admission = new DXInterpreter('#dx-entry', {
    dx_id   : 'admission_case',
    endpoint: '/dx-engine/public/api/dx.php',

    onComplete: function(data) {
      // Bridge back to your legacy app here:
      window.location.href = '/admissions/view.php?id=' + data.admission_id;
    }
  });

  admission.load();
</script>
```

**That is all.** No changes to routing, sessions, or existing PHP files.

### Edit mode (pre-populating the form)

Pass `params: { admission_id: <id> }` to the constructor. PHP's `preProcess()` detects the ID and hydrates `initial_state` from the database. The JS interpreter populates every field automatically.

```html
<script>
  // Get the ID from a PHP variable, URL param, or data attribute
  const admissionId = <?= (int)($_GET['edit'] ?? 0) ?>;

  const admission = new DXInterpreter('#dx-entry', {
    dx_id   : 'admission_case',
    endpoint: '/dx-engine/public/api/dx.php',
    params  : { admission_id: admissionId },
    onComplete(data) { /* … */ }
  });
  admission.load();
</script>
```

### Communicating back to the legacy app

Use a custom DOM event from `onComplete`:
```js
onComplete: function(data) {
  document.dispatchEvent(
    new CustomEvent('dx:admission:complete', { detail: data })
  );
}
```

The rest of the legacy app listens without touching DX-Engine:
```js
document.addEventListener('dx:admission:complete', function(e) {
  console.log('Admission ID:', e.detail.admission_id);
});
```

---

## 10. Scalability Guide

### 10.1 Adding a new UI Component

To add a `star_rating` component:

**1. Register the renderer** (in your page script, or in a separate JS file loaded after `dx-interpreter.js`):

```js
DXInterpreter.registry.register('star_rating', function(descriptor, instance) {
  const wrap = document.createElement('div');
  wrap.className = 'dx-field-wrap';

  const label = document.createElement('label');
  label.className = 'form-label fw-medium small';
  label.textContent = descriptor.label ?? '';
  wrap.appendChild(label);

  // Build 5 star buttons
  const stars = document.createElement('div');
  stars.className = 'd-flex gap-1';
  const hidden = document.createElement('input');
  hidden.type  = 'hidden';
  hidden.name  = descriptor.field_key ?? '';
  hidden.value = descriptor.value ?? '';

  for (let i = 1; i <= 5; i++) {
    const btn = document.createElement('button');
    btn.type        = 'button';
    btn.className   = 'btn btn-sm btn-outline-warning';
    btn.textContent = '★';
    btn.dataset.val = String(i);
    btn.addEventListener('click', function() {
      hidden.value = btn.dataset.val;
      // Update visual state, update instance state
      instance._state[descriptor.field_key] = btn.dataset.val;
    });
    stars.appendChild(btn);
  }

  wrap.appendChild(stars);
  wrap.appendChild(hidden);
  return wrap;
});
```

**2. Use it in any DX's `getFlow()` return value:**
```php
$this->component('star_rating', [
    'field_key'  => 'patient_satisfaction',
    'label'      => 'Patient Satisfaction (1–5)',
    'required'   => true,
    'value'      => $state['patient_satisfaction'] ?? '',
    'col_span'   => 6,
]),
```

No changes to the interpreter core. No changes to the API. No changes to the CSS file (add custom styles in your own stylesheet under `.dx-root .dx-star-rating { … }`).

### 10.2 Adding a new Database Entity (Model)

**1. Write the SQL migration:**
```sql
-- database/migrations/002_create_lab_requests.sql
CREATE TABLE lab_requests (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admission_id INT UNSIGNED NOT NULL,
    test_code    VARCHAR(30)  NOT NULL,
    status       ENUM('pending','in_progress','complete') DEFAULT 'pending',
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admission_id) REFERENCES admissions(id)
);
```

**2. Create the model class:**
```php
// app/Models/LabRequestModel.php
namespace App\Models;

use DXEngine\Core\DataModel;

class LabRequestModel extends DataModel
{
    protected function table(): string { return 'lab_requests'; }

    protected function fieldMap(): array
    {
        return [
            'admission_id' => [
                'column'   => 'admission_id',
                'type'     => 'int',
                'label'    => 'Admission',
                'required' => true,
                'relation' => [
                    'model'       => AdmissionModel::class,
                    'foreign_key' => 'admission_id',
                    'type'        => 'belongs_to',
                ],
            ],
            'test_code' => [
                'column'   => 'test_code',
                'type'     => 'string',
                'label'    => 'Test Code',
                'required' => true,
                'rules'    => ['max:30'],
            ],
            'status' => [
                'column'   => 'status',
                'type'     => 'string',
                'label'    => 'Status',
                'required' => false,
                'default'  => 'pending',
            ],
        ];
    }
}
```

The Autoloader picks it up automatically — no registration needed.

### 10.3 Adding a new Digital Experience (DX)

**1. Create the DX class:**
```php
// app/DX/LabRequestDX.php
namespace App\DX;

use App\Models\LabRequestModel;
use DXEngine\Core\DXController;

class LabRequestDX extends DXController
{
    private LabRequestModel $labModel;

    public function __construct()
    {
        $this->labModel = new LabRequestModel();
    }

    protected function preProcess(array $context): array
    {
        return $context;
    }

    protected function getFlow(array $context): array
    {
        return [
            'dx_id'         => 'lab_request',
            'title'         => 'Lab Test Request',
            'post_endpoint' => '/dx-engine/public/api/dx.php?dx=lab_request',
            'initial_state' => [],
            'context'       => [],
            'steps'         => [
                $this->step('request_info', 'Request Details', [
                    $this->component('text_input', [
                        'field_key' => 'test_code',
                        'label'     => 'Test Code',
                        'required'  => true,
                        'col_span'  => 6,
                    ]),
                    // … more components
                ], ['submit_label' => 'Submit Request', 'is_final' => true]),
            ],
        ];
    }

    protected function postProcess(string $step, array $payload, array $context): array
    {
        $result = $this->labModel->validate($payload);
        if (!$result['valid']) return $this->fail($result['errors']);

        $id = $this->labModel->insert($payload);
        return $this->success('Lab request submitted.', ['lab_request_id' => $id], null);
    }
}
```

**2. Register it in `public/api/dx.php`** (one line):
```php
$router->register('lab_request', \App\DX\LabRequestDX::class);
```

**3. Mount it in any page:**
```js
new DXInterpreter('#dx-lab', {
    dx_id: 'lab_request',
    endpoint: '/dx-engine/public/api/dx.php'
}).load();
```

### 10.4 Adding a new step to an existing DX

In `AdmissionDX::getFlow()`, add a new step descriptor to the `steps` array:

```php
'steps' => [
    $this->buildStepPatientInfo($initialState),
    $this->buildStepClinicalData($initialState, $deptOptions),
    $this->buildStepConsent($initialState),          // ← new step
],
```

Add a private builder method:
```php
private function buildStepConsent(array $state): array
{
    return $this->step('consent', 'Consent & Sign-off', [
        $this->component('checkbox_group', [
            'field_key' => 'consents',
            'label'     => 'Patient Consents',
            'required'  => true,
            'col_span'  => 12,
            'options'   => [
                ['value' => 'treatment',  'label' => 'Consent to treatment'],
                ['value' => 'data_share', 'label' => 'Consent to data sharing'],
            ],
        ]),
    ], ['submit_label' => 'Finalise Admission', 'is_final' => true]);
}
```

In `postProcess()`, add a handler:
```php
if ($step === 'consent') {
    return $this->handleConsent($payload);
}
```

The step indicator bar in the UI updates automatically — no JS changes required.

---

## 11. Database Setup

Run the migration SQL against your MySQL/MariaDB database:

```bash
mysql -u root -p your_database < database/migrations/001_create_tables.sql
```

The migration creates:
- `patients` — core patient demographics
- `departments` — lookup table with seed data (5 departments)
- `admissions` — the main admission record
- `insurance_details` — optional insurance linked to an admission

Seed data is included in the migration for `departments`.

---

## 12. Configuration Reference

### `config/database.php`

```php
return new PDO(
    'mysql:host=127.0.0.1;dbname=your_db;charset=utf8mb4',
    'your_user',
    'your_password',
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]
);
```

### `config/app.php`

```php
return [
    'debug'    => true,           // false in production
    'timezone' => 'UTC',
    'base_url' => 'http://localhost',
    'session'  => [
        'name'     => 'DXSID',
        'lifetime' => 7200,
        'secure'   => false,      // true when using HTTPS
    ],
];
```

### CSS Design Tokens

Override any token in your own stylesheet (after `dx-engine.css`):

```css
:root {
  --dx-primary:        #1d4ed8;   /* brand colour */
  --dx-primary-hover:  #1e40af;
  --dx-primary-light:  #dbeafe;
  --dx-surface:        #ffffff;   /* card background */
  --dx-border:         #e2e8f0;
  --dx-radius:         0.625rem;  /* card/field corner rounding */
  --dx-font:           'Inter', system-ui, sans-serif;
}
```

All DX-Engine styles are scoped to `.dx-root` — changing these tokens only affects elements inside the DX-Engine mount point.
