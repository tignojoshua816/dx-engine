/**
 * DX-Engine — Frontend Interpreter  v2.0
 * =============================================================================
 * Canonical class-based SDUI interpreter. Parses the PHP Metadata Bridge JSON
 * and renders a fully interactive Bootstrap 5 multi-step form inside any DOM
 * element.
 *
 * Architecture layers (top → bottom):
 *   ComponentRegistry  – Registry pattern; renderers keyed by component_type
 *   Validator          – Client-side rule evaluation (mirrors PHP field rules)
 *   VisibilityEngine   – Evaluates visibility_rule on every state change
 *   Stepper            – Named step-indicator bar (not just a progress strip)
 *   DXInterpreter      – Core class: fetch → render → validate → POST → advance
 *
 * Quick-start:
 *   <div id="dx-root" class="dx-root"></div>
 *   <script src="dx-interpreter.js"></script>
 *   <script>
 *     const app = new DXInterpreter('#dx-root', {
 *       dx_id    : 'admission_case',
 *       endpoint : '/dx-engine/public/api/dx.php',
 *       onComplete(data) { console.log('Done', data); }
 *     });
 *     app.load();
 *   </script>
 *
 * Adding a custom component (no core edits needed):
 *   DXInterpreter.registry.register('my_widget', (descriptor, instance) => {
 *     const el = document.createElement('div');
 *     // build DOM…
 *     return el;
 *   });
 * =============================================================================
 */

'use strict';

/* ============================================================================
 * § 0 — Base-path auto-detection
 * ============================================================================
 * Derives the API endpoint from the location of THIS script file so the
 * interpreter works regardless of the subfolder name (dx-engine/, myapp/,
 * or the document root itself).
 *
 * Strategy:
 *   document.currentScript.src  →  http://localhost/dx-engine/public/js/dx-interpreter.js
 *   strip filename              →  http://localhost/dx-engine/public/js/
 *   go up two directories       →  http://localhost/dx-engine/
 *   append api path             →  http://localhost/dx-engine/public/api/dx.php
 *
 * This is captured at parse time (before any DXInterpreter is constructed)
 * so it is always available even for dynamically inserted <script> tags,
 * provided the src attribute is present and absolute.
 *
 * The resolved URL is stored as DXInterpreter._defaultEndpoint and is used
 * as the fallback when the caller does not supply an explicit endpoint option.
 * ==========================================================================*/

const _DX_SCRIPT_SRC = (function () {
  try {
    // document.currentScript is the <script> element being parsed right now.
    // It is null in async/deferred execution — fall back to searching by src.
    const el = document.currentScript
            ?? [...document.querySelectorAll('script[src*="dx-interpreter"]')].pop();

    if (!el?.src) return null;

    // Build a URL object from the script src so we can manipulate segments.
    const u = new URL(el.src);           // e.g. http://localhost/dx-engine/public/js/dx-interpreter.js

    // Strip the filename → /dx-engine/public/js/
    let parts = u.pathname.split('/');   // ['', 'dx-engine', 'public', 'js', 'dx-interpreter.js']
    parts.pop();   // remove filename    → ['', 'dx-engine', 'public', 'js']
    parts.pop();   // remove /js         → ['', 'dx-engine', 'public']
    // append /api/dx.php              → ['', 'dx-engine', 'public', 'api', 'dx.php']
    parts.push('api', 'dx.php');

    return u.origin + parts.join('/');   // http://localhost/dx-engine/public/api/dx.php
  } catch (_) {
    return null;   // fallback: caller must supply explicit endpoint option
  }
})();

/* ============================================================================
 * § 1 — ComponentRegistry
 * ========================================================================== */

/**
 * Singleton registry that maps component_type strings to renderer functions.
 *
 * Renderer signature:
 *   (descriptor: Object, instance: DXInterpreter) => HTMLElement
 *
 * The renderer receives the full component descriptor from the JSON payload
 * and the live DXInterpreter instance (for accessing formState, etc.).
 */
const ComponentRegistry = (() => {
  const _map = new Map();

  return {
    /**
     * Register a renderer.
     * @param {string}   type
     * @param {Function} renderer  (descriptor, instance) → HTMLElement
     */
    register(type, renderer) {
      _map.set(type, renderer);
      return this;  // fluent
    },

    /** @returns {Function|null} */
    get(type) {
      return _map.get(type) ?? null;
    },

    /** @returns {string[]} */
    types() {
      return [..._map.keys()];
    }
  };
})();

