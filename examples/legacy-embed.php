<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Legacy Application — DX-Engine Embedded</title>

  <!--
  ╔══════════════════════════════════════════════════════════════════════╗
  ║  LEGACY INTEGRATION EXAMPLE                                          ║
  ║  ─────────────────────────────────────────────────────────────────── ║
  ║  This page simulates an existing .php application that already has  ║
  ║  its own layout, navbar, and Bootstrap version.                     ║
  ║  We embed the DX-Engine Admission form by:                          ║
  ║    1. Adding the DX-Engine CSS after the existing stylesheet        ║
  ║    2. Placing a <div id="dx-root" class="dx-root"> anywhere         ║
  ║    3. Loading dx-engine.js and calling DXEngine.mount()             ║
  ║  That is ALL that is required.  No PHP changes needed on this page. ║
  ╚══════════════════════════════════════════════════════════════════════╝
  -->

  <!-- EXISTING LEGACY STYLES ──────────────────────────────────────────── -->
  <!--
    Bootstrap 5.3.3 — no integrity/crossorigin for XAMPP localhost compatibility.
    Production hashes:
      CSS: sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH
      JS:  sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmFU0NGgm/c1Bs7qAGRa0f0BBML6
    Add them back (with crossorigin="anonymous") on any public server.
  -->
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap">

  <!-- STEP 1: Add DX-Engine CSS after your existing stylesheets ────────── -->
  <link rel="stylesheet" href="../public/css/dx-engine.css">

  <style>
    /* Legacy app styles — not related to DX-Engine */
    body { background: #f8f9fa; font-family: 'Inter', sans-serif; }
    .legacy-topbar {
      background: #212529;
      padding: .75rem 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .legacy-topbar .brand { color: #fff; font-weight: 700; font-size: 1rem; letter-spacing: -.01em; }
    .legacy-topbar .nav-link { color: rgba(255,255,255,.75); font-size: .875rem; }
    .legacy-topbar .nav-link:hover { color: #fff; }
    .legacy-sidebar {
      width: 220px; min-height: 100vh;
      background: #fff; border-right: 1px solid #dee2e6;
      padding: 1.5rem 1rem;
      flex-shrink: 0;
    }
    .legacy-sidebar .nav-link {
      color: #495057; border-radius: .375rem;
      padding: .45rem .75rem; font-size: .875rem;
    }
    .legacy-sidebar .nav-link.active,
    .legacy-sidebar .nav-link:hover { background: #e9ecef; color: #1d4ed8; }
    .legacy-content { flex: 1; padding: 2rem; max-width: 900px; }
    .legacy-page-title { font-size: 1.1rem; font-weight: 600; margin-bottom: .25rem; }
    .legacy-breadcrumb { font-size: .8125rem; color: #6c757d; margin-bottom: 1.5rem; }
  </style>
</head>
<body>

<!-- LEGACY TOP NAVIGATION ─────────────────────────────────────────────────── -->
<div class="legacy-topbar">
  <span class="brand">HospitalIS v3.4</span>
  <nav class="d-flex gap-3">
    <a href="#" class="nav-link">Dashboard</a>
    <a href="#" class="nav-link">Patients</a>
    <a href="#" class="nav-link active">Admissions</a>
    <a href="#" class="nav-link">Reports</a>
  </nav>
</div>

<div class="d-flex">

  <!-- LEGACY SIDEBAR ──────────────────────────────────────────────────────── -->
  <aside class="legacy-sidebar d-none d-md-block">
    <p style="font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:#adb5bd;font-weight:600;margin-bottom:.75rem;">Admissions</p>
    <a href="#" class="nav-link active">New Admission</a>
    <a href="#" class="nav-link">Admission List</a>
    <a href="#" class="nav-link">Pending Triage</a>
    <a href="#" class="nav-link">Discharge</a>
    <p style="font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:#adb5bd;font-weight:600;margin:.75rem 0 .75rem;margin-top:1.5rem;">Patients</p>
    <a href="#" class="nav-link">Patient Registry</a>
    <a href="#" class="nav-link">Search Patients</a>
  </aside>

  <!-- LEGACY MAIN CONTENT ─────────────────────────────────────────────────── -->
  <main class="legacy-content">
    <p class="legacy-page-title">New Patient Admission</p>
    <p class="legacy-breadcrumb">Admissions / New Admission</p>

    <!-- ================================================================
         STEP 2: Place this single <div> anywhere in your legacy page.
         class="dx-root" scopes all DX-Engine styles — no conflicts.
         ================================================================ -->
    <div id="dx-admission" class="dx-root">
      <!-- DX-Engine renders the full multi-step form here -->
    </div>

  </main>
</div><!-- /d-flex -->

<!-- EXISTING LEGACY SCRIPTS ─────────────────────────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!--
  STEP 3 — Load the DX-Engine interpreter and initialise.
  No other JS changes are needed anywhere in this legacy page.
-->
<script src="../public/js/dx-interpreter.js"></script>
<script>
  /**
   * Create a DXInterpreter instance bound to the #dx-admission div.
   * This is the ONLY code you need to add to any legacy page.
   */
  const admissionDX = new DXInterpreter('#dx-admission', {
    dx_id   : 'admission_case',
    endpoint: '../public/api/dx.php',

    // Edit mode — pass admission_id to pre-populate every field from the DB:
    // params: { admission_id: <?php echo (int)($_GET['edit'] ?? 0); ?> },

    onComplete: function (data) {
      // `data` is the `data` key from the PHP postProcess success response.
      // Here we bridge back to the legacy app's own flow:
      alert('Admission #' + (data.admission_id || '?') + ' registered successfully.');
      // window.location.href = '/legacy/admissions/view.php?id=' + data.admission_id;
    },

    successTitle: 'Admission Registered',
    resetLabel  : 'Add Another Patient'
  });

  // Start the interpreter — fetches JSON from PHP and renders Step 1.
  admissionDX.load();
</script>

</body>
</html>
