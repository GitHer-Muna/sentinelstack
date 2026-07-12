<?php
declare(strict_types=1);

namespace Controllers;

use App\Session;
use App\View;
use Models\MoodEntry;
use Models\User;

final class MoodController
{
    public function __construct(private string $root) {}

    public function index(): void
    {
        $uid = Session::requireAuth();
        $user = User::find($uid);
        $tz = $user['timezone'] ?? 'UTC';

        View::render('mood', [
            'user' => $user,
            'tz' => $tz,
            'pageTitle' => 'Mood & gratitude',
            'today' => MoodEntry::today($uid, $tz),
            'history' => MoodEntry::history($uid, 90),
            'moods' => MoodEntry::MOODS,
        ]);
    }
}
