<?php
declare(strict_types=1);

namespace Models;

/**
 * Helpers for date math in the user's local timezone.
 */
final class DateUtil
{
    public static function now(string $tz): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone($tz));
    }

    public static function today(string $tz): string
    {
        return self::now($tz)->format('Y-m-d');
    }

    public static function daysAgo(int $n, string $tz): string
    {
        return self::now($tz)->modify("-$n days")->format('Y-m-d');
    }

}
