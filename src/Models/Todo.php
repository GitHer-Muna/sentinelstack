<?php
declare(strict_types=1);

namespace Models;

use App\Database;

/**
 * Todos/intentions. Habits have their own row (the canonical definition).
 * Each habit completion is logged in habit_completions so we have real
 * per-day history for streak math.
 *
 * - Daily habits: due_date = NULL. Always listed for today.
 * - Weekly habits: due_date = the next-pending due date. Listed only when
 *   today's day-of-week matches the due_date's day-of-week. On completion,
 *   due_date advances by 7 days.
 */
final class Todo
{
    public static function listForDay(int $userId, string $tz, string $date, bool $includeCompleted = true): array
    {
        $sql = <<<'SQL'
SELECT t.*, EXISTS(
         SELECT 1 FROM habit_completions hc
         WHERE hc.habit_id = t.id AND hc.local_date = :day
       ) AS habit_done_today
FROM todos t
WHERE t.user_id = :uid
   AND (
     t.type = 'habit'
     OR (t.type = 'task' AND (t.due_date IS NULL OR t.due_date = :day))
   )
ORDER BY
  CASE WHEN ((t.type = 'task' AND t.completed_log = :day) OR habit_done_today)
    THEN 1 ELSE 0 END ASC,
  CASE t.priority
    WHEN 'high' THEN 0
    WHEN 'med'  THEN 1
    WHEN 'low'  THEN 2
  END ASC,
  t.sort_order ASC,
  t.created_at ASC
SQL;
        $stmt = Database::connect()->prepare($sql);
        $stmt->execute([':uid' => $userId, ':day' => $date]);
        $rows = $stmt->fetchAll();

        $today = DateUtil::today($tz);
        $out = [];
        foreach ($rows as $row) {
            $isCompletedToday = false;
            if ($row['type'] === 'habit') {
                // Filter weekly habits: only include on the matching day-of-week.
                if ($row['recurrence_period'] === 'weekly') {
                    if (!$row['due_date']) continue;
                    $tzObj = new \DateTimeZone($tz);
                    $anchorDow = (int) (new \DateTime($row['due_date'], $tzObj))->format('w');
                    $todayDow  = (int) (new \DateTime($today,   $tzObj))->format('w');
                    if ($anchorDow !== $todayDow) continue;
                }
                $isCompletedToday = (int) $row['habit_done_today'] === 1;
            } else {
                $isCompletedToday = $row['completed_log'] === $today
                                     || ($row['completed_log'] === $date && $row['due_date'] === null);
            }
            $row['is_completed_today'] = $isCompletedToday ? 1 : 0;
            if (!$includeCompleted && $isCompletedToday) {
                continue;
            }
            $out[] = $row;
        }
        return $out;
    }

