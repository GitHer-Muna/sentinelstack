<?php
declare(strict_types=1);

namespace Controllers;

use App\Csrf;
use App\Response;
use App\Session;
use App\Validator;
use Models\DateUtil;
use Models\MoodEntry;
use Models\MovementLog;
use Models\MindfulnessSession;
use Models\SleepLog;
use Models\Todo;
use Models\User;
use Models\WaterLog;

/**
 * Single AJAX entrypoint.

   Each endpoint expects POST + application/x-www-form-urlencoded
   (or JSON), includes _csrf (CSRF token), and returns JSON.
 */
final class ApiController
{
    public function __construct(private string $root) {}

    public function hydration()
    {
        $this->bootstrap();
        $uid = $this->uid();
        $user = User::find($uid);
        $tz = $user['timezone'];

        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $amount = Validator::positiveInt($_POST['amount'] ?? null);
            $unit = Validator::inArray($_POST['unit'] ?? $user['water_unit'], ['ml','oz']);
            if (!$amount || !$unit) {
                return $this->fail('amount (positive integer) and unit (ml or oz) are required.');
            }
            $id = WaterLog::add($uid, $amount, $unit, $tz, trim((string) ($_POST['note'] ?? '')) ?: null);
            return $this->ok([
                'id' => $id,
                'today_total' => WaterLog::todayTotal($uid, $tz),
                'goal' => (int) $user['water_goal'],
                'pct' => min(100, (int) round((WaterLog::todayTotal($uid, $tz) / max(1,(int) $user['water_goal'])) * 100)),
                'entries' => array_map(function ($e) {
                    return [
                        'id' => (int) $e['id'],
                        'amount' => (int) $e['amount'],
                        'unit' => $e['unit'],
                        'logged_at' => $e['logged_at'],
                    ];
                }, WaterLog::todayEntries($uid, $tz)),
            ]);
        }

