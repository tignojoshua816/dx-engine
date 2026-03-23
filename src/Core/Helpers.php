<?php
/**
 * DX-Engine — Helpers
 * -----------------------------------------------------------------------
 * Static utility functions used across the framework.
 */

namespace DXEngine\Core;

class Helpers
{
    /**
     * Sanitise a string for safe HTML output.
     */
    public static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Recursively sanitise an array of strings.
     */
    public static function escArray(array $data): array
    {
        return array_map(function ($value) {
            if (is_string($value)) {
                return self::esc($value);
            }
            if (is_array($value)) {
                return self::escArray($value);
            }
            return $value;
        }, $data);
    }

    /**
     * Coerce a value to the correct PHP type based on a DataModel field type.
     */
    public static function coerce(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int'   => (int)   $value,
            'float' => (float) $value,
            'bool'  => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => (string) $value,
        };
    }

    /**
     * Flatten nested POST data into dot-notation keys.
     * e.g. ['patient' => ['name' => 'John']]  →  ['patient.name' => 'John']
     */
    public static function flatten(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;
            if (is_array($value)) {
                $result += self::flatten($value, $newKey);
            } else {
                $result[$newKey] = $value;
            }
        }
        return $result;
    }

    /**
     * Return a UUID v4 string.
     */
    public static function uuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * JSON-encode and immediately emit with correct headers (for standalone scripts).
     */
    public static function jsonResponse(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Generate a CSRF token and store it in the session.
     */
    public static function csrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['_dx_csrf'])) {
            $_SESSION['_dx_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_dx_csrf'];
    }

    /**
     * Validate a CSRF token against the session.
     */
    public static function verifyCsrf(string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        return hash_equals($_SESSION['_dx_csrf'] ?? '', $token);
    }
}
