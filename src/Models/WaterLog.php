<?php
declare(strict_types=1);

namespace Models;

use App\Database;

final class WaterLog
{
    public static function add(int $userId, int $amount, string $unit, string $tz, ?string $note = null): int
    {
        $now = new \DateTime('now', new \DateTimeZone($tz));
        $sql = 'INSERT INTO water_logs (user_id, amount, unit, logged_at, local_date, note)
                VALUES (:uid, :amt, :unit, :logged_at, :day, :note)';
        $stmt = Database::connect()->prepare($sql);
        $stmt->execute([
            ':uid'       => $userId,
            ':amt'       => $amount,
            ':unit'      => $unit,
            ':logged_at' => $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            ':day'       => $now->format('Y-m-d'),
            ':note'      => $note,
        ]);
        return (int) Database::connect()->lastInsertId();
    }

    public static function delete(int $userId, int $logId): void
    {
        $stmt = Database::connect()->prepare('DELETE FROM water_logs WHERE id = :id AND user_id = :uid');
        $stmt->execute([':id' => $logId, ':uid' => $userId]);
    }

    public static function todayTotal(int $userId, string $tz): int
    {
        $today = DateUtil::today($tz);
        $stmt = Database::connect()->prepare(
            'SELECT COALESCE(SUM(amount), 0) AS total FROM water_logs
             WHERE user_id = :uid AND local_date = :day'
        );
        $stmt->execute([':uid' => $userId, ':day' => $today]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Returns ['by_day' => [date => amount], 'avg' => x, 'days_with_logs' => n]
     * for the last N days (default 7).
     */
    public static function summary(int $userId, string $tz, int $days = 7): array
    {
        $start = DateUtil::daysAgo($days - 1, $tz);
        $stmt = Database::connect()->prepare(
            'SELECT local_date, SUM(amount) AS total
             FROM water_logs
             WHERE user_id = :uid AND local_date >= :start
             GROUP BY local_date
             ORDER BY local_date ASC'
        );
        $stmt->execute([':uid' => $userId, ':start' => $start]);

        $byDay = [];
        foreach ($stmt->fetchAll() as $row) {
            $byDay[$row['local_date']] = (int) $row['total'];
        }

        $today = DateUtil::now($tz);
        $orderedDates = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = $today->modify("-$i days")->format('Y-m-d');
            $orderedDates[] = $d;
        }
        $series = [];
        foreach ($orderedDates as $d) {
            $series[] = ['date' => $d, 'amount' => $byDay[$d] ?? 0];
        }

        $nonZero = array_filter(array_column($series, 'amount'), fn($v) => $v > 0);
        $avg = count($nonZero) ? (int) round(array_sum($nonZero) / count($nonZero)) : 0;

        return [
            'series' => $series,
            'avg'    => $avg,
            'days_with_logs' => count($nonZero),
        ];
    }

    public static function todayEntries(int $userId, string $tz): array
    {
        $today = DateUtil::today($tz);
        $stmt = Database::connect()->prepare(
            'SELECT id, amount, unit, local_date, logged_at, note
             FROM water_logs
             WHERE user_id = :uid AND local_date = :day
             ORDER BY logged_at DESC'
        );
        $stmt->execute([':uid' => $userId, ':day' => $today]);
        return $stmt->fetchAll();
    }
}
