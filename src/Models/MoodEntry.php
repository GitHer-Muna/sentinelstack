<?php
declare(strict_types=1);

namespace Models;

use App\Database;

final class MoodEntry
{
    /** Mood set kept deliberately small per spec */
    public const MOODS = ['great','good','okay','low','rough'];

    public static function save(int $userId, string $mood, ?string $gratitude, ?string $note, string $tz): void
    {
        if (!in_array($mood, self::MOODS, true)) {
            throw new \InvalidArgumentException('Invalid mood');
        }
        $today = DateUtil::today($tz);
        $gratitude = trim((string) $gratitude) ?: null;
        $note = trim((string) $note) ?: null;

        $stmt = Database::connect()->prepare(
            'INSERT INTO mood_entries (user_id, mood, gratitude, note, local_date)
             VALUES (:uid, :mood, :grat, :note, :day)
             ON CONFLICT(user_id, local_date) DO UPDATE SET
               mood = excluded.mood,
               gratitude = excluded.gratitude,
               note = excluded.note,
               updated_at = strftime(\'%Y-%m-%dT%H:%M:%fZ\',\'now\')'
        );
        $stmt->execute([
            ':uid'  => $userId,
            ':mood' => $mood,
            ':grat' => $gratitude,
            ':note' => $note,
            ':day'  => $today,
        ]);
    }

    public static function today(int $userId, string $tz): ?array
    {
        $today = DateUtil::today($tz);
        $stmt = Database::connect()->prepare(
            'SELECT * FROM mood_entries WHERE user_id = :uid AND local_date = :day'
        );
        $stmt->execute([':uid' => $userId, ':day' => $today]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function history(int $userId, int $days = 60): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT * FROM mood_entries WHERE user_id = :uid
             ORDER BY local_date DESC LIMIT :n'
        );
        $stmt->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':n', $days, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
