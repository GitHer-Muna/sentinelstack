<?php
declare(strict_types=1);

namespace Controllers;

use App\Session;
use App\View;
use Models\SleepLog;
use Models\User;

final class SleepController
{
    public function __construct(private string $root) {}

    public function index(): void
    {
        $uid = Session::requireAuth();
        $user = User::find($uid);
        $tz = $user['timezone'] ?? 'UTC';

        View::render('sleep', [
            'user' => $user,
            'tz' => $tz,
            'pageTitle' => 'Sleep',
            'today' => SleepLog::today($uid, $tz),
            'recent' => SleepLog::recent($uid, 14),
            'streak' => SleepLog::currentStreak($uid, $tz),
            'avg14' => SleepLog::averageLastDays($uid, $tz, 14),
        ]);
    }
}
