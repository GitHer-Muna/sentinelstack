<?php
declare(strict_types=1);

namespace App;

/**
 * Secure session handling.
 *
 * - HttpOnly, SameSite=Lax
 * - Secure when SESSION_SECURE=true (only runs behind HTTPS)
 * - Frequency check for session fixation (regenerate on login)
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $name = Env::get('SESSION_NAME', 'sentinelstack_session');
        $secure = filter_var(Env::get('SESSION_SECURE', 'false'), FILTER_VALIDATE_BOOLEAN);

        session_name($name);

        $params = [
            'lifetime' => (int) (Env::get('SESSION_LIFETIME', '0')),
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        // setcookie params must be called before session_start
        session_set_cookie_params($params);
        ini_set('session.use_strict_mode', '1');
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flush(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                ['expires' => time() - 42000,
                 'path'     => $params['path'],
                 'domain'   => $params['domain'],
                 'secure'   => $params['secure'],
                 'httponly' => $params['httponly'],
                 'samesite' => $params['samesite'] ?? 'Lax',]
            );
        }
        session_destroy();
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function flash(string $key, ?string $value = null): ?string
    {
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }
        $msg = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $msg;
    }

    public static function userId(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;
        return $id !== null ? (int) $id : null;
    }

    public static function requireAuth(): int
    {
        $id = self::userId();
        if (!$id) {
            Response::redirect('/login');
        }
        return $id;
    }
}
