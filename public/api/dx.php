<?php
declare(strict_types=1);
/**
 * DX-Engine — API Entry Point
 * =============================================================================
 * All DX requests hit this file.
 * GET  /dx-engine/public/api/dx.php?dx=admission  →  returns Metadata Bridge JSON
 * POST /dx-engine/public/api/dx.php?dx=admission  →  processes a step submission
 *
 * Directory structure (single src/ root — no separate app/):
 *   dx-engine/
 *   ├── src/
 *   │   ├── Core/           DXEngine\Core\*   (Autoloader, DataModel, DXController …)
 *   │   └── App/
 *   │       ├── Models/     DXEngine\App\Models\*
 *   │       └── DX/         DXEngine\App\DX\*
 *   ├── config/             app.php, database.php
 *   └── public/
 *       ├── api/            ← this file
 *       └── js/             dx-interpreter.js
 *
 * DEBUG MODE
 * ----------
 * Set $forceDebug = true to receive the real PHP error in the JSON response
 * instead of a generic "internal server error" message.
 * NEVER leave $forceDebug = true in production.
 * =============================================================================
 */

// ── 0. Debug switch ───────────────────────────────────────────────────────────
$forceDebug = false;   // keep production-safe default; app config/env can enable debug

// ── 1. Fatal-error capture (runs BEFORE the try-catch can fire) ───────────────
//
// Catches: parse errors, class-not-found fatals, PHP-version mismatches.
// Without this, Apache returns a blank 500 HTML page instead of JSON.
//
register_shutdown_function(function () use ($forceDebug): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
        }
        $payload = ['status' => 'error', 'message' => 'Fatal PHP error.'];
        if ($forceDebug) {
            $payload['debug'] = [
                'type'    => $err['type'],
                'message' => $err['message'],
                'file'    => str_replace('\\', '/', $err['file']),
                'line'    => $err['line'],
            ];
        }
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
});

// Convert PHP warnings/notices into catchable ErrorExceptions.
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    if (error_reporting() & $errno) {
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
    return false;
});

// Error display settings: never mix HTML errors into the JSON body.
ini_set('display_errors', '0');
ini_set('log_errors',     '1');
error_reporting($forceDebug ? E_ALL : E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// ── 2. PHP version guard ──────────────────────────────────────────────────────
if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'status'  => 'error',
        'message' => 'DX-Engine requires PHP 8.0+. Running: ' . PHP_VERSION
                     . '. Update PHP in the XAMPP Control Panel.',
    ]);
    exit;
}

// ── 3. DX_ROOT — absolute path to the dx-engine project root ─────────────────
//
// __FILE__               →  /htdocs/dx-engine/public/api/dx.php
// dirname(__FILE__, 1)   →  /htdocs/dx-engine/public/api
// dirname(__FILE__, 2)   →  /htdocs/dx-engine/public
// dirname(__FILE__, 3)   →  /htdocs/dx-engine            ← DX_ROOT
//
// Works regardless of whether the project is in the document root or a
// XAMPP subfolder (/dx-engine/, /myapp/, etc.).
//
if (!defined('DX_ROOT')) {
    define('DX_ROOT', dirname(__FILE__, 3));
}

