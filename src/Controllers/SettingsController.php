<?php
declare(strict_types=1);

namespace Controllers;

use App\Csrf;
use App\Database;
use App\Env;
use App\Response;
use App\Session;
use App\Smtp;
use App\Validator;
use App\View;
use Models\Notification;
use Models\Reminder;
use Models\User;

final class SettingsController
{
    /**
     * Process-lifetime cache for the user-facing brand string. Filled
     * lazily by brandName() — static so all instances of this controller
     * within a single request share one read of composer.json.
     *
     * The explicit `= null` is load-bearing: PHP 8.0+ typed nullable
     * properties default to the canonical null, but accessing the
     * property fires "must not be accessed before initialization" on the
     * first read if no value is ever assigned. The initial `= null`
     * marks the property as initialized (with null) and lets the
     * `self::$brandCache !== null` guard work on the very first call.
     */
    private static ?string $brandCache = null;

    public function __construct(private string $root) {}

    public function index(): void
    {
        $uid = Session::requireAuth();
        $user = User::find($uid);
        $tz = $user['timezone'] ?? 'UTC';
        Reminder::ensureDefaults($uid, $tz);

        $reminders = Reminder::allFor($uid);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $pausedUntil = User::pausedUntil($uid, $now);
        $pausedHuman = $pausedUntil
            ? self::humanizeUntil($pausedUntil, $tz)
            : null;

        View::render('settings', [
            'user' => $user,
            'tz' => $tz,
            'pageTitle' => 'Settings',
            'flash' => Session::flash('notice'),
            'error' => Session::flash('error'),
            'reminders' => $reminders,
            'pausedUntil' => $pausedUntil,
            'pausedHuman' => $pausedHuman,
            'emailEnabled' => filter_var(Env::get('SEND_NOTIFICATIONS_EMAIL', 'false'), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function updateReminders(): void
    {
        Csrf::requireValid();
        $uid = Session::requireAuth();
        $user = User::find($uid);
        $tz = $user['timezone'] ?? 'UTC';
        Reminder::ensureDefaults($uid, $tz);

        $posted = is_array($_POST['reminders'] ?? null) ? $_POST['reminders'] : [];
        foreach (Reminder::KINDS as $kind) {
            $row = is_array($posted[$kind] ?? null) ? $posted[$kind] : [];
            Reminder::update($uid, $kind, $row);
        }

        Session::flash('notice', 'Reminders saved.');
        Response::redirect('/settings');
    }

    public function setPause(): void
    {
        Csrf::requireValid();
        $uid = Session::requireAuth();
        $user = User::find($uid);
        $tz = $user['timezone'] ?? 'UTC';

        // Defend against crafted payloads like `duration[]=1h`. Without
        // this guard the `(string)` cast emits an "Array to string
        // conversion" warning and lets `in_array` skip the check
        // silently, neither of which is what we want.
        $raw = $_POST['duration'] ?? null;
        $duration = is_string($raw) ? $raw : '';
        $allowed = ['off', '1h', '3h', 'evening', 'bedtime', 'tomorrow'];
        if (!in_array($duration, $allowed, true)) {
            Session::flash('error', 'Unknown pause duration.');
            Response::redirect('/settings');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        User::setPauseByDuration($uid, $duration, $tz, $now);

        Session::flash('notice', $duration === 'off' ? 'Reminders resumed.' : 'Reminders paused.');
        Response::redirect('/settings');
    }

    public function testNotification(): void
    {
        Csrf::requireValid();
        $uid = Session::requireAuth();
        $user = User::find($uid);
        $tz = $user['timezone'] ?? 'UTC';

        Notification::log($uid, 'intentions',
            'This is a test notification. If you can see it in the bell drawer, in-app reminders are working.',
            $tz);

        Session::flash('notice', 'Test notification sent. Open the bell to see it.');
        Response::redirect('/settings');
    }

    public function testEmail(): void
    {
        Csrf::requireValid();
        $uid = Session::requireAuth();
        $user = User::find($uid);

        $envEnabled = filter_var(Env::get('SEND_NOTIFICATIONS_EMAIL', 'false'), FILTER_VALIDATE_BOOLEAN);
        if (!$envEnabled) {
            Session::flash('error', 'Email reminders are disabled. Set SEND_NOTIFICATIONS_EMAIL=true in .env and try again.');
            Response::redirect('/settings');
        }

        $email = (string) ($user['email'] ?? '');
        if ($email === '') {
            Session::flash('error', 'No email address on file for this account.');
            Response::redirect('/settings');
        }

        $tz = $user['timezone'] ?? 'UTC';
        // One source of truth for the brand string in this method. If the
        // product is ever re-branded (portfolio review, fork, etc.) —
        // update this single line rather than chasing four hardcoded
        // copies through user-facing email copy.
        $brand = $this->brandName();
        $subject = "{$brand} test email";
        $body = "This is a test email from {$brand}.\n\n"
              . "If you got this, your email side-channel is working.\n"
              . "To enable email on a specific reminder kind, tick the \"Email me too\" checkbox on /settings and Save reminders.\n\n"
              . "— {$brand}\n";
        $ok = Smtp::send($email, $subject, $body);

        $notifBody = $ok
            ? "Test email sent to {$email}. If you also see it in your inbox, the email side-channel works."
            : "Test email FAILED to send. Check PHP error log for [sentinelstack] details; verify NOTIFICATION_SMTP_* in .env.";
        $notifId = Notification::log($uid, 'intentions', $notifBody, $tz);
        if ($notifId > 0) {
            Notification::markDelivered($notifId, $ok);
        }

        if ($ok) {
            Session::flash('notice', 'Test email sent to ' . $email . '. Open the bell — the test entry is logged there too.');
        } else {
            Session::flash('error', 'Test email FAILED. Check the PHP error log and your .env SMTP settings.');
        }
        Response::redirect('/settings');
    }

    public function update(): void
    {
        Csrf::requireValid();
        $uid = Session::requireAuth();
        $user = User::find($uid);

        // Drop array-shaped payloads (`display_name[]=...`) so a crafted
        // POST can't trip a PHP "Array to string conversion" warning —
        // (string)-cast on an array emits a warning; is_string() is the
        // silent non-warning re-route. The same shape is used in
        // changePassword() / deleteAccount() / setPause() so a future
        // glance at this controller tells a consistent story.
        $rawName  = $_POST['display_name'] ?? null;
        $rawTz    = $_POST['timezone'] ?? null;
        $rawTheme = $_POST['theme'] ?? null;
        $rawUnit  = $_POST['water_unit'] ?? null;

        $changes = [];
        $displayName = is_string($rawName) ? trim($rawName) : '';
        if (Validator::nonEmpty($displayName, 80) !== null && $displayName !== $user['display_name']) {
            $changes['display_name'] = $displayName;
        }
        $tz = is_string($rawTz) ? $rawTz : '';
        if (Validator::timezone($tz) !== null && $tz !== $user['timezone']) {
            $changes['timezone'] = $tz;
        }
        $theme = is_string($rawTheme) ? $rawTheme : 'light';
        if (in_array($theme, ['light','dark'], true)) $changes['theme'] = $theme;
        $unit = is_string($rawUnit) ? $rawUnit : 'ml';
        if (in_array($unit, ['ml','oz'], true)) $changes['water_unit'] = $unit;
        // PHP 8+: filter_var on a non-scalar returns false silently, no
        // warning, so an array-shaped payload here cleanly drops out
        // without us needing an explicit is_numeric() short-circuit.
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

        // Same defensive cast shape as update() / deleteAccount() /
        // setPause(): a crafted `current_password[]=...` payload would
        // otherwise trip an "Array to string conversion" warning and we
        // want a clean silent rejection for malformed input.
        $rawCurrent = $_POST['current_password'] ?? null;
        $rawNew     = $_POST['new_password'] ?? null;
        $rawConfirm = $_POST['new_password_confirm'] ?? null;
        $current = is_string($rawCurrent) ? $rawCurrent : '';
        $new     = is_string($rawNew)     ? $rawNew     : '';
        $confirm = is_string($rawConfirm) ? $rawConfirm : '';

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
        // Same defensive cast shape as update() / changePassword() /
        // setPause(): a crafted `confirm[]=...` payload would otherwise
        // trip an "Array to string conversion" warning; we want a clean
        // silent rejection for malformed input.
        $rawConfirm = $_POST['confirm'] ?? null;
        $confirm = is_string($rawConfirm) ? trim($rawConfirm) : '';
        // Case-insensitive: the form's pattern="(?i)^delete$" already
        // normalises "Delete", "DELETE", "delete" — match it server-side
        // so a user whose Caps Lock tripped up at the worst possible
        // moment still gets through without surprise frustration.
        if (strtolower($confirm) !== 'delete') {
            Session::flash('error', 'Type the word "delete" to confirm.');
            Response::redirect('/settings');
        }
        User::delete($uid);
        Session::flush();
        Response::redirect('/login');
    }

    /**
     * Resolve the brand name for outbound test emails. composer.json's
     * `name` field is the canonical source; we fall back to a sensible
     * default if it's missing or unreadable. The raw composer name is
     * conventionally `vendor/project` slug (e.g. `acme/well-being-tracker`)
     * — user-facing copy wants the tail only, humanised (each token
     * title-cased). Memoised per process so testEmail() doesn't re-read
     * the fixture file on every click.
     */
    private function brandName(): string
    {
        if (self::$brandCache !== null) return self::$brandCache;
        $fallback = 'SentinelStack';
        $candidate = $fallback;
        $raw = '';
        if ($this->root !== '') {
            // @-suppress warning so a missing composer.json is a silent
            // skip rather than a PHP warning on the response page.
            $raw = (string) @file_get_contents($this->root . '/composer.json');
        }
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && !empty($decoded['name']) && is_string($decoded['name'])) {
                // composer.json's `name` is *recommended* to be `vendor/project`
                // but private apps often ship a flat slug like `well-being-tracker`.
                // explode + end gives us the tail in both shapes — unlike
                // strrchr($name . '/', '/') which silently returns '' for the
                // no-slash case and leaks to the fallback.
                $parts = explode('/', (string) $decoded['name']);
                $tail  = strtolower(trim((string) end($parts)));
                if ($tail !== '') {
                    $tokens = preg_split('/[-_\s]+/', $tail, -1, PREG_SPLIT_NO_EMPTY);
                    if (!empty($tokens)) {
                        $candidate = implode(' ', array_map(
                            static function (string $t): string {
                                // mb_convert_case is locale-aware; ucfirst is
                                // byte-level and would mangle the first byte of
                                // a multi-byte UTF-8 character (e.g. Äpfel).
                                // Fallback to ucfirst only if mbstring is off.
                                return function_exists('mb_convert_case')
                                    ? mb_convert_case($t, MB_CASE_TITLE, 'UTF-8')
                                    : ucfirst($t);
                            },
                            $tokens
                        ));
                    }
                }
            }
        }
        return self::$brandCache = $candidate !== '' ? $candidate : $fallback;
    }

    /**
     * Pretty-print a future timestamp in the user's local TZ.
     * e.g. "in 45 minutes", "at 9:00 AM tomorrow", "until 6:00 PM today".
     */
    private static function humanizeUntil(\DateTimeImmutable $until, string $tz): string
    {
        try {
            $local = $until->setTimezone(new \DateTimeZone($tz));
        } catch (\Exception) {
            $local = $until;
        }
        $now = new \DateTimeImmutable('now', $local->getTimezone());
        $diff = $local->getTimestamp() - $now->getTimestamp();
        $absDiff = abs($diff);
        $sameDay = $local->format('Y-m-d') === $now->format('Y-m-d');
        $time = $local->format('g:i A');
        if ($absDiff < 90) {
            $mins = max(1, (int) round($diff / 60));
            return $diff >= 0 ? "in $mins minute" . ($mins === 1 ? '' : 's') : "$mins minute" . ($mins === 1 ? '' : 's') . ' ago';
        }
        if ($sameDay) return "at $time today";
        $tomorrow = (clone $now)->modify('+1 day')->format('Y-m-d');
        if ($local->format('Y-m-d') === $tomorrow) return "at $time tomorrow";
        return "at $time on " . $local->format('M j');
    }
}
