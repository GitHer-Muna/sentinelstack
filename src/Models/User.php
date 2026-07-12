<?php
declare(strict_types=1);

namespace Models;

use App\Database;

final class User
{
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
