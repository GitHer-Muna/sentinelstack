<?php
declare(strict_types=1);

namespace Models;

use App\Database;

/**
 * Deterministic "affirmation of the day".
 *
 * Spec: deterministic by date+user id, not random every reload, no duplicates
 * on the user's first reload of the day.
 *
 * Implementation: pick the affirmation row whose id matches
 *   floor((epochDay + userId) mod total)
 * with totals falling back to ORDER BY id LIMIT 1 if the table is small.
 */
final class Affirmation
{
    public static function forDay(int $userId, string $tz): string
    {
        $today = DateUtil::today($tz);
        $epochDay = (int) floor(
            (new \DateTimeImmutable($today . ' 00:00:00', new \DateTimeZone($tz)))->getTimestamp() / 86400
        );

        $pdo = Database::connect();
        $total = (int) $pdo->query('SELECT COUNT(*) FROM affirmations')->fetchColumn();
        if ($total === 0) {
            return 'Take a slow breath. Begin.';
        }
        $idx = ($epochDay + $userId) % $total;

        $stmt = $pdo->prepare('SELECT body FROM affirmations ORDER BY id ASC LIMIT 1 OFFSET :off');
        $stmt->bindValue(':off', $idx, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? (string) $row['body'] : 'Take a slow breath. Begin.';
    }
}
