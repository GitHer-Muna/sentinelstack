<?php
declare(strict_types=1);

namespace App;

use Controllers\AuthController;
use Controllers\DashboardController;
use Controllers\HydrationController;
use Controllers\TodoController;
use Controllers\MindfulnessController;
use Controllers\MoodController;
use Controllers\MovementController;
use Controllers\SleepController;
use Controllers\StatsController;
use Controllers\SettingsController;
use Controllers\ApiController;
use Controllers\HealthController;

/**
 * Application bootstrap.
 *
 * - Loads .env
 * - Configures the session securely
 * - Dispatches the Router when handle() is called
 */
final class App
{
    private string $root;
    private ?Router $router = null;

    public function __construct(string $root)
    {
        $this->root = $root;
    }

    public static function boot(string $root): void
    {
        Env::load($root . '/.env');
        if (Env::get('APP_ENV') === 'development' && session_status() !== PHP_SESSION_ACTIVE) {
            // development convenience: surface errors
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            error_reporting(E_ALL);
        }

        Session::start();
        Csrf::rotateIfStale();
    }

    public function handle(): void
    {
        $this->router = new Router($this->root);
        $this->registerRoutes();
        $this->router->dispatch();
    }

    private function registerRoutes(): void
    {
        $r = $this->router;

        // Use the aliased short names (AuthController, not Controllers\AuthController)
        // because PHP's `::class` constant only consults `use` aliases by their
        // *last* segment. Writing the multi-segment form here silently falls
        // back to the current namespace and produces 'App\...'.

        $r->get('/',         [AuthController::class, 'home']);
        $r->get('/login',    [AuthController::class, 'showLogin']);
        $r->post('/login',   [AuthController::class, 'login']);
        $r->get('/register', [AuthController::class, 'showRegister']);
        $r->post('/register',[AuthController::class, 'register']);
        $r->post('/logout',  [AuthController::class, 'logout']);

        // App pages (auth required)
        $r->get('/dashboard',     [DashboardController::class, 'index']);
        $r->get('/hydration',     [HydrationController::class, 'index']);
        $r->get('/todos',         [TodoController::class, 'index']);
        $r->get('/mindfulness',   [MindfulnessController::class, 'index']);
        $r->get('/mood',          [MoodController::class, 'index']);
        $r->get('/movement',      [MovementController::class, 'index']);
        $r->get('/sleep',         [SleepController::class, 'index']);
        $r->get('/stats',         [StatsController::class, 'index']);
        $r->get('/settings',      [SettingsController::class, 'index']);

        // Health probes (no auth) — used by container healthchecks and
        // the Prometheus blackbox exporter. Order matters; declare them
        // BEFORE the auth-required app-page block so they can never be
        // satisfied by a redirect.
        $r->get('/healthz',     [HealthController::class, 'index']);
        $r->get('/healthz/db',  [HealthController::class, 'db']);

        // General settings updates
        $r->post('/settings/update',     [SettingsController::class, 'update']);
        $r->post('/settings/password',   [SettingsController::class, 'changePassword']);
        $r->post('/settings/delete',     [SettingsController::class, 'deleteAccount']);

        // AJAX / API endpoints. All require auth.
        $r->post('/api/hydration',       [ApiController::class, 'hydration']);
        $r->post('/api/todos',           [ApiController::class, 'todos']);
        $r->post('/api/todos/reorder',   [ApiController::class, 'todosReorder']);
        $r->post('/api/mindfulness',     [ApiController::class, 'mindfulness']);
        $r->post('/api/mood',            [ApiController::class, 'mood']);
        $r->post('/api/movement',        [ApiController::class, 'movement']);
        $r->post('/api/sleep',           [ApiController::class, 'sleep']);
    }
}
