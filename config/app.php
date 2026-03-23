<?php
/**
 * DX-Engine — Application Configuration
 * =============================================================================
 * Returns a plain array consumed by public/api/dx.php.
 *
 * Dynamic base-URL detection
 * --------------------------
 * Works without any manual configuration whether the project lives at:
 *   http://localhost/dx-engine/    (XAMPP subfolder — typical dev setup)
 *   http://localhost/              (document root)
 *   https://your-hospital.com/    (production server)
 *
 * How it works:
 *   1. Reads the HTTP scheme from $_SERVER['HTTPS'].
 *   2. Reads the host from HTTP_HOST or SERVER_NAME.
 *   3. Derives the project sub-path by comparing __FILE__ to DOCUMENT_ROOT.
 *      __FILE__  = C:/xampp/htdocs/dx-engine/config/app.php
 *      DOCUMENT_ROOT = C:/xampp/htdocs
 *      sub-path  = /dx-engine/config/app.php
 *      project   = /dx-engine  (strip filename + /config segment)
 *   4. Result:  http://localhost/dx-engine
 *
 * The DX_API_ENDPOINT is always constructed as:
 *   {base}/public/api/dx.php
 *
 * Override with APP_URL or DX_ENDPOINT environment variables.
 * =============================================================================
 */

$_base = (function (): string {

    // ── Environment variable wins (use in production / Docker) ───────────────
    if (!empty($_ENV['APP_URL'])) {
        return rtrim($_ENV['APP_URL'], '/');
    }

    // ── CLI mode (migration scripts, cron jobs) ───────────────────────────────
    if (PHP_SAPI === 'cli') {
        return 'http://localhost';
    }

    // ── HTTP mode: derive from server globals ─────────────────────────────────
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');

    // Normalise both paths to forward slashes for reliable string comparison.
    $docRoot  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $thisFile = str_replace('\\', '/', __FILE__);   // this config/app.php

    if ($docRoot !== '' && str_starts_with($thisFile, $docRoot)) {
        // Derive sub-path: strip doc root prefix, then strip /config/app.php.
        $sub = substr($thisFile, strlen($docRoot));   // → /dx-engine/config/app.php
        $sub = dirname(dirname($sub));                // → /dx-engine
        $sub = rtrim($sub, '/');
    } else {
        // Edge case: doc root mismatch (symlinks, virtual hosts).
        $sub = '';
    }

    return "{$scheme}://{$host}{$sub}";
})();

$_endpoint = $_ENV['DX_ENDPOINT'] ?? ($_base . '/public/api/dx.php');

return [

    /*
    |--------------------------------------------------------------------------
    | Application
    |--------------------------------------------------------------------------
    */
    'name'    => $_ENV['APP_NAME']  ?? 'DX-Engine',
    'env'     => $_ENV['APP_ENV']   ?? 'production',   // production | development
    'debug'   => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
    'url'     => $_base,

    /*
    |--------------------------------------------------------------------------
    | DX API Endpoint
    | This URL is embedded into every JSON Metadata Bridge payload as
    | post_endpoint.  The JS interpreter reads it and POST's step submissions
    | to this address.
    |--------------------------------------------------------------------------
    */
    'dx_api_endpoint' => $_endpoint,

    /*
    |--------------------------------------------------------------------------
    | Session
    |--------------------------------------------------------------------------
    */
    'session_name'    => 'DXSID',
    'session_lifetime'=> 7200,

    /*
    |--------------------------------------------------------------------------
    | CORS
    |--------------------------------------------------------------------------
    */
    'cors_origins' => explode(',', $_ENV['CORS_ORIGINS'] ?? '*'),

];
