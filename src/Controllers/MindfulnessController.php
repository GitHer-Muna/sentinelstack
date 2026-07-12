<?php
declare(strict_types=1);

namespace Controllers;

use App\Session;
use App\View;
use Models\MindfulnessSession;
use Models\User;

final class MindfulnessController
{
    public function __construct(private string $root) {}

    public function index(): void
    {
        $uid = Session::requireAuth();
        $user = User::find($uid);
        $tz = $user['timezone'] ?? 'UTC';

        View::render('mindfulness', [
            'user' => $user,
            'tz' => $tz,
            'pageTitle' => 'Mindfulness',
            'recent' => MindfulnessSession::recent($uid, 14),
            'streak' => MindfulnessSession::currentStreak($uid, $tz),
        ]);
    }
}
