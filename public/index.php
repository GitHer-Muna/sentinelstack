<?php
declare(strict_types=1);

/**
 * Wellspring — front controller.
 *
 * When run under PHP's built-in dev server (`php -S host:port -t public public/index.php`),
 * this script acts as the ROUTER for every request that does not already map to a real
 * file on disk. To make static assets (CSS, JS, fonts, images) coexist with application
 * routes, we short-circuit on real-file paths BEFORE falling into the app.
 *
 * In production under Apache + mod_rewrite, `public/.htaccess` already does this check
 * via `RewriteCond %{REQUEST_FILENAME} -f`, so this guard is a no-op for Apache-served
 * requests (it never even runs because the rewrite rule keeps real files out of the
 * router). The guard exists purely so `php -S` works out of the box for development.
 */

if (PHP_SAPI === 'cli-server') {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    // Normalize \ to / for the docroot-relative check (Windows-friendly) and
    // refuse `..` segments outright — defense against any upstream that doesn't
    // already URL-normalize.
    $uri = str_replace('\\', '/', $uri);
    $hasTraversal = strpos($uri, '..') !== false;
    $isController = ($uri === '/' || $uri === '/index.php');
    // Only consider the request a static asset if it maps to a real file under
    // public/ AND it's not the front controller itself (which would recurse).
    if (!$hasTraversal && !$isController && is_file(__DIR__ . $uri)) {
        // Returning false tells the built-in server to fall back to its native
        // file-serving path: correct Content-Type, 304, range, etc.
        return false;
    }
}

// ─── Application bootstrap ──────────────────────────────────────────────────
$root = dirname(__DIR__);

// Load Composer autoloader
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Autoloader missing. Run `composer install` in the project root.\n");
    http_response_code(500);
    echo "Autoloader missing. Run `composer install` in the project root.";
    exit(1);
}
require $autoload;

// Load .env if it exists (gracefully skip otherwise — .env files are optional)
\App\App::boot($root);

// Hand the rest to the Router
$app = new \App\App($root);
$app->handle();
