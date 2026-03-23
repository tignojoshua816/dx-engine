<?php
/**
 * DX-Engine — PSR-4 Autoloader
 * =============================================================================
 * Zero-dependency, PSR-4 compliant class loader.
 *
 * Default namespace map (registered by public/api/dx.php):
 *
 *   DXEngine\  →  {srcRoot}/          (covers DXEngine\Core\* and DXEngine\App\*)
 *   App\       →  {appRoot}/          (legacy fallback — app/ directory)
 *
 * Resolution examples with srcRoot = /htdocs/dx-engine/src :
 *
 *   DXEngine\Core\DataModel          →  src/Core/DataModel.php
 *   DXEngine\App\Models\PatientModel →  src/App/Models/PatientModel.php
 *   DXEngine\App\DX\AdmissionDX      →  src/App/DX/AdmissionDX.php
 *
 * The legacy App\ mapping is registered ONLY as a fallback alias that
 * redirects to src/App/ — so any code still using the old App\DX\AdmissionDX
 * class name continues to resolve correctly even though the app/ directory
 * has been removed.
 *
 * Windows / XAMPP compatibility:
 *   All path separators are normalised to DIRECTORY_SEPARATOR before any
 *   file_exists() / is_file() call, so the loader works identically on
 *   Windows (backslash) and Linux / macOS (forward slash).
 *
 * PHP 7.4 compatibility:
 *   str_starts_with() polyfill is defined below so the file is safe to
 *   require on PHP 7.4.  The version guard in dx.php halts before any
 *   PHP-8-only syntax is reached, but the autoloader must be self-contained.
 * =============================================================================
 */

// ── PHP 7.x polyfill ─────────────────────────────────────────────────────────
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

namespace DXEngine\Core;

class Autoloader
{
    /**
     * Registered namespace-prefix → base-directory mappings.
     * Stored with a trailing DIRECTORY_SEPARATOR for fast concatenation.
     *
     * Lookups happen in insertion order; the first matching prefix wins.
     *
     * @var array<string, string>   prefix => /abs/path/to/base/dir/
     */
    private static array $namespaces = [];

    /**
     * Register the SPL autoloader and add the default namespace roots.
     *
     * Signature accepted by dx.php:
     *
     *   \DXEngine\Core\Autoloader::register(DX_ROOT . '/src');
     *
     * This registers (in resolution order):
     *
     *   1.  DXEngine\  →  {srcRoot}/
     *          DXEngine\Core\DataModel          →  src/Core/DataModel.php
     *          DXEngine\App\Models\PatientModel →  src/App/Models/PatientModel.php
     *          DXEngine\App\DX\AdmissionDX      →  src/App/DX/AdmissionDX.php
     *
     *   2.  App\       →  {srcRoot}/App/          (legacy alias → src/App/)
     *          App\DX\AdmissionDX                →  src/App/DX/AdmissionDX.php
     *          App\Models\PatientModel           →  src/App/Models/PatientModel.php
     *
     * The App\ alias means code that still uses the old namespace string
     * (e.g. any cached autoload map or stale require) resolves correctly
     * without needing the deleted app/ directory on disk.
     *
     * @param string $srcRoot  Absolute path to the /src directory.
     */
    public static function register(string $srcRoot): void
    {
        $srcRoot = self::normalisePath($srcRoot);

        // ── Primary mapping: DXEngine\ → src/ ────────────────────────────────
        // PSR-4 sub-namespace resolution handles Core\ and App\ automatically.
        self::addNamespace('DXEngine\\', $srcRoot);

        // ── Legacy alias: App\ → src/App/ ────────────────────────────────────
        // Catches any remaining references to the old App\* namespace.
        // Points to src/App/ so both:
        //   DXEngine\App\Models\PatientModel  (preferred)
        //   App\Models\PatientModel           (legacy)
        // load the same physical file: src/App/Models/PatientModel.php
        self::addNamespace(
            'App\\',
            rtrim($srcRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'App'
        );

        // Register the SPL callback once — safe to call register() multiple
        // times (test bootstraps, etc.).
        static $registered = false;
        if (!$registered) {
            spl_autoload_register([self::class, 'load'], true, false);
            $registered = true;
        }
    }

    /**
     * Add an arbitrary namespace prefix → base directory mapping.
     *
     * Useful for third-party libraries outside src/:
     *   \DXEngine\Core\Autoloader::addNamespace('Vendor\\Lib\\', '/path/to/lib');
     *
     * @param string $prefix   Namespace prefix, e.g. 'Vendor\\Lib\\'.
     * @param string $baseDir  Absolute directory path.
     */
    public static function addNamespace(string $prefix, string $baseDir): void
    {
        $prefix  = rtrim($prefix, '\\') . '\\';
        $baseDir = rtrim(self::normalisePath($baseDir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        self::$namespaces[$prefix] = $baseDir;
    }

    /**
     * SPL autoload callback — invoked automatically by PHP when a class is
     * first used.
     *
     * PSR-4 resolution algorithm:
     *   1. Iterate registered prefixes in insertion order.
     *   2. If $class starts with the prefix, strip it → relative path.
     *   3. Replace namespace separator (\) with DIRECTORY_SEPARATOR.
     *   4. Append '.php' → absolute file path.
     *   5. require the file if it exists; continue to next prefix otherwise.
     *
     * Resolution examples (srcRoot = /htdocs/dx-engine/src):
     *
     *   DXEngine\Core\DataModel
     *     prefix = 'DXEngine\'   rel = 'Core\DataModel'
     *     file   = /htdocs/dx-engine/src/Core/DataModel.php
     *
     *   DXEngine\App\Models\PatientModel
     *     prefix = 'DXEngine\'   rel = 'App\Models\PatientModel'
     *     file   = /htdocs/dx-engine/src/App/Models/PatientModel.php
     *
     *   App\DX\AdmissionDX  (legacy alias)
     *     prefix = 'App\'   rel = 'DX\AdmissionDX'
     *     file   = /htdocs/dx-engine/src/App/DX/AdmissionDX.php
     *
     *   App\Models\PatientModel  (legacy alias)
     *     prefix = 'App\'   rel = 'Models\PatientModel'
     *     file   = /htdocs/dx-engine/src/App/Models/PatientModel.php
     *
     * @param  string $class  Fully-qualified class name.
     * @return bool           true if the file was loaded, false otherwise.
     */
    public static function load(string $class): bool
    {
        foreach (self::$namespaces as $prefix => $baseDir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            // Strip the prefix → relative path segment.
            // e.g. 'App\Models\PatientModel' with prefix 'App\' → 'Models\PatientModel'
            $relativeClass = substr($class, strlen($prefix));

            // Convert namespace separators to OS-level path separators.
            $relativeFile = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

            $absoluteFile = $baseDir . $relativeFile;

            // is_file() is safer than file_exists() — returns false for dirs.
            if (is_file($absoluteFile)) {
                require $absoluteFile;
                return true;
            }
        }

        return false;
    }

    /**
     * Return all registered namespace → directory mappings.
     * Used by check_env.php for diagnostics.
     *
     * @return array<string, string>
     */
    public static function registeredNamespaces(): array
    {
        return self::$namespaces;
    }

    /**
     * Normalise a path to the OS directory separator.
     * Handles mixed-separator paths such as C:\xampp\htdocs/dx-engine/src.
     *
     * @param  string $path
     * @return string
     */
    private static function normalisePath(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}
