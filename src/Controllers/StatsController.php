<?php
declare(strict_types=1);

namespace Controllers;

use App\Session;
use App\View;
use Models\DateUtil;
use Models\MindfulnessSession;
use Models\MovementLog;
use Models\SleepLog;
use Models\User;
use Models\WaterLog;

final class StatsController
{
    public function __construct(private string $root) {}

    public function index(): void
    {
        $uid = Session::requireAuth();
        $user = User::find($uid);
        $tz = $user['timezone'] ?? 'UTC';

        // 14-day series for the chart
        $water14 = WaterLog::summary($uid, $tz, 14);
        $mindful14 = MindfulnessSession::totalsLastDays($uid, $tz, 14);
        $move14 = MovementLog::sessionsLastDays($uid, $tz, 14);

        $waterStreak = self::waterGoalStreak($uid, $tz, $user['water_goal']);
        $mindfulCurrent = MindfulnessSession::currentStreak($uid, $tz);
        $mindfulLongest = MindfulnessSession::longestStreak($uid, $tz);
        $moveCurrent = MovementLog::currentStreak($uid, $tz);
        $moveLongest = MovementLog::longestStreak($uid);

        $sleep14 = SleepLog::totalsLastDays($uid, $tz, 14);
        $sleepCurrent = SleepLog::currentStreak($uid, $tz);
        $sleepLongest = SleepLog::longestStreak($uid);
        $sleepAvg14 = SleepLog::averageLastDays($uid, $tz, 14);

        $review = self::weeklyReview($uid, $tz, (int) $user['water_goal']);

        // Treat the page as empty for new users so the template can show a
        // friendly onboarding state instead of four zero-streak cards. The
        // bar is intentionally low — if you've logged ANY movement, sleep,
        // mindfulness, or hit a water goal once, you see the full charts.
        $hasData = $waterStreak > 0
                || $mindfulCurrent > 0
                || $moveCurrent > 0
                || $sleepCurrent > 0
                || (int) ($water14['days_with_logs'] ?? 0) > 0
                || (int) ($review['mindful_count'] ?? 0) > 0
                || (int) ($review['movement_count'] ?? 0) > 0;

        View::render('stats', [
            'user' => $user,
            'tz' => $tz,
            'pageTitle' => 'Stats',
            'water14' => $water14,
            'mindful14' => $mindful14,
            'move14' => $move14,
            'waterStreak' => $waterStreak,
            'mindfulCurrent' => $mindfulCurrent,
            'mindfulLongest' => $mindfulLongest,
            'moveCurrent' => $moveCurrent,
            'moveLongest' => $moveLongest,
            'sleep14' => $sleep14,
            'sleepCurrent' => $sleepCurrent,
            'sleepLongest' => $sleepLongest,
            'sleepAvg14' => $sleepAvg14,
            'review' => $review,
            'hasData' => $hasData,
        ]);
    }

    private static function waterGoalStreak(int $uid, string $tz, int $goal): int
    {
        $today = DateUtil::now($tz);
        // Look back up to 2 years. Days without data count as below-goal.
        $start = DateUtil::daysAgo(730, $tz);
        $stmt = \App\Database::connect()->prepare(
            'SELECT local_date, SUM(amount) AS total FROM water_logs
             WHERE user_id = :uid AND local_date >= :start
             GROUP BY local_date ORDER BY local_date DESC'
        );
        $stmt->execute([':uid' => $uid, ':start' => $start]);
        $byDay = [];
        foreach ($stmt->fetchAll() as $r) {
            $byDay[$r['local_date']] = (int) $r['total'];
        }

        $streak = 0;
        $cursor = $today;
        // Walk 730 days. Today alone gets a grace day (skip in break condition);
        // any earlier day below goal breaks the streak.
        for ($i = 0; $i < 730; $i++) {
            $d = $cursor->format('Y-m-d');
            $val = $byDay[$d] ?? 0;
            if ($val >= $goal) {
                $streak++;
            } elseif ($i === 0) {
                // today doesn't break the streak — user might not have logged yet
            } else {
                break;
            }
            $cursor = $cursor->modify('-1 day');
        }
        return $streak;
    }

    private static function weeklyReview(int $uid, string $tz, int $goal): array
    {
        $today = DateUtil::now($tz);
        $start = $today->modify('-6 days')->format('Y-m-d');

        $pdo = \App\Database::connect();
        $waterEach = $pdo->prepare(
            'SELECT local_date, SUM(amount) AS total FROM water_logs
             WHERE user_id = :uid AND local_date BETWEEN :s AND :e
             GROUP BY local_date'
        );
        $waterEach->execute([':uid' => $uid, ':s' => $start, ':e' => $today->format('Y-m-d')]);
        $byDay = [];
        foreach ($waterEach->fetchAll() as $r) {
            $byDay[$r['local_date']] = (int) $r['total'];
        }
        $waterHits = 0;
        foreach ($byDay as $tot) {
            if ($tot >= $goal) $waterHits++;
        }

        $mindfulEach = $pdo->prepare(
            'SELECT local_date, SUM(duration_seconds) AS total FROM mindfulness_sessions
             WHERE user_id = :uid AND completed = 1 AND local_date BETWEEN :s AND :e
             GROUP BY local_date'
        );
        $mindfulEach->execute([':uid' => $uid, ':s' => $start, ':e' => $today->format('Y-m-d')]);
        $mindfulCount = 0;
        $mindfulTotalSec = 0;
        foreach ($mindfulEach->fetchAll() as $r) {
            $mindfulCount++;
            $mindfulTotalSec += (int) $r['total'];
        }

        $moveEach = $pdo->prepare(
            'SELECT COUNT(*) AS n FROM movement_logs
             WHERE user_id = :uid AND local_date BETWEEN :s AND :e'
        );
        $moveEach->execute([':uid' => $uid, ':s' => $start, ':e' => $today->format('Y-m-d')]);
        $moveCount = (int) $moveEach->fetchColumn();

        return [
            'water_hits'     => $waterHits,
            'water_total_days' => 7,
            'water_goal'     => $goal,
            'mindful_count'  => $mindfulCount,
            'mindful_minutes' => (int) round($mindfulTotalSec / 60),
            'movement_count' => $moveCount,
        ];
    }
}
