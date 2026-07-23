<?php
declare(strict_types=1);

namespace Models;

use App\Database;
use App\Env;

/**
 * Reminder dispatcher — the engine that fires due reminders.
 *
 * Two modes:
 *  - **Time-of-day** (mindful / intentions / mood / sleep): fire if the user's
 *    local HH:MM matches `scheduled_time` AND no notification has already
 *    been logged for this kind on today's local_date. Idempotent across
 *    the minute boundary — cron at 09:00 fires, cron at 09:01 sees the
 *    row and skips.
 *  - **Interval** (drinking): fire if (now - last fire) >= threshold_minutes,
 *    OR this is the user's very first reminder of this kind.
 *
 * The optional email side-channel is gated on two things:
 *  1. .env `SEND_NOTIFICATIONS_EMAIL=true` (server-wide opt-in — keeps a
 *     self-hosted install without a working MTA from flooding the postfix
 *     queue).
 *  2. The per-(user, kind) `notify_email` opt-in on the pref row.
 * Both must be true for an email to go out.
 */
final class ReminderDispatcher
{
    /**
     * Time-of-day catch-up window, in minutes. A time-of-day reminder
     * whose scheduled HH:MM has passed by at most THIS many minutes
     * still gets fired (de-duped against alreadyFiredToday). This
     * smooths over brief cron jitter (1-2 min late) without flooding
     * the inbox if cron was offline for an extended period. The
     * interval kind (drinking) has its own threshold_minutes; this
     * knob only affects the four time-of-day kinds.
     */
    private const TIME_OF_DAY_CATCHUP_MIN = 5;

    /** Returns the pref rows that should fire for $userId at $now. */
    public static function dueFor(int $userId, string $tz, \DateTimeImmutable $now): array
    {
        // Master pause — when set to a future timestamp, skip every fire
        // regardless of per-kind enabled state.
        if (User::pausedUntil($userId, $now) !== null) {
            return [];
        }

        $stmt = Database::connect()->prepare(
            'SELECT * FROM reminder_prefs WHERE user_id = :uid AND enabled = 1'
        );
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll();
        if (!$rows) return [];

        // Compute the user's local clock ONCE outside the loop — the
        // per-iteration setTimezone() is harmless but pointless and
        // muddies the diff between "we recomputed because the wall
        // moved" vs "we recomputed for no reason".
        $nowInTz    = $now->setTimezone(new \DateTimeZone($tz));
        $todayLocal = $nowInTz->format('Y-m-d');
        $nowMin     = ((int) $nowInTz->format('H')) * 60 + ((int) $nowInTz->format('i'));

        $fired = [];
        foreach ($rows as $r) {
            $kind = (string) $r['kind'];

            if ($kind === 'drinking' && !empty($r['threshold_minutes'])) {
                $lastLocal = self::lastLocalFire($userId, 'drinking', $tz);
                $minsSince = $lastLocal
                    ? (int) floor(($now->getTimestamp() - $lastLocal->getTimestamp()) / 60)
                    : PHP_INT_MAX;
                if ($minsSince >= (int) $r['threshold_minutes']) {
                    $fired[] = $r;
                }
                continue;
            }

            // Time-of-day. Skip junk schedules so a malformed row never
            // matches the "missed by N minutes" math below (which would
            // otherwise silently treat '' as minute 0).
            $scheduled = (string) ($r['scheduled_time'] ?? '');
            if (!preg_match('/^(\d{2}):(\d{2})$/', $scheduled, $m)) continue;
            $schedMin = ((int) $m[1]) * 60 + ((int) $m[2]);
            $delta = $nowMin - $schedMin;

            // Three cases:
            //   delta <  0                    — schedule is later today, skip
            //   delta == 0 (or >0 catch-up)   — schedule has passed today;
            //                                   fire only if not already fired
            //   delta >  CATCHUP_MIN          — too late for today's bell
            //                                   (next-day fire is gated by
            //                                   alreadyFiredToday on the new day)
            if ($delta < 0) continue;
            if ($delta > self::TIME_OF_DAY_CATCHUP_MIN) continue;
            if (self::alreadyFiredToday($userId, $kind, $todayLocal)) continue;
            $fired[] = $r;
        }
        return $fired;
    }

