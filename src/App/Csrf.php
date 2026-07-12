<?php
declare(strict_types=1);

namespace App;

/**
 * CSRF token helper. Synchronizer token pattern stored in the session.
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
            $_SESSION['_csrf_at'] = time();
        }
        return $_SESSION['_csrf'];
    }

    public static function rotateIfStale(): void
    {
        if (empty($_SESSION['_csrf']) || empty($_SESSION['_csrf_at'])) {
            self::token();
            return;
        }
        $lifetime = (int) (Env::get('CSRF_LIFETIME', '14400'));
        if ((time() - (int) $_SESSION['_csrf_at']) > $lifetime) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
            $_SESSION['_csrf_at'] = time();
        }
    }

    public static function verify(?string $supplied): bool
    {
        if (empty($supplied) || empty($_SESSION['_csrf'])) {
            return false;
        }
        return hash_equals($_SESSION['_csrf'], (string) $supplied);
    }

    public static function requireValid(): void
    {
        $header = null;
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $k => $v) {
                if (strcasecmp($k, 'X-CSRF-Token') === 0) {
                    $header = $v;
                    break;
                }
            }
        }
        $supplied = $_POST['_csrf'] ?? null;
        if ($supplied === null && $header !== null) {
            $supplied = $header;
        }
        if (!self::verify($supplied)) {
            Response::abort(419, 'CSRF token invalid or missing.');
        }
    }
}