/* ============================================================================
 * § 2 — Validator
 * ========================================================================== */

const Validator = {
  /**
   * Validate a single value against one component descriptor.
   * Returns a string error message, or null if the value is valid.
   *
   * Rules evaluated (in order):
   *   required   – non-empty string check
   *   min        – minimum character length
   *   max        – maximum character length
   *   pattern    – JS RegExp (backend sends as a string, no delimiters)
   *   email_input component type – built-in RFC-lite e-mail check
   *
   * @param  {*}      value
   * @param  {Object} descriptor
   * @return {string|null}
   */
  field(value, descriptor) {
    const rules = descriptor.validation_rules ?? {};
    const label = descriptor.label || descriptor.field_key || 'Field';
    const str   = (value !== null && value !== undefined) ? String(value).trim() : '';

    if (descriptor.required && str === '') {
      return `${label} is required.`;
    }
    if (str === '') return null;   // empty + optional → pass

    if (rules.min !== undefined && str.length < Number(rules.min)) {
      return `${label} must be at least ${rules.min} characters.`;
    }
    if (rules.max !== undefined && str.length > Number(rules.max)) {
      return `${label} may not exceed ${rules.max} characters.`;
    }
    if (rules.pattern) {
      try {
        if (!new RegExp(rules.pattern).test(str)) {
          return rules.message ?? `${label} format is invalid.`;
        }
      } catch (_) { /* malformed pattern — skip silently */ }
    }
    if (descriptor.component_type === 'email_input') {
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(str)) {
        return `${label} must be a valid email address.`;
      }
    }

    return null;
  },

  /**
   * Validate all visible fields in one step.
   *
   * @param  {Object}   stepDescriptor
   * @param  {Object}   formState
   * @param  {Function} isVisible  (descriptor) → boolean
   * @return {{ valid: boolean, errors: Object.<string,string> }}
   */
  step(stepDescriptor, formState, isVisible) {
    const errors = {};
    for (const c of (stepDescriptor.components ?? [])) {
      if (!c.field_key) continue;
      if (isVisible && !isVisible(c)) continue;
      const err = this.field(formState[c.field_key], c);
      if (err) errors[c.field_key] = err;
    }
    return { valid: Object.keys(errors).length === 0, errors };
  }
};

/* ============================================================================
 * § 3 — VisibilityEngine
 * ========================================================================== */

const VisibilityEngine = {
  /**
   * Evaluate a visibility_rule object against the current formState.
   * Returns true (show) or false (hide).
   *
   * Supported operators:
   *   eq, neq, gt, lt, gte, lte, in, nin, empty, not_empty
   *
   * @param  {Object|null} rule
   * @param  {Object}      formState
   * @return {boolean}
   */
  evaluate(rule, formState) {
    if (!rule?.field) return true;

    const fv  = String(formState[rule.field] ?? '');
    const rv  = String(rule.value ?? '');
    const op  = rule.operator ?? 'eq';

    switch (op) {
      case 'eq':        return fv === rv;
      case 'neq':       return fv !== rv;
      case 'gt':        return parseFloat(fv) > parseFloat(rv);
      case 'lt':        return parseFloat(fv) < parseFloat(rv);
      case 'gte':       return parseFloat(fv) >= parseFloat(rv);
      case 'lte':       return parseFloat(fv) <= parseFloat(rv);
      case 'in':        return rv.split(',').map(v => v.trim()).includes(fv);
      case 'nin':       return !rv.split(',').map(v => v.trim()).includes(fv);
      case 'empty':     return fv === '';
      case 'not_empty': return fv !== '';
      default:          return true;
    }
  },

  /**
   * Walk every [data-dx-vis] wrapper in a container and show/hide it.
   *
   * @param {HTMLElement} container
   * @param {Object}      formState
   */
  applyAll(container, formState) {
    container.querySelectorAll('[data-dx-vis]').forEach(wrapper => {
      try {
        const rule    = JSON.parse(wrapper.dataset.dxVis);
        const visible = this.evaluate(rule, formState);
        wrapper.style.display    = visible ? '' : 'none';
        wrapper.dataset.dxHidden = visible ? '0' : '1';
      } catch (_) { /* malformed rule — leave visible */ }
    });
  }
};

/* ============================================================================
 * § 4 — Stepper UI
 * ========================================================================== */

