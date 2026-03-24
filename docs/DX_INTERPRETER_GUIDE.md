# DX Interpreter - Complete Integration Guide

## Overview

The DX Interpreter is a powerful, plug-and-play JavaScript framework for rendering dynamic, multi-step forms with beautiful UI components, validation, and workflow management. It supports **two rendering modes** to fit different integration scenarios.

---

## 🎯 Rendering Modes

### 1. **Modal Mode** (Public Button → Pop-up)
Perfect for public-facing websites where users initiate workflows without being logged in.

**Use Cases:**
- Public admission forms
- Contact/inquiry forms
- Registration workflows
- Lead capture forms

**Features:**
- Opens in a Bootstrap modal overlay
- Can create cases before user authentication
- Automatically closes on completion
- Responsive and mobile-friendly

### 2. **Embedded Mode** (Portal Button → Inline)
Ideal for integrating into existing legacy systems or authenticated portals.

**Use Cases:**
- Internal dashboards
- Legacy system integration
- Authenticated user workflows
- Admin panels

**Features:**
- Renders inline within existing page layout
- Seamless integration with existing UI
- No page navigation required
- Maintains portal context

---

## 📦 Installation

### 1. Include Required Files

```html
<!-- Bootstrap 5.3.3 (required) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- DX Engine CSS -->
<link rel="stylesheet" href="/public/css/dx-engine.css">

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- DX Interpreter JS -->
<script src="/public/js/dx-interpreter.js"></script>
```

### 2. Create Container Element

```html
<!-- For Modal Mode -->
<div id="dx-modal-container"></div>

<!-- For Embedded Mode -->
<div id="dx-embedded-container" class="dx-root"></div>
```

---

## 🚀 Quick Start Examples

### Example 1: Modal Mode (Public Form)

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Public Admission Form</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/public/css/dx-engine.css">
</head>
<body>

<button id="startBtn" class="btn btn-primary">Start Application</button>
<div id="dx-modal-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/public/js/dx-interpreter.js"></script>
<script>
document.getElementById('startBtn').addEventListener('click', function() {
  const app = new DXInterpreter('#dx-modal-container', {
    dx_id: 'admission_case',
    endpoint: '/public/api/dx.php',
    renderMode: 'modal',
    modalOptions: {
      size: 'lg',              // 'sm', 'lg', 'xl'
      backdrop: 'static',      // true, false, 'static'
      keyboard: false,         // Allow ESC to close
      closeOnComplete: false   // Auto-close after completion
    },
    onStepComplete: function(stepData) {
      console.log('Step completed:', stepData);
      // Custom logic after each step
    },
    onComplete: function(data) {
      console.log('Form completed:', data);
      // Redirect or show success message
    },
    onModalClose: function(state) {
      console.log('Modal closed:', state);
    }
  });
  
  app.load();
});
</script>

</body>
</html>
```

### Example 2: Embedded Mode (Portal Integration)

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Portal Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/public/css/dx-engine.css">
</head>
<body>

<div class="container mt-4">
  <h2>My Dashboard</h2>
  <button id="startBtn" class="btn btn-success">New Application</button>
  
  <div id="dx-embedded-container" class="dx-root mt-4"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/public/js/dx-interpreter.js"></script>
<script>
document.getElementById('startBtn').addEventListener('click', function() {
  const app = new DXInterpreter('#dx-embedded-container', {
    dx_id: 'admission_case',
    endpoint: '/public/api/dx.php',
    renderMode: 'embedded', // Default mode
    params: {
      user_id: 123 // Pass context parameters
    },
    onStepComplete: function(stepData) {
      console.log('Step completed:', stepData);
    },
    onComplete: function(data) {
      console.log('Form completed:', data);
      // Show success message inline
      document.getElementById('dx-embedded-container').innerHTML = `
        <div class="alert alert-success">
          Application submitted successfully!
        </div>
      `;
    }
  });
  
  app.load();
});
</script>

</body>
</html>
```

---

## ⚙️ Configuration Options

### Constructor Parameters

