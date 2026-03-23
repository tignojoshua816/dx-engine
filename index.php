<?php
/**
 * DX-Engine — Root Entry Point
 * =============================================================================
 * http://localhost/dx-engine/           →  redirects to admission form
 * http://localhost/dx-engine/?dx=...    →  redirects to the API (dev shortcut)
 *
 * On a production server this file can also serve as a simple router.
 * For now it forwards visitors to the Admission demo page so the project
 * works immediately after dropping the folder into htdocs.
 * =============================================================================
 */

declare(strict_types=1);

// If the URL contains ?dx=..., forward to the API directly (useful for testing
// the JSON feed from a browser tab without typing the full path).
if (!empty($_GET['dx'])) {
    $dxId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['dx']);
    header('Location: public/api/dx.php?dx=' . urlencode($dxId), true, 302);
    exit;
}

// Otherwise redirect to the Admission demo page.
header('Location: public/admission.php', true, 302);
exit;
