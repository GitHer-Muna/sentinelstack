<?php
declare(strict_types=1);

namespace Controllers;

use App\Session;
use App\View;
use Models\MovementLog;
use Models\User;

final class MovementController
{
    public function __construct(private string $root) {}

    public function index(): void
    {
        $uid = Session::requireAuth();
        $user = User::find($uid);
        $tz = $user['timezone'] ?? 'UTC';

        $routines = MovementLog::routines();
        $today = MovementLog::today($uid, $tz);

        View::render('movement', [
            'user' => $user,
            'tz' => $tz,
            'pageTitle' => 'Movement',
            'routines' => $routines,
            'today' => $today,
        ]);
    }
}
