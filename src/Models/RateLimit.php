<?php
declare(strict_types=1);

namespace Models;

use App\Database;
use App\Env;

/**
 * Simple DB-backed rate limit for login: counts failed attempts by email
 * within a window. Successful logins clear the counter for that email.
 */
final class RateLimit
{
    public static function recordFailure(string $email, string $ip): void
    {
        $stmt = Database::connect()->prepare(
            'INSERT INTO login_attempts (email, ip, succeeded) VALUES (:e, :ip, 0)'
        );
        $stmt->execute([':e' => $email, ':ip' => $ip]);
    }

    public static function recordSuccess(string $email, string $ip): void
    {
        $stmt = Database::connect()->prepare(
            'INSERT INTO login_attempts (email, ip, succeeded) VALUES (:e, :ip, 1)'
        );
        $stmt->execute([':e' => $email, ':ip' => $ip]);
    }

    public static function clearFailures(string $email): void
    {
        // Successful: drop rows for this email so the next failure starts fresh.
        $stmt = Database::connect()->prepare('DELETE FROM login_attempts WHERE email = :e AND succeeded = 0');
        $stmt->execute([':e' => $email]);
    }

    public static function recentFailures(string $email): int
    {
        $window = (int) Env::get('LOGIN_WINDOW_SECONDS', '900');
        $since = date('Y-m-d\TH:i:s\Z', time() - $window);
        $stmt = Database::connect()->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE email = :e AND succeeded = 0 AND attempted_at >= :since'
        );
        $stmt->execute([':e' => $email, ':since' => $since]);
        return (int) $stmt->fetchColumn();
    }

    public static function isBlocked(string $email): bool
    {
        $maxAttempts = (int) Env::get('LOGIN_MAX_ATTEMPTS', '5');
        return self::recentFailures($email) >= $maxAttempts;
    }

    // ─── Register rate-limit (per-IP; defends email enumeration)  ───────

    public static function recordRegisterFailure(string $ip): void
    {
        $stmt = Database::connect()->prepare(
            'INSERT INTO register_attempts (ip, succeeded) VALUES (:ip, 0)'
        );
        $stmt->execute([':ip' => $ip]);
    }

    public static function recordRegisterSuccess(string $ip): void
    {
        // One audit row per successful register, kept after we clear the
        // failed-probe trail so admins can correlate signup spikes.
        $stmt = Database::connect()->prepare(
            'INSERT INTO register_attempts (ip, succeeded) VALUES (:ip, 1)'
        );
        $stmt->execute([':ip' => $ip]);
    }

    public static function clearRegisterFailures(string $ip): void
    {
        $stmt = Database::connect()->prepare('DELETE FROM register_attempts WHERE ip = :ip AND succeeded = 0');
        $stmt->execute([':ip' => $ip]);
    }

    public static function recentRegisterFailures(string $ip): int
    {
        $window = (int) Env::get('LOGIN_WINDOW_SECONDS', '900');
        $since = date('Y-m-d\TH:i:s\Z', time() - $window);
        $stmt = Database::connect()->prepare(
            'SELECT COUNT(*) FROM register_attempts WHERE ip = :ip AND succeeded = 0 AND attempted_at >= :since'
        );
        $stmt->execute([':ip' => $ip, ':since' => $since]);
        return (int) $stmt->fetchColumn();
    }

    public static function isRegisterBlocked(string $ip): bool
    {
        $maxAttempts = (int) Env::get('LOGIN_MAX_ATTEMPTS', '5');
        return self::recentRegisterFailures($ip) >= $maxAttempts;
    }
}