        if ($action === 'delete') {
            $id = Validator::positiveInt($_POST['id'] ?? null);
            if (!$id) return $this->fail('id is required');
            WaterLog::delete($uid, $id);
            return $this->ok([
                'today_total' => WaterLog::todayTotal($uid, $tz),
                'goal' => (int) $user['water_goal'],
            ]);
        }
        return $this->fail('Unknown action.');
    }

    public function todos()
    {
        $this->bootstrap();
        $uid = $this->uid();
        $user = User::find($uid);
        $tz = $user['timezone'];
        $today = DateUtil::today($tz);

        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $input = [
                'title' => trim((string) ($_POST['title'] ?? '')),
                'note' => trim((string) ($_POST['note'] ?? '')),
                'priority' => (string) ($_POST['priority'] ?? 'med'),
                'type' => (string) ($_POST['type'] ?? 'task'),
                'due_date' => trim((string) ($_POST['due_date'] ?? '')),
                'recurrence_period' => (string) ($_POST['recurrence_period'] ?? 'daily'),
            ];
            try {
                $id = Todo::create($uid, $input, $tz);
            } catch (\InvalidArgumentException $e) {
                return $this->fail($e->getMessage());
            }
            return $this->ok(['id' => $id]);
        }

        if ($action === 'toggle') {
            $id = Validator::positiveInt($_POST['id'] ?? null);
            if (!$id) return $this->fail('id is required');
            $state = Todo::toggle($uid, $id, $tz);
            if ($state === null) return $this->fail('Todo not found.');
            return $this->ok(['is_completed' => $state]);
        }

        if ($action === 'update') {
            $id = Validator::positiveInt($_POST['id'] ?? null);
            if (!$id) return $this->fail('id is required');
            $input = [];
            foreach (['title','note','priority','due_date'] as $k) {
                if (isset($_POST[$k])) $input[$k] = $_POST[$k];
            }
            Todo::update($uid, $id, $input);
            return $this->ok(['id' => $id]);
        }

        if ($action === 'delete') {
            $id = Validator::positiveInt($_POST['id'] ?? null);
            if (!$id) return $this->fail('id is required');
            Todo::delete($uid, $id);
            return $this->ok(['id' => $id]);
        }
        return $this->fail('Unknown action.');
    }

    public function todosReorder()
    {
        $this->bootstrap();
        $uid = $this->uid();
        $ids = [];
        if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
            foreach ($_POST['ids'] as $id) {
                $n = Validator::positiveInt($id);
                if ($n) $ids[] = $n;
            }
        }
        if (!$ids) return $this->fail('ids required');
        Todo::reorder($uid, $ids);
        return $this->ok();
    }

    public function mindfulness()
    {
        $this->bootstrap();
        $uid = $this->uid();
        $user = User::find($uid);
        $tz = $user['timezone'];

        $action = $_POST['action'] ?? '';

        if ($action === 'log') {
            $dur = Validator::positiveInt($_POST['duration_seconds'] ?? null);
            $pat = Validator::inArray($_POST['pattern'] ?? 'box', ['box','4-7-8','equal']);
            $comp = filter_var($_POST['completed'] ?? true, FILTER_VALIDATE_BOOLEAN);
            if (!$dur || !$pat) return $this->fail('duration_seconds and pattern are required.');
            $id = MindfulnessSession::log($uid, $dur, $pat, $comp, $tz);
            return $this->ok([
                'id' => $id,
                'completed' => (bool) $comp,
                'streak' => MindfulnessSession::currentStreak($uid, $tz),
            ]);
        }
        return $this->fail('Unknown action.');
    }

    public function mood()
    {
        $this->bootstrap();
        $uid = $this->uid();
        $user = User::find($uid);
        $tz = $user['timezone'];

        $action = $_POST['action'] ?? '';
        if ($action === 'save') {
            $mood = Validator::inArray($_POST['mood'] ?? '', MoodEntry::MOODS);
            $gratitude = (string) ($_POST['gratitude'] ?? '');
            $note = (string) ($_POST['note'] ?? '');
            if (!$mood) return $this->fail('Pick a mood.');
            MoodEntry::save($uid, $mood, $gratitude, $note, $tz);
            return $this->ok(['today' => MoodEntry::today($uid, $tz)]);
        }
        return $this->fail('Unknown action.');
    }

    public function movement()
    {
        $this->bootstrap();
        $uid = $this->uid();
        $user = User::find($uid);
        $tz = $user['timezone'];

        $action = $_POST['action'] ?? '';
        $key = preg_match('/^[a-z0-9_]+$/i', (string) ($_POST['routine_key'] ?? '')) ? (string) $_POST['routine_key'] : '';
        $routines = MovementLog::routines();
        if (!$key || !isset($routines[$key])) return $this->fail('Unknown routine.');

        if ($action === 'mark') {
            $created = MovementLog::mark($uid, $key, $tz, $routines[$key]['duration']);
            return $this->ok(['marked' => $created]);
        }
        if ($action === 'unmark') {
            MovementLog::unmark($uid, $key, $tz);
            return $this->ok(['unmarked' => true]);
        }
        return $this->fail('Unknown action.');
    }

    public function sleep()
    {
        $this->bootstrap();
        $uid = $this->uid();
        $user = User::find($uid);
        $tz = $user['timezone'];

        $action = $_POST['action'] ?? '';
        if ($action === 'log') {
            $hours = (int) ($_POST['hours'] ?? 0);
            $minutes = (int) ($_POST['minutes'] ?? 0);
            if ($hours < 0 || $hours > 24) {
                return $this->fail('Hours must be between 0 and 24.');
            }
            if ($minutes < 0 || $minutes > 59) {
                return $this->fail('Minutes must be between 0 and 59.');
            }
            $total = $hours * 60 + $minutes;
            if ($total <= 0 || $total > 1440) {
                return $this->fail('Hours and minutes must add up to between 1 minute and 24 hours.');
            }
            $note = (string) ($_POST['note'] ?? '');
            try {
                SleepLog::log($uid, $total, $tz, $note);
            } catch (\InvalidArgumentException $e) {
                return $this->fail($e->getMessage());
            }
            return $this->ok([
                'today' => SleepLog::today($uid, $tz),
                'streak' => SleepLog::currentStreak($uid, $tz),
            ]);
        }
        if ($action === 'delete') {
            $id = Validator::positiveInt($_POST['id'] ?? null);
            if (!$id) return $this->fail('id is required');
            SleepLog::delete($uid, $id);
            return $this->ok(['id' => $id]);
        }
        return $this->fail('Unknown action.');
    }

    private function bootstrap(): void
    {
        Csrf::requireValid();
        header('Cache-Control: no-store');
    }

    private function uid(): int
    {
        $id = Session::userId();
        if (!$id) Response::json(['ok' => false, 'error' => 'Not authenticated'], 401);
        return $id;
    }

    private function ok(array $data = []): void
    {
        Response::json(['ok' => true] + $data);
    }

    private function fail(string $message, int $status = 400): void
    {
        Response::json(['ok' => false, 'error' => $message], $status);
    }
}
