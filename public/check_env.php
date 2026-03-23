<?php
/**
 * DX-Engine — Environment & Database Diagnostic Tool
 * =============================================================================
 * Hit this URL in your browser to run a full pre-flight check:
 *
 *   http://localhost/dx-engine/public/check_env.php
 *
 * REMOVE OR RESTRICT THIS FILE BEFORE GOING LIVE.
 * It exposes server paths, PHP config, and DB credentials.
 * =============================================================================
 */

declare(strict_types=1);

// ── Always show all errors here (this is a dev-only diagnostic file) ─────────
ini_set('display_errors', '1');
ini_set('log_errors',     '1');
error_reporting(E_ALL);

header('Content-Type: text/html; charset=UTF-8');

// ─────────────────────────────────────────────────────────────────────────────
//  Helpers
// ─────────────────────────────────────────────────────────────────────────────

function pass(string $label, string $detail = ''): void
{
    echo '<tr><td class="pass">&#10003; PASS</td><td><strong>' . e($label) . '</strong>'
       . ($detail ? '<br><small>' . e($detail) . '</small>' : '') . '</td></tr>';
}

function fail(string $label, string $detail = ''): void
{
    echo '<tr><td class="fail">&#10007; FAIL</td><td><strong>' . e($label) . '</strong>'
       . ($detail ? '<br><small class="err">' . e($detail) . '</small>' : '') . '</td></tr>';
}