    /**
     * Insert an in-app notification + (optionally) send an email.
     *
     * Returns the inserted notification id. On email failure the row
     * is still written, with delivered_email=0 so the drawer can show
     * a paper-airplane status indicator.
     */
    public static function fire(int $userId, array $pref, string $tz): int
    {
        $kind = (string) ($pref['kind'] ?? '');
        if ($kind === '') return 0;

        $body = self::compose($kind);
        $id = Notification::log($userId, $kind, $body, $tz);

        $envEnabled  = filter_var(Env::get('SEND_NOTIFICATIONS_EMAIL', 'false'), FILTER_VALIDATE_BOOLEAN);
        $userOptedIn = !empty($pref['notify_email']);
        if ($envEnabled && $userOptedIn) {
            $email = self::userEmail($userId);
            if ($email !== null) {
                $ok = \App\Smtp::send($email, self::subject($kind), $body);
                Notification::markDelivered($id, $ok);
                error_log(sprintf(
                    '[sentinelstack] mail %s to=%s kind=%s',
                    $ok ? 'sent' : 'FAILED',
                    $email,
                    $kind
                ));
            }
        }
        return $id;
    }

    /** Short user-facing copy for the fired reminder. */
    public static function compose(string $kind): string
    {
        return match ($kind) {
            'drinking'   => 'A glass of water would be a kind thing to do for yourself.',
            'mindful'    => 'Two minutes of breath. The kettle can wait.',
            'intentions' => 'What would help future-you today? A small item fits.',
            'mood'       => 'A short check-in is enough. Honest is fine.',
            'sleep'      => 'A long Tuesday is still a Tuesday. Begin tonight’s wind-down.',
            default      => 'A small reminder, from SentinelStack.',
        };
    }

    /** Email subject line. */
    public static function subject(string $kind): string
    {
        return match ($kind) {
            'drinking'   => 'Time for a sip',
            'mindful'    => 'Two minutes of breath',
            'intentions' => 'Today’s intention',
            'mood'       => 'A quick check-in',
            'sleep'      => 'Wind-down has arrived',
            default      => 'A small reminder',
        };
    }

    /** Has this (user, kind) already fired today in the user's local clock? */
    private static function alreadyFiredToday(int $userId, string $kind, string $todayLocal): bool
    {
        $stmt = Database::connect()->prepare(
            'SELECT 1 FROM notifications
             WHERE user_id = :uid AND kind = :k AND substr(fired_at_local, 1, 10) = :day
             LIMIT 1'
        );
        $stmt->execute([':uid' => $userId, ':k' => $kind, ':day' => $todayLocal]);
        return (bool) $stmt->fetchColumn();
    }

    /** Most recent fired_at_local for a (user, kind), interpreted in the user's TZ. */
    public static function lastLocalFire(int $userId, string $kind, string $tz): ?\DateTimeImmutable
    {
        $stmt = Database::connect()->prepare(
            'SELECT fired_at_local FROM notifications
             WHERE user_id = :uid AND kind = :k
             ORDER BY fired_at_local DESC LIMIT 1'
        );
        $stmt->execute([':uid' => $userId, ':k' => $kind]);
        $val = $stmt->fetchColumn();
        if (!$val) return null;
        try {
            return new \DateTimeImmutable((string) $val, new \DateTimeZone($tz));
        } catch (\Exception) {
            return null;
        }
    }

    private static function userEmail(int $userId): ?string
    {
        $stmt = Database::connect()->prepare('SELECT email FROM users WHERE id = :uid');
        $stmt->execute([':uid' => $userId]);
        $e = $stmt->fetchColumn();
        return $e ?: null;
    }
}
