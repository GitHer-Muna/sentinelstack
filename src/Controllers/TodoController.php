<?php
declare(strict_types=1);

namespace Controllers;

use App\Session;
use App\View;
use Models\DateUtil;
use Models\User;
use Models\Todo;

final class TodoController
{
    public function __construct(private string $root) {}

    public function index(): void
    {
        $uid = Session::requireAuth();
        $user = User::find($uid);
        $tz = $user['timezone'] ?? 'UTC';
        $today = DateUtil::today($tz);

        $showCompleted = !empty($_GET['show_completed']);

        // ?day=YYYY-MM-DD selects the day the list shows (defaults to today).
        // ?month=YYYY-MM selects the month the calendar shows (defaults to today's month).
        // Validation is strict: reject anything that isn't a real YYYY-MM-DD / YYYY-MM
        // rather than silently coercing, so a bad query string can never crash the
        // page render.
        $selectedDay = $today;
        if (!empty($_GET['day']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['day'])) {
            $d = \DateTime::createFromFormat('Y-m-d', (string) $_GET['day']);
            if ($d && $d->format('Y-m-d') === (string) $_GET['day']) {
                $selectedDay = (string) $_GET['day'];
            }
        }
        $selectedMonth = substr($today, 0, 7);
        if (!empty($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', (string) $_GET['month'])) {
            $selectedMonth = (string) $_GET['month'];
        }
        [$year, $month] = array_map('intval', explode('-', $selectedMonth));
        $year  = $year  > 0 ? $year  : (int) substr($today, 0, 4);
        $month = $month >= 1 && $month <= 12 ? $month : (int) substr($today, 5, 2);

        $todos = Todo::listForDay($uid, $tz, $selectedDay, $showCompleted);
        $monthSummary = Todo::monthSummary($uid, $tz, $year, $month);

        $prevMonth = self::shiftMonth($year, $month, -1);
        $nextMonth = self::shiftMonth($year, $month, +1);

        View::render('todos', [
            'user' => $user,
            'tz' => $tz,
            'pageTitle' => 'Intentions',
            'todos' => $todos,
            'showCompleted' => $showCompleted,
            'today' => $today,
            'selectedDay' => $selectedDay,
            'selectedMonth' => $selectedMonth,
            'calendarYear' => $year,
            'calendarMonth' => $month,
            'monthSummary' => $monthSummary,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
        ]);
    }

    /**
     * Shift a YYYY-MM by ±N months, wrapping across year boundaries.
     * Returns a YYYY-MM string.
     */
    private static function shiftMonth(int $year, int $month, int $delta): string
    {
        $d = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $d = $delta >= 0 ? $d->modify('+' . $delta . ' months') : $d->modify($delta . ' months');
        return $d->format('Y-m');
    }
}