function info(string $label, string $detail = ''): void
{
    echo '<tr><td class="info">&#9432; INFO</td><td><strong>' . e($label) . '</strong>'
       . ($detail ? '<br><small>' . e($detail) . '</small>' : '') . '</td></tr>';
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function section(string $title): void
{
    echo '<tr><th colspan="2" class="section">' . e($title) . '</th></tr>';
}

// ─────────────────────────────────────────────────────────────────────────────
//  Compute DX_ROOT
// ─────────────────────────────────────────────────────────────────────────────
//  check_env.php lives in /dx-engine/public/ so:
//    dirname(__FILE__)    →  /dx-engine/public
//    dirname(__FILE__, 2) →  /dx-engine            ← DX_ROOT
//
$DX_ROOT = dirname(__FILE__, 2);

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DX-Engine — Environment Check</title>
<style>
  body  { font-family: monospace; font-size: 14px; background:#0f0f0f; color:#e0e0e0; margin:0; padding:20px; }
  h1    { color:#58a6ff; border-bottom:1px solid #333; padding-bottom:8px; }
  table { border-collapse:collapse; width:100%; max-width:900px; }
  th, td{ padding:7px 12px; border:1px solid #333; vertical-align:top; }
  th.section { background:#1c1c1c; color:#58a6ff; font-size:13px; text-transform:uppercase;
               letter-spacing:1px; text-align:left; }
  td:first-child { width:90px; text-align:center; font-weight:bold; }
  .pass { color:#3fb950; } .fail { color:#f85149; } .info { color:#d29922; }
  small { color:#8b949e; } small.err { color:#f85149; }
  pre   { background:#161616; padding:10px; border-radius:4px; overflow:auto;
          font-size:12px; color:#c9d1d9; margin:0; }
</style>
</head>
<body>
<h1>DX-Engine — Environment Check</h1>
<p style="color:#8b949e">DX_ROOT resolved to: <code><?= e($DX_ROOT) ?></code></p>
<table>

<?php

// ═════════════════════════════════════════════════════════════════════════════
//  1. PHP VERSION
// ═════════════════════════════════════════════════════════════════════════════
section('1. PHP Version');

$phpVersion = PHP_VERSION_ID;
if ($phpVersion >= 80100) {
    pass('PHP ' . PHP_VERSION, 'PHP 8.1+ — full feature support.');
} elseif ($phpVersion >= 80000) {
    pass('PHP ' . PHP_VERSION, 'PHP 8.0 — minimum supported version. Upgrade to 8.1+ recommended.');
} else {
    fail('PHP ' . PHP_VERSION, 'DX-Engine requires PHP 8.0+. In XAMPP: open php.ini, check phpXX folder is active, restart Apache.');
}

// PDO extension
if (extension_loaded('pdo') && extension_loaded('pdo_mysql')) {
    pass('PDO + PDO_MySQL loaded');
} else {
    fail('PDO / PDO_MySQL missing', 'Enable extension=pdo_mysql in php.ini and restart Apache.');
}

// JSON extension
if (extension_loaded('json')) {
    pass('JSON extension loaded');
} else {
    fail('JSON extension missing');
}

// ═════════════════════════════════════════════════════════════════════════════
//  2. DIRECTORY STRUCTURE
// ═════════════════════════════════════════════════════════════════════════════
section('2. Directory & File Structure');

$requiredPaths = [
    'src/Core/Autoloader.php'             => 'PSR-4 Autoloader',
    'src/Core/DataModel.php'              => 'ORM base class',
    'src/Core/DXController.php'           => 'DX orchestrator',
    'src/Core/Router.php'                 => 'HTTP router',
    'src/Core/Helpers.php'                => 'Utility helpers',
    'app/DX/AdmissionDX.php'              => 'Admission DX controller',
    'app/Models/PatientModel.php'         => 'Patient model',
    'app/Models/AdmissionModel.php'       => 'Admission model',
    'app/Models/DepartmentModel.php'      => 'Department model',
    'app/Models/InsuranceModel.php'       => 'Insurance model',
    'config/app.php'                      => 'App config',
    'config/database.php'                 => 'Database config',
    'public/api/dx.php'                   => 'API entry point',
    'public/js/dx-interpreter.js'         => 'Frontend interpreter',
    'database/migrations/001_create_tables.sql'  => 'Migration 001',
    'database/migrations/002_master_sync.sql'    => 'Migration 002 (sync)',
];

// Confirm no duplicate Core files exist in /app/Core/
$staleFiles = [
    'app/Core/DataModel.php'    => 'Duplicate DataModel — causes "Cannot redeclare class" fatal',
    'app/Core/DXController.php' => 'Duplicate DXController — causes "Cannot redeclare class" fatal',
    'app/Core/Router.php'       => 'Duplicate Router — causes "Cannot redeclare class" fatal',
];

foreach ($requiredPaths as $rel => $desc) {
    $abs = $DX_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (is_file($abs)) {
        pass($rel, $desc);
    } else {
        fail($rel . ' — NOT FOUND', $desc . " | Expected at: {$abs}");
    }
}

foreach ($staleFiles as $rel => $desc) {
    $abs = $DX_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (is_file($abs)) {
        fail($rel . ' — STALE DUPLICATE EXISTS', $desc);
    } else {
        pass($rel . ' — correctly absent', 'No duplicate Core file in /app/Core/.');
    }
}

// ═════════════════════════════════════════════════════════════════════════════
//  3. AUTOLOADER
// ═════════════════════════════════════════════════════════════════════════════
section('3. Autoloader');

$autoloaderPath = $DX_ROOT . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Autoloader.php';

if (is_file($autoloaderPath)) {
    try {
        require_once $autoloaderPath;
        \DXEngine\Core\Autoloader::register(
            $DX_ROOT . DIRECTORY_SEPARATOR . 'src',
            $DX_ROOT . DIRECTORY_SEPARATOR . 'app'
        );
        pass('Autoloader registered');

        $ns = \DXEngine\Core\Autoloader::registeredNamespaces();
        foreach ($ns as $prefix => $dir) {
            info("Namespace: {$prefix}", "→ {$dir}");
        }
    } catch (\Throwable $e) {
        fail('Autoloader threw an exception', $e->getMessage());
    }
} else {
    fail('Autoloader not found', $autoloaderPath);
}

// Test that each key class can be resolved
$classTests = [
    'DXEngine\Core\DataModel'      => 'Core ORM base',
    'DXEngine\Core\DXController'   => 'Core DX controller',
    'DXEngine\Core\Router'         => 'Core router',
    'App\DX\AdmissionDX'           => 'Admission DX',
    'App\Models\PatientModel'      => 'Patient model',
    'App\Models\AdmissionModel'    => 'Admission model',
    'App\Models\DepartmentModel'   => 'Department model',
    'App\Models\InsuranceModel'    => 'Insurance model',
];

foreach ($classTests as $fqcn => $desc) {
    try {
        // Use class_exists with autoload=true — triggers the SPL callback
        if (class_exists($fqcn, true)) {
            pass("class_exists: {$fqcn}", $desc);
        } else {
            fail("class_exists: {$fqcn} returned false", $desc);
        }
    } catch (\Throwable $e) {
        fail("class_exists: {$fqcn} threw", $e->getMessage());
    }
}

// ═════════════════════════════════════════════════════════════════════════════
//  4. DATABASE CONNECTION
// ═════════════════════════════════════════════════════════════════════════════
section('4. Database Connection');

$dbConfigPath = $DX_ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';

if (!is_file($dbConfigPath)) {
    fail('config/database.php not found', $dbConfigPath);
} else {
    try {
        $pdo = require $dbConfigPath;

        if ($pdo instanceof PDO) {
            pass('PDO connection established');

            // Show which DB we connected to
            $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
            info('Connected database', (string) $dbName);

            // ── Table existence checks ────────────────────────────────────
            $expectedTables = ['departments', 'patients', 'admissions', 'insurance_details'];

            foreach ($expectedTables as $table) {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM information_schema.tables
                     WHERE table_schema = DATABASE() AND table_name = ?"
                );
                $stmt->execute([$table]);
                $exists = (int) $stmt->fetchColumn();

                if ($exists) {
                    // Get row count
                    $count = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
                    pass("Table `{$table}` exists", "{$count} row(s)");
                } else {
                    fail(
                        "Table `{$table}` NOT FOUND",
                        "Run database/migrations/002_master_sync.sql in phpMyAdmin."
                    );
                }
            }

            // ── Column alignment spot-check ───────────────────────────────
            $columnChecks = [
                'patients'          => ['first_name','last_name','date_of_birth','gender','contact_phone','contact_email','address'],
                'admissions'        => ['patient_id','department_id','triage_level','chief_complaint','attending_physician','status','notes'],
                'departments'       => ['code','name','is_active'],
                'insurance_details' => ['admission_id','provider_name','policy_number','group_number','holder_name','holder_dob','coverage_type','expiry_date'],
            ];

            foreach ($columnChecks as $table => $expectedCols) {
                try {
                    $stmt   = $pdo->query("DESCRIBE `{$table}`");
                    $actual = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
                    $missing = array_diff($expectedCols, $actual);

                    if (empty($missing)) {
                        pass("Column alignment: `{$table}`", implode(', ', $expectedCols));
                    } else {
                        fail(
                            "Column alignment: `{$table}` — MISSING COLUMNS",
                            'Missing: ' . implode(', ', $missing)
                            . " — Re-run 002_master_sync.sql."
                        );
                    }
                } catch (\Throwable $e) {
                    fail("Could not describe `{$table}`", $e->getMessage());
                }
            }

            // ── Department seed check ─────────────────────────────────────
            try {
                $deptCount = (int) $pdo->query("SELECT COUNT(*) FROM departments WHERE is_active = 1")->fetchColumn();
                if ($deptCount > 0) {
                    pass("departments seed data present", "{$deptCount} active department(s)");
                } else {
                    fail('departments table is empty',
                         'Run 002_master_sync.sql to seed department records.');
                }
            } catch (\Throwable $e) {
                fail('Could not query departments', $e->getMessage());
            }

        } else {
            fail('config/database.php did not return a PDO instance', gettype($pdo));
        }
    } catch (\RuntimeException $e) {
        fail('Database connection failed', $e->getMessage());
    } catch (\Throwable $e) {
        fail('Database exception', get_class($e) . ': ' . $e->getMessage());
    }
}

// ═════════════════════════════════════════════════════════════════════════════
//  5. API ENDPOINT SMOKE TEST
// ═════════════════════════════════════════════════════════════════════════════
section('5. API Endpoint Smoke Test');

// Derive the URL for the API endpoint from the current request
$scheme   = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$apiUrl   = $scheme . '://' . $host . '/dx-engine/public/api/dx.php?dx=admission_case';

info('API URL to test', $apiUrl);

// Use file_get_contents with a stream context (available on most XAMPP installs)
$ctx = stream_context_create([
    'http' => [
        'timeout'        => 10,
        'ignore_errors'  => true,   // Don't throw on 4xx/5xx
    ],
]);

$body = @file_get_contents($apiUrl, false, $ctx);

if ($body === false) {
    fail('Cannot reach API endpoint', "file_get_contents failed. Check Apache is running.");
} else {
    $decoded = json_decode($body, true);

    if ($decoded === null) {
        fail('API returned non-JSON', substr($body, 0, 300));
    } elseif (($decoded['_status'] ?? '') === 'ok') {
        pass('API GET returned status: ok', 'DX flow loaded successfully.');
        info('dx_id in response', $decoded['dx_id'] ?? '(missing)');
        info('Steps returned', (string) count($decoded['steps'] ?? []));
    } elseif (($decoded['status'] ?? '') === 'error') {
        fail('API returned error', $decoded['message'] ?? 'No message');
        if (isset($decoded['debug'])) {
            echo '<tr><td colspan="2"><pre>' . e(json_encode($decoded['debug'], JSON_PRETTY_PRINT)) . '</pre></td></tr>';
        }
    } else {
        info('API returned unexpected shape', substr($body, 0, 500));
    }
}

// ═════════════════════════════════════════════════════════════════════════════
//  6. CONFIG REVIEW
// ═════════════════════════════════════════════════════════════════════════════
section('6. Configuration Values');

$appConfigPath = $DX_ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
if (is_file($appConfigPath)) {
    try {
        $cfg = require $appConfigPath;
        info('dx_api_endpoint', $cfg['dx_api_endpoint'] ?? '(not set)');
        info('app.debug flag',  ($cfg['debug'] ?? false) ? 'true (debug ON)' : 'false (debug OFF)');
    } catch (\Throwable $e) {
        fail('config/app.php threw', $e->getMessage());
    }
} else {
    fail('config/app.php not found');
}

?>
</table>

<p style="color:#555; margin-top:20px; font-size:11px">
  REMOVE <code>check_env.php</code> before deploying to a production server.
</p>
</body>
</html>
