<?php
declare(strict_types=1);

namespace Models;

use App\Database;

/**
 * One sleep entry per (user, local_date). The `local_date` is the wake-up
 * date of the night just slept. UPSERT lets the user edit "last night's"
 * number without losing history.
 */
final class SleepLog
{
    public static function log(int $userId, int $minutes, string $tz, ?string $note = null): void
    {
        if ($minutes <= 0 || $minutes > 1440) {
            throw new \InvalidArgumentException('Sleep duration must be between 1 minute and 24 hours.');
        }
        $today = DateUtil::today($tz);
        $stmt = Database::connect()->prepare(
            'INSERT INTO sleep_logs (user_id, duration_minutes, local_date, note)
             VALUES (:uid, :dur, :day, :note)
             ON CONFLICT(user_id, local_date) DO UPDATE SET
               duration_minutes = excluded.duration_minutes,
               note             = excluded.note,
               updated_at       = strftime(\'%Y-%m-%dT%H:%M:%fZ\',\'now\')'
        );
        $stmt->execute([
            ':uid'  => $userId,
            ':dur'  => $minutes,
            ':day'  => $today,
            ':note' => $note,
        ]);
    }

    public static function today(int $userId, string $tz): ?array
    {
        $today = DateUtil::today($tz);
        $stmt = Database::connect()->prepare(
            'SELECT * FROM sleep_logs WHERE user_id = :uid AND local_date = :day'
        );
        $stmt->execute([':uid' => $userId, ':day' => $today]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function recent(int $userId, int $limit = 14): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT id, duration_minutes, local_date, note, created_at
             FROM sleep_logs
             WHERE user_id = :uid
             ORDER BY local_date DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function delete(int $userId, int $id): void
    {
        Database::connect()->prepare(
            'DELETE FROM sleep_logs WHERE id = :id AND user_id = :uid'
        )->execute([':id' => $id, ':uid' => $userId]);
    }

    public static function currentStreak(int $userId, string $tz): int
    {
        $horizon = DateUtil::daysAgo(365, $tz);
        $stmt = Database::connect()->prepare(
            'SELECT DISTINCT local_date FROM sleep_logs
             WHERE user_id = :uid AND local_date >= :h
             ORDER BY local_date DESC'
        );
        $stmt->execute([':uid' => $userId, ':h' => $horizon]);
        $days = array_column($stmt->fetchAll(), 'local_date');
        return Todo::streakFromDays($days, $tz);
    }

    public static function longestStreak(int $userId): int
    {
        $stmt = Database::connect()->prepare(
            'SELECT DISTINCT local_date FROM sleep_logs
             WHERE user_id = :uid ORDER BY local_date ASC'
        );
        $stmt->execute([':uid' => $userId]);
        $days = array_column($stmt->fetchAll(), 'local_date');
        return Todo::longestFromDays($days);
    }

    /** Average hours/night over the last N logged nights. Null if no data. */
    public static function averageLastDays(int $userId, string $tz, int $days): ?float
    {
        $start = DateUtil::daysAgo($days - 1, $tz);
        $stmt = Database::connect()->prepare(
            'SELECT duration_minutes FROM sleep_logs
             WHERE user_id = :uid AND local_date >= :start'
        );
        $stmt->execute([':uid' => $userId, ':start' => $start]);
        $rows = $stmt->fetchAll();
        if (!$rows) return null;
        $total = 0;
        foreach ($rows as $r) {
            $total += (int) $r['duration_minutes'];
        }
        return round($total / count($rows) / 60.0, 2);
    }

    /**
     * Series for the chart — one entry per calendar day for the last N days,
     * zero-filled for missed nights so the X-axis is evenly spaced.
     */
    public static function totalsLastDays(int $userId, string $tz, int $days): array
    {
        $start = DateUtil::daysAgo($days - 1, $tz);
        $stmt = Database::connect()->prepare(
            'SELECT local_date, duration_minutes FROM sleep_logs
             WHERE user_id = :uid AND local_date >= :start
             ORDER BY local_date ASC'
        );
        $stmt->execute([':uid' => $userId, ':start' => $start]);
        $by = [];
        foreach ($stmt->fetchAll() as $r) {
            $by[$r['local_date']] = (int) $r['duration_minutes'];
        }
        $today = DateUtil::now($tz);
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = $today->modify("-$i days")->format('Y-m-d');
            $minutes = $by[$d] ?? 0;
            $series[] = ['date' => $d, 'hours' => round($minutes / 60.0, 2)];
        }
        return $series;
    }
}
