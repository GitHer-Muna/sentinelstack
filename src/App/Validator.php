<?php
declare(strict_types=1);

namespace App;

/**
 * Validation helpers — return ['value' => ..., 'errors' => [...]].
 */
final class Validator
{
    public static function email(string $value): ?string
    {
        return filter_var(trim($value), FILTER_VALIDATE_EMAIL) ?: null;
    }

    public static function nonEmpty(string $value, int $max = 255): ?string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > $max) {
            return null;
        }
        return $value;
    }

    public static function password(string $value): ?string
    {
        if (strlen($value) < 8 || strlen($value) > 256) {
            return null;
        }
        return $value;
    }

    public static function inArray(mixed $value, array $allowed): ?string
    {
        return in_array($value, $allowed, true) ? (string) $value : null;
    }

    public static function positiveInt(mixed $value): ?int
    {
        $n = filter_var($value, FILTER_VALIDATE_INT);
        return ($n !== false && $n > 0) ? $n : null;
    }

    public static function nonNegativeInt(mixed $value): ?int
    {
        $n = filter_var($value, FILTER_VALIDATE_INT);
        return ($n !== false && $n >= 0 && $n <= 100000) ? $n : null;
    }

    public static function date(string $value): ?string
    {
        $d = \DateTime::createFromFormat('Y-m-d', trim($value));
        return ($d && $d->format('Y-m-d') === trim($value)) ? $d->format('Y-m-d') : null;
    }

    public static function timezone(string $value): ?string
    {
        try {
            new \DateTimeZone($value);
            return $value;
        } catch (\Exception) {
            return null;
        }
    }
}