/**
 * Builds and updates a named Bootstrap step-indicator bar.
 *
 * Renders:
 *   ①──────②──────③
 *   Step 1  Step 2  Step 3
 */
const Stepper = {
  /**
   * Build the stepper element for a given flow.
   *
   * @param  {Object[]} steps      Array of step descriptors
   * @param  {number}   activeIdx  Zero-based current step index
   * @return {HTMLElement}
   */
  build(steps, activeIdx) {
    const nav = _el('nav', 'dx-stepper');
    nav.setAttribute('aria-label', 'Form progress');

    const ol = _el('ol', 'dx-stepper-list');

    steps.forEach((step, i) => {
      const li   = _el('li', 'dx-stepper-item');
      const past = i < activeIdx;
      const curr = i === activeIdx;

      if (past)  li.classList.add('dx-stepper-item--done');
      if (curr)  li.classList.add('dx-stepper-item--active');

      // Circle badge
      const badge = _el('span', 'dx-stepper-badge');
      badge.setAttribute('aria-hidden', 'true');
      if (past) {
        // Checkmark SVG
        badge.innerHTML = `<svg width="12" height="12" viewBox="0 0 12 12" fill="none">
          <path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round"/>
        </svg>`;
      } else {
        badge.textContent = String(i + 1);
      }

      // Connector line (not for last item)
      const label = _el('span', 'dx-stepper-label');
      label.textContent = step.title ?? `Step ${i + 1}`;

      li.appendChild(badge);
      li.appendChild(label);
      if (i < steps.length - 1) {
        li.appendChild(_el('span', 'dx-stepper-connector'));
      }
      li.setAttribute('aria-current', curr ? 'step' : false);

      ol.appendChild(li);
    });

    nav.appendChild(ol);
    return nav;
  }
};

/* ============================================================================
 * § 5 — DOM Helpers
 * ========================================================================== */

/** Create an element with an optional class string. */
function _el(tag, className) {
  const el = document.createElement(tag);
  if (className) el.className = className;
  return el;
}

/**
 * Wrap any input element with its label, required marker, and optional
 * help text to produce the standard DX field block.
 *
 * @param  {Object}               descriptor
 * @param  {HTMLElement|HTMLElement[]} inputEl
 * @return {HTMLElement}
 */
function _wrapField(descriptor, inputEl) {
  const wrap  = _el('div', 'dx-field-wrap');
  const label = _el('label', 'form-label fw-medium small');
  label.setAttribute('for', `dx-${descriptor.field_key ?? ''}`);
  label.textContent = descriptor.label ?? '';

  if (descriptor.required) {
    const req = _el('span', 'dx-required text-danger ms-1');
    req.setAttribute('aria-hidden', 'true');
    req.textContent = '*';
    label.appendChild(req);
  }
  wrap.appendChild(label);

  const inputs = Array.isArray(inputEl) ? inputEl : [inputEl];
  inputs.forEach(i => wrap.appendChild(i));

  if (descriptor.help_text) {
    const help = _el('div', 'form-text');
    help.textContent = descriptor.help_text;
    wrap.appendChild(help);
  }

  return wrap;
}

/**
 * Apply shared attributes (id, name, readonly, required, placeholder,
 * value, custom attrs, extra css_class) to a form control.
 */
function _applyAttrs(el, descriptor) {
  el.id   = `dx-${descriptor.field_key ?? ''}`;
  el.name = descriptor.field_key ?? '';
  if (descriptor.readonly)    el.readOnly = true;
  if (descriptor.required)    el.required = true;
  if (descriptor.placeholder) el.placeholder = descriptor.placeholder;
  if (descriptor.value != null) el.value = descriptor.value;

  for (const [k, v] of Object.entries(descriptor.attrs ?? {})) {
    el.setAttribute(k, v);
  }
  if (descriptor.css_class) {
    descriptor.css_class.split(' ').filter(Boolean)
      .forEach(cls => el.classList.add(cls));
  }
}

/* ============================================================================
 * § 6 — Built-in Component Renderers
 * ========================================================================== */

/* ── text_input ────────────────────────────────────────────────────────── */
ComponentRegistry.register('text_input', d => {
  const inp = _el('input', 'form-control');
  inp.type  = 'text';
  _applyAttrs(inp, d);
  return _wrapField(d, inp);
});