```javascript
new DXInterpreter(target, options)
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `target` | string\|HTMLElement | ✅ | CSS selector or DOM element |
| `options` | Object | ✅ | Configuration object |

### Options Object

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `dx_id` | string | **required** | Workflow identifier (matches backend) |
| `endpoint` | string | auto-detected | API endpoint URL |
| `renderMode` | string | `'embedded'` | `'modal'` or `'embedded'` |
| `modalOptions` | Object | `{}` | Modal configuration (see below) |
| `params` | Object | `{}` | Extra GET parameters |
| `csrf` | string | `''` | CSRF token |
| `onStepComplete` | Function | `null` | Callback after each step |
| `onComplete` | Function | `null` | Callback on final completion |
| `onModalClose` | Function | `null` | Callback when modal closes |
| `successTitle` | string | `'Success'` | Completion screen title |
| `resetLabel` | string | `null` | "Start over" button label |

### Modal Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `size` | string | `'lg'` | Modal size: `'sm'`, `'lg'`, `'xl'` |
| `backdrop` | boolean\|string | `true` | `true`, `false`, or `'static'` |
| `keyboard` | boolean | `true` | Allow ESC key to close |
| `closeOnComplete` | boolean | `false` | Auto-close after completion |

---

## 📊 Callback Functions

### onStepComplete(stepData)

Called after each step is successfully submitted.

```javascript
onStepComplete: function(stepData) {
  console.log('Step ID:', stepData.step);
  console.log('Step Index:', stepData.stepIndex);
  console.log('Next Step:', stepData.nextStep);
  console.log('Form Data:', stepData.payload);
  console.log('Server Response:', stepData.response);
}
```

**stepData Object:**
```javascript
{
  step: 'personal_info',        // Current step ID
  stepIndex: 0,                 // Zero-based index
  nextStep: 'academic_background', // Next step ID
  payload: { /* form data */ }, // Accumulated form state
  response: { /* server response */ }
}
```

### onComplete(data)

Called when all steps are completed.

```javascript
onComplete: function(data) {
  console.log('Final data:', data);
  // Redirect, show message, etc.
  window.location.href = '/success';
}
```

### onModalClose(state)

Called when modal is closed (modal mode only).

```javascript
onModalClose: function(state) {
  console.log('Modal closed with state:', state);
  // Cleanup, analytics, etc.
}
```

---

## 🎨 Styling & Customization

### CSS Variables

Override these in your custom CSS:

```css
.dx-root {
  --dx-primary: #2563eb;
  --dx-primary-hover: #1d4ed8;
  --dx-primary-rgb: 37, 99, 235;
  --dx-success: #16a34a;
  --dx-error: #dc2626;
  --dx-border: #e2e8f0;
  --dx-radius: 12px;
  --dx-radius-sm: 6px;
}
```

### Custom Stepper Styling

The stepper automatically renders for multi-step forms:

```css
/* Customize stepper colors */
.dx-root .dx-stepper-badge {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.dx-root .dx-stepper-item--active .dx-stepper-badge {
  box-shadow: 0 4px 16px rgba(102, 126, 234, 0.5);
}
```

### Modal Customization

```css
/* Custom modal styling */
.modal-dialog-dx {
  max-width: 900px;
}

.modal-body-dx .dx-card {
  box-shadow: none;
  border: none;
}
```

---

## 🔄 Workflow Integration

### Creating Cases from Public Forms

```javascript
const app = new DXInterpreter('#dx-modal-container', {
  dx_id: 'admission_case',
  renderMode: 'modal',
  onStepComplete: async function(stepData) {
    if (stepData.step === 'personal_info') {
      // Create case and user account
      const response = await fetch('/api/admission_public.php?action=start_admission', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(stepData.payload)
      });
      
      const result = await response.json();
      
      if (result.status === 'success') {
        // Show credentials to user
        showCredentials(result.data.credentials);
        
        // Close modal
        app.closeModal();
      }
    }
  }
});
```

### Routing to Different User Groups

```javascript
onComplete: function(data) {
  // Route based on case status or user role
  if (data.requires_review) {
    // Assign to reviewer group
    assignToGroup('reviewers', data.case_id);
  } else if (data.requires_approval) {
    // Assign to admin group
    assignToGroup('admins', data.case_id);
  }
}
```

---

## 🧪 Testing

### Test Modal Mode

```bash
# Start XAMPP
# Navigate to:
http://localhost/dx-engine/public/portal/index.php
```

### Test Embedded Mode

```bash
# Navigate to:
http://localhost/dx-engine/examples/embedded-mode-example.html
```

---

## 🐛 Troubleshooting

### Modal Not Showing

**Problem:** Modal doesn't appear when button is clicked.

**Solution:**
1. Ensure Bootstrap 5 JS is loaded
2. Check browser console for errors
3. Verify `renderMode: 'modal'` is set

```javascript
// Check if Bootstrap is loaded
if (typeof bootstrap === 'undefined') {
  console.error('Bootstrap 5 not loaded!');
}
```

### Stepper Not Rendering

**Problem:** Stepper doesn't show in modal.

**Solution:**
1. Ensure `.dx-root` class is on container
2. Check that workflow has multiple steps
3. Verify CSS is loaded

```html
<!-- Ensure dx-root class -->
<div id="container" class="dx-root"></div>
```

### Form Data Not Saving

**Problem:** Form submits but data isn't saved.

**Solution:**
1. Check `endpoint` URL is correct
2. Verify backend API is responding
3. Check browser network tab for errors

```javascript
// Debug endpoint
const app = new DXInterpreter('#container', {
  dx_id: 'admission_case',
  endpoint: '/public/api/dx.php' // Verify this path
});

console.log('Endpoint:', app.endpoint);
```

---

## 📚 Advanced Examples

### Example: Multi-Step with Conditional Logic

```javascript
const app = new DXInterpreter('#container', {
  dx_id: 'complex_workflow',
  renderMode: 'embedded',
  onStepComplete: function(stepData) {
    // Conditional routing based on answers
    if (stepData.step === 'eligibility_check') {
      if (stepData.payload.is_eligible === 'no') {
        // Show rejection message
        this.closeModal();
        showRejectionMessage();
        return false; // Prevent next step
      }
    }
  }
});
```

### Example: Save Progress Automatically

```javascript
const app = new DXInterpreter('#container', {
  dx_id: 'admission_case',
  onStepComplete: async function(stepData) {
    // Auto-save after each step
    await fetch('/api/save_progress.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        case_id: stepData.payload.case_id,
        step: stepData.step,
        data: stepData.payload
      })
    });
  }
});
```

---

## 🎓 Best Practices

1. **Always use HTTPS** in production
2. **Validate on both client and server** side
3. **Use CSRF tokens** for security
4. **Provide clear error messages** to users
5. **Test on mobile devices** for responsiveness
6. **Use onStepComplete** for progress tracking
7. **Close modals explicitly** when needed
8. **Handle network errors** gracefully

---

## 📞 Support

For issues or questions:
- Check the examples in `/examples/`
- Review the source code in `/public/js/dx-interpreter.js`
- Consult the backend API documentation

---

## 📄 License

Part of the DX-Engine framework.
