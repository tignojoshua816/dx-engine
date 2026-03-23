<?php
/**
 * DX-Engine — Legacy Integration Demo  (index.php)
 * =====================================================================
 * This file shows the MINIMUM code needed to embed a DX-Engine Digital
 * Experience into any existing PHP page.
 *
 * Three things are required on the host page:
 *   1.  <link rel="stylesheet" href="…/dx-engine.css">        (after Bootstrap)
 *   2.  <div id="dx-entry" class="dx-root" data-case="admission"></div>
 *   3.  <script src="…/dx-interpreter.js"> + new DXInterpreter(…).load()
 *
 * Everything else on this page represents an existing legacy application.
 * No other changes to the legacy code are necessary.
 * =====================================================================
 */

// --- Legacy app session / auth would live here ---
// session_start();
// require_once 'legacy/auth.php';

// Optional: pass edit context from the legacy URL
$admissionId = (int) ($_GET['edit'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Patient Admissions — HospitalIS</title>

  <!-- ── 1. EXISTING LEGACY STYLES ────────────────────────────────── -->
  <!--
    Bootstrap 5.3.3 — no integrity/crossorigin for XAMPP localhost compatibility.
    The correct hashes for production are:
      CSS: sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH
      JS:  sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmFU0NGgm/c1Bs7qAGRa0f0BBML6
    Add them back (with crossorigin="anonymous") on any public server.
  -->
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">

  <!-- ── STEP 1: Add dx-engine.css AFTER your existing stylesheets ─ -->
  <!--   It is fully scoped to .dx-root — no Bootstrap conflicts.    -->
  <link rel="stylesheet" href="../public/css/dx-engine.css">

  <!-- Legacy page styles (unrelated to DX-Engine) -->
  <style>
    /* ── Legacy shell styles ─────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; }

    body {
      font-family: 'Inter', system-ui, sans-serif;
      background:  #f1f5f9;
      color:       #1e293b;
      margin:      0;
    }

    /* Top navigation */
    .ls-topbar {
      background:      #0f172a;
      padding:         0 1.5rem;
      height:          56px;
      display:         flex;
      align-items:     center;
      justify-content: space-between;
      position:        sticky;
      top:             0;
      z-index:         100;
    }
    .ls-topbar .brand {
      color:       #f8fafc;
      font-weight: 700;
      font-size:   0.9375rem;
      letter-spacing: -0.02em;
    }
    .ls-topbar .brand small {
      color:       #94a3b8;
      font-weight: 400;
      font-size:   0.75rem;
      margin-left: 6px;
    }
    .ls-topbar nav a {
      color:       #94a3b8;
      text-decoration: none;
      font-size:   0.8125rem;
      font-weight: 500;
      padding:     0 0.75rem;
      line-height: 56px;
      display:     inline-block;
      border-bottom: 2px solid transparent;
      transition:  color 0.15s, border-color 0.15s;
    }
    .ls-topbar nav a:hover,
    .ls-topbar nav a.active {
      color:        #f8fafc;
      border-color: #3b82f6;
    }

    /* Layout: sidebar + main */
    .ls-layout {
      display:    flex;
      min-height: calc(100vh - 56px);
    }
    .ls-sidebar {
      width:       220px;
      flex-shrink: 0;
      background:  #ffffff;
      border-right: 1px solid #e2e8f0;
      padding:     1.25rem 0.75rem;
    }
    .ls-sidebar .section-label {
      font-size:      0.6875rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color:          #94a3b8;
      font-weight:    600;
      padding:        0 0.75rem;
      margin:         1rem 0 0.4rem;
    }
    .ls-sidebar a {
      display:      flex;
      align-items:  center;
      gap:          0.5rem;
      color:        #475569;
      text-decoration: none;
      font-size:    0.8125rem;
      font-weight:  500;
      padding:      0.4rem 0.75rem;
      border-radius: 0.375rem;
      transition:   background 0.12s, color 0.12s;
    }
    .ls-sidebar a:hover { background: #f1f5f9; color: #1e40af; }
    .ls-sidebar a.active { background: #dbeafe; color: #1d4ed8; font-weight: 600; }

    /* Main content */
    .ls-main {
      flex: 1;
      padding: 2rem 2.5rem;
      min-width: 0;
    }
    .ls-page-title {
      font-size:   1.125rem;
      font-weight: 700;
      color:       #0f172a;
      margin:      0 0 0.2rem;
    }
    .ls-breadcrumb {
      font-size: 0.8125rem;
      color:     #64748b;
      margin:    0 0 1.75rem;
    }
    .ls-breadcrumb a {
      color: #3b82f6;
      text-decoration: none;
    }
    .ls-breadcrumb a:hover { text-decoration: underline; }

    /* Edit-mode banner (shown when ?edit=N is in the URL) */
    .dx-edit-banner {
      display:       flex;
      align-items:   center;
      gap:           0.5rem;
      background:    #eff6ff;
      border:        1px solid #bfdbfe;
      border-radius: 0.5rem;
      padding:       0.6rem 1rem;
      font-size:     0.8125rem;
      color:         #1d4ed8;
      margin-bottom: 1.25rem;
    }

    /* Constrain the DX form on wide screens */
    #dx-entry {
      max-width: 740px;
    }

    @media (max-width: 768px) {
      .ls-sidebar  { display: none; }
      .ls-main     { padding: 1.25rem; }
    }
  </style>
</head>
<body>

<!-- ── Legacy top navigation ─────────────────────────────────────────── -->
<header class="ls-topbar">
  <span class="brand">HospitalIS <small>v3.4</small></span>
  <nav>
    <a href="#">Dashboard</a>
    <a href="#">Patients</a>
    <a href="#" class="active">Admissions</a>
    <a href="#">Wards</a>
    <a href="#">Reports</a>
  </nav>
</header>

<div class="ls-layout">

  <!-- ── Legacy sidebar ──────────────────────────────────────────────── -->
  <aside class="ls-sidebar">
    <p class="section-label">Admissions</p>
    <a href="index.php" class="active">
      <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1.5a6.5 6.5 0 100 13 6.5 6.5 0 000-13zM0 8a8 8 0 1116 0A8 8 0 010 8z"/><path d="M8 4a.5.5 0 01.5.5V8H11a.5.5 0 010 1H8a.5.5 0 01-.5-.5v-4A.5.5 0 018 4z"/></svg>
      New Admission
    </a>
    <a href="#">
      <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M2.5 1h11A1.5 1.5 0 0115 2.5v11a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 011 13.5v-11A1.5 1.5 0 012.5 1zm0 1a.5.5 0 00-.5.5v11a.5.5 0 00.5.5h11a.5.5 0 00.5-.5v-11a.5.5 0 00-.5-.5h-11z"/><path d="M4 5.5a.5.5 0 01.5-.5h7a.5.5 0 010 1h-7a.5.5 0 01-.5-.5zm0 3a.5.5 0 01.5-.5h7a.5.5 0 010 1h-7a.5.5 0 01-.5-.5zm0 3a.5.5 0 01.5-.5h4a.5.5 0 010 1h-4a.5.5 0 01-.5-.5z"/></svg>
      Admission List
    </a>
    <a href="#">Pending Triage</a>
    <a href="#">Discharge</a>

    <p class="section-label">Patients</p>
    <a href="#">Patient Registry</a>
    <a href="#">Search Patients</a>

    <p class="section-label">Clinical</p>
    <a href="#">Lab Requests</a>
    <a href="#">Imaging</a>
  </aside>

  <!-- ── Legacy main content ─────────────────────────────────────────── -->
  <main class="ls-main">

    <h1 class="ls-page-title">
      <?= $admissionId > 0 ? 'Edit Admission #' . $admissionId : 'New Patient Admission' ?>
    </h1>
    <p class="ls-breadcrumb">
      <a href="#">Home</a> /
      <a href="#">Admissions</a> /
      <?= $admissionId > 0 ? 'Edit' : 'New' ?>
    </p>

    <?php if ($admissionId > 0): ?>
    <!-- Edit-mode indicator rendered by the legacy PHP page -->
    <div class="dx-edit-banner">
      <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor">
        <path d="M12.146.854a.5.5 0 01.707 0l2.293 2.292a.5.5 0 010 .708l-10 10a.5.5 0 01-.168.11l-4 1.5a.5.5 0 01-.65-.65l1.5-4a.5.5 0 01.11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 01.5.5v.5h.5a.5.5 0 01.5.5v.5h.293l6.5-6.5z"/>
      </svg>
      Editing existing admission record — form is pre-populated from the database.
    </div>
    <?php endif; ?>

    <!--
    ═══════════════════════════════════════════════════════════════════
    STEP 2 — Place this single <div> wherever the form should appear.

    Rules:
      • id="dx-entry"    → JS selector target
      • class="dx-root"  → scopes ALL DX-Engine CSS (no style bleed)
      • data-case is optional metadata; the dx_id is set in JS below

    That is ALL the HTML you need to add to a legacy page.
    ═══════════════════════════════════════════════════════════════════
    -->
    <div id="dx-entry" class="dx-root" data-case="admission"></div>

  </main><!-- /ls-main -->

</div><!-- /ls-layout -->

<!-- ── Existing legacy scripts ─────────────────────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!--
  STEP 3 — Load dx-interpreter.js and call new DXInterpreter(…).load().
  This is the only JS you need to add. It is completely self-contained.
-->
<script src="../public/js/dx-interpreter.js"></script>
<script>
  /**
   * Initialise the DX-Engine interpreter and mount it into #dx-entry.
   *
   * Option reference (all optional except dx_id):
   * ┌──────────────────┬──────────────────────────────────────────────────┐
   * │ dx_id            │ Must match a key registered in api/dx.php Router │
   * │ endpoint         │ Absolute path to dx.php (default shown below)    │
   * │ params           │ Extra GET params merged onto the fetch URL        │
   * │ csrf             │ CSRF token string; included in every POST body    │
   * │ onComplete(data) │ Called with response.data when final step passes  │
   * │ successTitle     │ Heading on the built-in completion screen         │
   * │ resetLabel       │ If set, shows a "start over" button on completion │
   * │ completionTemplate(response) → HTMLElement  — fully custom screen   │
   * └──────────────────┴──────────────────────────────────────────────────┘
   */
  const admission = new DXInterpreter('#dx-entry', {

    dx_id   : 'admission_case',
    endpoint: '../public/api/dx.php',

    // Edit mode — the PHP $admissionId is echoed into JS here.
    // When > 0, AdmissionDX::preProcess() hydrates the form from the DB.
    params: {
      admission_id: <?= $admissionId ?>
    },

    // CSRF token (recommended for production; uncomment and wire up):
    // csrf: '<?php // echo htmlspecialchars(\DXEngine\Core\Helpers::csrfToken()); ?>',

    /**
     * onComplete — called once the final postProcess returns status: "success"
     * with next_step: null.  `data` contains whatever the PHP returned,
     * e.g. { admission_id: 42 }.  Bridge back to the legacy app here.
     */
    onComplete: function (data) {
      // Example: redirect to the legacy admission view after save
      // window.location.href = '/legacy/admissions/view.php?id=' + data.admission_id;

      // Example: dispatch a custom DOM event so the legacy page can react
      document.dispatchEvent(new CustomEvent('dx:admission:complete', { detail: data }));

      console.log('[DX-Engine] Admission complete:', data);
    },

    successTitle: 'Admission Registered',
    resetLabel  : 'Add Another Patient',
  });

  // Start the interpreter — GETs the Metadata Bridge JSON from PHP
  // and renders the first step inside #dx-entry.
  admission.load();

  // --- Optional: listen to the custom event from onComplete above ---
  document.addEventListener('dx:admission:complete', function (e) {
    // The legacy app can react here without touching the DX-Engine code.
    // e.detail = { admission_id: 42 }
  });
</script>

</body>
</html>
