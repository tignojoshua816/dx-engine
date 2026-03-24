/**
 * DX-Engine — Frontend Interpreter  v1.0
 * =======================================================================
 * A lightweight Vanilla JS library that:
 *   1. Fetches a DX flow JSON from the PHP backend
 *   2. Renders Bootstrap 5 components dynamically into a target <div>
 *   3. Manages multi-step navigation driven by backend step descriptors
 *   4. Validates fields using backend-supplied rules before every submit
 *   5. Posts step payloads and follows the backend's next_step instruction
 *
 * Usage:
 *   <div id="dx-root"></div>
 *   <script>
 *     DXEngine.mount('#dx-root', {
 *       dx_id    : 'admission_case',
 *       endpoint : '/dx-engine/public/api/dx.php',
 *       onComplete: function(data) { console.log('Done', data); }
 *     });
 *   </script>
 *
 * Extending (adding a new component type):
 *   DXEngine.registry.register('my_component', function(descriptor, engine) {
 *     var el = document.createElement('div');
 *     // build your component…
 *     return el;
 *   });
 * =======================================================================
 */

(function (global) {
  'use strict';

  /* ====================================================================
   * 1. Component Registry
   * ==================================================================== */
  var ComponentRegistry = (function () {
    var _registry = {};

    return {
      /**
       * Register a renderer for a component type.
       * @param {string}   type     Component type string (e.g. 'text_input')
       * @param {Function} renderer function(descriptor, engineInstance) → HTMLElement
       */
      register: function (type, renderer) {
        _registry[type] = renderer;
      },

      /** Retrieve a renderer or null. */
      get: function (type) {
        return _registry[type] || null;
      },

      /** List all registered types. */
      list: function () {
        return Object.keys(_registry);
      }
    };
  })();

  /* ====================================================================
   * 2. Validator
   * ==================================================================== */
  var Validator = {
    /**
     * Validate a single field value against its descriptor.
     * Returns null if valid, or an error string if invalid.
     *
     * @param  {*}      value
     * @param  {Object} descriptor  Component descriptor from DX flow
     * @return {string|null}
     */
    validate: function (value, descriptor) {
      var rules = descriptor.validation_rules || {};
      var label = descriptor.label || descriptor.field_key || 'Field';
      var str   = value !== null && value !== undefined ? String(value) : '';

      // Required check
      if (descriptor.required && str.trim() === '') {
        return label + ' is required.';
      }

      // If empty and not required, skip further checks
      if (str.trim() === '') return null;

      // min length
      if (rules.min !== undefined && str.length < Number(rules.min)) {
        return label + ' must be at least ' + rules.min + ' characters.';
      }
      // max length
      if (rules.max !== undefined && str.length > Number(rules.max)) {
        return label + ' may not exceed ' + rules.max + ' characters.';
      }
      // regex pattern
      if (rules.pattern) {
        try {
          var re = new RegExp(rules.pattern);
          if (!re.test(str)) {
            return rules.message || label + ' format is invalid.';
          }
        } catch (e) { /* malformed pattern — skip */ }
      }
      // email
      if (descriptor.component_type === 'email_input') {
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(str)) {
          return label + ' must be a valid email address.';
        }
      }

      return null;
    },

    /**
     * Validate all visible components in a step.
     * Returns { valid: Boolean, errors: { field_key: message } }
     */
    validateStep: function (stepDescriptor, formState, visibilityFn) {
      var errors = {};
      (stepDescriptor.components || []).forEach(function (c) {
        if (!c.field_key) return;
        if (visibilityFn && !visibilityFn(c)) return;   // skip hidden
        var err = Validator.validate(formState[c.field_key], c);
        if (err) errors[c.field_key] = err;
      });
      return { valid: Object.keys(errors).length === 0, errors: errors };
    }
  };

  /* ====================================================================
   * 3. Visibility Engine
   * ==================================================================== */
  var VisibilityEngine = {
    /**
     * Evaluate a single visibility_rule against current form state.
     * Supported operators: eq, neq, gt, lt, gte, lte, in, nin, empty, not_empty
     */
    evaluate: function (rule, formState) {
      if (!rule || !rule.field) return true;   // no rule = always visible

      var fieldVal  = String(formState[rule.field] || '');
      var ruleVal   = String(rule.value || '');
      var op        = rule.operator || 'eq';

      switch (op) {
        case 'eq':        return fieldVal === ruleVal;
        case 'neq':       return fieldVal !== ruleVal;
        case 'gt':        return parseFloat(fieldVal) > parseFloat(ruleVal);
        case 'lt':        return parseFloat(fieldVal) < parseFloat(ruleVal);
        case 'gte':       return parseFloat(fieldVal) >= parseFloat(ruleVal);
        case 'lte':       return parseFloat(fieldVal) <= parseFloat(ruleVal);
        case 'in':        return ruleVal.split(',').map(function(v){return v.trim();}).indexOf(fieldVal) > -1;
        case 'nin':       return ruleVal.split(',').map(function(v){return v.trim();}).indexOf(fieldVal) === -1;
        case 'empty':     return fieldVal.trim() === '';
        case 'not_empty': return fieldVal.trim() !== '';
        default:          return true;
      }
    },

    /**
     * Apply visibility to all component wrappers inside a step container.
     * Reads data-field-key and visibility_rule meta stored on wrappers.
     */
    applyAll: function (stepEl, formState) {
      var wrappers = stepEl.querySelectorAll('[data-dx-visibility]');
      wrappers.forEach(function (wrapper) {
        try {
          var rule    = JSON.parse(wrapper.getAttribute('data-dx-visibility'));
          var visible = VisibilityEngine.evaluate(rule, formState);
          wrapper.style.display = visible ? '' : 'none';
        } catch (e) { /* malformed rule */ }
      });
    }
  };

  /* ====================================================================
   * 4. DX Engine Core
   * ==================================================================== */
  function DXEngineInstance(targetEl, options) {
    this.targetEl   = targetEl;
    this.options    = options || {};
    this.flow       = null;        // full Metadata Bridge
    this.stepIndex  = 0;          // current step index
    this.formState  = {};          // accumulated field values across all steps
    this.registry   = ComponentRegistry;
    this._activeController = null; // AbortController for in-flight fetches
    this._lastVisibilitySignature = ''; // prevent redundant visibility DOM writes
  }

  DXEngineInstance.prototype = {

    /* ── Fetch and render the DX flow ─────────────────────────────── */
    load: function () {
      var self     = this;
      var endpoint = (this.options.endpoint || '/dx-engine/public/api/dx.php')
                   + '?dx=' + encodeURIComponent(this.options.dx_id || '');

      // Append any extra context params
      if (this.options.params) {
        Object.keys(this.options.params).forEach(function (k) {
          endpoint += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(self.options.params[k]);
        });
      }

      self._showLoader();

      if (self._activeController) self._activeController.abort();
      self._activeController = new AbortController();

      fetch(endpoint, {
        method : 'GET',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        signal : self._activeController.signal
      })
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function (json) {
        if (!json || typeof json !== 'object' || !Array.isArray(json.steps)) {
          throw new Error('Malformed DX metadata payload.');
        }
        self.flow       = json;
        self.formState  = Object.assign({}, json.initial_state || {});
        self.stepIndex  = 0;
        self._lastVisibilitySignature = '';
        self._renderStep();
      })
      .catch(function (err) {
        if (err && err.name === 'AbortError') return;
        self._showError('Failed to load the form. ' + err.message);
      });
    },

    /* ── Render the current step ──────────────────────────────────── */
    _renderStep: function () {
      var self  = this;
      var step  = (this.flow.steps || [])[this.stepIndex];
      if (!step) return;

      this.targetEl.innerHTML = '';

      // ---- Wrapper card
      var card      = _el('div', 'dx-card card shadow-sm');
      var cardHeader= _el('div', 'dx-card-header card-header');
      var cardBody  = _el('div', 'dx-card-body card-body p-4');
      var cardFooter= _el('div', 'dx-card-footer card-footer');

      // ---- Step progress bar
      var totalSteps = (this.flow.steps || []).length;
      if (totalSteps > 1) {
        var progressWrap = _el('div', 'dx-progress mb-3');
        cardHeader.appendChild(this._buildProgressBar(totalSteps));
      }

      // ---- Title
      var titleEl = _el('h5', 'dx-step-title fw-semibold mb-0');
      titleEl.textContent = (this.flow.title || '') + (totalSteps > 1 ? ' — ' + step.title : '');
      cardHeader.appendChild(titleEl);

      // ---- Build form
      var form = _el('form', 'dx-form needs-validation');
      form.setAttribute('novalidate', 'true');
      form.setAttribute('data-step', step.step_id);

      var rowWrap = _el('div', 'row g-3');

      (step.components || []).forEach(function (descriptor) {
        var colEl = self._buildComponent(descriptor);
        rowWrap.appendChild(colEl);
      });

      form.appendChild(rowWrap);

      // ---- Footer buttons
      var btnRow = _el('div', 'd-flex gap-2 justify-content-between');

      if (self.stepIndex > 0 && step.cancel_label) {
        var backBtn = _el('button', 'btn btn-outline-secondary dx-btn-back');
        backBtn.type = 'button';
        backBtn.textContent = step.cancel_label || 'Back';
        backBtn.addEventListener('click', function () { self._goBack(); });
        btnRow.appendChild(backBtn);
      } else {
        btnRow.appendChild(_el('span', ''));  // spacer
      }

      var submitBtn = _el('button', 'btn btn-primary dx-btn-submit');
      submitBtn.type = 'submit';
      submitBtn.innerHTML = '<span class="dx-btn-label">' + (step.submit_label || 'Continue') + '</span>';
      btnRow.appendChild(submitBtn);

      cardFooter.appendChild(btnRow);

      // ---- Assemble
      cardBody.appendChild(form);
      card.appendChild(cardHeader);
      card.appendChild(cardBody);
      card.appendChild(cardFooter);
      self.targetEl.appendChild(card);

      // ---- Apply initial visibility
      VisibilityEngine.applyAll(self.targetEl, self.formState);

      // ---- Wire live visibility (change/input on any named field re-runs the engine)
      self._attachVisibilityListeners(form);

      // ---- Wire form submit
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        self._handleSubmit(step, form, submitBtn);
      });
    },

    /* ── Build a single component column wrapper ──────────────────── */
    _buildComponent: function (descriptor) {
      var colSize = descriptor.col_span || 12;
      var colEl   = _el('div', 'col-md-' + colSize);

      // Store visibility rule on wrapper
      if (descriptor.visibility_rule) {
        colEl.setAttribute('data-dx-visibility', JSON.stringify(descriptor.visibility_rule));
      }

      // Look up renderer in registry
      var renderer = ComponentRegistry.get(descriptor.component_type);
      if (renderer) {
        var built = renderer(descriptor, this);
        if (built) colEl.appendChild(built);
      } else {
        var unknown = _el('div', 'alert alert-warning small');
        unknown.textContent = 'Unknown component type: ' + descriptor.component_type;
        colEl.appendChild(unknown);
      }

      return colEl;
    },

    /* ── Progress bar builder ─────────────────────────────────────── */
    _buildProgressBar: function (totalSteps) {
      var pct   = Math.round(((this.stepIndex + 1) / totalSteps) * 100);
      var wrap  = _el('div', 'dx-stepper mb-3');
      var stepsEl = _el('div', 'dx-stepper-steps d-flex gap-1 mb-2');

      for (var i = 0; i < totalSteps; i++) {
        var dot = _el('div', 'dx-step-dot flex-fill rounded-1');
        dot.style.height = '4px';
        dot.style.background = i <= this.stepIndex ? 'var(--dx-primary)' : 'var(--dx-muted)';
        stepsEl.appendChild(dot);
      }

      var label = _el('small', 'text-muted');
      label.textContent = 'Step ' + (this.stepIndex + 1) + ' of ' + totalSteps;
      wrap.appendChild(stepsEl);
      wrap.appendChild(label);
      return wrap;
    },

    /* ── Collect current form values ──────────────────────────────── */
    _collectFormValues: function (form) {
      var values = {};
      var inputs = form.querySelectorAll('[name]');
      inputs.forEach(function (inp) {
        var name = inp.getAttribute('name');
        if (!name) return;
        if (inp.type === 'checkbox') {
          values[name] = inp.checked ? inp.value : '';
        } else if (inp.type === 'radio') {
          if (inp.checked) values[name] = inp.value;
        } else {
          values[name] = inp.value;
        }
      });
      return values;
    },

    /* ── Handle submit ────────────────────────────────────────────── */
    _handleSubmit: function (step, form, submitBtn) {
      var self    = this;
      var current = this._collectFormValues(form);

      // Merge into accumulated state
      Object.assign(self.formState, current);

      // Frontend validation
      var result = Validator.validateStep(step, self.formState, function (c) {
        if (!c.visibility_rule) return true;
        return VisibilityEngine.evaluate(c.visibility_rule, self.formState);
      });

      if (!result.valid) {
        self._showFieldErrors(form, result.errors);
        return;
      }

      // Clear inline errors
      self._clearFieldErrors(form);

      // Build payload
      var payload = Object.assign({}, self.formState, {
        _step   : step.step_id,
        _dx_id  : self.flow.dx_id || '',
        _csrf   : (self.options.csrf || '')
      });

      // Include context (e.g. admission_id for edits)
      if (self.flow.context) {
        Object.assign(payload, self.flow.context);
      }

      self._setLoading(submitBtn, true);

      var postEndpoint = self._resolvePostEndpoint();
      if (!postEndpoint) {
        self._setLoading(submitBtn, false);
        self._showFormAlert(form, 'No submission endpoint configured.', 'danger');
        return;
      }

      if (self._activeController) self._activeController.abort();
      self._activeController = new AbortController();

      fetch(postEndpoint, {
        method : 'POST',
        headers: {
          'Content-Type'    : 'application/json',
          'Accept'          : 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload),
        signal: self._activeController.signal
      })
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function (response) {
        self._setLoading(submitBtn, false);

        if (response.status === 'success') {
          // Merge returned data back into state (e.g. patient_id, admission_id)
          if (response.data) Object.assign(self.formState, response.data);

          if (response.next_step) {
            // Advance to next step by ID
            var nextIndex = (self.flow.steps || []).findIndex(function (s) {
              return s.step_id === response.next_step;
            });
            if (nextIndex > -1) {
              self.stepIndex = nextIndex;
              self._renderStep();
              self._scrollToTop();
              return;
            }
          }

          // No next step → completion
          self._renderComplete(response);
          if (typeof self.options.onComplete === 'function') {
            self.options.onComplete(response.data);
          }

        } else if (response.status === 'validation_error') {
          self._showFieldErrors(form, response.errors || {});
          if (response.message) {
            self._showFormAlert(form, response.message, 'warning');
          }

        } else {
          self._showFormAlert(form, response.message || 'An unexpected error occurred.', 'danger');
        }
      })
      .catch(function (err) {
        if (err && err.name === 'AbortError') return;
        self._setLoading(submitBtn, false);
        self._showFormAlert(form, 'Network error: ' + err.message, 'danger');
      });
    },

    _resolvePostEndpoint: function () {
      var pe = this.flow && this.flow.post_endpoint;
      if (!pe) return this.options.endpoint || '/dx-engine/public/api/dx.php';
      if (/^https?:\/\//i.test(pe) || pe.charAt(0) === '/') return pe;
      var base = (this.options.endpoint || '/dx-engine/public/api/dx.php');
      var idx = base.lastIndexOf('/');
      return idx > -1 ? base.slice(0, idx + 1) + pe : pe;
    },

    /* ── Navigate back ────────────────────────────────────────────── */
    _goBack: function () {
      if (this.stepIndex > 0) {
        this.stepIndex--;
        this._renderStep();
        this._scrollToTop();
      }
    },

    /* ── Completion screen ────────────────────────────────────────── */
    _renderComplete: function (response) {
      var self    = this;
      var tpl     = self.options.completionTemplate;

      if (typeof tpl === 'function') {
        self.targetEl.innerHTML = '';
        self.targetEl.appendChild(tpl(response));
        return;
      }

      self.targetEl.innerHTML = [
        '<div class="dx-card card shadow-sm">',
        '  <div class="card-body text-center p-5">',
        '    <div class="dx-success-icon mb-3">',
        '      <svg width="56" height="56" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">',
        '        <circle cx="28" cy="28" r="28" fill="var(--dx-success-bg)"/>',
        '        <path d="M16 28.5L24 36.5L40 20" stroke="var(--dx-success)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>',
        '      </svg>',
        '    </div>',
        '    <h4 class="fw-semibold mb-2">' + (self.options.successTitle || 'Submitted Successfully') + '</h4>',
        '    <p class="text-muted mb-4">' + (response.message || '') + '</p>',
        (self.options.resetLabel
          ? '<button class="btn btn-outline-primary dx-btn-reset">' + self.options.resetLabel + '</button>'
          : ''),
        '  </div>',
        '</div>'
      ].join('');

      if (self.options.resetLabel) {
        self.targetEl.querySelector('.dx-btn-reset').addEventListener('click', function () {
          self.formState = {};
          self.stepIndex = 0;
          self.load();
        });
      }
    },

    /* ── Field error display ──────────────────────────────────────── */
    _showFieldErrors: function (form, errors) {
      // Clear first
      this._clearFieldErrors(form);

      Object.keys(errors).forEach(function (key) {
        var inputs = form.querySelectorAll('[name="' + key + '"]');
        inputs.forEach(function (inp) {
          inp.classList.add('is-invalid');
        });
        // Attach feedback element after last input in group
        var lastInput = inputs[inputs.length - 1];
        if (lastInput) {
          var fb = document.createElement('div');
          fb.className = 'invalid-feedback dx-field-error';
          fb.setAttribute('data-for', key);
          fb.textContent = errors[key];
          lastInput.parentNode.insertBefore(fb, lastInput.nextSibling);
        }
      });
    },

    _clearFieldErrors: function (form) {
      form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
      form.querySelectorAll('.dx-field-error').forEach(function (el) { el.remove(); });
      form.querySelectorAll('.dx-form-alert').forEach(function (el) { el.remove(); });
    },

    _showFormAlert: function (form, message, type) {
      var existing = form.querySelector('.dx-form-alert');
      if (existing) existing.remove();
      var alert = _el('div', 'alert alert-' + type + ' dx-form-alert mt-3 mb-0');
      alert.textContent = message;
      form.appendChild(alert);
    },

    /* ── Loading state ────────────────────────────────────────────── */
    _setLoading: function (btn, isLoading) {
      var label = btn.querySelector('.dx-btn-label');
      if (isLoading) {
        btn.disabled = true;
        if (label) label.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing…';
      } else {
        btn.disabled = false;
        var step = (this.flow.steps || [])[this.stepIndex];
        if (label && step) label.textContent = step.submit_label || 'Continue';
      }
    },

    _showLoader: function () {
      this.targetEl.innerHTML = [
        '<div class="dx-loader d-flex flex-column align-items-center justify-content-center py-5">',
        '  <div class="spinner-border text-primary" role="status">',
        '    <span class="visually-hidden">Loading…</span>',
        '  </div>',
        '  <p class="text-muted mt-3 small">Loading form…</p>',
        '</div>'
      ].join('');
    },

    _showError: function (message) {
      this.targetEl.innerHTML = '';
      var wrap = _el('div', 'alert alert-danger m-3');
      wrap.setAttribute('role', 'alert');
      var strong = document.createElement('strong');
      strong.textContent = 'Error: ';
      wrap.appendChild(strong);
      wrap.appendChild(document.createTextNode(String(message || 'Unknown error')));
      this.targetEl.appendChild(wrap);
    },

    _scrollToTop: function () {
      this.targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
    },

    /** Re-evaluate visibility whenever a tracked field changes. */
    _attachVisibilityListeners: function (form) {
      var self = this;
      form.addEventListener('change', function (e) {
        if (e.target && e.target.name) {
          self.formState[e.target.name] = e.target.type === 'checkbox'
            ? (e.target.checked ? e.target.value : '')
            : e.target.value;
          self._applyVisibilityIfChanged();
        }
      });
      form.addEventListener('input', function (e) {
        if (e.target && e.target.name) {
          self.formState[e.target.name] = e.target.value;
          self._applyVisibilityIfChanged();
        }
      });
    },

    _applyVisibilityIfChanged: function () {
      var sig = JSON.stringify(this.formState || {});
      if (sig === this._lastVisibilitySignature) return;
      this._lastVisibilitySignature = sig;
      VisibilityEngine.applyAll(this.targetEl, this.formState);
    }
  };

  /* ====================================================================
   * 5. DOM Helper
   * ==================================================================== */
  function _el(tag, className) {
    var el = document.createElement(tag);
    if (className) el.className = className;
    return el;
  }

  /* ====================================================================
   * 6. Built-in Component Renderers
   * ==================================================================== */

  /** Shared: wrap an input with label + optional help text. */
  function _wrapField(descriptor, inputEl) {
    var wrap  = _el('div', 'dx-field-wrap');
    var label = _el('label', 'form-label fw-medium small');
    label.setAttribute('for', 'dx-' + (descriptor.field_key || ''));
    label.textContent = descriptor.label || '';

    if (descriptor.required) {
      var req = _el('span', 'text-danger ms-1');
      req.setAttribute('aria-hidden', 'true');
      req.textContent = '*';
      label.appendChild(req);
    }

    wrap.appendChild(label);

    if (Array.isArray(inputEl)) {
      inputEl.forEach(function (el) { wrap.appendChild(el); });
    } else {
      wrap.appendChild(inputEl);
    }

    if (descriptor.help_text) {
      var help = _el('div', 'form-text text-muted');
      help.textContent = descriptor.help_text;
      wrap.appendChild(help);
    }

    return wrap;
  }

  /** Set shared attributes on an input element. */
  function _applyAttrs(el, descriptor) {
    el.id   = 'dx-' + (descriptor.field_key || '');
    el.name = descriptor.field_key || '';
    if (descriptor.readonly)     el.readOnly = true;
    if (descriptor.required)     el.required = true;
    if (descriptor.placeholder)  el.placeholder = descriptor.placeholder;
    if (descriptor.value !== undefined && descriptor.value !== null) {
      el.value = descriptor.value;
    }
    // Extra HTML attrs
    Object.keys(descriptor.attrs || {}).forEach(function (k) {
      el.setAttribute(k, descriptor.attrs[k]);
    });
    if (descriptor.css_class)    el.classList.add(...descriptor.css_class.split(' ').filter(Boolean));
  }

  /* ─── text_input ───────────────────────────────────────────────────── */
  ComponentRegistry.register('text_input', function (d) {
    var inp = _el('input', 'form-control');
    inp.type = 'text';
    _applyAttrs(inp, d);
    return _wrapField(d, inp);
  });

  /* ─── email_input ──────────────────────────────────────────────────── */
  ComponentRegistry.register('email_input', function (d) {
    var inp = _el('input', 'form-control');
    inp.type = 'email';
    _applyAttrs(inp, d);
    return _wrapField(d, inp);
  });

  /* ─── number_input ─────────────────────────────────────────────────── */
  ComponentRegistry.register('number_input', function (d) {
    var inp = _el('input', 'form-control');
    inp.type = 'number';
    _applyAttrs(inp, d);
    return _wrapField(d, inp);
  });

  /* ─── date_input ───────────────────────────────────────────────────── */
  ComponentRegistry.register('date_input', function (d) {
    var inp = _el('input', 'form-control');
    inp.type = 'date';
    _applyAttrs(inp, d);
    return _wrapField(d, inp);
  });

  /* ─── textarea ─────────────────────────────────────────────────────── */
  ComponentRegistry.register('textarea', function (d) {
    var inp = _el('textarea', 'form-control');
    _applyAttrs(inp, d);
    inp.value = undefined;                 // reset (applyAttrs sets .value)
    inp.textContent = d.value || '';
    Object.keys(d.attrs || {}).forEach(function (k) { inp.setAttribute(k, d.attrs[k]); });
    return _wrapField(d, inp);
  });

  /* ─── select ───────────────────────────────────────────────────────── */
  ComponentRegistry.register('select', function (d) {
    var sel = _el('select', 'form-select');
    _applyAttrs(sel, d);
    sel.value = undefined;

    (d.options || []).forEach(function (opt) {
      var o    = document.createElement('option');
      o.value  = opt.value;
      o.textContent = opt.label;
      if (String(opt.value) === String(d.value)) o.selected = true;
      sel.appendChild(o);
    });

    return _wrapField(d, sel);
  });

  /* ─── radio ────────────────────────────────────────────────────────── */
  ComponentRegistry.register('radio', function (d) {
    var wrap  = _el('div', 'dx-field-wrap');
    var label = _el('label', 'form-label fw-medium small d-block mb-2');
    label.textContent = d.label || '';
    if (d.required) {
      var req = _el('span', 'text-danger ms-1');
      req.textContent = '*';
      label.appendChild(req);
    }
    wrap.appendChild(label);

    (d.options || []).forEach(function (opt) {
      var formCheck = _el('div', 'form-check dx-radio-item mb-2');
      var inp = document.createElement('input');
      inp.type      = 'radio';
      inp.className = 'form-check-input';
      inp.name      = d.field_key || '';
      inp.id        = 'dx-' + (d.field_key || '') + '-' + opt.value;
      inp.value     = opt.value;
      if (String(opt.value) === String(d.value)) inp.checked = true;
      if (opt.css_class) formCheck.classList.add(...opt.css_class.split(' ').filter(Boolean));

      var lbl = document.createElement('label');
      lbl.className = 'form-check-label';
      lbl.setAttribute('for', inp.id);
      lbl.textContent = opt.label;

      formCheck.appendChild(inp);
      formCheck.appendChild(lbl);
      wrap.appendChild(formCheck);
    });

    return wrap;
  });

  /* ─── checkbox_group ───────────────────────────────────────────────── */
  ComponentRegistry.register('checkbox_group', function (d) {
    var selected = Array.isArray(d.value) ? d.value : (d.value ? [d.value] : []);
    var wrap     = _el('div', 'dx-field-wrap');
    var label    = _el('label', 'form-label fw-medium small d-block mb-2');
    label.textContent = d.label || '';
    wrap.appendChild(label);

    (d.options || []).forEach(function (opt) {
      var formCheck = _el('div', 'form-check');
      var inp = document.createElement('input');
      inp.type      = 'checkbox';
      inp.className = 'form-check-input';
      inp.name      = (d.field_key || '') + '[]';
      inp.id        = 'dx-' + (d.field_key || '') + '-' + opt.value;
      inp.value     = opt.value;
      if (selected.indexOf(String(opt.value)) > -1) inp.checked = true;

      var lbl = document.createElement('label');
      lbl.className = 'form-check-label';
      lbl.setAttribute('for', inp.id);
      lbl.textContent = opt.label;

      formCheck.appendChild(inp);
      formCheck.appendChild(lbl);
      wrap.appendChild(formCheck);
    });

    return wrap;
  });

  /* ─── file_upload ──────────────────────────────────────────────────── */
  ComponentRegistry.register('file_upload', function (d) {
    var inp = _el('input', 'form-control');
    inp.type   = 'file';
    inp.id     = 'dx-' + (d.field_key || '');
    inp.name   = d.field_key || '';
    if (d.required) inp.required = true;
    if (d.attrs && d.attrs.accept) inp.accept = d.attrs.accept;
    if (d.attrs && d.attrs.multiple) inp.multiple = true;
    return _wrapField(d, inp);
  });

  /* ─── heading ──────────────────────────────────────────────────────── */
  ComponentRegistry.register('heading', function (d) {
    var el = _el('h6', 'dx-section-heading fw-semibold mt-2 mb-1 pb-1 border-bottom');
    el.textContent = d.label || '';
    return el;
  });

  /* ─── paragraph ────────────────────────────────────────────────────── */
  ComponentRegistry.register('paragraph', function (d) {
    var el = _el('p', 'text-muted small mb-0');
    el.textContent = d.label || '';
    return el;
  });

  /* ─── divider ───────────────────────────────────────────────────────── */
  ComponentRegistry.register('divider', function () {
    return _el('hr', 'dx-divider my-2');
  });

  /* ─── alert ─────────────────────────────────────────────────────────── */
  ComponentRegistry.register('alert', function (d) {
    var el = _el('div', 'alert alert-' + (d.attrs && d.attrs.variant ? d.attrs.variant : 'info') + ' mb-0');
    el.textContent = d.label || '';
    return el;
  });

  /* ─── hidden ────────────────────────────────────────────────────────── */
  ComponentRegistry.register('hidden', function (d) {
    var inp  = document.createElement('input');
    inp.type = 'hidden';
    inp.name = d.field_key || '';
    inp.value = d.value || '';
    return inp;
  });

  /* ====================================================================
   * 7. Public API
   * ==================================================================== */
  var DXEngine = {
    /** Component registry — expose so external code can add renderers. */
    registry: ComponentRegistry,

    /** Validator — expose for external use. */
    validator: Validator,

    /**
     * Mount a Digital Experience into a DOM element.
     *
     * @param {string|HTMLElement} target  CSS selector or DOM element
     * @param {Object}             options
     *   @option {string}   dx_id            Required. The DX identifier.
     *   @option {string}   endpoint         URL of dx.php. Default: '/dx-engine/public/api/dx.php'
     *   @option {Object}   params           Extra GET params for pre-processing (e.g. admission_id)
     *   @option {string}   csrf             CSRF token to include in POST payloads
     *   @option {Function} onComplete       Callback(data) on final success
     *   @option {string}   successTitle     Completion screen title
     *   @option {string}   resetLabel       If set, shows a "start over" button on completion
     *   @option {Function} completionTemplate  function(response) → HTMLElement for custom completion
     */
    mount: function (target, options) {
      var el = typeof target === 'string' ? document.querySelector(target) : target;
      if (!el) {
        console.error('[DXEngine] Target element not found:', target);
        return null;
      }
      var instance = new DXEngineInstance(el, options);
      instance.load();
      return instance;
    },

    /** Utility: create a DOM element with a class name. */
    el: _el
  };

  // Expose to global scope
  global.DXEngine = DXEngine;

})(window);
