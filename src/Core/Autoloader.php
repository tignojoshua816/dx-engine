<?php
/**
 * DX-Engine — PSR-4 Autoloader
 */

declare(strict_types=1);

namespace DXEngine\Core;

class Autoloader
{
    private static array $namespaces = [];

    public static function register(string $srcRoot): void
    {
        $srcRoot = self::normalisePath($srcRoot);

        self::addNamespace('DXEngine\\', $srcRoot);
        self::addNamespace(
            'App\\',
            rtrim($srcRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'App'
        );

        static $registered = false;
        if (!$registered) {
            spl_autoload_register([self::class, 'load'], true, false);
            $registered = true;
        }
    }

    public static function addNamespace(string $prefix, string $baseDir): void
    {
        $prefix  = rtrim($prefix, '\\') . '\\';
        $baseDir = rtrim(self::normalisePath($baseDir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        self::$namespaces[$prefix] = $baseDir;
    }

    public static function load(string $class): bool
    {
        foreach (self::$namespaces as $prefix => $baseDir) {
            if (!self::startsWith($class, $prefix)) {
                continue;
            }

            $relativeClass = substr($class, strlen($prefix));
            $relativeFile  = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
            $absoluteFile  = $baseDir . $relativeFile;

            if (is_file($absoluteFile)) {
                require $absoluteFile;
                return true;
            }
        }

        return false;
    }

    public static function registeredNamespaces(): array
    {
        return self::$namespaces;
    }

    private static function normalisePath(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    private static function startsWith(string $haystack, string $needle): bool
    {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
