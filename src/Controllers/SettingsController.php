<?php
declare(strict_types=1);

namespace Controllers;

use App\Csrf;
use App\Response;
use App\Session;
use App\Validator;
use App\View;
use Models\User;

final class SettingsController
{
    public function __construct(private string $root) {}

    public function index(): void
    {
        $uid = Session::requireAuth();
        $user = User::find($uid);

        View::render('settings', [
            'user' => $user,
            'pageTitle' => 'Settings',
            'flash' => Session::flash('notice'),
            'error' => Session::flash('error'),
        ]);
    }

    public function update(): void
    {
        Csrf::requireValid();
        $uid = Session::requireAuth();
        $user = User::find($uid);

        $changes = [];
        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        if (Validator::nonEmpty($displayName, 80) !== null && $displayName !== $user['display_name']) {
            $changes['display_name'] = $displayName;
        }
        $tz = (string) ($_POST['timezone'] ?? '');
        if (Validator::timezone($tz) !== null && $tz !== $user['timezone']) {
            $changes['timezone'] = $tz;
        }
        $theme = (string) ($_POST['theme'] ?? 'light');
        if (in_array($theme, ['light','dark'], true)) $changes['theme'] = $theme;
        $unit = (string) ($_POST['water_unit'] ?? 'ml');
        if (in_array($unit, ['ml','oz'], true)) $changes['water_unit'] = $unit;
        $goal = filter_var($_POST['water_goal'] ?? 0, FILTER_VALIDATE_INT);
        if ($goal !== false && $goal >= 250 && $goal <= 8000) $changes['water_goal'] = $goal;

        if ($changes) {
            User::update($uid, $changes);
        }
        Session::flash('notice', 'Settings saved.');
        Response::redirect('/settings');
    }

    public function changePassword(): void
    {
        Csrf::requireValid();
        $uid = Session::requireAuth();
        $user = User::find($uid);

        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');

        if (!User::verifyPassword($user, $current)) {
            Session::flash('error', 'The current password you entered is not correct.');
            Response::redirect('/settings');
        }
        if (!Validator::password($new)) {
            Session::flash('error', 'Choose a new password of at least 8 characters.');
            Response::redirect('/settings');
        }
        if ($new !== $confirm) {
            Session::flash('error', 'The new passwords do not match.');
            Response::redirect('/settings');
        }

        User::updatePassword($uid, $new);
        Session::flash('notice', 'Password updated.');
        Response::redirect('/settings');
    }

    public function deleteAccount(): void
    {
        Csrf::requireValid();
        $uid = Session::requireAuth();
        $confirm = trim((string) ($_POST['confirm'] ?? ''));
        if ($confirm !== 'delete') {
            Session::flash('error', 'Type the word "delete" to confirm.');
            Response::redirect('/settings');
        }
        User::delete($uid);
        Session::flush();
        Response::redirect('/login');
    }
}
