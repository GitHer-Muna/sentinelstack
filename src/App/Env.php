<?php
declare(strict_types=1);

namespace App;

/**
 * Tiny .env loader.
 *
 * Reads KEY=VALUE pairs into getenv() and $_ENV. Always overwrites with
 * the .env file's values so the file is the authoritative source of
 * configuration — a stale env var hanging around in the shell can't
 * silently override a .env change.
 *
 * Should be called very early — App::boot invokes this.
 */
final class Env
{
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            // strip optional surrounding quotes
            if (strlen($value) >= 2 &&
                (($value[0] === '"' && substr($value, -1) === '"') ||
                 ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $v = getenv($key);
        if ($v === false || $v === null) {
            $v = $_ENV[$key] ?? $default;
        }
        return $v === null ? null : (string) $v;
    }
}
