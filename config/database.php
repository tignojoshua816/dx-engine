<?php
/**
 * DX-Engine — Database Configuration
 * =============================================================================
 * Returns a live PDO instance on success, or throws a plain-English
 * \RuntimeException on failure so public/api/dx.php's outer try-catch can
 * return a precise JSON error instead of a blank 500 page.
 *
 * XAMPP Quick-Start
 * -----------------
 *   Host     : localhost
 *   Port     : 3306
 *   Database : dx_engine        ← create: CREATE DATABASE dx_engine;
 *   Username : root
 *   Password : (empty string)   ← XAMPP default
 *
 * Edit the $defaults array below to change credentials.
 * For production, set the corresponding environment variables instead of
 * editing this file.
 *
 * Pathing note
 * ------------
 * This file contains no require/include statements and no path resolution.
 * It is required by dx.php after DX_ROOT is defined; it only needs PDO.
 * =============================================================================
 */

return (function (): PDO {

    // ── Credentials — env vars take priority over these hardcoded defaults ────
    $host    = $_ENV['DB_HOST']     ?? 'localhost';
    $port    = $_ENV['DB_PORT']     ?? '3306';
    $dbname  = $_ENV['DB_DATABASE'] ?? 'dx_engine';
    $user    = $_ENV['DB_USERNAME'] ?? 'root';
    $pass    = $_ENV['DB_PASSWORD'] ?? '';          // XAMPP default: empty
    $charset = $_ENV['DB_CHARSET']  ?? 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // always throw on error
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,                   // real prepared statements
        PDO::ATTR_TIMEOUT            => 5,                       // fail fast, not 30 s
    ];

    try {
        return new PDO($dsn, $user, $pass, $options);

    } catch (\PDOException $e) {
        /*
         * Common XAMPP failures and their fixes:
         *
         *  "Unknown database 'dx_engine'"
         *      → Open phpMyAdmin (http://localhost/phpmyadmin) and run:
         *        CREATE DATABASE dx_engine CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
         *        Then import database/migrations/003_schema.sql.
         *
         *  "Access denied for user 'root'@'localhost'"
         *      → Wrong password. XAMPP root password is empty by default.
         *        Check DB_PASSWORD in $defaults above.
         *
         *  "Can't connect to MySQL server" / "Connection refused"
         *      → MySQL is not running. Start it from the XAMPP Control Panel.
         *
         *  "No such file or directory" (Linux / macOS UNIX socket)
         *      → Change $host from 'localhost' to '127.0.0.1' to force TCP.
         */
        $msg = match (true) {
            str_contains($e->getMessage(), 'Unknown database')
                => "Database '{$dbname}' does not exist. "
                 . "Create it in phpMyAdmin: CREATE DATABASE {$dbname} CHARACTER SET utf8mb4; "
                 . "Then import database/migrations/003_schema.sql.",

            str_contains($e->getMessage(), 'Access denied')
                => "Access denied for '{$user}'@'{$host}'. "
                 . "Check DB_USERNAME / DB_PASSWORD in config/database.php.",

            str_contains($e->getMessage(), "Can't connect")
             || str_contains($e->getMessage(), 'Connection refused')
             || str_contains($e->getMessage(), 'No such file')
                => "Cannot connect to MySQL at {$host}:{$port}. "
                 . "Start MySQL in the XAMPP Control Panel. "
                 . "If 'localhost' fails, try '127.0.0.1' for DB_HOST.",

            str_contains($e->getMessage(), 'php_network_getaddresses')
                => "DNS lookup failed for '{$host}'. Use '127.0.0.1' instead.",

            default
                => "PDO error ({$host}:{$port}/{$dbname}): " . $e->getMessage(),
        };

        throw new \RuntimeException($msg, (int) $e->getCode(), $e);
    }
})();
