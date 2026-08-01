<?php
declare(strict_types=1);

/**
 * Reminder cron — runs every 60 seconds via the cron container.
 *
 * For each user with at least one enabled reminder pref, checks whether
 * any reminders are due and fires them. Email is sent inside fire() when
 * SEND_NOTIFICATIONS_EMAIL=true and the per-kind notify_email flag is set.
 */

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

App\Env::load($root . '/.env');

use App\Database;
use Models\ReminderDispatcher;

$now     = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
$started = microtime(true);
$fired   = 0;

$users = Database::connect()->query(
    'SELECT DISTINCT u.id, u.timezone
     FROM users u
     JOIN reminder_prefs rp ON rp.user_id = u.id AND rp.enabled = 1'
)->fetchAll(\PDO::FETCH_ASSOC);

foreach ($users as $u) {
    $uid = (int) $u['id'];
    $tz  = (string) ($u['timezone'] ?: 'UTC');

    foreach (ReminderDispatcher::dueFor($uid, $tz, $now) as $pref) {
        try {
            $id = ReminderDispatcher::fire($uid, $pref, $tz);
            if ($id > 0) {
                $fired++;
                echo "[cron] fired uid=$uid kind={$pref['kind']} notif_id=$id\n";
            }
        } catch (\Throwable $e) {
            error_log("[cron] uid=$uid kind={$pref['kind']}: " . $e->getMessage());
        }
    }
}

$ms = round((microtime(true) - $started) * 1000);
echo "[cron] {$fired} fired — " . $now->format('Y-m-d H:i') . " UTC ({$ms}ms)\n";
