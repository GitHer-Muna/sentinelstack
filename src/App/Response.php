<?php
declare(strict_types=1);

namespace App;

/**
 * Response helpers.
 */
final class Response
{
    public static function redirect(string $url, int $status = 302): void
    {
        // If the URL is absolute, send as-is.
        if (preg_match('#^https?://#', $url)) {
            header('Location: ' . $url, true, $status);
            exit;
        }

        // Reject scheme-relative targets ("//evil.com/x") so a future caller
        // passing user-controlled $url can't bounce the user off-host. None
        // of the current callers pass user input, but defense-in-depth costs
        // nothing.
        if (str_starts_with($url, '//')) {
            self::abort(400, 'Invalid redirect target.');
        }

        // For relative paths, emit them as-is. The browser resolves a relative
        // Location header against the URL it is currently on, so the redirect
        // stays on whichever host or proxy the request came in on (loopback,
        // a Codespaces / GitHub.dev port-forwarder, or a production domain).
        // Building an absolute URL from $_SERVER['HTTP_HOST'] would break
        // behind any forwarder that rewrites or strips the upstream Host
        // header — the browser would then end up on the wrong machine and
        // refuse the connection.
        header('Location: ' . $url, true, $status);
        exit;
    }

    public static function abort(int $status, string $message = ''): void
    {
        http_response_code($status);
        $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "HTTP $status $safe\n");
            exit(1);
        }
        // Simple bare-bones error page that keeps Wellspring styling.
        $debug = filter_var(Env::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
        echo <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>$status · Wellspring</title>
<link rel="stylesheet" href="/assets/css/tokens.css">
<link rel="stylesheet" href="/assets/css/base.css">
<style>body{display:flex;min-height:100vh;align-items:center;justify-content:center;padding:2rem}.card{max-width:520px;text-align:center;background:var(--surface);padding:2.4rem;border-radius:14px;box-shadow:0 4px 20px rgba(0,0,0,0.06)}h1{margin:0 0 .6rem;font-family:var(--font-serif)}p{color:var(--text-muted);line-height:1.6}.btn{display:inline-block;margin-top:1.4rem;background:var(--accent);color:#fff;padding:.7rem 1.2rem;border-radius:10px;text-decoration:none}</style>
</head>
<body>
<div class="card">
<h1>$status</h1>
<p>$safe</p>
<a class="btn" href="/">Back to start</a>
</div>
</body>
</html>
HTML;
        exit;
    }

    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
