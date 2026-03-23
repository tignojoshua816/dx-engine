# DX-Engine — SDUI Framework

> **Server-Driven UI for PHP/Bootstrap applications.**  
> DX-Engine lets a PHP backend own 100 % of the form definition — field types, validation rules, visibility logic, layout, and multi-step flow — while a lightweight Vanilla-JS interpreter renders and submits everything on the client. No page rebuilds. No framework lock-in. Drop into any existing PHP page with three lines of HTML.

---

## Table of Contents

1. [Repository Layout](#1-repository-layout)
2. [High-Level Architecture](#2-high-level-architecture)
   - 2.1 [The Three-Layer Model](#21-the-three-layer-model)
   - 2.2 [The Handshake: DXController ↔ JSON Schema ↔ JS Interpreter](#22-the-handshake-dxcontroller--json-schema--js-interpreter)
   - 2.3 [Full Request Lifecycle](#23-full-request-lifecycle)
3. [Developer How-To Guides](#3-developer-how-to-guides)
   - 3.1 [Creating a New Case Type](#31-creating-a-new-case-type)
   - 3.2 [Adding a New Data Model](#32-adding-a-new-data-model)
   - 3.3 [Extending the UI Registry](#33-extending-the-ui-registry)
4. [JSON Schema Reference](#4-json-schema-reference)
   - 4.1 [Response Envelope](#41-response-envelope)
   - 4.2 [Flow Descriptor Keys](#42-flow-descriptor-keys)
   - 4.3 [Step Descriptor Keys](#43-step-descriptor-keys)
   - 4.4 [Component Descriptor Keys](#44-component-descriptor-keys)
   - 4.5 [validation\_rules Object](#45-validation_rules-object)
   - 4.6 [visibility\_rule Object](#46-visibility_rule-object)
   - 4.7 [POST Body Shape](#47-post-body-shape)
   - 4.8 [Response Envelope (POST)](#48-response-envelope-post)
   - 4.9 [Built-in Component Registry](#49-built-in-component-registry)
5. [Legacy Integration Manual](#5-legacy-integration-manual)
   - 5.1 [Quick-Start Snippet](#51-quick-start-snippet)
   - 5.2 [DXInterpreter Constructor Options](#52-dxinterpreter-constructor-options)
   - 5.3 [Edit-Mode (Pre-population)](#53-edit-mode-pre-population)
   - 5.4 [Custom Completion Screen](#54-custom-completion-screen)
6. [Best Practices & Scaling](#6-best-practices--scaling)
   - 6.1 [Managing Large-Scale State](#61-managing-large-scale-state)
   - 6.2 [Security & Sanitisation in the PHP Post-Processor](#62-security--sanitisation-in-the-php-post-processor)
   - 6.3 [Maintaining CSS Consistency with Bootstrap](#63-maintaining-css-consistency-with-bootstrap)
   - 6.4 [Performance](#64-performance)
7. [Environment Variables Reference](#7-environment-variables-reference)
8. [Database Migration](#8-database-migration)

---

## 1. Repository Layout

```
dx-engine/
├── app/                        # Project-specific code (your namespace: App\)
│   ├── DX/
│   │   └── AdmissionDX.php     # Admission case type (DXController subclass)
│   └── Models/
│       ├── AdmissionModel.php
│       ├── DepartmentModel.php
│       ├── InsuranceModel.php
│       └── PatientModel.php
│
├── config/
│   ├── app.php                 # App name, env, dynamic base-URL detection
│   └── database.php            # PDO factory (reads env vars)
│
├── database/
│   └── migrations/
│       └── 001_create_tables.sql
│
├── docs/
│   ├── admission_flow.example.json   # Annotated sample Metadata Bridge payload
│   ├── DOCUMENTATION.md              # (legacy notes — superseded by this README)
│   └── XAMPP_SETUP.md
│
├── examples/
│   ├── custom-component.js     # Star rating, phone flag, signature-pad examples
│   ├── index.php               # Legacy-embed demo
│   └── legacy-embed.php
│
├── public/
│   ├── admission.php           # Standalone admission page
│   ├── api/
│   │   └── dx.php              # HTTP entry point — register DX classes here
│   ├── css/
│   │   └── dx-engine.css       # Scoped stylesheet (requires Bootstrap 5 first)
│   └── js/
│       ├── dx-engine.js        # Alias / thin wrapper (if present)
│       └── dx-interpreter.js   # Full interpreter: registry + validator + renderer
│
└── src/                        # Framework core (namespace: DXEngine\)
    └── Core/
        ├── Autoloader.php      # PSR-4 loader (optional when Composer is available)
        ├── DataModel.php       # ORM-lite base class
        ├── DXController.php    # Abstract orchestrator
        ├── Helpers.php         # Escape, CSRF, UUID, coerce utilities
        └── Router.php          # Maps ?dx=<id> to DXController subclasses
```

---

## 2. High-Level Architecture

### 2.1 The Three-Layer Model

```
┌─────────────────────────────────────────────────────────────┐
│  PHP Backend                                                │
│  ┌──────────────┐   ┌──────────────┐   ┌────────────────┐  │
│  │  DataModel   │◄──│  DXController│──►│    Router      │  │
│  │ (ORM-lite)   │   │ (Orchestrator│   │ (?dx=<id>)     │  │
│  └──────────────┘   └──────┬───────┘   └────────────────┘  │
│                             │ getFlow() returns JSON         │
└─────────────────────────────┼───────────────────────────────┘
                              │  Metadata Bridge (JSON)
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  JSON Schema (transport layer)                              │
│  { dx_id, steps[], initial_state, post_endpoint, … }        │
└─────────────────────────────┬───────────────────────────────┘
                              │  GET response / POST target
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  JS Interpreter (dx-interpreter.js)                         │
│  ┌──────────────────┐  ┌──────────┐  ┌──────────────────┐  │
│  │ ComponentRegistry│  │Validator │  │VisibilityEngine  │  │
│  │ (renderer map)   │  │(mirrors  │  │(show/hide rules) │  │
│  └──────────────────┘  │ PHP rules│  └──────────────────┘  │
│                         └──────────┘                        │
│  ┌───────────────────────────────────────────────────────┐  │
│  │  DXInterpreter  (main class)                          │  │
│  │  load() → _renderStep() → _handleSubmit() → advance  │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

Each layer has a single, clearly-bounded responsibility:

| Layer | Responsibility | Key file |
|-------|---------------|----------|
| **DataModel** | Schema definition, CRUD, server-side validation, relationship resolution | `src/Core/DataModel.php` |
| **DXController** | Pre-processing context, flow/step/component building, post-processing business logic | `src/Core/DXController.php` |
| **Router** | URL dispatch; maps `?dx=<id>` to a controller class | `src/Core/Router.php` |
| **JSON Schema** | Decoupled transport between PHP and JS; the "contract" | `docs/admission_flow.example.json` |
| **ComponentRegistry** | Maps `component_type` strings to DOM-building functions | `public/js/dx-interpreter.js §1` |
| **Validator** | Client-side rule mirror of PHP DataModel rules | `dx-interpreter.js §2` |
| **VisibilityEngine** | Evaluates `visibility_rule` on every form-state change | `dx-interpreter.js §3` |
| **Stepper** | Named multi-step indicator bar | `dx-interpreter.js §4` |
| **DXInterpreter** | Fetch → render → validate → POST → advance | `dx-interpreter.js §7` |

---

### 2.2 The Handshake: DXController ↔ JSON Schema ↔ JS Interpreter

The "Handshake" is the GET exchange that boots a Digital Experience.

```
Browser                         public/api/dx.php
   │                                    │
   │  GET /api/dx.php?dx=admission_case │
   │───────────────────────────────────►│
   │                                    │  1. Router::dispatch('admission_case')
   │                                    │  2. new AdmissionDX()
   │                                    │  3. AdmissionDX::dispatch($context)
   │                                    │     a. preProcess() → load dept options,
   │                                    │        hydrate initial_state from DB
   │                                    │     b. getFlow()    → build step/component tree
   │                                    │     c. wrapFlow()   → add _dx_version, _status
   │                                    │
   │  HTTP 200  application/json        │
   │◄───────────────────────────────────│
   │  { dx_id, steps[], initial_state,  │
   │    post_endpoint, context, … }     │
   │                                    │
   │  DXInterpreter._flow = json        │
   │  DXInterpreter._renderStep(0)      │
   │  → ComponentRegistry renders DOM   │
   │  → VisibilityEngine.applyAll()     │
```

Key design decisions:
- The **`post_endpoint`** URL is **owned by the server** and embedded in the JSON, so the JS never hard-codes a route.
- The **`initial_state`** flat map pre-populates every `value` field — enabling edit-mode with no extra JS.
- The **`context`** object is a server-opaque payload merged into every POST body so business identifiers (e.g. `admission_id`) always travel with submissions without the JS needing to understand them.

---

### 2.3 Full Request Lifecycle

#### Phase 1 — Pre-processing (GET)

```
HTTP GET ?dx=<id> [&admission_id=42]
  ↓
Router → resolves controller class
  ↓
DXController::dispatch() detects GET
  ↓
preProcess($context)
  • Load dropdown data from DataModel (DepartmentModel::where())
  • If edit context present: find() patient + admission + insurance
  • Build initial_state flat map
  • Return enriched $context
  ↓
getFlow($context)
  • Call $this->step() and $this->component() builders
  • Embed initial_state values into each component's 'value' key
  • Embed post_endpoint from $context['dx_api_endpoint']
  • Return full flow array
  ↓
wrapFlow() adds _dx_version, _status, _timestamp
  ↓
JSON response → browser
```

#### Phase 2 — Render (JS)

```
DXInterpreter.load()
  • Fetch JSON from endpoint
  • _flow = json; _state = json.initial_state
  ↓
_renderStep(index=0)
  • Build card shell + Stepper (if multi-step)
  • Iterate step.components[]:
      colWrapper = col-md-{col_span}
      If visibility_rule → data-dx-vis attribute on wrapper
      ComponentRegistry.get(component_type)(descriptor) → HTMLElement
  • VisibilityEngine.applyAll() — initial visibility pass
  • Wire <form> submit → _handleSubmit()
  • Wire change/input → _attachVisibilityListeners()
```

#### Phase 3 — User Input

```
User types/selects in form fields
  → Every change/input event fires update()
  → this._state[name] = value
  → VisibilityEngine.applyAll() re-evaluates all [data-dx-vis] wrappers
  → Hidden fields are removed from DOM; visible fields shown
```

#### Phase 4 — Post-processing (POST)

```
User clicks submit
  ↓
_handleSubmit(step, form, submitBtn)
  ↓
1. _collectForm(form) → current field values
   Object.assign(this._state, current)  ← accumulate across steps
  ↓
2. Validator.step(step, this._state, isVisible)
   If invalid → _showFieldErrors(); return (no network request)
  ↓
3. Build payload:
   { ...this._state, _step, _dx_id, _csrf, ...this._flow.context }
  ↓
4. fetch(post_endpoint, { method: 'POST', body: JSON.stringify(payload) })
  ↓
PHP side:
  DXController::dispatch() detects POST
  preProcess() (runs again — refreshes context)
  postProcess($step, $payload, $context)
    ↓
    DataModel::validate($payload)       ← server-side re-validation
    DataModel::insert() / update()
    Return success() / fail() envelope
  ↓
JSON response → browser
```

#### Phase 5 — State Transition

```
response.status === 'success'
  → Object.assign(this._state, response.data)   ← picks up new IDs (patient_id, etc.)
  → If response.next_step:
        find step by step_id → _stepIndex = idx → _renderStep()
  → If response.next_step === null:
        _renderComplete(response)
        options.onComplete(response.data)         ← bridge back to host app

response.status === 'validation_error'
  → _showFieldErrors(form, response.errors)
  → _showFormAlert(form, response.message, 'warning')

response.status === 'error'
  → _showFormAlert(form, response.message, 'danger')
```

---

## 3. Developer How-To Guides

### 3.1 Creating a New Case Type

A "Case Type" is a PHP class that extends `DXController` and implements the three abstract methods. This walkthrough creates a simple **Lab Request** case type.

#### Step 1 — Create the DX class

```php
// app/DX/LabRequestDX.php
<?php
namespace App\DX;

use App\Models\LabTestModel;
use App\Models\PatientModel;
use DXEngine\Core\DXController;

class LabRequestDX extends DXController
{
    private PatientModel $patientModel;
    private LabTestModel $labTestModel;

    public function __construct()
    {
        $this->patientModel = new PatientModel();
        $this->labTestModel = new LabTestModel();
    }
```

#### Step 2 — Implement `preProcess()`

`preProcess()` runs on **every** dispatch (GET and POST). Use it to:
- Load lookup data from models (dropdown options, reference tables)
- Authenticate or authorise the user
- Hydrate `initial_state` for edit flows

```php
    protected function preProcess(array $context): array
    {
        // Load available lab tests for the checkbox group
        $testOptions = $this->optionsFromModel(
            $this->labTestModel,
            'id',           // physical column → option value
            'test_name',    // physical column → option label
            ['is_active' => 1]
        );

        // Optionally hydrate for edit mode
        $requestId    = (int) ($context['params']['request_id'] ?? 0);
        $initialState = [];
        if ($requestId > 0) {
            $row = $this->labTestModel->find($requestId);
            if ($row) $initialState = $row;
        }

        return array_merge($context, [
            'test_options'  => $testOptions,
            'initial_state' => $initialState,
            'request_id'    => $requestId,
        ]);
    }
```

#### Step 3 — Implement `getFlow()`

`getFlow()` builds the Metadata Bridge array. Use the inherited `step()`, `component()`, and `optionsFromModel()` helper methods.

```php
    protected function getFlow(array $context): array
    {
        $testOptions  = $context['test_options']  ?? [];
        $initialState = $context['initial_state'] ?? [];

        return [
            'dx_id'         => 'lab_request',
            'title'         => 'Lab Request',
            'description'   => 'Submit a laboratory test request.',
            'version'       => '1.0',
            'post_endpoint' => $context['dx_api_endpoint'] . '?dx=lab_request',
            'initial_state' => $initialState,
            'context'       => ['request_id' => $context['request_id']],
            'steps'         => [
                $this->buildStep($initialState, $testOptions),
            ],
        ];
    }

    private function buildStep(array $state, array $testOptions): array
    {
        return $this->step('request_details', 'Request Details', [

            $this->component('text_input', [
                'field_key'        => 'patient_name',
                'label'            => 'Patient Name',
                'required'         => true,
                'value'            => $state['patient_name'] ?? '',
                'col_span'         => 12,
                'validation_rules' => ['min' => 2, 'max' => 160],
            ]),

            $this->component('checkbox_group', [
                'field_key' => 'tests_requested',
                'label'     => 'Tests Required',
                'required'  => true,
                'value'     => $state['tests_requested'] ?? [],
                'col_span'  => 12,
                'options'   => $testOptions,
            ]),

            $this->component('textarea', [
                'field_key' => 'clinical_notes',
                'label'     => 'Clinical Notes',
                'required'  => false,
                'value'     => $state['clinical_notes'] ?? '',
                'col_span'  => 12,
                'attrs'     => ['rows' => 4],
            ]),

        ], [
            'submit_label' => 'Submit Request',
            'is_final'     => true,
        ]);
    }
```

#### Step 4 — Implement `postProcess()`

`postProcess()` dispatches to per-step handlers. Always re-validate server-side even though the JS already validated.

```php
    protected function postProcess(string $step, array $payload, array $context): array
    {
        return match ($step) {
            'request_details' => $this->saveRequest($payload),
            default           => $this->fail([], "Unknown step: {$step}"),
        };
    }

    private function saveRequest(array $payload): array
    {
        // Server-side validation via model
        $result = $this->labTestModel->validate($payload);
        if (!$result['valid']) {
            return $this->fail($result['errors']);
        }

        $requestId = (int) ($payload['request_id'] ?? 0);

        if ($requestId > 0) {
            $this->labTestModel->update($requestId, $payload);
        } else {
            $requestId = (int) $this->labTestModel->insert($payload);
        }

        // next_step: null signals "no more steps → completion screen"
        return $this->success('Lab request submitted.', ['request_id' => $requestId], null);
    }
}
```

#### Step 5 — Register in the Router

Open `public/api/dx.php` and add one line:

```php
$router->register('lab_request', \App\DX\LabRequestDX::class);
```

#### Step 6 — Embed on a page

```html
<div id="lab-form" class="dx-root"></div>
<script>
  new DXInterpreter('#lab-form', {
    dx_id   : 'lab_request',
    endpoint: '/dx-engine/public/api/dx.php',
    onComplete(data) { console.log('Request ID:', data.request_id); }
  }).load();
</script>
```

That is the complete pattern. Every new Case Type follows exactly these five steps.

---

### 3.2 Adding a New Data Model

A Data Model maps one PHP class to one physical database table. It provides automatic CRUD, server-side validation, schema introspection, and a `frontendSchema()` export consumed by `DXController`.

#### Step 1 — Create the SQL table

```sql
-- database/migrations/002_create_lab_tests.sql
CREATE TABLE IF NOT EXISTS lab_tests (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    test_code   VARCHAR(20)  NOT NULL UNIQUE,
    test_name   VARCHAR(120) NOT NULL,
    category    VARCHAR(60)  NOT NULL,
    turnaround  SMALLINT UNSIGNED NOT NULL COMMENT 'Hours',
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Step 2 — Create the Model class

```php
// app/Models/LabTestModel.php
<?php
namespace App\Models;

use DXEngine\Core\DataModel;

class LabTestModel extends DataModel
{
    /**
     * Must return the exact physical table name.
     */
    protected function table(): string
    {
        return 'lab_tests';
    }

    /**
     * Field map — each key is the "logical key" used throughout the
     * framework (POST body keys, state keys, validation error keys).
     * The 'column' entry maps it to the physical column.
     */
    protected function fieldMap(): array
    {
        return [
            'test_code' => [
                'column'   => 'test_code',       // physical column
                'type'     => 'string',           // string|int|float|bool|date|datetime|email|phone|text
                'label'    => 'Test Code',        // used in validation error messages
                'required' => true,
                'rules'    => ['min:2', 'max:20'],// extra validation rule strings
            ],
            'test_name' => [
                'column'   => 'test_name',
                'type'     => 'string',
                'label'    => 'Test Name',
                'required' => true,
                'rules'    => ['min:3', 'max:120'],
            ],
            'category' => [
                'column'   => 'category',
                'type'     => 'string',
                'label'    => 'Category',
                'required' => true,
                'rules'    => ['max:60'],
            ],
            'turnaround' => [
                'column'   => 'turnaround',
                'type'     => 'int',              // triggers FILTER_VALIDATE_INT
                'label'    => 'Turnaround (hours)',
                'required' => true,
                'rules'    => [],
            ],
            'is_active' => [
                'column'   => 'is_active',
                'type'     => 'bool',
                'label'    => 'Active',
                'required' => false,
                'default'  => true,
            ],
            // read-only system columns — excluded from INSERT/UPDATE
            'created_at' => [
                'column'   => 'created_at',
                'type'     => 'datetime',
                'label'    => 'Created At',
                'readonly' => true,              // excluded from writableColumns()
            ],
        ];
    }
}
```

#### Step 3 — Use the model

```php
$model = new LabTestModel();

// Find by PK
$test = $model->find(3);

// Query with conditions
$active = $model->where(['is_active' => 1], 'test_name ASC');

// Validate before write
$result = $model->validate($_POST);
if (!$result['valid']) {
    // $result['errors'] is field_key => message
}

// Insert
$id = $model->insert(['test_code' => 'HBA1C', 'test_name' => 'HbA1c', ...]);

// Update
$model->update($id, ['turnaround' => 4]);

// Delete
$model->delete($id);
```

#### Field Map Entry Reference

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `column` | `string` | Yes | Physical column name in the database table |
| `type` | `string` | No (default: `'string'`) | One of `string`, `int`, `float`, `bool`, `date`, `datetime`, `email`, `phone`, `text` — drives type-coercion validation |
| `label` | `string` | No | Human-readable label used in validation error messages |
| `required` | `bool` | No | If `true`, a missing/empty value fails validation |
| `rules` | `string[]` | No | Extra rule strings: `min:N`, `max:N`, `regex:/pattern/` |
| `default` | `mixed` | No | Default value used by `schema()` introspection |
| `readonly` | `bool` | No | If `true`, excluded from `INSERT`/`UPDATE` statements |
| `relation` | `array` | No | Foreign-key metadata: `['model' => SomeModel::class, 'foreign_key' => 'col', 'type' => 'belongs_to\|has_many']` |

#### Defining a Relationship

```php
// In AdmissionModel::fieldMap():
'patient_id' => [
    'column'   => 'patient_id',
    'type'     => 'int',
    'label'    => 'Patient',
    'required' => true,
    'relation' => [
        'model'       => PatientModel::class,
        'foreign_key' => 'patient_id',
        'type'        => 'belongs_to',
    ],
],
```

Resolve at runtime:

```php
$admissionRow = (new AdmissionModel)->find(15);
$patient      = (new AdmissionModel)->relatedOne($admissionRow, 'patient_id');
// Returns the patients row or null

$insuranceRows = (new AdmissionModel)->relatedMany($admissionRow, 'insurance');
// Returns all insurance_details rows for this admission
```

---

### 3.3 Extending the UI Registry

The `ComponentRegistry` is an in-memory singleton `Map`. Any code loaded **after** `dx-interpreter.js` can register new component types without touching the core file.

#### Renderer Function Signature

```js
/**
 * @param {Object}        descriptor  Full ComponentDescriptor from the JSON
 * @param {DXInterpreter} instance    Live interpreter (access formState, etc.)
 * @returns {HTMLElement}             The DOM node to insert into the column wrapper
 */
function myRenderer(descriptor, instance) { … }
```

#### Built-in Helper Functions (available globally after the script loads)

| Function | Signature | Description |
|----------|-----------|-------------|
| `_el(tag, className)` | `(string, string?) → HTMLElement` | Create an element with an optional CSS class string |
| `_wrapField(descriptor, inputEl)` | `(Object, HTMLElement\|HTMLElement[]) → HTMLElement` | Wrap an input in the standard DX label + required marker + help-text block |
| `_applyAttrs(el, descriptor)` | `(HTMLElement, Object) → void` | Apply `id`, `name`, `readonly`, `required`, `placeholder`, `value`, `attrs`, `css_class` to a form control |

#### Example 1 — A Native Date-Picker with Min/Max

```js
// custom-components.js  (loaded after dx-interpreter.js)

DXInterpreter.registry.register('date_range_input', function (descriptor) {
  const group = _el('div', 'row g-2');

  // Start date
  const startWrap = _el('div', 'col-6');
  const startInp  = _el('input', 'form-control');
  startInp.type   = 'date';
  startInp.id     = 'dx-' + descriptor.field_key + '_start';
  startInp.name   = descriptor.field_key + '_start';
  if (descriptor.attrs?.min) startInp.min = descriptor.attrs.min;
  if (descriptor.attrs?.max) startInp.max = descriptor.attrs.max;
  startInp.value = descriptor.value?.start ?? '';
  startWrap.appendChild(_wrapField(
    { ...descriptor, label: 'From', field_key: descriptor.field_key + '_start', required: descriptor.required },
    startInp
  ));

  // End date
  const endWrap = _el('div', 'col-6');
  const endInp  = _el('input', 'form-control');
  endInp.type   = 'date';
  endInp.id     = 'dx-' + descriptor.field_key + '_end';
  endInp.name   = descriptor.field_key + '_end';
  endInp.value  = descriptor.value?.end ?? '';
  endWrap.appendChild(_wrapField(
    { ...descriptor, label: 'To', field_key: descriptor.field_key + '_end', required: false },
    endInp
  ));

  group.appendChild(startWrap);
  group.appendChild(endWrap);
  return group;
});
```

Use in a DX controller:

```php
$this->component('date_range_input', [
    'field_key' => 'stay_period',
    'label'     => 'Stay Period',
    'required'  => true,
    'col_span'  => 12,
    'attrs'     => ['min' => date('Y-m-d')],
])
```

#### Example 2 — A File Upload with Accept Filter

```js
DXInterpreter.registry.register('file_upload_filtered', function (descriptor) {
  const inp = _el('input', 'form-control');
  inp.type  = 'file';
  inp.id    = 'dx-' + descriptor.field_key;
  inp.name  = descriptor.field_key;

  // Accept attribute from descriptor.attrs (e.g. ".pdf,.doc,.docx")
  if (descriptor.attrs?.accept)   inp.accept   = descriptor.attrs.accept;
  if (descriptor.attrs?.multiple) inp.multiple = true;
  if (descriptor.required)        inp.required = true;

  return _wrapField(descriptor, inp);
});
```

PHP usage:

```php
$this->component('file_upload_filtered', [
    'field_key' => 'referral_letter',
    'label'     => 'Referral Letter (PDF)',
    'required'  => true,
    'col_span'  => 6,
    'attrs'     => ['accept' => '.pdf', 'multiple' => false],
])
```

> **Note on file uploads:** The interpreter currently sends payloads as `application/json`. For binary file uploads, the renderer must build and submit a `FormData` object via a custom submit handler, or base64-encode the file into the JSON payload. See `examples/custom-component.js` for the signature-pad approach (canvas → base64 PNG → hidden input → JSON).

#### Example 3 — A Select2-style Searchable Dropdown

```js
DXInterpreter.registry.register('select_search', function (descriptor, instance) {
  // Build native <select> first, then progressively enhance
  const sel = _el('select', 'form-select');
  _applyAttrs(sel, descriptor);

  (descriptor.options ?? []).forEach(opt => {
    const o       = document.createElement('option');
    o.value       = opt.value;
    o.textContent = opt.label;
    if (String(opt.value) === String(descriptor.value)) o.selected = true;
    sel.appendChild(o);
  });

  // Progressive enhancement: if Tom Select is available, activate it
  if (typeof TomSelect !== 'undefined') {
    new TomSelect(sel, { create: false, placeholder: descriptor.placeholder });
  }

  return _wrapField(descriptor, sel);
});
```

---

## 4. JSON Schema Reference

The Metadata Bridge is the JSON object that PHP's `DXController::getFlow()` returns and that the JS interpreter reads. It is the single source of truth for the form's structure, content, and behaviour.

### 4.1 Response Envelope

These keys are injected by `DXController::wrapFlow()` on every GET response.

| Key | Type | Description |
|-----|------|-------------|
| `_dx_version` | `string` | Framework version string (currently `"1.0"`) |
| `_status` | `string` | Always `"ok"` on a successful GET |
| `_timestamp` | `string` | ISO-8601 server timestamp |

### 4.2 Flow Descriptor Keys

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `dx_id` | `string` | Yes | Unique snake-case identifier. Must match the key passed to `$router->register()`. |
| `title` | `string` | Yes | Top-level form heading. Displayed in the card header when there is only one step. |
| `description` | `string` | No | Subtitle shown only on Step 1, below the title. |
| `version` | `string` | No | Business-logic version string for auditing/caching. |
| `post_endpoint` | `string` | Yes | Full URL the interpreter POSTs every step payload to. Always set dynamically from `$context['dx_api_endpoint']`; never hardcode. |
| `initial_state` | `object` | No | Flat `field_key → value` map. Pre-populates every matching component's `value`. Used for edit flows. |
| `context` | `object` | No | Server-opaque data merged into every POST body automatically. Use for IDs or metadata the server needs back. |
| `steps` | `StepDescriptor[]` | Yes | Ordered array. The interpreter renders them in sequence. |

### 4.3 Step Descriptor Keys

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `step_id` | `string` | Yes | Snake-case identifier. Echoed in the POST body as `_step`. The `postProcess()` method routes on this value. |
| `title` | `string` | Yes | Displayed in the Stepper bar and as the card heading. |
| `submit_label` | `string` | No (default: `"Continue"`) | Text of the primary submit button. |
| `cancel_label` | `string\|null` | No | Text of the Back button. Set to `null` (or omit) to hide it. |
| `is_final` | `bool` | No (default: `false`) | Semantic flag for the last step. Has no runtime effect today but is available for custom renderers and hooks. |
| `components` | `ComponentDescriptor[]` | Yes | Ordered array of component descriptors rendered inside a Bootstrap `.row.g-3` grid. |

### 4.4 Component Descriptor Keys

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `component_type` | `string` | Yes | Registry key. See [§4.9 Built-in Component Registry](#49-built-in-component-registry). |
| `field_key` | `string\|null` | Depends | The DOM `name` attribute, POST body key, and `formState` key. `null` for layout-only components (heading, divider, paragraph). |
| `label` | `string` | No | Human-readable label rendered in a `<label>` or heading element. |
| `placeholder` | `string` | No | Input `placeholder` attribute. |
| `required` | `bool` | No (default: `false`) | Drives client-side **and** server-side validation. Renders a red `*` marker. |
| `readonly` | `bool` | No (default: `false`) | Renders the input as `readOnly`; value is still submitted. |
| `value` | `mixed` | No | Pre-filled value. For `checkbox_group`, pass an array of selected values. For `select`/`radio`, pass the matching option value string. |
| `options` | `array` | Context-dependent | Array of `{ "value": "x", "label": "Label", "css_class": "..." }` objects. Required for `select`, `radio`, `checkbox_group`. |
| `validation_rules` | `object` | No | See [§4.5](#45-validation_rules-object). |
| `visibility_rule` | `object\|null` | No | See [§4.6](#46-visibility_rule-object). When set, the column wrapper gets a `data-dx-vis` attribute evaluated by `VisibilityEngine`. |
| `col_span` | `int` | No (default: `12`) | Bootstrap grid column width (1–12). Maps to `col-md-{N}`. Full-width = `12`, half = `6`, third = `4`, quarter = `3`. |
| `css_class` | `string` | No | Extra CSS classes appended to the form control element (not the wrapper). |
| `help_text` | `string` | No | Small hint text rendered in a `.form-text` `<div>` below the input. |
| `attrs` | `object` | No | Arbitrary HTML attributes merged onto the input element. Common uses: `{ "rows": 4 }` for textarea, `{ "min": "0", "max": "100" }` for number_input, `{ "max": "2024-12-31" }` for date_input, `{ "accept": ".pdf" }` for file_upload. |

### 4.5 `validation_rules` Object

Evaluated **client-side** by `Validator.field()` and **server-side** by `DataModel::validate()`. The two engines mirror each other so the user sees immediate feedback but the server is never bypassed.

| Key | Type | Description |
|-----|------|-------------|
| `min` | `number` | Minimum string **length** (character count). |
| `max` | `number` | Maximum string **length** (character count). |
| `pattern` | `string` | JavaScript RegExp pattern string (no delimiters — e.g. `"^[A-Z]+$"`, not `"/^[A-Z]+$/"`) . Also used as the PHP `regex:` rule value on the backend. |
| `message` | `string` | Custom error message shown when `pattern` fails. Falls back to `"{Label} format is invalid."`. |

**Example:**

```json
"validation_rules": {
  "min": 2,
  "max": 80,
  "pattern": "^[a-zA-Z\\s\\-']+$",
  "message": "Name may only contain letters, spaces, hyphens, or apostrophes."
}
```

> `min`/`max` always validate string **length**. For numeric range validation on a `number_input`, use the `attrs` approach: `{ "min": "0", "max": "150" }`, which sets native HTML5 `min`/`max` attributes, and combine with `type: 'int'` in the model.

### 4.6 `visibility_rule` Object

Controls whether a component is shown or hidden based on the current form state. Evaluated on every `change`/`input` event by `VisibilityEngine.evaluate()`.

| Key | Type | Description |
|-----|------|-------------|
| `field` | `string` | The `field_key` of the controlling field. |
| `operator` | `string` | Comparison operator (see table below). |
| `value` | `string` | The right-hand side value. For `in`/`nin`, use a comma-separated list (`"1,2,3"`). |

**Supported operators:**

| Operator | Meaning |
|----------|---------|
| `eq` | Field value exactly equals `value` (string comparison) |
| `neq` | Field value does not equal `value` |
| `gt` | Field value (as float) is greater than `value` |
| `lt` | Field value (as float) is less than `value` |
| `gte` | Field value (as float) is greater than or equal to `value` |
| `lte` | Field value (as float) is less than or equal to `value` |
| `in` | Field value is one of the comma-separated items in `value` |
| `nin` | Field value is NOT one of the comma-separated items in `value` |
| `empty` | Field value is an empty string |
| `not_empty` | Field value is a non-empty string |

**Example — Show insurance block only when `has_insurance === "1"`:**

```json
"visibility_rule": {
  "field"   : "has_insurance",
  "operator": "eq",
  "value"   : "1"
}
```

> Invisible components are hidden with `display: none` via `data-dx-hidden="1"`. Their inputs are still in the DOM and still submitted. The PHP `postProcess()` must decide whether to act on conditionally-hidden fields based on the controlling field's value (e.g. skip insurance logic when `has_insurance !== '1'`).

### 4.7 POST Body Shape

On every step submission the interpreter POSTs a flat JSON object:

```json
{
  "_step"         : "patient_info",
  "_dx_id"        : "admission_case",
  "_csrf"         : "abc123token",

  "first_name"    : "Jane",
  "last_name"     : "Doe",
  "date_of_birth" : "1990-06-15",
  "gender"        : "female",
  "contact_phone" : "+1 555-234-5678",
  "contact_email" : "jane.doe@example.com",
  "address"       : "123 Main St, Springfield, IL 62701",

  "admission_id"  : 0
}
```

Notes:
- `_step` maps to a `postProcess()` handler via `match ($step) { … }`.
- `_dx_id` identifies the Digital Experience for logging/auditing.
- `_csrf` is the CSRF token if `options.csrf` was set in the interpreter.
- All accumulated state from **previous steps** is included (the interpreter does `Object.assign(this._state, current)` before POSTing), so `patient_id` saved from step 1 is present in the step 2 body.
- Keys from `this._flow.context` (e.g. `admission_id`) are merged in last.

### 4.8 Response Envelope (POST)

`DXController::postProcess()` must return one of two shapes built by the `success()` or `fail()` helpers:

**Success:**

```json
{
  "status"   : "success",
  "message"  : "Patient information saved.",
  "data"     : { "patient_id": 42 },
  "errors"   : [],
  "next_step": "clinical_data"
}
```

**Validation failure:**

```json
{
  "status"   : "validation_error",
  "message"  : "Please correct the highlighted fields.",
  "data"     : null,
  "errors"   : {
    "first_name"   : "First Name is required.",
    "contact_phone": "Contact Phone must be a valid phone number."
  },
  "next_step": null
}
```

| Key | Type | Description |
|-----|------|-------------|
| `status` | `string` | `"success"` \| `"validation_error"` \| `"error"` |
| `message` | `string` | Human-readable status message shown in the completion screen or inline alert |
| `data` | `object\|null` | Arbitrary return data merged into `formState` by the interpreter. Use this to pass back database IDs (e.g. `patient_id`) that subsequent steps need. |
| `errors` | `object` | `field_key → error message` map. Non-empty only on `validation_error`. |
| `next_step` | `string\|null` | `step_id` of the next step to render. `null` triggers the completion screen. |

### 4.9 Built-in Component Registry

| `component_type` | HTML Output | Notes |
|-----------------|-------------|-------|
| `text_input` | `<input type="text" class="form-control">` | General-purpose text field |
| `email_input` | `<input type="email" class="form-control">` | Triggers additional RFC-lite email check in `Validator` |
| `number_input` | `<input type="number" class="form-control">` | Use `attrs: { min, max, step }` for numeric bounds |
| `date_input` | `<input type="date" class="form-control">` | Use `attrs: { min, max }` to constrain date range |
| `textarea` | `<textarea class="form-control">` | Use `attrs: { rows: N }` to set height |
| `select` | `<select class="form-select">` | Requires `options` array |
| `radio` | `<div class="form-check">` × N | Requires `options` array. `options[].css_class` is added to the wrapper div |
| `checkbox_group` | `<div class="form-check">` × N | Requires `options` array. Value is an array of selected option values |
| `file_upload` | `<input type="file" class="form-control">` | Use `attrs: { accept, multiple }` |
| `heading` | `<h6 class="dx-section-heading">` | Layout-only; no `field_key` |
| `paragraph` | `<p class="text-muted small">` | Layout-only; `label` becomes paragraph text |
| `divider` | `<hr class="dx-divider">` | Layout-only horizontal rule |
| `alert` | `<div class="alert alert-{variant}">` | Use `attrs: { variant: "info\|warning\|danger\|success" }` |
| `hidden` | `<input type="hidden">` | Submitted with the form; not visible to the user |

---

## 5. Legacy Integration Manual

### 5.1 Quick-Start Snippet

Drop DX-Engine into any existing PHP/HTML page with **exactly three additions**:

```html
<!-- ① Load dx-engine.css AFTER your Bootstrap stylesheet -->
<link rel="stylesheet" href="/dx-engine/public/css/dx-engine.css">

<!-- ② Place the target <div> where the form should appear.
     • id         — any unique selector you choose
     • class="dx-root" — REQUIRED: scopes all DX CSS; prevents style bleed
     • data-case  — optional metadata for your own JS hooks -->
<div id="dx-entry" class="dx-root" data-case="admission"></div>

<!-- ③ Load dx-interpreter.js and initialise — after Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/dx-engine/public/js/dx-interpreter.js"></script>
<script>
  new DXInterpreter('#dx-entry', {
    dx_id   : 'admission_case',
    endpoint: '/dx-engine/public/api/dx.php',
    onComplete(data) {
      // Bridge back to the legacy app
      console.log('Done:', data);
    }
  }).load();
</script>
```

> **Bootstrap is required.** DX-Engine CSS is intentionally thin and relies on Bootstrap 5.3+ for base component styles. Load Bootstrap *before* `dx-engine.css`.

### 5.2 DXInterpreter Constructor Options

```js
new DXInterpreter(target, options)
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `dx_id` | `string` | — | **Required.** Must match a key registered in `$router->register()` in `dx.php`. |
| `endpoint` | `string` | `'/dx-engine/public/api/dx.php'` | Full absolute path to the DX API entry point. |
| `params` | `object` | `{}` | Extra query-string parameters appended to the GET fetch URL. Used for edit mode: `{ admission_id: 42 }`. |
| `csrf` | `string` | `''` | CSRF token string. Included in every POST body as `_csrf`. Generate server-side with `Helpers::csrfToken()` and echo it into the page. |
| `onComplete` | `function(data)` | `null` | Called once the final step's POST returns `status: 'success'` with `next_step: null`. `data` is `response.data` from PHP. Use this to redirect, update the legacy UI, or fire a custom event. |
| `successTitle` | `string` | `'Submitted Successfully'` | Heading text in the built-in completion card. |
| `resetLabel` | `string` | `''` | If set, renders a "start over" button on the completion screen. The button clears state and calls `load()` again. |
| `completionTemplate` | `function(response) → HTMLElement` | `null` | Full override of the completion screen. Return any `HTMLElement` to replace the default success card. |

### 5.3 Edit-Mode (Pre-population)

Pass the record's ID as a `params` key. The PHP `preProcess()` method detects it, queries the database, and returns the record's data in `initial_state`. The interpreter seeds `this._state` from `initial_state` before rendering, so every field is pre-filled.

```js
const admission = new DXInterpreter('#dx-entry', {
  dx_id  : 'admission_case',
  endpoint: '/dx-engine/public/api/dx.php',
  params : { admission_id: <?= (int) $admissionId ?> },
  onComplete(data) { window.location.href = '/admissions/' + data.admission_id; }
});
admission.load();
```

The PHP side (already in `AdmissionDX::preProcess()`):

```php
$admissionId = (int) ($context['params']['admission_id'] ?? 0);
if ($admissionId > 0) {
    $admission    = $this->admissionModel->find($admissionId);
    $patient      = $this->patientModel->find($admission['patient_id']);
    $insurance    = $this->insuranceModel->where(['admission_id' => $admissionId], '', 1)[0] ?? [];
    $initialState = array_merge($patient ?? [], $admission, $insurance);
}
```

### 5.4 Custom Completion Screen

```js
new DXInterpreter('#dx-entry', {
  dx_id   : 'admission_case',
  endpoint: '/dx-engine/public/api/dx.php',

  completionTemplate(response) {
    const div = document.createElement('div');
    div.className = 'alert alert-success p-4';
    div.innerHTML = `
      <h4 class="alert-heading">Admission #${response.data.admission_id} Registered</h4>
      <p>${response.message}</p>
      <hr>
      <a class="btn btn-sm btn-success" href="/admissions/${response.data.admission_id}">
        View Record
      </a>
    `;
    return div;
  }
}).load();
```

---

## 6. Best Practices & Scaling

### 6.1 Managing Large-Scale State

**Symptom:** Many steps, many fields, and the flat `formState` object becomes unwieldy or difficult to audit.

**Recommendations:**

1. **Persist partial state server-side after each step.**  
   Return a server-side session key or a temporary draft record ID in `response.data`. The interpreter merges it into `this._state`, so subsequent POST bodies carry it automatically. Re-hydrate from the draft in `preProcess()` on back-navigation.

   ```php
   // In postProcess(), save step 1 data to a draft table
   $draftId = $this->draftModel->upsert($payload);
   return $this->success('Step 1 saved.', ['draft_id' => $draftId], 'step_2');
   ```

2. **Use the `context` key for static server identifiers.**  
   Never embed mutable state in `context`. Use it only for IDs that are known at GET time (e.g. `admission_id` for an edit flow). Mutable data belongs in `initial_state`.

3. **Namespace field keys** when multiple models share a form.  
   Prefix field keys by entity: `patient_first_name`, `admission_triage_level`. This prevents collision in the flat `formState` map and makes POST body parsing unambiguous.

4. **Paginate long SELECT option lists.**  
   If a dropdown has more than ~200 options, load them lazily via a separate AJAX call and use a searchable select extension (see §3.3 Tom Select example). Do not embed thousands of options in the Metadata Bridge JSON — it inflates the initial GET response and slows rendering.

5. **Avoid deeply nested flows.**  
   Each step in DX-Engine is a flat list of components. If your flow requires true branching (e.g. step 3A vs step 3B depending on step 2), model it as separate Case Types and redirect between them via `onComplete`, or pre-load all branches and control visibility via `visibility_rule`.

---

### 6.2 Security & Sanitisation in the PHP Post-Processor

DX-Engine's architecture means the PHP backend is the **last line of defence**. The client-side `Validator` mirrors PHP rules for UX, but is never trusted for security.

#### 1. Always re-validate server-side

```php
private function savePatientInfo(array $payload): array
{
    // NEVER skip this — client validation is advisory only
    $result = $this->patientModel->validate($payload);
    if (!$result['valid']) {
        return $this->fail($result['errors']);
    }
    // ...
}
```

#### 2. Use `Helpers::esc()` for any HTML output

```php
use DXEngine\Core\Helpers;

$safeName = Helpers::esc($payload['patient_name']);
// htmlspecialchars with ENT_QUOTES | ENT_HTML5 | UTF-8
```

For arrays:

```php
$safePayload = Helpers::escArray($payload);
```

#### 3. Use CSRF tokens for state-changing requests

Generate on the PHP page:

```php
<?php
use DXEngine\Core\Helpers;
$csrf = Helpers::csrfToken();   // stored in $_SESSION['_dx_csrf']
?>
<script>
  new DXInterpreter('#dx-entry', {
    dx_id: 'admission_case',
    csrf : '<?= htmlspecialchars($csrf) ?>',
    // ...
  }).load();
</script>
```

Verify in `postProcess()`:

```php
use DXEngine\Core\Helpers;

protected function postProcess(string $step, array $payload, array $context): array
{
    if (!Helpers::verifyCsrf($payload['_csrf'] ?? '')) {
        return $this->fail([], 'Invalid request. Please reload the page.');
    }
    // ...
}
```

#### 4. Use parameterised queries — never string interpolation

`DataModel::insert()` and `DataModel::update()` always use PDO prepared statements with named placeholders. Do not bypass them with raw SQL in `postProcess()`.

```php
// CORRECT — uses DataModel's prepared statements
$this->patientModel->insert($payload);

// DANGEROUS — never do this
$name = $payload['first_name'];
$pdo->exec("INSERT INTO patients (first_name) VALUES ('$name')");
```

#### 5. Sanitise file uploads separately

`DataModel` does not handle file validation. In `postProcess()`:

```php
$file = $context['files']['referral_letter'] ?? null;
if ($file && $file['error'] === UPLOAD_ERR_OK) {
    $allowed = ['application/pdf'];
    $finfo   = new finfo(FILEINFO_MIME_TYPE);
    $mime    = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed, true)) {
        return $this->fail(['referral_letter' => 'Only PDF files are accepted.']);
    }
    // Move to a location outside the web root
    move_uploaded_file($file['tmp_name'], '/var/uploads/' . Helpers::uuid() . '.pdf');
}
```

#### 6. Restrict CORS origins in production

In `src/Core/Router.php`, the CORS header defaults to `*`. Replace with your domain before deploying:

```php
// config/app.php
'cors_origins' => ['https://your-hospital.com'],
```

Then in `Router::dispatch()`, replace the wildcard with:

```php
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = $config['cors_origins'] ?? ['*'];
if (in_array('*', $allowed) || in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
}
```

---

### 6.3 Maintaining CSS Consistency with Bootstrap

#### Scoping strategy

All DX-Engine styles are scoped under `.dx-root`. This means:
- No DX styles bleed into the host page.
- Bootstrap classes (`form-control`, `btn`, `alert`, etc.) still apply inside `.dx-root` because Bootstrap is not scoped.
- Host-page styles **can** accidentally override DX components unless the host styles are also scoped.

**Rule:** Always add `class="dx-root"` to the target `<div>`. Never remove it.

#### Overriding design tokens

Every visual property is a CSS custom property. Override them in your host stylesheet **after** loading `dx-engine.css`:

```css
/* my-hospital-theme.css */
:root {
  --dx-primary:       #006D77;   /* Teal brand colour */
  --dx-primary-hover: #004F57;
  --dx-primary-light: #E8F4F5;
  --dx-radius:        0.25rem;   /* Flatter corners */
  --dx-font:          'Roboto', system-ui, sans-serif;
}
```

#### Adding triage or status colour classes

The stylesheet ships with `--dx-triage-1` through `--dx-triage-5`. To add domain-specific palette entries:

```css
:root {
  --dx-status-pending:    #f59e0b;
  --dx-status-admitted:   #22c55e;
  --dx-status-discharged: #64748b;
}

.dx-root .status-badge-pending    { color: var(--dx-status-pending); }
.dx-root .status-badge-admitted   { color: var(--dx-status-admitted); }
.dx-root .status-badge-discharged { color: var(--dx-status-discharged); }
```

Then use these classes via the `css_class` key on any component descriptor.

#### Bootstrap version compatibility

DX-Engine is tested against Bootstrap **5.3.x**. Class names used:
`form-control`, `form-select`, `form-check`, `form-check-input`, `form-check-label`,
`form-label`, `form-text`, `btn`, `btn-primary`, `btn-outline-secondary`,
`card`, `card-header`, `card-body`, `card-footer`,
`row`, `g-3`, `col-md-{n}`, `d-flex`, `gap-2`, `alert`, `alert-{variant}`,
`spinner-border`, `spinner-border-sm`, `visually-hidden`.

Bootstrap 4 is **not** compatible (different class names for grid, form controls, and flexbox utilities).

---

### 6.4 Performance

| Concern | Recommendation |
|---------|---------------|
| **Large option lists** | Load options lazily after mount via a secondary AJAX call and a custom component renderer. Do not embed more than ~200 options in the Metadata Bridge. |
| **Multiple DX on one page** | Each `new DXInterpreter()` is independent. Mount them in separate `dx-root` divs. Avoid more than two or three on a single page. |
| **Metadata Bridge caching** | The GET response is stateless and safe to cache. Add `Cache-Control: max-age=60` for flows whose options don't change per-user. Bust the cache by appending a version param. |
| **PHP opcode cache** | Enable OPcache in production (`opcache.enable=1`). The framework's autoloader calls `require` for every class per request. |
| **Network round-trips** | DX-Engine makes one GET (flow) + one POST per step. For a 3-step form that is 4 requests. If the backend is slow, use `preProcess()` to pre-load all data in the GET rather than making secondary AJAX calls. |
| **Asset delivery** | Serve `dx-interpreter.js` and `dx-engine.css` via a CDN or at minimum with a far-future `Expires` header. The files are small (~30 KB JS unminified, ~8 KB CSS) but latency matters on mobile. |

---

## 7. Environment Variables Reference

All variables are read via `$_ENV` in `config/app.php` and `config/database.php`. Set them in your server's environment, an Apache `SetEnv` directive, or a `.env` file loaded by a bootstrap include.

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_NAME` | `DX-Engine` | Application name used in logs |
| `APP_ENV` | `production` | `production` or `development` |
| `APP_DEBUG` | `false` | Set to `true` to expose PHP exception messages in API error responses |
| `APP_URL` | *(auto-detected)* | Override the base URL. Required when behind a reverse proxy or on a non-standard port. E.g. `https://my-hospital.com` |
| `DX_ENDPOINT` | *(derived from `APP_URL`)* | Override the `post_endpoint` URL embedded in all Metadata Bridge responses. Useful when the API is on a separate subdomain. |
| `DB_HOST` | `localhost` | MySQL/MariaDB host |
| `DB_PORT` | `3306` | MySQL/MariaDB port |
| `DB_DATABASE` | `dx_engine` | Database name |
| `DB_USERNAME` | `root` | Database user |
| `DB_PASSWORD` | *(empty)* | Database password |
| `CORS_ORIGINS` | `*` | Comma-separated allowed CORS origins. Set to your domain(s) in production: `https://app.example.com` |

---

## 8. Database Migration

Run the migration once to create all tables:

```bash
mysql -u root -p dx_engine < database/migrations/001_create_tables.sql
```

Or from phpMyAdmin: **Import → select `001_create_tables.sql` → Go.**

The migration creates four tables with referential integrity:

```
departments     (id, code, name, is_active)
       ▲
       │ FK
patients        (id, first_name, last_name, date_of_birth, gender,
       ▲         contact_phone, contact_email, address)
       │ FK
admissions      (id, patient_id►, department_id►, triage_level,
       ▲         chief_complaint, attending_physician, status, notes)
       │ FK
insurance_details (id, admission_id►, provider_name, policy_number,
                   group_number, holder_name, holder_dob, expiry_date)
```

Ten department seed rows are included (`ED`, `ICU`, `ORTHO`, `CARD`, `NEURO`, `PEDS`, `OB`, `SURG`, `PSYCH`, `ONCO`).

For additional migrations, create `database/migrations/002_*.sql` etc. and run them in order. Do not modify files that have already been executed against the database.

---

*DX-Engine — Server-Driven UI Framework. PHP 8.1+ · Bootstrap 5.3+ · Vanilla JS (ES6+)*