// ── 4. Main try-catch ─────────────────────────────────────────────────────────
$config = [];   // pre-declare so catch block can read $config['debug']
try {

    // ── 4a. Autoloader ────────────────────────────────────────────────────────
    //
    // The Autoloader lives at src/Core/Autoloader.php and must be the ONLY
    // file that is manually require'd.  After register() is called, every other
    // class is loaded on-demand by PHP's SPL mechanism.
    //
    $autoloaderFile = DX_ROOT
        . DIRECTORY_SEPARATOR . 'src'
        . DIRECTORY_SEPARATOR . 'Core'
        . DIRECTORY_SEPARATOR . 'Autoloader.php';

    if (!is_file($autoloaderFile)) {
        throw new \RuntimeException(
            "Autoloader not found at: {$autoloaderFile}\n"
            . "DX_ROOT resolved to: " . DX_ROOT . "\n"
            . "Ensure this file is at: dx-engine/src/Core/Autoloader.php"
        );
    }

    require_once $autoloaderFile;

    // Single call — registers DXEngine\ → src/
    // Resolves:
    //   DXEngine\Core\*             →  src/Core/*.php
    //   DXEngine\App\Models\*       →  src/App/Models/*.php
    //   DXEngine\App\DX\*           →  src/App/DX/*.php
    \DXEngine\Core\Autoloader::register(
        DX_ROOT . DIRECTORY_SEPARATOR . 'src'
    );

    // ── 4b. Session ───────────────────────────────────────────────────────────
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('DXSID');
        session_start();
    }

    // ── 4c. App config ────────────────────────────────────────────────────────
    $configFile = DX_ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
    if (!is_file($configFile)) {
        throw new \RuntimeException("Config not found: {$configFile}");
    }
    $config = require $configFile;

    // Let DX controllers read the endpoint from $_SERVER['DX_API_ENDPOINT'].
    // This avoids passing $config around explicitly.
    $_SERVER['DX_API_ENDPOINT'] = $config['dx_api_endpoint'];

    if ($forceDebug) {
        $config['debug'] = true;
    }

    // ── 4d. Database ──────────────────────────────────────────────────────────
    $dbFile = DX_ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
    if (!is_file($dbFile)) {
        throw new \RuntimeException("Database config not found: {$dbFile}");
    }

    // database.php is an IIFE that returns a live PDO instance or throws.
    $pdo = require $dbFile;
    \DXEngine\Core\DataModel::boot($pdo);

    // ── 4e. Router — register DX controllers ─────────────────────────────────
    //
    // KEY   = the ?dx= query-string value used in the URL
    // VALUE = the fully-qualified DXController subclass
    //
    // The Autoloader resolves DXEngine\App\DX\AdmissionDX to:
    //   src/App/DX/AdmissionDX.php
    //
    $router = new \DXEngine\Core\Router();

    // Canonical route used by the JS interpreter and new integrations.
    $router->register('admission',      \DXEngine\App\DX\AdmissionDX::class);

    // Legacy alias — maps ?dx=admission_case to the same controller.
    // Eliminates the "Unknown DX: admission_case" 404 reported by check_env.php.
    // Any external caller, bookmark, or curl test using the old query-string
    // value will continue to work without modification.
    $router->register('admission_case', \DXEngine\App\DX\AdmissionDX::class);

    // Add more Digital Experiences here:
    // $router->register('discharge',   \DXEngine\App\DX\DischargeDX::class);
    // $router->register('lab_request', \DXEngine\App\DX\LabRequestDX::class);

    $router->dispatch($_GET['dx'] ?? '');

} catch (\Throwable $e) {

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
    }

    $debug = $config['debug'] ?? $forceDebug;

    $response = [
        'status'  => 'error',
        'message' => $debug
            ? $e->getMessage()
            : 'An internal server error occurred. Enable debug mode for details.',
    ];

    if ($debug) {
        $response['debug'] = [
            'exception' => get_class($e),
            'message'   => $e->getMessage(),
            'file'      => str_replace('\\', '/', $e->getFile()),
            'line'      => $e->getLine(),
            'dx_root'   => defined('DX_ROOT') ? str_replace('\\', '/', DX_ROOT) : 'not defined',
            'php'       => PHP_VERSION,
            'trace'     => array_slice(
                array_map(
                    fn($f) => str_replace('\\', '/', ($f['file'] ?? '?'))
                            . ':' . ($f['line'] ?? '?')
                            . '  ' . ($f['function'] ?? ''),
                    $e->getTrace()
                ),
                0, 12
            ),
        ];
    }

    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}