/* ── email_input ───────────────────────────────────────────────────────── */
ComponentRegistry.register('email_input', d => {
  const inp = _el('input', 'form-control');
  inp.type  = 'email';
  inp.autocomplete = 'email';
  _applyAttrs(inp, d);
  return _wrapField(d, inp);
});

/* ── number_input ──────────────────────────────────────────────────────── */
ComponentRegistry.register('number_input', d => {
  const inp = _el('input', 'form-control');
  inp.type  = 'number';
  _applyAttrs(inp, d);
  return _wrapField(d, inp);
});

/* ── date_input ────────────────────────────────────────────────────────── */
ComponentRegistry.register('date_input', d => {
  const inp = _el('input', 'form-control');
  inp.type  = 'date';
  _applyAttrs(inp, d);
  return _wrapField(d, inp);
});

/* ── textarea ──────────────────────────────────────────────────────────── */
ComponentRegistry.register('textarea', d => {
  const ta = _el('textarea', 'form-control');
  ta.id   = `dx-${d.field_key ?? ''}`;
  ta.name = d.field_key ?? '';
  if (d.readonly)    ta.readOnly = true;
  if (d.required)    ta.required = true;
  if (d.placeholder) ta.placeholder = d.placeholder;
  ta.textContent = d.value ?? '';
  for (const [k, v] of Object.entries(d.attrs ?? {})) ta.setAttribute(k, v);
  if (!d.attrs?.rows) ta.rows = 3;
  if (d.css_class) d.css_class.split(' ').filter(Boolean).forEach(c => ta.classList.add(c));
  return _wrapField(d, ta);
});

/* ── select ────────────────────────────────────────────────────────────── */
ComponentRegistry.register('select', d => {
  const sel = _el('select', 'form-select');
  _applyAttrs(sel, d);
  sel.value = undefined;   // reset; we set via option.selected below

  (d.options ?? []).forEach(opt => {
    const o      = document.createElement('option');
    o.value      = opt.value;
    o.textContent = opt.label;
    if (String(opt.value) === String(d.value)) o.selected = true;
    sel.appendChild(o);
  });

  return _wrapField(d, sel);
});

/* ── radio ─────────────────────────────────────────────────────────────── */
ComponentRegistry.register('radio', d => {
  const wrap  = _el('div', 'dx-field-wrap');
  const label = _el('div', 'form-label fw-medium small mb-2');
  label.textContent = d.label ?? '';
  if (d.required) {
    const req = _el('span', 'text-danger ms-1');
    req.textContent = '*';
    label.appendChild(req);
  }
  wrap.appendChild(label);

  (d.options ?? []).forEach(opt => {
    const item = _el('div', 'form-check dx-radio-item mb-2');
    if (opt.css_class) {
      opt.css_class.split(' ').filter(Boolean).forEach(c => item.classList.add(c));
    }

    const inp       = document.createElement('input');
    inp.type        = 'radio';
    inp.className   = 'form-check-input';
    inp.name        = d.field_key ?? '';
    inp.id          = `dx-${d.field_key}-${opt.value}`;
    inp.value       = opt.value;
    if (String(opt.value) === String(d.value)) inp.checked = true;

    const lbl     = document.createElement('label');
    lbl.className = 'form-check-label';
    lbl.htmlFor   = inp.id;
    lbl.textContent = opt.label;

    item.appendChild(inp);
    item.appendChild(lbl);
    wrap.appendChild(item);
  });

  return wrap;
});

/* ── checkbox_group ────────────────────────────────────────────────────── */
ComponentRegistry.register('checkbox_group', d => {
  const selected = Array.isArray(d.value) ? d.value.map(String)
                 : (d.value ? [String(d.value)] : []);
  const wrap  = _el('div', 'dx-field-wrap');
  const label = _el('div', 'form-label fw-medium small mb-2');
  label.textContent = d.label ?? '';
  wrap.appendChild(label);

  (d.options ?? []).forEach(opt => {
    const item = _el('div', 'form-check');
    const inp       = document.createElement('input');
    inp.type        = 'checkbox';
    inp.className   = 'form-check-input';
    inp.name        = `${d.field_key ?? ''}[]`;
    inp.id          = `dx-${d.field_key}-${opt.value}`;
    inp.value       = opt.value;
    if (selected.includes(String(opt.value))) inp.checked = true;

    const lbl     = document.createElement('label');
    lbl.className = 'form-check-label';
    lbl.htmlFor   = inp.id;
    lbl.textContent = opt.label;

    item.appendChild(inp);
    item.appendChild(lbl);
    wrap.appendChild(item);
  });

  return wrap;
});

