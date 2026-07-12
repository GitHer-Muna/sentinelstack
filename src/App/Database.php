<?php
declare(strict_types=1);

namespace App;

/**
 * Lazy SQLite PDO connection.
 *
 * Ensures the directory exists, foreign keys are on, and we use sensible PRAGMAs.
 */
final class Database
{
    /** Per-request PDO singleton. Reused so `lastInsertId()` works. */
    private static ?\PDO $pdo = null;

    /**
     * Lazy SQLite PDO connection. First call opens it; subsequent calls in
     * the same process return the same connection (SQLite lastInsertId is
     * connection-local — a fresh connection has none).
     */
    public static function connect(?string $path = null): \PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        if ($path === null || $path === '') {
            $path = Env::get('DB_PATH', './data/wellspring.sqlite');
        }

        $dir = dirname($path);
        if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $dsn = 'sqlite:' . $path;
        $pdo = new \PDO($dsn, null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = NORMAL');

        self::$pdo = $pdo;
        return $pdo;
    }

    /**
     * Drop the cached singleton. Call this if the underlying database file
     * has been replaced underneath us (the seed script does this when the
     * user deletes data/wellspring.sqlite).
     */
    public static function reset(): void
    {
        self::$pdo = null;
    }
}
