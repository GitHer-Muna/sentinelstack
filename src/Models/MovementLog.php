<?php
declare(strict_types=1);

namespace Models;

use App\Database;

final class MovementLog
{
    public static function routines(): array
    {
        // Curated routines live in /config (configuration data, not a class).
        $path = dirname(__DIR__, 2) . '/config/routines.php';
        $r = is_file($path) ? require $path : [];
        return is_array($r) ? $r : [];
    }

    public static function mark(int $userId, string $key, string $tz, ?int $duration = null, ?string $note = null): bool
    {
        $today = DateUtil::today($tz);
        $stmt = Database::connect()->prepare(
            'INSERT OR IGNORE INTO movement_logs (user_id, routine_key, local_date, duration_seconds, note)
             VALUES (:uid, :key, :day, :dur, :note)'
        );
        $stmt->execute([
            ':uid'  => $userId,
            ':key'  => $key,
            ':day'  => $today,
            ':dur'  => $duration,
            ':note' => $note,
        ]);
        return $stmt->rowCount() > 0;
    }

    public static function unmark(int $userId, string $key, string $tz): void
    {
        $today = DateUtil::today($tz);
        $stmt = Database::connect()->prepare(
            'DELETE FROM movement_logs
             WHERE user_id = :uid AND routine_key = :key AND local_date = :day'
        );
        $stmt->execute([':uid' => $userId, ':key' => $key, ':day' => $today]);
    }

    public static function today(int $userId, string $tz): array
    {
        $today = DateUtil::today($tz);
        $stmt = Database::connect()->prepare(
            'SELECT routine_key, duration_seconds, note, created_at
             FROM movement_logs
             WHERE user_id = :uid AND local_date = :day'
        );
        $stmt->execute([':uid' => $userId, ':day' => $today]);
        $byKey = [];
        foreach ($stmt->fetchAll() as $row) {
            $byKey[$row['routine_key']] = $row;
        }
        return $byKey;
    }

    public static function currentStreak(int $userId, string $tz): int
    {
        $horizon = DateUtil::daysAgo(365, $tz);
        $stmt = Database::connect()->prepare(
            'SELECT DISTINCT local_date
             FROM movement_logs
             WHERE user_id = :uid AND local_date >= :h
             ORDER BY local_date DESC'
        );
        $stmt->execute([':uid' => $userId, ':h' => $horizon]);
        $days = array_column($stmt->fetchAll(), 'local_date');
        return \Models\Todo::streakFromDays($days, $tz);
    }

    public static function longestStreak(int $userId): int
    {
        $stmt = Database::connect()->prepare(
            'SELECT DISTINCT local_date FROM movement_logs
             WHERE user_id = :uid ORDER BY local_date ASC'
        );
        $stmt->execute([':uid' => $userId]);
        $days = array_column($stmt->fetchAll(), 'local_date');
        return \Models\Todo::longestFromDays($days);
    }

    public static function sessionsLastDays(int $userId, string $tz, int $days): array
    {
        $start = DateUtil::daysAgo($days - 1, $tz);
        $stmt = Database::connect()->prepare(
            'SELECT local_date, COUNT(*) AS n
             FROM movement_logs
             WHERE user_id = :uid AND local_date >= :start
             GROUP BY local_date
             ORDER BY local_date ASC'
        );
        $stmt->execute([':uid' => $userId, ':start' => $start]);
        $by = [];
        foreach ($stmt->fetchAll() as $row) {
            $by[$row['local_date']] = (int) $row['n'];
        }
        $today = DateUtil::now($tz);
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = $today->modify("-$i days")->format('Y-m-d');
            $series[] = ['date' => $d, 'count' => $by[$d] ?? 0];
        }
        return $series;
    }
}
