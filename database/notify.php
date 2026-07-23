<?php
declare(strict_types=1);

/**
 * SentinelStack — cron dispatcher.
 *
 * Run once per minute from cron. For every user, asks the dispatcher
 * which (user, kind) rows are due right now, then fires each one.
 *
 * Crontab:
 *   * * * * * /usr/bin/php /path/to/sentinelstack/database/notify.php >> /var/log/sentinelstack-notify.log 2>&1
 *
 * Dry-run mode for testing the cron job without writing:
 *   php database/notify.php --dry-run
 *
 * Idempotency is the dispatcher's job (time-of-day: skip if already fired
 * today; interval: skip if (now - last fire) < threshold). A missed minute
 * is fine — the next minute catches up.
 */

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Composer dependencies are missing. Run: composer install\n");
    exit(1);
}
require $autoload;

App\App::boot($root);

$dryRun = in_array('--dry-run', $argv ?? [], true);

$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

// Idempotent migration for the users.notifications_paused_until column.
// Safe to call on every cron tick — the PRAGMA check is cheap.
\Models\User::ensureSchema();

$users = App\Database::connect()->query('SELECT id, email, timezone FROM users')->fetchAll();
if (!$users) {
    if ($dryRun) echo "No users.\n";
    exit(0);
}

$totalFired = 0;
foreach ($users as $u) {
    $uid = (int) $u['id'];
    $tz  = (string) ($u['timezone'] ?: 'UTC');
    try {
        $due = \Models\ReminderDispatcher::dueFor($uid, $tz, $now);
    } catch (Throwable $e) {
        error_log(sprintf('[sentinelstack notify] dueFor user=%d failed: %s', $uid, $e->getMessage()));
        continue;
    }
    if (!$due) continue;

    foreach ($due as $pref) {
        $kind = (string) $pref['kind'];
        if ($dryRun) {
            printf("  [dry-run] user=%d kind=%s tz=%s\n", $uid, $kind, $tz);
            $totalFired++;
            continue;
        }
        try {
            $id = \Models\ReminderDispatcher::fire($uid, $pref, $tz);
            if ($id) {
                $totalFired++;
                printf("  user=%d kind=%s id=%d\n", $uid, $kind, $id);
            }
        } catch (Throwable $e) {
            error_log(sprintf('[sentinelstack notify] fire user=%d kind=%s failed: %s', $uid, $kind, $e->getMessage()));
        }
    }
}

if ($dryRun) {
    echo "Would fire: $totalFired reminder(s).\n";
} else {
    echo "Fired: $totalFired reminder(s).\n";
}
