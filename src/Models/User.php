<?php
declare(strict_types=1);

namespace Models;

use App\Database;

final class User
{
    /**
     * On-the-fly schema migration for users.notifications_paused_until.
     * Cheap PRAGMA check; ALTER is a no-op once the column exists.
     * Called from App::boot (every web request) and from the cron
     * dispatcher (database/notify.php).
     */
    public static function ensureSchema(): void
    {
        $cols = Database::connect()
            ->query("PRAGMA table_info(users)")
            ->fetchAll(\PDO::FETCH_COLUMN, 1);
        if (in_array('notifications_paused_until', $cols, true)) {
            return;
        }
        Database::connect()->exec(
            'ALTER TABLE users ADD COLUMN notifications_paused_until TEXT'
        );
    }

    /**
     * Returns the future paused-until timestamp for $userId, or null if
     * the user is not currently paused. Past timestamps (a pause that
     * has expired) read as null so the dispatcher naturally resumes.
     */
    public static function pausedUntil(int $userId, \DateTimeImmutable $now): ?\DateTimeImmutable
    {
        $stmt = Database::connect()->prepare(
            'SELECT notifications_paused_until FROM users WHERE id = :uid'
        );
        $stmt->execute([':uid' => $userId]);
        $val = $stmt->fetchColumn();
        if (!$val) return null;
        try {
            $until = new \DateTimeImmutable((string) $val);
        } catch (\Exception) {
            return null;
        }
        return $until > $now ? $until : null;
    }

    /** Set or clear the master pause. Pass null to clear. */
    public static function setPausedUntil(int $userId, ?\DateTimeImmutable $until): void
    {
        $val = $until?->format(\DateTimeInterface::ATOM);
        Database::connect()->prepare(
            'UPDATE users
             SET notifications_paused_until = :v,
                 updated_at = strftime(\'%Y-%m-%dT%H:%M:%fZ\',\'now\')
             WHERE id = :uid'
        )->execute([':v' => $val, ':uid' => $userId]);
    }

    /**
     * Pause by a short named duration. Returns the resolved timestamp.
     * Accepts: 'off' (clears), '1h', '3h', 'evening' (18:00 in $tz),
     * 'bedtime' (22:30 in $tz), 'tomorrow' (next 09:00 in $tz).
     * Unknown durations are a no-op (returns the current state).
     */
    public static function setPauseByDuration(
        int $userId,
        string $duration,
        string $tz,
        \DateTimeImmutable $now,
    ): ?\DateTimeImmutable {
        if ($duration === 'off' || $duration === '') {
            self::setPausedUntil($userId, null);
            return null;
        }
        $until = match ($duration) {
            '1h'       => $now->modify('+1 hour'),
            '3h'       => $now->modify('+3 hours'),
            'evening'  => self::nextOccurrenceInTz('18:00', $tz, $now),
            'bedtime'  => self::nextOccurrenceInTz('22:30', $tz, $now),
            'tomorrow' => self::tomorrowAtInTz('09:00', $tz, $now),
            default    => null,
        };
        if ($until === null) {
            return self::pausedUntil($userId, $now);
        }
        self::setPausedUntil($userId, $until);
        return $until;
    }

    /**
     * Returns the next occurrence of $hhmm in $tz on or after $now.
     * If today's HH:MM has already passed in $tz, returns tomorrow's.
     */
    private static function nextOccurrenceInTz(
        string $hhmm,
        string $tz,
        \DateTimeImmutable $now,
    ): \DateTimeImmutable {
        try {
            $local = $now->setTimezone(new \DateTimeZone($tz));
        } catch (\Exception) {
            $local = $now;
        }
        $today = $local->format('Y-m-d');
        $candidate = new \DateTimeImmutable(
            "$today $hhmm:00",
            $local->getTimezone()
        );
        if ($candidate <= $local) {
            $candidate = $candidate->modify('+1 day');
        }
        return $candidate;
    }

    /**
     * Returns tomorrow's HH:MM in $tz. The "Until tomorrow" chip means
     * "silence for the rest of today and the first chunk of tomorrow,
     * resuming at tomorrow's HH:MM" — so we always add a day, even when
     * today's HH:MM is still in the future.
     */
    private static function tomorrowAtInTz(
        string $hhmm,
        string $tz,
        \DateTimeImmutable $now,
    ): \DateTimeImmutable {
        try {
            $local = $now->setTimezone(new \DateTimeZone($tz));
        } catch (\Exception) {
            $local = $now;
        }
        $today = $local->format('Y-m-d');
        return new \DateTimeImmutable(
            "$today $hhmm:00",
            $local->getTimezone()
        )->modify('+1 day');
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::connect()->prepare(
            'SELECT * FROM users WHERE email = :email COLLATE NOCASE'
        );
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $email, string $displayName, string $password, string $timezone): int
    {
        // BCrypt silently truncates >72 bytes (and returns false on PHP 8.0+);
        // Argon2id has no such limit and is recommended by PHP for new apps.
        $hash = password_hash($password, PASSWORD_ARGON2ID);
        $stmt = Database::connect()->prepare(
            'INSERT INTO users (email, display_name, password_hash, timezone)
             VALUES (:email, :name, :hash, :tz)'
        );
        $stmt->execute([
            ':email' => $email,
            ':name'  => $displayName,
            ':hash'  => $hash,
            ':tz'    => $timezone,
        ]);
        return (int) Database::connect()->lastInsertId();
    }

    public static function verifyPassword(array $user, string $password): bool
    {
        return password_verify($password, $user['password_hash']);
    }

    public static function update(int $id, array $changes): void
    {
        $allowed = ['display_name','timezone','theme','water_goal','water_unit'];
        $sets = [];
        $params = [':id' => $id];
        foreach ($changes as $k => $v) {
            if (!in_array($k, $allowed, true)) continue;
            $sets[] = "$k = :$k";
            $params[":$k"] = $v;
        }
        if (!$sets) return;
        $sql = 'UPDATE users SET ' . implode(', ', $sets) .
               ", updated_at = strftime('%Y-%m-%dT%H:%M:%fZ','now') WHERE id = :id";
        Database::connect()->prepare($sql)->execute($params);
    }

    public static function updatePassword(int $id, string $newPassword): void
    {
        $hash = password_hash($newPassword, PASSWORD_ARGON2ID);
        $stmt = Database::connect()->prepare(
            'UPDATE users SET password_hash = :h, updated_at = strftime(\'%Y-%m-%dT%H:%M:%fZ\',\'now\') WHERE id = :id'
        );
        $stmt->execute([':h' => $hash, ':id' => $id]);
    }

    public static function delete(int $id): void
    {
        // FK ON DELETE CASCADE handles related rows
        $stmt = Database::connect()->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