/* ── file_upload ───────────────────────────────────────────────────────── */
ComponentRegistry.register('file_upload', d => {
  const inp  = _el('input', 'form-control');
  inp.type   = 'file';
  inp.id     = `dx-${d.field_key ?? ''}`;
  inp.name   = d.field_key ?? '';
  if (d.required)            inp.required = true;
  if (d.attrs?.accept)       inp.accept   = d.attrs.accept;
  if (d.attrs?.multiple)     inp.multiple = true;
  return _wrapField(d, inp);
});

/* ── heading ───────────────────────────────────────────────────────────── */
ComponentRegistry.register('heading', d => {
  const el = _el('h6', 'dx-section-heading');
  el.textContent = d.label ?? '';
  return el;
});

/* ── paragraph ─────────────────────────────────────────────────────────── */
ComponentRegistry.register('paragraph', d => {
  const el = _el('p', 'text-muted small mb-1');
  el.textContent = d.label ?? '';
  return el;
});

/* ── divider ────────────────────────────────────────────────────────────── */
ComponentRegistry.register('divider', () => _el('hr', 'dx-divider'));

/* ── alert ──────────────────────────────────────────────────────────────── */
ComponentRegistry.register('alert', d => {
  const variant = d.attrs?.variant ?? 'info';
  const el = _el('div', `alert alert-${variant} mb-0`);
  el.textContent = d.label ?? '';
  return el;
});

/* ── hidden ─────────────────────────────────────────────────────────────── */
ComponentRegistry.register('hidden', d => {
  const inp  = document.createElement('input');
  inp.type   = 'hidden';
  inp.name   = d.field_key ?? '';
  inp.value  = d.value ?? '';
  return inp;
});

/* ============================================================================
 * § 7 — DXInterpreter (Main Class)
 * ========================================================================== */

class DXInterpreter {
  /**
   * @param {string|HTMLElement} target   CSS selector or DOM element
   * @param {Object}             options
   *   @option {string}   dx_id            Required. Matches a key registered in api/dx.php.
   *   @option {string}   [endpoint]       Absolute URL to dx.php. Defaults to auto-detected
   *                                       path based on the location of dx-interpreter.js.
   *                                       Only set this if you serve the script from a CDN
   *                                       or a path that differs from your API server.
   *   @option {Object}   params           Extra GET params (e.g. { admission_id: 5 }).
   *   @option {string}   csrf             CSRF token, included in every POST body.
   *   @option {Function} onComplete       Invoked with response.data on final success.
   *   @option {string}   successTitle     Heading on the completion screen.
   *   @option {string}   resetLabel       If set, shows a "start over" button.
   *   @option {Function} completionTemplate  (response) → HTMLElement for custom screen.
   */
  constructor(target, options = {}) {
    this._target  = typeof target === 'string'
      ? document.querySelector(target)
      : target;

    if (!this._target) {
      throw new Error(`[DXInterpreter] Target element not found: ${target}`);
    }

    // Resolve the endpoint once at construction time.
    // Priority: explicit option → auto-detected script path → hard fallback.
    this._endpoint = options.endpoint
                  ?? _DX_SCRIPT_SRC
                  ?? '/public/api/dx.php';

    this._options   = options;
    this._flow      = null;   // full Metadata Bridge JSON
    this._stepIndex = 0;      // current step index
    this._state     = {};     // accumulated formState across all steps
  }

  /**
   * Expose the resolved endpoint for debugging.
   * console.log(app.endpoint)  →  'http://localhost/dx-engine/public/api/dx.php'
   */
  get endpoint() { return this._endpoint; }

  /* ── Static registry reference ─────────────────────────────────────── */
  static get registry() { return ComponentRegistry; }
  static get validator() { return Validator; }

  /* ── Public API ─────────────────────────────────────────────────────── */

