<?php
/**
 * DX-Engine — Environment & Autoloader Diagnostics
 * =============================================================================
 * Run this ONCE to verify that:
 *   1. PHP version and required extensions are present
 *   2. The Autoloader resolves every namespace to a real file
 *   3. PDO can connect to MySQL with the credentials in config/database.php
 *   4. The /public/api/dx.php bootstrap path is reachable from CLI
 *
 * Usage (XAMPP):
 *   Visit:  http://localhost/dx-engine/check_env.php
 *   OR run: php check_env.php   (from the dx-engine/ directory)
 *
 * DELETE or move this file outside the web root before going to production.
 * =============================================================================
 */

declare(strict_types=1);

// ── Helpers ──────────────────────────────────────────────────────────────────

function row(string $label, bool $ok, string $detail = ''): void
{
    $icon    = $ok ? '&#10003;' : '&#10007;';
    $colour  = $ok ? '#16a34a'  : '#dc2626';
    $detHtml = $detail ? "<br><small style='color:#6b7280;word-break:break-all'>{$detail}</small>" : '';
    echo "<tr>
            <td style='padding:6px 10px'>{$label}</td>
            <td style='padding:6px 10px;color:{$colour};font-weight:700'>{$icon} " .
            ($ok ? 'OK' : 'FAIL') . "</td>
            <td style='padding:6px 10px'>{$detHtml}</td>
          </tr>\n";
}

// ── Determine absolute root (works whether called via HTTP or CLI) ────────────

$root = __DIR__;   // dx-engine/

