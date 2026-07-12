<?php
declare(strict_types=1);

namespace Controllers;

use App\Session;
use App\View;
use Models\Affirmation;
use Models\DateUtil;
use Models\MoodEntry;
use Models\MovementLog;
use Models\MindfulnessSession;
use Models\SleepLog;
use Models\Todo;
use Models\User;
use Models\WaterLog;

final class DashboardController
{
    public function __construct(private string $root) {}

    public function index(): void
    {
        $uid = Session::requireAuth();
        $user = User::find($uid);
        $tz = $user['timezone'] ?? 'UTC';
        $today = DateUtil::today($tz);

        $waterTotal = WaterLog::todayTotal($uid, $tz);
        $waterGoal = (int) $user['water_goal'];
        $waterPct  = $waterGoal > 0 ? min(100, (int) round(($waterTotal / $waterGoal) * 100)) : 0;

        $openTodos = Todo::listForDay($uid, $tz, $today, false);
        $mindfulStreak = MindfulnessSession::currentStreak($uid, $tz);
        $moveStreak = MovementLog::currentStreak($uid, $tz);
        $sleepStreak = SleepLog::currentStreak($uid, $tz);

        $mood = MoodEntry::today($uid, $tz);
        $affirmation = Affirmation::forDay($uid, $tz);

        View::render('dashboard', [
            'user' => $user,
            'tz' => $tz,
            'today' => $today,
            'pageTitle' => 'Today',
            'waterTotal' => $waterTotal,
            'waterGoal' => $waterGoal,
            'waterUnit' => $user['water_unit'],
            'waterPct' => $waterPct,
            'openTodosCount' => count($openTodos),
            'mindfulStreak' => $mindfulStreak,
            'moveStreak' => $moveStreak,
            'sleepStreak' => $sleepStreak,
            'mood' => $mood,
            'affirmation' => $affirmation,
        ]);
    }
}