  /**
   * Fetch the Metadata Bridge from the PHP backend and render Step 1.
   * Safe to call multiple times (e.g. "start over").
   */
  load() {
    const opts = this._options;
    // this._endpoint is already resolved in the constructor (auto-detected or explicit).
    let url = this._endpoint + '?dx=' + encodeURIComponent(opts.dx_id ?? '');

    for (const [k, v] of Object.entries(opts.params ?? {})) {
      url += `&${encodeURIComponent(k)}=${encodeURIComponent(v)}`;
    }

    this._showLoader();

    fetch(url, {
      method : 'GET',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => {
      if (!res.ok) throw new Error(`HTTP ${res.status} from DX API`);
      return res.json();
    })
    .then(json => {
      this._flow      = json;
      this._state     = Object.assign({}, json.initial_state ?? {});
      this._stepIndex = 0;
      this._renderStep();
    })
    .catch(err => this._showError(err.message));
  }

  /* ── Internal: render step ──────────────────────────────────────────── */

  _renderStep() {
    const flow  = this._flow;
    const steps = flow.steps ?? [];
    const step  = steps[this._stepIndex];
    if (!step) return;

    this._target.innerHTML = '';

    /* Card shell */
    const card   = _el('div',  'dx-card card shadow-sm');
    const header = _el('div',  'dx-card-header card-header');
    const body   = _el('div',  'dx-card-body card-body');
    const footer = _el('div',  'dx-card-footer card-footer');

    /* Stepper (only when more than one step) */
    if (steps.length > 1) {
      header.appendChild(Stepper.build(steps, this._stepIndex));
    }

    /* Step title */
    const titleEl = _el('h5', 'dx-step-title fw-semibold mb-0 mt-2');
    titleEl.textContent = steps.length > 1
      ? step.title
      : (flow.title ?? step.title);
    header.appendChild(titleEl);

    if (flow.description && this._stepIndex === 0) {
      const desc = _el('p', 'text-muted small mb-0 mt-1');
      desc.textContent = flow.description;
      header.appendChild(desc);
    }

    /* Form */
    const form = _el('form', 'dx-form needs-validation');
    form.setAttribute('novalidate', 'true');
    form.dataset.step = step.step_id;

    const row = _el('div', 'row g-3');

    for (const descriptor of (step.components ?? [])) {
      row.appendChild(this._buildComponentColumn(descriptor));
    }

    form.appendChild(row);

    /* Footer buttons */
    const btnRow = _el('div', 'd-flex gap-2 justify-content-between align-items-center');

    // Back button (shown from step 2 onward when cancel_label is set)
    if (this._stepIndex > 0 && step.cancel_label) {
      const backBtn = _el('button', 'btn btn-outline-secondary dx-btn-back');
      backBtn.type        = 'button';
      backBtn.textContent = step.cancel_label;
      backBtn.addEventListener('click', () => this._goBack());
      btnRow.appendChild(backBtn);
    } else {
      btnRow.appendChild(_el('span', ''));  // spacer to keep submit right-aligned
    }

    const submitBtn = _el('button', 'btn btn-primary dx-btn-submit');
    submitBtn.type = 'submit';
    submitBtn.innerHTML =
      `<span class="dx-btn-label">${step.submit_label ?? 'Continue'}</span>`;
    btnRow.appendChild(submitBtn);

    footer.appendChild(btnRow);

    /* Assemble */
    body.appendChild(form);
    card.appendChild(header);
    card.appendChild(body);
    card.appendChild(footer);
    this._target.appendChild(card);

    /* Initial visibility pass */
    VisibilityEngine.applyAll(this._target, this._state);

    /* Wire submit */
    form.addEventListener('submit', e => {
      e.preventDefault();
      this._handleSubmit(step, form, submitBtn);
    });

    /* Live visibility on change/input */
    this._attachVisibilityListeners(form);
  }

  /* ── Internal: build one component column wrapper ───────────────────── */

  _buildComponentColumn(descriptor) {
    const span  = descriptor.col_span ?? 12;
    const colEl = _el('div', `col-md-${span}`);

    // Attach visibility rule as data attribute for VisibilityEngine
    if (descriptor.visibility_rule) {
      colEl.dataset.dxVis = JSON.stringify(descriptor.visibility_rule);
    }

    const renderer = ComponentRegistry.get(descriptor.component_type);

    if (renderer) {
      const built = renderer(descriptor, this);
      if (built) colEl.appendChild(built);
    } else {
      const unknown = _el('div', 'alert alert-warning small py-2 mb-0');
      unknown.textContent = `Unknown component type: "${descriptor.component_type}"`;
      colEl.appendChild(unknown);
    }

    return colEl;
  }

  /* ── Internal: collect values from a <form> ─────────────────────────── */

  _collectForm(form) {
    const values = {};
    for (const inp of form.querySelectorAll('[name]')) {
      const name = inp.getAttribute('name');
      if (!name) continue;
      if (inp.type === 'checkbox') {
        values[name] = inp.checked ? inp.value : '';
      } else if (inp.type === 'radio') {
        if (inp.checked) values[name] = inp.value;
      } else {
        values[name] = inp.value;
      }
    }
    return values;
  }

  /* ── Internal: handle form submit ──────────────────────────────────── */

  /**
   * Called when the user clicks the submit/continue button.
   *
   * Flow:
   *   1. Collect form values → merge into this._state
   *   2. Client-side validation (mirrors PHP rules)
   *   3. POST full state to backend (post_endpoint)
   *   4. On success:
   *      a. Merge response.data into state (picks up IDs like patient_id)
   *      b. If next_step is set → advance to that step
   *      c. If next_step is null → render completion screen
   *   5. On validation_error → show field errors from backend
   *   6. On error → show inline alert
   */
  _handleSubmit(step, form, submitBtn) {
    // 1. Collect + merge
    const current = this._collectForm(form);
    Object.assign(this._state, current);

    // 2. Client-side validation
    const isVisible = c => {
      if (!c.visibility_rule) return true;
      return VisibilityEngine.evaluate(c.visibility_rule, this._state);
    };

    const result = Validator.step(step, this._state, isVisible);
    if (!result.valid) {
      this._showFieldErrors(form, result.errors);
      return;
    }
    this._clearFieldErrors(form);

    // 3. Build payload
    const payload = Object.assign({}, this._state, {
      _step  : step.step_id,
      _dx_id : this._flow.dx_id ?? '',
      _csrf  : this._options.csrf ?? ''
    });
    // Include any backend context (e.g. admission_id for edits)
    if (this._flow.context) {
      Object.assign(payload, this._flow.context);
    }

    this._setLoading(submitBtn, true, step);

    // 4. POST
    fetch(this._flow.post_endpoint, {
      method : 'POST',
      headers: {
        'Content-Type'    : 'application/json',
        Accept            : 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(response => {
      this._setLoading(submitBtn, false, step);

      if (response.status === 'success') {
        // 4a. Merge returned IDs back into state
        if (response.data) Object.assign(this._state, response.data);

        // 4b. Advance to named next step
        if (response.next_step) {
          const nextIdx = (this._flow.steps ?? [])
            .findIndex(s => s.step_id === response.next_step);

          if (nextIdx > -1) {
            this._stepIndex = nextIdx;
            this._renderStep();
            this._scrollToTop();
            return;
          }
        }

        // 4c. No next step — all done
        this._renderComplete(response);
        if (typeof this._options.onComplete === 'function') {
          this._options.onComplete(response.data);
        }

      } else if (response.status === 'validation_error') {
        // 5. Backend validation errors
        this._showFieldErrors(form, response.errors ?? {});
        if (response.message) {
          this._showFormAlert(form, response.message, 'warning');
        }

      } else {
        // 6. Unexpected error
        this._showFormAlert(
          form,
          response.message ?? 'An unexpected error occurred.',
          'danger'
        );
      }
    })
    .catch(err => {
      this._setLoading(submitBtn, false, step);
      this._showFormAlert(form, `Network error: ${err.message}`, 'danger');
    });
  }

  /* ── Internal: navigate back ────────────────────────────────────────── */

  _goBack() {
    if (this._stepIndex > 0) {
      this._stepIndex--;
      this._renderStep();
      this._scrollToTop();
    }
  }

  /* ── Internal: completion screen ────────────────────────────────────── */

  _renderComplete(response) {
    const opts = this._options;

    if (typeof opts.completionTemplate === 'function') {
      this._target.innerHTML = '';
      this._target.appendChild(opts.completionTemplate(response));
      return;
    }

    // Default Bootstrap completion card
    const card = _el('div', 'dx-card card shadow-sm');
    const body = _el('div', 'card-body text-center py-5 px-4');

    const iconWrap = _el('div', 'dx-success-icon mb-3');
    iconWrap.innerHTML = `
      <svg width="60" height="60" viewBox="0 0 60 60" fill="none" role="img"
           aria-label="Success checkmark">
        <circle cx="30" cy="30" r="30" fill="var(--dx-success-bg)"/>
        <path d="M17 30.5l9 9 17-17"
              stroke="var(--dx-success)" stroke-width="3"
              stroke-linecap="round" stroke-linejoin="round"/>
      </svg>`;

    const h = _el('h4', 'fw-semibold mb-2');
    h.textContent = opts.successTitle ?? 'Submitted Successfully';

    const msg = _el('p', 'text-muted mb-4');
    msg.textContent = response.message ?? '';

    body.appendChild(iconWrap);
    body.appendChild(h);
    body.appendChild(msg);

    if (opts.resetLabel) {
      const resetBtn = _el('button', 'btn btn-outline-primary dx-btn-reset');
      resetBtn.textContent = opts.resetLabel;
      resetBtn.addEventListener('click', () => {
        this._state     = {};
        this._stepIndex = 0;
        this.load();
      });
      body.appendChild(resetBtn);
    }

    card.appendChild(body);
    this._target.innerHTML = '';
    this._target.appendChild(card);
  }

  /* ── Internal: field error display ─────────────────────────────────── */

  _showFieldErrors(form, errors) {
    this._clearFieldErrors(form);

    for (const [key, message] of Object.entries(errors)) {
      // Mark all inputs with that name invalid
      form.querySelectorAll(`[name="${key}"]`).forEach(inp => {
        inp.classList.add('is-invalid');
      });
      // Attach one .invalid-feedback node
      const lastInp = form.querySelector(`[name="${key}"]:last-of-type`)
                   ?? form.querySelector(`[name="${key}"]`);
      if (lastInp) {
        const fb = _el('div', 'invalid-feedback dx-field-error');
        fb.dataset.for  = key;
        fb.textContent  = message;
        lastInp.closest('.dx-field-wrap, .form-check, .form-floating')
          ?.appendChild(fb)
          ?? lastInp.insertAdjacentElement('afterend', fb);
      }
    }
  }

  _clearFieldErrors(form) {
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.dx-field-error, .dx-form-alert').forEach(el => el.remove());
  }

  _showFormAlert(form, message, type = 'danger') {
    form.querySelector('.dx-form-alert')?.remove();
    const alert = _el('div', `alert alert-${type} dx-form-alert mt-3 mb-0`);
    alert.setAttribute('role', 'alert');
    alert.textContent = message;
    form.appendChild(alert);
  }

  /* ── Internal: loading state ────────────────────────────────────────── */

  _setLoading(btn, loading, step) {
    const label = btn.querySelector('.dx-btn-label');
    btn.disabled = loading;
    if (loading) {
      if (label) label.innerHTML =
        `<span class="spinner-border spinner-border-sm me-2"
               role="status" aria-hidden="true"></span>Processing&hellip;`;
    } else {
      if (label) label.textContent = step?.submit_label ?? 'Continue';
    }
  }

  /* ── Internal: loader + error screens ─────────────────────────────── */

  _showLoader() {
    this._target.innerHTML = `
      <div class="dx-loader d-flex flex-column align-items-center
                  justify-content-center py-5" aria-live="polite">
        <div class="spinner-border text-primary mb-3" role="status">
          <span class="visually-hidden">Loading form&hellip;</span>
        </div>
        <p class="text-muted small mb-0">Loading&hellip;</p>
      </div>`;
  }

  _showError(message) {
    this._target.innerHTML =
      `<div class="alert alert-danger m-3" role="alert">
         <strong>Error:</strong> ${message}
       </div>`;
  }

  _scrollToTop() {
    this._target.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  /* ── Internal: live visibility wiring ──────────────────────────────── */

  /**
   * Attach `change` and `input` listeners to a <form> so that any time
   * the user interacts with a tracked field, the VisibilityEngine re-runs
   * across the entire step container.
   */
  _attachVisibilityListeners(form) {
    const update = e => {
      if (!e.target?.name) return;
      const name = e.target.name;
      if (e.target.type === 'checkbox') {
        this._state[name] = e.target.checked ? e.target.value : '';
      } else if (e.target.type === 'radio') {
        if (e.target.checked) this._state[name] = e.target.value;
      } else {
        this._state[name] = e.target.value;
      }
      VisibilityEngine.applyAll(this._target, this._state);
    };

    form.addEventListener('change', update);
    form.addEventListener('input',  update);
  }
}

/* ============================================================================
 * § 8 — Global Export
 * ========================================================================== */

// Expose class and its static registry globally for legacy embed pages.
window.DXInterpreter = DXInterpreter;

// Expose the auto-detected endpoint so it is visible in the browser console:
//   console.log(DXInterpreter.detectedEndpoint)
DXInterpreter.detectedEndpoint = _DX_SCRIPT_SRC;