// ── Header ───────────────────────────────────────────────────────────────────

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    header('Content-Type: text/html; charset=UTF-8');
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <title>DX-Engine — Environment Check</title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc;
               color: #1e293b; margin: 0; padding: 2rem; }
        h1   { font-size: 1.25rem; font-weight: 700; margin: 0 0 .25rem; }
        p    { color: #64748b; margin: 0 0 1.5rem; font-size: .875rem; }
        table { border-collapse: collapse; width: 100%; max-width: 860px;
                background: #fff; border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,.1); font-size: .875rem; }
        th   { background: #f1f5f9; text-align: left;
               padding: 8px 10px; font-weight: 600; }
        tr:nth-child(even) td { background: #f8fafc; }
        .section { font-size: .7rem; text-transform: uppercase;
                   letter-spacing: .07em; color: #94a3b8;
                   font-weight: 700; padding: 10px 10px 4px; }
        .warn { color: #b45309; font-weight: 700; }
      </style>
    </head>
    <body>
    <h1>DX-Engine &mdash; Environment Diagnostics</h1>
    <p>Results as of <?= date('Y-m-d H:i:s') ?> &nbsp;|&nbsp;
       <strong>Delete this file before deploying to production.</strong></p>
    <table>
      <colgroup>
        <col style="width:30%"><col style="width:10%"><col>
      </colgroup>
      <thead>
        <tr><th>Check</th><th>Result</th><th>Detail</th></tr>
      </thead>
      <tbody>
    HTML;
}

// ============================================================================
// SECTION 1 — PHP Environment
// ============================================================================

if (!$isCli) echo "<tr><td colspan='3' class='section'>1. PHP Environment</td></tr>\n";

// PHP version
$phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
row('PHP Version ≥ 8.0', $phpOk, 'Running: ' . PHP_VERSION);

// Required extensions
foreach (['pdo', 'pdo_mysql', 'mbstring', 'json', 'session'] as $ext) {
    $loaded = extension_loaded($ext);
    row("ext/{$ext}", $loaded, $loaded ? 'Loaded' : 'NOT loaded — enable in php.ini');
}

// OS path separator awareness
$sep = DIRECTORY_SEPARATOR;
row('DIRECTORY_SEPARATOR', true, "Value: '{$sep}' (Windows='\\', Linux='/')");

// ============================================================================
// SECTION 2 — Directory Structure
// ============================================================================

if (!$isCli) echo "<tr><td colspan='3' class='section'>2. Required Directories &amp; Files</td></tr>\n";

$required = [
    'src/Core/Autoloader.php',
    'src/Core/DXController.php',
    'src/Core/DataModel.php',
    'src/Core/Router.php',
    'src/Core/Helpers.php',
    'app/DX/AdmissionDX.php',
    'app/Models/PatientModel.php',
    'app/Models/AdmissionModel.php',
    'app/Models/DepartmentModel.php',
    'app/Models/InsuranceModel.php',
    'config/app.php',
    'config/database.php',
    'public/api/dx.php',
    'public/js/dx-interpreter.js',
    'public/css/dx-engine.css',
];

foreach ($required as $rel) {
    $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $ok  = file_exists($abs);
    row($rel, $ok, $ok ? realpath($abs) : "NOT FOUND — expected: {$abs}");
}

// ============================================================================
// SECTION 3 — Autoloader Namespace Resolution
// ============================================================================

if (!$isCli) echo "<tr><td colspan='3' class='section'>3. Autoloader Namespace Resolution</td></tr>\n";

$autoloaderFile = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Autoloader.php';

if (!file_exists($autoloaderFile)) {
    row('Autoloader.php found', false, $autoloaderFile);
} else {
    require_once $autoloaderFile;

    try {
        \DXEngine\Core\Autoloader::register(
            $root . DIRECTORY_SEPARATOR . 'src',
            $root . DIRECTORY_SEPARATOR . 'app'
        );
        row('Autoloader::register()', true, "src → {$root}/src  |  app → {$root}/app");
    } catch (\Throwable $e) {
        row('Autoloader::register()', false, $e->getMessage());
    }

    // Test each class resolution
    $classes = [
        'DXEngine\\Core\\DXController' => 'src/Core/DXController.php',
        'DXEngine\\Core\\DataModel'    => 'src/Core/DataModel.php',
        'DXEngine\\Core\\Router'       => 'src/Core/Router.php',
        'DXEngine\\Core\\Helpers'      => 'src/Core/Helpers.php',
        'App\\DX\\AdmissionDX'         => 'app/DX/AdmissionDX.php',
        'App\\Models\\PatientModel'    => 'app/Models/PatientModel.php',
        'App\\Models\\AdmissionModel'  => 'app/Models/AdmissionModel.php',
        'App\\Models\\DepartmentModel' => 'app/Models/DepartmentModel.php',
        'App\\Models\\InsuranceModel'  => 'app/Models/InsuranceModel.php',
    ];

    foreach ($classes as $fqcn => $expectedRel) {
        // Build the expected file path the same way Autoloader::load() does —
        // handles Windows backslash vs Linux forward slash correctly.
        $prefix  = strpos($fqcn, 'DXEngine\\') === 0 ? 'DXEngine\\' : 'App\\';
        $baseDir = $prefix === 'DXEngine\\' ? ($root . DIRECTORY_SEPARATOR . 'src') : ($root . DIRECTORY_SEPARATOR . 'app');
        $rel     = substr($fqcn, strlen($prefix));
        $file    = $baseDir . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $rel) . '.php';

        $exists = file_exists($file);
        row(
            "Resolves: {$fqcn}",
            $exists,
            $exists
                ? realpath($file)
                : "Expected: {$file}"
        );
    }
}

// ============================================================================
// SECTION 4 — Database Connection
// ============================================================================

if (!$isCli) echo "<tr><td colspan='3' class='section'>4. Database Connection</td></tr>\n";

$dbConfigFile = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';

if (!file_exists($dbConfigFile)) {
    row('config/database.php', false, 'File not found');
} else {
    try {
        $pdo = require $dbConfigFile;
        if ($pdo instanceof PDO) {
            // Grab server version
            $version = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            row('PDO MySQL connection', true, "MySQL server: {$version}");

            // Check if the dx_engine database exists (or whichever DB is configured)
            $stmt = $pdo->query('SELECT DATABASE() AS db');
            $db   = $stmt->fetchColumn();
            row('Active database', !empty($db), "Database: {$db}");

            // Check for expected tables
            foreach (['dx_patients', 'dx_admissions', 'dx_insurance', 'dx_departments'] as $tbl) {
                $check = $pdo->query("SHOW TABLES LIKE '{$tbl}'")->fetch();
                $found = $check !== false;
                row("Table: {$tbl}", $found, $found ? 'Exists' : "Not found — run database/migrations/001_create_tables.sql");
            }
        } else {
            row('PDO instance returned', false, 'database.php did not return a PDO object');
        }
    } catch (\PDOException $e) {
        row('PDO MySQL connection', false, $e->getMessage());
        $hint = '';
        if (str_contains($e->getMessage(), 'Access denied')) {
            $hint = 'Hint: Check DB_USERNAME and DB_PASSWORD in config/database.php (XAMPP default: root / empty password).';
        } elseif (str_contains($e->getMessage(), "Can't connect")) {
            $hint = 'Hint: Ensure MySQL is started in the XAMPP Control Panel.';
        } elseif (str_contains($e->getMessage(), 'Unknown database')) {
            $hint = 'Hint: Create the database first — run: CREATE DATABASE dx_engine; in phpMyAdmin.';
        }
        if ($hint) row('Connection hint', false, $hint);
    } catch (\Throwable $e) {
        row('Database config load', false, $e->getMessage());
    }
}

// ============================================================================
// SECTION 5 — HTTP / URL Reachability (HTTP mode only)
// ============================================================================

if (!$isCli) {
    echo "<tr><td colspan='3' class='section'>5. Base URL &amp; Endpoint Detection</td></tr>\n";

    // Detect base URL dynamically — the same logic used in config/app.php
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $self     = $_SERVER['PHP_SELF'] ?? '/dx-engine/check_env.php';
    // Strip everything from check_env.php onward
    $basePath = rtrim(dirname($self), '/\\');
    $baseUrl  = "{$scheme}://{$host}{$basePath}";

    row('Detected base URL', true, $baseUrl);
    row('DX API endpoint', true, "{$baseUrl}/public/api/dx.php?dx=admission_case");

    // Check .htaccess files exist
    foreach (['.htaccess', 'public/.htaccess'] as $htFile) {
        $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $htFile);
        row($htFile, file_exists($abs), file_exists($abs) ? realpath($abs) : 'Missing — create it (see XAMPP Fix Guide)');
    }

    // Check mod_rewrite is loaded (Apache)
    $modRw = function_exists('apache_get_modules')
        ? in_array('mod_rewrite', apache_get_modules(), true)
        : null;

    if ($modRw === null) {
        row('mod_rewrite', true, 'Cannot detect (non-Apache or Apache in CGI mode) — assumed OK');
    } else {
        row('mod_rewrite enabled', $modRw, $modRw ? 'Loaded' : 'NOT loaded — enable in httpd.conf: LoadModule rewrite_module modules/mod_rewrite.so');
    }

    $modHeaders = function_exists('apache_get_modules')
        ? in_array('mod_headers', apache_get_modules(), true)
        : null;
    if ($modHeaders !== null) {
        row('mod_headers enabled', $modHeaders, $modHeaders ? 'Loaded' : 'NOT loaded — enable in httpd.conf: LoadModule headers_module modules/mod_headers.so');
    }
}

// ============================================================================
// Footer
// ============================================================================

if (!$isCli) {
    echo <<<HTML
      </tbody>
    </table>
    <p style="margin-top:1rem;font-size:.8rem;color:#94a3b8">
      <strong class="warn">Security note:</strong>
      Remove or rename this file (<code>check_env.php</code>) before
      deploying to any publicly accessible server.
    </p>
    </body></html>
    HTML;
} else {
    echo "\nDone. Review any FAIL rows above.\n";
    echo "Security note: remove check_env.php before going to production.\n\n";
}
