<?php
declare(strict_types=1);

namespace Models;

use App\Database;

/**
 * Per-(user, kind) reminder preference.
 *
 * The five kinds are hardcoded in code. drinking is interval-based
 * (threshold_minutes), the other four are time-of-day (scheduled_time).
 * ensureDefaults() lazily inserts the full set on first settings visit
 * so a brand-new user picks up sensible defaults without admin work.
 */
final class Reminder
{
    /** The five reminder kinds. drinking is interval; the others are time-of-day. */
    public const KINDS = ['drinking', 'mindful', 'intentions', 'mood', 'sleep'];

    /** Sensible defaults: drinking 120min interval; the rest 09:00 local.
     *
     * notify_email defaults to 1 (on) so new users automatically receive
     * email reminders without needing to visit /settings. Existing users
     * who registered before this change keep their existing opt-in state
     * (INSERT OR IGNORE is a no-op when the row already exists).
     */
    public const DEFAULTS = [
        'drinking'   => ['enabled' => 1, 'scheduled_time' => null,    'threshold_minutes' => 120, 'notify_email' => 1],
        'mindful'    => ['enabled' => 1, 'scheduled_time' => '09:00', 'threshold_minutes' => null, 'notify_email' => 1],
        'intentions' => ['enabled' => 1, 'scheduled_time' => '09:00', 'threshold_minutes' => null, 'notify_email' => 1],
        'mood'       => ['enabled' => 1, 'scheduled_time' => '21:00', 'threshold_minutes' => null, 'notify_email' => 1],
        'sleep'      => ['enabled' => 1, 'scheduled_time' => '22:30', 'threshold_minutes' => null, 'notify_email' => 1],
    ];

    /**
     * Lazily create the full set of pref rows for a user. Safe to call
     * repeatedly — `INSERT OR IGNORE` is a no-op when the row exists.
     */
    public static function ensureDefaults(int $userId, string $tz): void
    {
        $stmt = Database::connect()->prepare(
            'INSERT OR IGNORE INTO reminder_prefs
                 (user_id, kind, enabled, scheduled_time, threshold_minutes, notify_email)
             VALUES (:uid, :k, :e, :st, :thr, :ne)'
        );
        foreach (self::DEFAULTS as $kind => $d) {
            $stmt->execute([
                ':uid' => $userId,
                ':k'   => $kind,
                ':e'   => $d['enabled'],
                ':st'  => $d['scheduled_time'],
                ':thr' => $d['threshold_minutes'],
                ':ne'  => $d['notify_email'],
            ]);
        }
    }

    /** All pref rows for a user, keyed by kind. */
    public static function allFor(int $userId): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT * FROM reminder_prefs WHERE user_id = :uid'
        );
        $stmt->execute([':uid' => $userId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['kind']] = $row;
        }
        return $out;
    }

    /**
     * Update one pref row from a $_POST-shaped input.
     * Unknown kinds are ignored. scheduled_time is normalized to HH:MM.
     * threshold_minutes is clamped to [15, 360] (15min..6h).
     *
     * Implementation note: drinking kind writes `threshold_minutes`
     * only, and the time-of-day kinds write `scheduled_time` only —
     * never both. This matches the domain (interval-kind has no
     * time-of-day; time-of-day-kind has no interval) AND sidesteps a
     * live-database constraint gotcha: older production schemas had
     * `scheduled_time TEXT NOT NULL DEFAULT '09:00'`, so writing null
     * on the drinking row used to crash the request with a NOT NULL
     * constraint violation. `CREATE TABLE IF NOT EXISTS` cannot fix
     * that without an ALTER; the kind-specific UPDATE works against
     * either schema version because we never touch the "other"
     * column at all.
     */
    public static function update(int $userId, string $kind, array $input): void
    {
        if (!in_array($kind, self::KINDS, true)) return;

        $enabled     = !empty($input['enabled']) ? 1 : 0;
        $notifyEmail = !empty($input['notify_email']) ? 1 : 0;

        if ($kind === 'drinking') {
            // Interval kind: only threshold_minutes matters.
            $threshold = isset($input['threshold_minutes'])
                ? self::clampMinutes((int) $input['threshold_minutes'])
                : 120;

            $stmt = Database::connect()->prepare(
                'UPDATE reminder_prefs
                 SET enabled = :e, threshold_minutes = :thr, notify_email = :ne,
                     updated_at = strftime(\'%Y-%m-%dT%H:%M:%fZ\',\'now\')
                 WHERE user_id = :uid AND kind = :k'
            );
            $stmt->execute([
                ':e'   => $enabled,
                ':thr' => $threshold,
                ':ne'  => $notifyEmail,
                ':uid' => $userId,
                ':k'   => $kind,
            ]);
            return;
        }

        // Time-of-day kinds: only scheduled_time matters.
        // Mirror the SettingsController defensive-cast shape: a crafted
        // `scheduled_time[]=...` payload would otherwise trip an
        // "Array to string conversion" warning under (string). One null
        // check at the end collapses \"missing key\", \"non-string\",
        // and \"failed to parse HH:MM\" into the same fallback.
        $rawScheduled = $input['scheduled_time'] ?? null;
        $scheduledTime = is_string($rawScheduled) ? self::normalizeTime($rawScheduled) : null;
        if ($scheduledTime === null) $scheduledTime = '09:00';

        $stmt = Database::connect()->prepare(
            'UPDATE reminder_prefs
             SET enabled = :e, scheduled_time = :st, notify_email = :ne,
                 updated_at = strftime(\'%Y-%m-%dT%H:%M:%fZ\',\'now\')
             WHERE user_id = :uid AND kind = :k'
        );
        $stmt->execute([
            ':e'   => $enabled,
            ':st'  => $scheduledTime,
            ':ne'  => $notifyEmail,
            ':uid' => $userId,
            ':k'   => $kind,
        ]);
    }

    private static function normalizeTime(string $s): ?string
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $s, $m)) return null;
        $h = (int) $m[1]; $mi = (int) $m[2];
        if ($h < 0 || $h > 23 || $mi < 0 || $mi > 59) return null;
        return sprintf('%02d:%02d', $h, $mi);
    }

    private static function clampMinutes(int $n): int
    {
        if ($n < 15) return 15;
        if ($n > 360) return 360;
        return $n;
    }
}
