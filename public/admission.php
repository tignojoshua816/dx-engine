<?php
/**
 * DX-Engine — Patient Admission Page
 * ─────────────────────────────────────────────────────────────────────────────
 * Bootstrap:
 *   1. Loads the DX-Engine config (which auto-detects the base URL).
 *   2. Echoes the resolved dx_api_endpoint into the JS initialiser so the
 *      interpreter never uses a hardcoded subfolder path.
 *
 * XAMPP note:
 *   The Bootstrap CDN links below have NO integrity= or crossorigin= attributes.
 *   This is intentional — XAMPP's localhost environment does not send the
 *   CORS headers that browsers require to verify SRI hashes, causing the
 *   "Failed to find a valid digest" console error and blocking the stylesheet.
 *   SRI is a network-security feature; it provides no benefit on localhost.
 *   Re-add integrity= and crossorigin= when deploying to production.
 */

declare(strict_types=1);

// Load config so we can echo the dynamic API endpoint into JS below.
// DX_ROOT is defined here rather than in dx.php because admission.php is
// a standalone page that bootstraps itself.
if (!defined('DX_ROOT')) {
    define('DX_ROOT', dirname(__DIR__));  // /public/../  →  /dx-engine/
}

$config      = require DX_ROOT . '/config/app.php';
$apiEndpoint = htmlspecialchars($config['dx_api_endpoint'], ENT_QUOTES, 'UTF-8');
$editId      = (int) ($_GET['edit'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Patient Admission — DX-Engine</title>

  <!--
    Bootstrap 5.3.3 — CDN, NO integrity/crossorigin attributes.
    Reason: SRI hash verification requires the CDN to send CORS headers
    (Access-Control-Allow-Origin).  On localhost/XAMPP this fails, producing:
      "Failed to find a valid digest in the 'integrity' attribute… blocked."
    The correct SHA-384 hashes for Bootstrap 5.3.3 ARE:
      CSS: sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH
      JS:  sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmFU0NGgm/c1Bs7qAGRa0f0BBML6
    Re-add them (with crossorigin="anonymous") for production deployments.
  -->
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

  <!-- DX-Engine skin -->
  <link rel="stylesheet" href="css/dx-engine.css">

  <!-- Inter font (optional — remove if using system font) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap">

  <style>
    body {
      background: #f1f5f9;
      min-height: 100vh;
    }
    .page-header {
      background:    #1d4ed8;
      padding:       1rem 0;
      margin-bottom: 2rem;
    }
    .page-header .brand {
      color:       #fff;
      font-weight: 700;
      font-size:   1.1rem;
      letter-spacing: -0.02em;
    }
    .page-header .brand span {
      color:       #bfdbfe;
      font-weight: 400;
    }
    #dx-root {
      max-width: 720px;
      margin: 0 auto;
    }
  </style>
</head>
<body>

<!-- Page header -->
<header class="page-header">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between">
      <p class="brand mb-0">DX-Engine <span>/ Patient Admission</span></p>
      <span class="badge bg-white text-primary fw-semibold" style="font-size:.7rem;">
        SDUI Framework v1.0
      </span>
    </div>
  </div>
</header>

<main class="container pb-5">
  <!-- ================================================================
       DX Mount Point
       All rendering is handled by the JS interpreter.
       ================================================================ -->
  <div id="dx-root" class="dx-root">
    <!-- Interpreter renders here -->
  </div>
</main>

<!-- Bootstrap JS bundle — no integrity/crossorigin for localhost compatibility -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- DX-Engine Interpreter (class-based Vanilla JS — no framework required) -->
<script src="js/dx-interpreter.js"></script>

<script>
  /**
   * Mount the Admission Digital Experience into #dx-root.
   *
   * endpoint is resolved server-side by config/app.php and echoed here.
   * It automatically adapts to the folder the app is served from:
   *   localhost/             → http://localhost/public/api/dx.php
   *   localhost/dx-engine/   → http://localhost/dx-engine/public/api/dx.php
   *   https://my-server.com/ → https://my-server.com/public/api/dx.php
   */
  const admission = new DXInterpreter('#dx-root', {
    dx_id   : 'admission',
    endpoint: '<?= $apiEndpoint ?>',

    <?php if ($editId > 0): ?>
    // Edit mode — pre-populates every field from the DB via AdmissionDX::preProcess()
    params: { admission_id: <?= $editId ?> },
    <?php endif; ?>

    // CSRF token (uncomment for production):
    // csrf: '<?php // echo htmlspecialchars(\DXEngine\Core\Helpers::csrfToken()); ?>',

    onComplete(data) {
      // `data` contains { admission_id } from the final postProcess response.
      console.log('Admission registered:', data);
      // Redirect example:
       //window.location.href = '<?= htmlspecialchars($config['url'], ENT_QUOTES, 'UTF-8') ?>?admission_id=' + data.admission_id;
    },

    successTitle: 'Admission Complete',
    resetLabel  : 'Register Another Patient'
  });

  // Kick off the GET request to fetch the Metadata Bridge JSON
  admission.load();
</script>

</body>
</html>
