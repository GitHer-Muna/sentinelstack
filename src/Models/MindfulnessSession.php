<?php
declare(strict_types=1);

namespace Models;

use App\Database;

final class MindfulnessSession
{
    public static function log(int $userId, int $seconds, string $pattern, bool $completed, string $tz, ?string $note = null): int
    {
        $now = new \DateTime('now', new \DateTimeZone($tz));
        $stmt = Database::connect()->prepare(
            'INSERT INTO mindfulness_sessions (user_id, duration_seconds, pattern, completed, local_date, started_at, ended_at, note)
             VALUES (:uid, :dur, :pat, :comp, :day, :st, :en, :note)'
        );
        $started = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        $ended = $now->modify("+{$seconds} seconds")->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        $stmt->execute([
            ':uid'  => $userId,
            ':dur'  => $seconds,
            ':pat'  => $pattern,
            ':comp' => $completed ? 1 : 0,
            ':day'  => $now->format('Y-m-d'),
            ':st'   => $started,
            ':en'   => $ended,
            ':note' => $note,
        ]);
        return (int) Database::connect()->lastInsertId();
    }

    public static function recent(int $userId, int $limit = 20): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT id, duration_seconds, pattern, completed, local_date, started_at, note
             FROM mindfulness_sessions
             WHERE user_id = :uid
             ORDER BY started_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function currentStreak(int $userId, string $tz): int
    {
        $cutoff = DateUtil::daysAgo(365, $tz);
        $stmt = Database::connect()->prepare(
            'SELECT DISTINCT local_date
             FROM mindfulness_sessions
             WHERE user_id = :uid AND completed = 1 AND local_date >= :cut
             ORDER BY local_date DESC'
        );
        $stmt->execute([':uid' => $userId, ':cut' => $cutoff]);
        $days = array_column($stmt->fetchAll(), 'local_date');
        return \Models\Todo::streakFromDays($days, $tz);
    }

    public static function longestStreak(int $userId): int
    {
        $stmt = Database::connect()->prepare(
            'SELECT DISTINCT local_date FROM mindfulness_sessions
             WHERE user_id = :uid AND completed = 1
             ORDER BY local_date ASC'
        );
        $stmt->execute([':uid' => $userId]);
        $days = array_column($stmt->fetchAll(), 'local_date');
        return \Models\Todo::longestFromDays($days);
    }

    public static function totalsLastDays(int $userId, string $tz, int $days): array
    {
        $start = DateUtil::daysAgo($days - 1, $tz);
        $stmt = Database::connect()->prepare(
            'SELECT local_date, SUM(duration_seconds) AS ttl
             FROM mindfulness_sessions
             WHERE user_id = :uid AND completed = 1 AND local_date >= :start
             GROUP BY local_date
             ORDER BY local_date ASC'
        );
        $stmt->execute([':uid' => $userId, ':start' => $start]);

        $by = [];
        foreach ($stmt->fetchAll() as $row) {
            $by[$row['local_date']] = (int) $row['ttl'];
        }
        $today = DateUtil::now($tz);
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = $today->modify("-$i days")->format('Y-m-d');
            $series[] = ['date' => $d, 'minutes' => isset($by[$d]) ? (int) round($by[$d] / 60) : 0];
        }
        return $series;
    }
}
