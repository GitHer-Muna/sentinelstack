<?php
declare(strict_types=1);

namespace Controllers;

use App\Database;

final class HealthController
{
    public function __construct(private string $root) {}

    public function index(): void
    {
        http_response_code(200);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'ok';
    }

    public function db(): void
    {
        try {
            Database::connect()->query('SELECT 1')->fetchColumn();
            http_response_code(200);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'ok';
        } catch (\Throwable $e) {
            http_response_code(503);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'db unavailable';
        }
    }
}
