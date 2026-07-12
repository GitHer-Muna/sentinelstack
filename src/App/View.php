<?php
declare(strict_types=1);

namespace App;

/**
 * Template engine. Extracts $data and requires a template file.
 */
final class View
{
    public static function render(string $name, array $data = [], ?string $layout = 'layout'): void
    {
        $templateDir = dirname(__DIR__, 2) . '/templates';
        $file = $templateDir . '/' . ltrim($name, '/') . '.php';

        if (!is_file($file)) {
            Response::abort(500, "Template not found: $name");
        }

        // Make data + helpers available to templates
        extract($data, EXTR_SKIP);

        $csrf = Csrf::token();
        $user = null;
        $uid = Session::userId();
        if ($uid) {
            $pdo = Database::connect();
            $stmt = $pdo->prepare('SELECT id, email, display_name, timezone, theme, water_goal, water_unit FROM users WHERE id = :id');
            $stmt->execute([':id' => $uid]);
            $user = $stmt->fetch() ?: null;
        }

        $flash = Session::flash('notice');

        if ($layout === null) {
            require $file;
            return;
        }

        $layoutFile = $templateDir . '/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            require $file;
            return;
        }

        // Render the inner template into a buffer, then render the layout.
        ob_start();
        require $file;
        $content = ob_get_clean();

        require $layoutFile;
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function date(?string $iso, string $tz): string
    {
        if (!$iso) return '';
        try {
            $dt = new \DateTime($iso, new \DateTimeZone($tz));
            return $dt->format('Y-m-d');
        } catch (\Exception) {
            return '';
        }
    }

    public static function prettyDate(?string $iso, string $tz): string
    {
        if (!$iso) return '';
        try {
            $dt = new \DateTime($iso, new \DateTimeZone($tz));
            return $dt->format('M j, Y');
        } catch (\Exception) {
            return '';
        }
    }
}
