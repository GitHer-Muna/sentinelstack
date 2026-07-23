<?php
declare(strict_types=1);

namespace Models;

use App\Database;

/**
 * In-app inbox for fired reminders.
 *
 * The dispatcher writes a row each time a reminder fires (ReminderDispatcher::fire).
 * The bell drawer reads via recent() + unread(). The markRead* methods stamp
 * read_at so the unread badge in the header counts down.
 */
final class Notification
{
    /**
     * Write a new notification. Returns the row id.
     *
     * fired_at_local is the user's local clock at the moment the cron ticked,
     * NOT a UTC timestamp. The dispatcher is responsible for converting its
     * `now` (which is UTC) to the user's TZ before calling this.
     */
    public static function log(int $userId, string $kind, string $body, string $tz): int
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $local = $now->setTimezone(new \DateTimeZone($tz));
        $fired = $local->format('Y-m-d H:i:s');

        $stmt = Database::connect()->prepare(
            'INSERT INTO notifications (user_id, kind, body, fired_at_local)
             VALUES (:uid, :k, :b, :f)'
        );
        $stmt->execute([
            ':uid' => $userId,
            ':k'   => $kind,
            ':b'   => $body,
            ':f'   => $fired,
        ]);
        return (int) Database::connect()->lastInsertId();
    }

    /**
     * Most recent N notifications for a user, newest first.
     *
     * Each row is augmented with `fired_at_display` — a server-formatted
     * "9:00 AM" / "Nov 1, 9:00 AM" string in the USER's timezone. Why
     * server-side? Because `fired_at_local` is stored WITHOUT a TZ
     * suffix, and the browser would otherwise interpret it in the
     * device's local TZ — wrong for a user on a phone that's traveling,
     * running a guest VM, etc. (Notification::log writes the user's
     * local wall clock with no offset, deliberately — TZ-agnostic so a
     * user who moves timezones can re-display correctly. But for THIS
     * row, at render time, we know the user's TZ and can format on the
     * server, eliminating the device-TZ bug.)
     *
     * The optional $tz argument skips a per-call `SELECT timezone FROM
     * users` roundtrip when the caller already has the user row in
     * hand (the API controller does — `User::find($uid)` precedes this
     * call). Falls back to the DB lookup if not provided. This isn't
     * expensive in absolute terms (cheap prep+SQLite fetch), but it runs
     * on every poll (60s + on drawer open), so threading the value is
     * the polite thing for the DB.
     */
    public static function recent(int $userId, int $limit = 30, ?string $tz = null): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT id, kind, body, fired_at_local, delivered_email, read_at
             FROM notifications
             WHERE user_id = :uid
             ORDER BY fired_at_local DESC, id DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        if ($tz === null || $tz === '') {
            $tz = self::resolveUserTz($userId);
        }
        $rows = $stmt->fetchAll();
        foreach ($rows as &$n) {
            $n['fired_at_display'] = self::formatDisplay((string) $n['fired_at_local'], $tz);
        }
        return $rows;
    }

    /** Look up the user's stored timezone. Falls back to UTC. */
    private static function resolveUserTz(int $userId): string
    {
        $stmt = Database::connect()->prepare('SELECT timezone FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $val = $stmt->fetchColumn();
        return is_string($val) && $val !== '' ? $val : 'UTC';
    }

    /**
     * Format a wall-clock string (no TZ suffix) as a human-readable
     * label in the user's timezone. "Today" rows become "g:i A";
     * anything older becomes "M j, g:i A". Garbage input returns its
     * raw text rather than '1970-01-01' — the JS drawer uses this as
     * authoritative display, so a parse failure should stay visible.
     */
    private static function formatDisplay(string $localNoTz, string $tz): string
    {
        try {
            $dt = new \DateTimeImmutable($localNoTz, new \DateTimeZone($tz));
        } catch (\Exception) {
            return $localNoTz;
        }
        $now = new \DateTimeImmutable('now', $dt->getTimezone());
        if ($dt->format('Y-m-d') === $now->format('Y-m-d')) {
            return $dt->format('g:i A');
        }
        return $dt->format('M j, g:i A');
    }

    /** Unread count for the bell badge. */
    public static function unread(int $userId): int
    {
        $stmt = Database::connect()->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND read_at IS NULL'
        );
        $stmt->execute([':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /** Mark a single notification read. The row is stamped in the user's local TZ. */
    public static function markRead(int $userId, int $id, string $tz): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $local = $now->setTimezone(new \DateTimeZone($tz))->format('Y-m-d H:i:s');
        $stmt = Database::connect()->prepare(
            'UPDATE notifications SET read_at = :r
             WHERE id = :id AND user_id = :uid AND read_at IS NULL'
        );
        $stmt->execute([':r' => $local, ':id' => $id, ':uid' => $userId]);
    }

    /** Mark every unread notification read for the user. */
    public static function markAllRead(int $userId, string $tz): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $local = $now->setTimezone(new \DateTimeZone($tz))->format('Y-m-d H:i:s');
        $stmt = Database::connect()->prepare(
            'UPDATE notifications SET read_at = :r
             WHERE user_id = :uid AND read_at IS NULL'
        );
        $stmt->execute([':r' => $local, ':uid' => $userId]);
    }

    /** Stamp delivered_email on a single row (called by the dispatcher after SMTP returns). */
    public static function markDelivered(int $id, bool $ok): void
    {
        $stmt = Database::connect()->prepare(
            'UPDATE notifications SET delivered_email = :d WHERE id = :id'
        );
        $stmt->execute([':d' => $ok ? 1 : 0, ':id' => $id]);
    }
}
