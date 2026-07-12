<?php
declare(strict_types=1);

namespace Controllers;

use App\Session;
use App\View;
use Models\DateUtil;
use Models\User;
use Models\WaterLog;

final class HydrationController
{
    public function __construct(private string $root) {}

    public function index(): void
    {
        $uid = Session::requireAuth();
        $user = User::find($uid);
        $tz = $user['timezone'] ?? 'UTC';

        $total = WaterLog::todayTotal($uid, $tz);
        $summary7 = WaterLog::summary($uid, $tz, 7);
        $summary30 = WaterLog::summary($uid, $tz, 30);
        $entries = WaterLog::todayEntries($uid, $tz);

        View::render('hydration', [
            'user' => $user,
            'tz' => $tz,
            'pageTitle' => 'Hydration',
            'todayTotal' => $total,
            'goal' => (int) $user['water_goal'],
            'unit' => $user['water_unit'],
            'pct' => ((int) $user['water_goal']) > 0 ? min(100, (int) round(($total / (int) $user['water_goal']) * 100)) : 0,
            'summary7' => $summary7,
            'summary30' => $summary30,
            'entries' => $entries,
        ]);
    }
}
