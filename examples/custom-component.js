/**
 * DX-Engine — Custom Component Extension Example
 * -----------------------------------------------------------------------
 * Demonstrates how to add new UI components to the registry without
 * touching dx-engine.js.
 *
 * Load this file AFTER dx-engine.js in your page:
 *   <script src="js/dx-engine.js"></script>
 *   <script src="examples/custom-component.js"></script>
 *
 * Then use the component_type in your PHP DX controller:
 *   $this->component('star_rating', [
 *       'field_key' => 'satisfaction',
 *       'label'     => 'Patient Satisfaction',
 *       'required'  => true,
 *       'value'     => '',
 *       'col_span'  => 6,
 *   ])
 */

/* ── Example 1: Star Rating ────────────────────────────────────────── */
DXEngine.registry.register('star_rating', function (descriptor) {
  var wrap  = document.createElement('div');
  wrap.className = 'dx-field-wrap';

  // Label
  var label = document.createElement('label');
  label.className = 'form-label fw-medium small d-block mb-2';
  label.textContent = descriptor.label || 'Rating';
  if (descriptor.required) {
    var req = document.createElement('span');
    req.className   = 'text-danger ms-1';
    req.textContent = '*';
    label.appendChild(req);
  }
  wrap.appendChild(label);

  // Stars
  var starWrap = document.createElement('div');
  starWrap.className = 'dx-star-wrap d-flex gap-2';
  starWrap.style.fontSize = '1.5rem';
  starWrap.style.cursor   = 'pointer';

  var hidden = document.createElement('input');
  hidden.type  = 'hidden';
  hidden.name  = descriptor.field_key || '';
  hidden.value = descriptor.value || '';

  var maxStars = descriptor.attrs && descriptor.attrs.max ? Number(descriptor.attrs.max) : 5;
  var stars    = [];

  function setRating(n) {
    hidden.value = n;
    stars.forEach(function (s, i) {
      s.textContent = i < n ? '★' : '☆';
      s.style.color = i < n ? '#f59e0b' : '#cbd5e1';
    });
  }

  for (var i = 1; i <= maxStars; i++) {
    (function (rating) {
      var star = document.createElement('span');
      star.textContent  = '☆';
      star.style.color  = '#cbd5e1';
      star.style.userSelect = 'none';
      star.setAttribute('role', 'button');
      star.setAttribute('aria-label', rating + ' star' + (rating > 1 ? 's' : ''));
      star.addEventListener('click', function () { setRating(rating); });
      star.addEventListener('mouseover', function () {
        stars.forEach(function (s, i) {
          s.textContent = i < rating ? '★' : '☆';
          s.style.color = i < rating ? '#f59e0b' : '#cbd5e1';
        });
      });
      starWrap.appendChild(star);
      stars.push(star);
    })(i);
  }

  starWrap.addEventListener('mouseleave', function () {
    setRating(parseInt(hidden.value, 10) || 0);
  });

  if (descriptor.value) setRating(parseInt(descriptor.value, 10));

  wrap.appendChild(starWrap);
  wrap.appendChild(hidden);
  return wrap;
});

/* ── Example 2: Phone Input with country flag ──────────────────────── */
DXEngine.registry.register('phone_input', function (descriptor) {
  var wrap = document.createElement('div');
  wrap.className = 'dx-field-wrap';

  var label = document.createElement('label');
  label.className   = 'form-label fw-medium small';
  label.htmlFor     = 'dx-' + descriptor.field_key;
  label.textContent = descriptor.label || 'Phone';
  if (descriptor.required) {
    var req = document.createElement('span');
    req.className = 'text-danger ms-1';
    req.textContent = '*';
    label.appendChild(req);
  }
  wrap.appendChild(label);

  var group = document.createElement('div');
  group.className = 'input-group';

  var prefix = document.createElement('span');
  prefix.className   = 'input-group-text';
  prefix.textContent = '+1';   // Hardcoded; extend with a country-code select

  var inp = document.createElement('input');
  inp.type        = 'tel';
  inp.className   = 'form-control';
  inp.id          = 'dx-' + descriptor.field_key;
  inp.name        = descriptor.field_key || '';
  inp.placeholder = descriptor.placeholder || '(555) 000-0000';
  inp.value       = descriptor.value || '';
  if (descriptor.required) inp.required = true;

  group.appendChild(prefix);
  group.appendChild(inp);
  wrap.appendChild(group);

  if (descriptor.help_text) {
    var help = document.createElement('div');
    help.className   = 'form-text text-muted';
    help.textContent = descriptor.help_text;
    wrap.appendChild(help);
  }

  return wrap;
});

/* ── Example 3: Signature Pad (canvas-based) ──────────────────────── */
DXEngine.registry.register('signature_pad', function (descriptor) {
  var wrap = document.createElement('div');
  wrap.className = 'dx-field-wrap';

  var label = document.createElement('label');
  label.className   = 'form-label fw-medium small d-block mb-2';
  label.textContent = descriptor.label || 'Signature';
  wrap.appendChild(label);

  var canvas = document.createElement('canvas');
  canvas.width     = 480;
  canvas.height    = 120;
  canvas.className = 'border rounded w-100';
  canvas.style.cursor   = 'crosshair';
  canvas.style.touchAction = 'none';

  var ctx      = canvas.getContext('2d');
  var drawing  = false;

  ctx.strokeStyle = '#1e293b';
  ctx.lineWidth   = 1.8;
  ctx.lineCap     = 'round';

  canvas.addEventListener('pointerdown', function (e) {
    drawing = true;
    ctx.beginPath();
    var r = canvas.getBoundingClientRect();
    ctx.moveTo((e.clientX - r.left) * (canvas.width / r.width),
               (e.clientY - r.top)  * (canvas.height / r.height));
  });

  canvas.addEventListener('pointermove', function (e) {
    if (!drawing) return;
    var r = canvas.getBoundingClientRect();
    ctx.lineTo((e.clientX - r.left) * (canvas.width / r.width),
               (e.clientY - r.top)  * (canvas.height / r.height));
    ctx.stroke();
    hidden.value = canvas.toDataURL('image/png');
  });

  canvas.addEventListener('pointerup',   function () { drawing = false; });
  canvas.addEventListener('pointerleave',function () { drawing = false; });

  var clearBtn = document.createElement('button');
  clearBtn.type      = 'button';
  clearBtn.className = 'btn btn-sm btn-outline-secondary mt-2';
  clearBtn.textContent = 'Clear';
  clearBtn.addEventListener('click', function () {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    hidden.value = '';
  });

  var hidden = document.createElement('input');
  hidden.type  = 'hidden';
  hidden.name  = descriptor.field_key || '';
  hidden.value = descriptor.value || '';

  wrap.appendChild(canvas);
  wrap.appendChild(clearBtn);
  wrap.appendChild(hidden);
  return wrap;
});