    public static function create(int $userId, array $input, string $tz): int
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('Title is required.');
        }
        $type = in_array($input['type'] ?? 'task', ['task','habit'], true) ? (string) $input['type'] : 'task';
        $priority = in_array($input['priority'] ?? 'med', ['low','med','high'], true) ? (string) $input['priority'] : 'med';
        $note = trim((string) ($input['note'] ?? '')) ?: null;

        $recurrence = null;
        $due = null;
        if ($type === 'habit') {
            $recurrence = ((string) ($input['recurrence_period'] ?? 'daily')) === 'weekly' ? 'weekly' : 'daily';
        } else {
            if (!empty($input['due_date'])) {
                $d = \DateTime::createFromFormat('Y-m-d', (string) $input['due_date']);
                $due = ($d && $d->format('Y-m-d') === (string) $input['due_date'])
                    ? $d->format('Y-m-d')
                    : DateUtil::today($tz);
            }
        }

        // Habit anchors: weekly habits need an initial due_date = today (we'll
        // advance it on each completion). Daily habits stay NULL.
        if ($type === 'habit' && $recurrence === 'weekly') {
            $due = DateUtil::today($tz);
        }

        $max = (int) Database::connect()
            ->query('SELECT COALESCE(MAX(sort_order),0) FROM todos WHERE user_id = ' . (int) $userId)
            ->fetchColumn();
        $sortOrder = $max + 10;

        $stmt = Database::connect()->prepare(
            'INSERT INTO todos (user_id, title, note, priority, type, due_date,
                                recurrence_period, sort_order)
             VALUES (:uid, :title, :note, :pri, :type, :due, :rec, :so)'
        );
        $stmt->execute([
            ':uid'   => $userId,
            ':title' => $title,
            ':note'  => $note,
            ':pri'   => $priority,
            ':type'  => $type,
            ':due'   => $due,
            ':rec'   => $recurrence,
            ':so'    => $sortOrder,
        ]);
        return (int) Database::connect()->lastInsertId();
    }

    public static function update(int $userId, int $id, array $input): bool
    {
        $allowed = ['title','note','priority','due_date'];
        $sets = [];
        $params = [':id' => $id, ':uid' => $userId];
        foreach ($input as $k => $v) {
            if (!in_array($k, $allowed, true)) continue;
            $sets[] = "$k = :$k";
            $params[":$k"] = $v;
        }
        if (!$sets) return false;
        $sql = 'UPDATE todos SET ' . implode(', ', $sets) .
               ", updated_at = strftime('%Y-%m-%dT%H:%M:%fZ','now') WHERE id = :id AND user_id = :uid";
        return Database::connect()->prepare($sql)->execute($params) !== false;
    }

    public static function toggle(int $userId, int $id, string $tz): ?int
    {
        $today = DateUtil::today($tz);
        $row = Database::connect()->prepare('SELECT * FROM todos WHERE id = :id AND user_id = :uid');
        $row->execute([':id' => $id, ':uid' => $userId]);
        $todo = $row->fetch();
        if (!$todo) return null;

        if ($todo['type'] === 'task') {
            // Already completed today?  -> uncomplete, else complete
            if (!empty($todo['completed_at']) || $todo['completed_log'] === $today) {
                self::uncompleteTask($userId, $id, $today);
                return 0;
            }
            self::completeTask($userId, $id, $today);
            return 1;
        }

        // habit
        $exists = self::habitCompletionExists($userId, $id, $today);
        if ($exists) {
            self::uncompleteHabit($userId, $id, $today);
            return 0;
        }
        self::completeHabit($userId, $id, $today);
        // For weekly habits, advance due_date by 7 days.
        if ($todo['recurrence_period'] === 'weekly') {
            $next = (new \DateTimeImmutable($today, new \DateTimeZone($tz)))->modify('+7 days')->format('Y-m-d');
            Database::connect()->prepare(
                'UPDATE todos SET due_date = :next, updated_at = strftime(\'%Y-%m-%dT%H:%M:%fZ\',\'now\')
                 WHERE id = :id AND user_id = :uid'
            )->execute([':next' => $next, ':id' => $id, ':uid' => $userId]);
        }
        return 1;
    }

    public static function delete(int $userId, int $id): void
    {
        // Cascade via FK deletes habit_completions too.
        $stmt = Database::connect()->prepare('DELETE FROM todos WHERE id = :id AND user_id = :uid');
        $stmt->execute([':id' => $id, ':uid' => $userId]);
    }

    public static function reorder(int $userId, array $orderedIds): void
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'UPDATE todos SET sort_order = :so WHERE id = :id AND user_id = :uid'
        );
        $pos = 10;
        foreach ($orderedIds as $id) {
            $stmt->execute([':so' => $pos, ':id' => (int) $id, ':uid' => $userId]);
            $pos += 10;
        }
        $pdo->commit();
    }

    /**
     * Shared streak math. $days must be YYYY-MM-DD strings.
     * - `streakFromDays` is a "current" streak — alive if the latest day is
     *   today or yesterday; otherwise 0.
     * - `longestFromDays` returns the historical maximum.
     */
    public static function streakFromDays(array $days, string $tz): int
    {
        if (!$days) return 0;
        $today = DateUtil::today($tz);
        $yesterday = (new \DateTime($today))->modify('-1 day')->format('Y-m-d');
        $latest = $days[0];
        if ($latest !== $today && $latest !== $yesterday) {
            return 0;
        }
        $cursor = new \DateTime($latest);
        $streak = 0;
        foreach ($days as $d) {
            if ($d === $cursor->format('Y-m-d')) {
                $streak++;
                $cursor = $cursor->modify('-1 day');
            } else {
                break;
            }
        }
        return $streak;
    }

    public static function longestFromDays(array $days): int
    {
        if (!$days) return 0;
        $max = 1; $current = 1; $prev = null;
        foreach ($days as $d) {
            if ($prev !== null) {
                $expected = (new \DateTime($prev))->modify('+1 day')->format('Y-m-d');
                if ($d === $expected) {
                    $current++;
                } else {
                    $current = 1;
                }
            }
            if ($current > $max) $max = $current;
            $prev = $d;
        }
        return $max;
    }

    /**
     * Per-day status for a calendar grid. Returns one row per day in the
     * requested month with the activity counts so the template can color
     * the cell, plus an `empty` / `active` status string for fast rendering.
     *
     * Status semantics:
     *   - 'empty'  : no habit completions and no task completions on that day
     *   - 'active' : at least one habit or task completion on that day
     *
     * We don't try to compute "all habits done" because weekly habits
     * only count on their scheduled day-of-week and undated tasks can
     * appear on any day — a "full" flag would be misleading. The dot
     * indicator is enough for the visual cue.
     */
    public static function monthSummary(int $userId, string $tz, int $year, int $month): array
    {
        $first = sprintf('%04d-%02d-01', $year, $month);
        $daysInMonth = (int) (new \DateTimeImmutable($first, new \DateTimeZone($tz)))->format('t');
        $last = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        // Habit completions per day.
        $habitDoneByDate = [];
        $stmt = Database::connect()->prepare(
            'SELECT local_date, COUNT(*) AS n
             FROM habit_completions
             WHERE user_id = :uid AND local_date >= :first AND local_date <= :last
             GROUP BY local_date'
        );
        $stmt->execute([':uid' => $userId, ':first' => $first, ':last' => $last]);
        foreach ($stmt->fetchAll() as $row) {
            $habitDoneByDate[$row['local_date']] = (int) $row['n'];
        }

        // Task completions per day (only one-off tasks have completed_log).
        $taskDoneByDate = [];
        $stmt = Database::connect()->prepare(
            "SELECT completed_log AS local_date, COUNT(*) AS n
             FROM todos
             WHERE user_id = :uid AND type = 'task' AND completed_log IS NOT NULL
               AND completed_log >= :first AND completed_log <= :last
             GROUP BY completed_log"
        );
        $stmt->execute([':uid' => $userId, ':first' => $first, ':last' => $last]);
        foreach ($stmt->fetchAll() as $row) {
            $taskDoneByDate[$row['local_date']] = (int) $row['n'];
        }

        $summary = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $habitsDone = $habitDoneByDate[$date] ?? 0;
            $tasksDone  = $taskDoneByDate[$date]  ?? 0;
            $summary[] = [
                'date'        => $date,
                'habits_done' => $habitsDone,
                'tasks_done'  => $tasksDone,
                'status'      => ($habitsDone + $tasksDone) > 0 ? 'active' : 'empty',
            ];
        }
        return $summary;
    }

    public static function habitCompletionExists(int $userId, int $habitId, string $today): bool
    {
        $stmt = Database::connect()->prepare(
            'SELECT 1 FROM habit_completions
             WHERE user_id = :uid AND habit_id = :hid AND local_date = :day'
        );
        $stmt->execute([':uid' => $userId, ':hid' => $habitId, ':day' => $today]);
        return (bool) $stmt->fetchColumn();
    }

    private static function completeTask(int $userId, int $id, string $today): void
    {
        Database::connect()->prepare(
            'UPDATE todos
             SET completed_log = :day,
                 completed_at = strftime(\'%Y-%m-%dT%H:%M:%fZ\',\'now\'),
                 updated_at = strftime(\'%Y-%m-%dT%H:%M:%fZ\',\'now\')
             WHERE id = :id AND user_id = :uid'
        )->execute([':day' => $today, ':id' => $id, ':uid' => $userId]);
    }

    private static function uncompleteTask(int $userId, int $id, string $today): void
    {
        Database::connect()->prepare(
            'UPDATE todos
             SET completed_log = NULL,
                 completed_at = NULL,
                 updated_at = strftime(\'%Y-%m-%dT%H:%M:%fZ\',\'now\')
             WHERE id = :id AND user_id = :uid'
        )->execute([':id' => $id, ':uid' => $userId]);
    }

    private static function completeHabit(int $userId, int $habitId, string $today): void
    {
        Database::connect()->prepare(
            'INSERT INTO habit_completions (user_id, habit_id, local_date)
             VALUES (:uid, :hid, :day)'
        )->execute([':uid' => $userId, ':hid' => $habitId, ':day' => $today]);

        // Also stamp the parent row for the "look done today" affordance.
        Database::connect()->prepare(
            'UPDATE todos
             SET completed_log = :day,
                 completed_at = strftime(\'%Y-%m-%dT%H:%M:%fZ\',\'now\'),
                 updated_at = strftime(\'%Y-%m-%dT%H:%M:%fZ\',\'now\')
             WHERE id = :id AND user_id = :uid'
        )->execute([':day' => $today, ':id' => $habitId, ':uid' => $userId]);
    }

    private static function uncompleteHabit(int $userId, int $habitId, string $today): void
    {
        Database::connect()->prepare(
            'DELETE FROM habit_completions WHERE user_id = :uid AND habit_id = :hid AND local_date = :day'
        )->execute([':uid' => $userId, ':hid' => $habitId, ':day' => $today]);

        Database::connect()->prepare(
            'UPDATE todos
             SET completed_log = NULL,
                 completed_at = NULL,
                 updated_at = strftime(\'%Y-%m-%dT%H:%M:%fZ\',\'now\')
             WHERE id = :id AND user_id = :uid'
        )->execute([':id' => $habitId, ':uid' => $userId]);
    }
}
