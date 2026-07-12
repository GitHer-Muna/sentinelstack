<?php use App\View; ?>
<?php use App\Csrf; ?>
<section class="todos-page">
  <article class="card calendar-card">
    <header class="card-head calendar-head">
      <h2 class="card-title">Calendar</h2>
      <div class="calendar-nav">
        <a class="link-btn" href="?month=<?= View::escape($prevMonth) ?>" rel="prev" aria-label="Previous month">&lsaquo;</a>
        <span class="calendar-month-name"><?= View::escape((new \DateTimeImmutable(sprintf('%04d-%02d-01', $calendarYear, $calendarMonth)))->format('F Y')) ?></span>
        <a class="link-btn" href="?month=<?= View::escape($nextMonth) ?>" rel="next" aria-label="Next month">&rsaquo;</a>
        <?php $currentMonth = substr($today, 0, 7); if ($selectedMonth !== $currentMonth): ?>
          <a class="link subtle calendar-today-link" href="?month=<?= View::escape($currentMonth) ?>">Today</a>
        <?php endif; ?>
      </div>
    </header>
    <div class="calendar-weekdays" aria-hidden="true">
      <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
    </div>
    <div class="calendar-grid" role="grid" aria-label="Monthly activity grid">
      <?php
        // Pad the first week with empty cells. ISO day-of-week is 1=Mon..7=Sun
        // and we render Mon-first, so we need (dow - 1) leading empties.
        $firstOfMonth = new \DateTimeImmutable(sprintf('%04d-%02d-01', $calendarYear, $calendarMonth));
        $firstDow = (int) $firstOfMonth->format('N');
        for ($i = 1; $i < $firstDow; $i++) {
            echo '<span class="calendar-cell is-outside" aria-hidden="true"></span>';
        }
        foreach ($monthSummary as $day) {
            $isToday    = $day['date'] === $today;
            $isSelected = $day['date'] === $selectedDay;
            $cls = 'calendar-cell';
            if ($isToday)                     $cls .= ' is-today';
            if ($isSelected)                  $cls .= ' is-selected';
            if ($day['status'] === 'active') $cls .= ' is-active';
            $href = '?day=' . View::escape($day['date']) . '&amp;month=' . View::escape($selectedMonth);
            $dot  = $day['status'] === 'active' ? '<span class="calendar-cell-dot" aria-hidden="true"></span>' : '';
            $bits = [$day['date']];
            if ($isToday)                        $bits[] = 'today';
            if ($day['habits_done'] > 0)         $bits[] = $day['habits_done'] . ' habit' . ($day['habits_done'] === 1 ? '' : 's') . ' done';
            if ($day['tasks_done']  > 0)         $bits[] = $day['tasks_done']  . ' task'  . ($day['tasks_done']  === 1 ? '' : 's') . ' done';
            $aria = htmlspecialchars(implode(', ', $bits), ENT_QUOTES, 'UTF-8');
            $dom  = (int) (new \DateTimeImmutable($day['date']))->format('j');
            echo '<a href="' . $href . '" class="' . $cls . '" role="gridcell" aria-label="' . $aria . '">'
               . '<span class="calendar-cell-num">' . $dom . '</span>'
               . $dot
               . "</a>\n";
        }
      ?>
    </div>
    <p class="muted-inline small calendar-legend">
      <span class="legend-dot" aria-hidden="true"></span> has activity &middot; today is outlined &middot; selected day is filled
    </p>
  </article>

  <article class="card">
    <header class="card-head">
      <h2 class="card-title">Add to your list</h2>
      <p class="muted-inline">Tasks are for today's focus. Habits recur on their own.</p>
    </header>

    <form id="todo-form" class="form-grid" novalidate>
      <input type="hidden" name="_csrf" value="<?= View::escape(\App\Csrf::token()) ?>">
      <input type="hidden" name="action" value="create">

      <label class="field">
        <span class="field-label">What</span>
        <input class="field-input" name="title" required maxlength="140" placeholder="e.g. Take a quick stretch break" autofocus>
      </label>

      <fieldset class="type-toggle" aria-label="Type">
        <legend class="field-label">Type</legend>
        <label><input type="radio" name="type" value="task" checked> One-off task</label>
        <label><input type="radio" name="type" value="habit"> Recurring habit</label>
      </fieldset>

      <div class="habit-only" hidden>
        <label class="field">
          <span class="field-label">Frequency</span>
          <select name="recurrence_period">
            <option value="daily" selected>Every day</option>
            <option value="weekly">Each week</option>
          </select>
        </label>
        <p class="habit-preview" aria-live="polite">
          <span class="preview-chip" id="habit-preview-chip" data-period="daily" data-hidden="false">
            <svg class="preview-chip__icon" viewBox="0 0 16 16" width="12" height="12" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 8 L7 12 L13 4"/></svg>
            appears every <strong id="habit-preview-period">day</strong>
          </span>
          <span class="field-hint">Daily shows up on every list. Weekly rolls forward by 7 days when you check it off.</span>
        </p>
      </div>

      <label class="field task-only">
        <span class="field-label">Due date (optional)</span>
        <span class="date-input-wrap">
          <input class="field-input" type="date" name="due_date" id="todo-due-date" aria-describedby="due-date-hint">
          <button type="button" class="date-input-icon" data-action="open-date-picker" aria-label="Open date picker">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="5" width="18" height="16" rx="2"/>
            <path d="M3 9h18"/>
            <path d="M8 3v4M16 3v4"/>
          </svg>
          </button>
        </span>
        <span class="field-hint" id="due-date-hint">Pick a day, or leave it blank for an undated task. Click the calendar icon to open the picker.</span>
        <div class="date-quick" role="group" aria-label="Quick date shortcuts">
          <button class="link-btn date-quick__btn" type="button" data-date-offset="0">Today</button>
          <button class="link-btn date-quick__btn" type="button" data-date-offset="1">Tomorrow</button>
          <button class="link-btn date-quick__btn" type="button" data-date-offset="7">Next week</button>
          <button class="link-btn date-quick__btn date-quick__btn--clear" type="button" data-date-offset="clear">Clear</button>
        </div>
      </label>

      <label class="field">
        <span class="field-label">Priority</span>
        <select name="priority">
          <option value="med">Medium</option>
          <option value="high">High</option>
          <option value="low">Low</option>
        </select>
      </label>

      <label class="field span-2">
        <span class="field-label">Note (optional)</span>
        <textarea class="field-input" name="note" rows="2" placeholder="Anything you'd like future-you to see."></textarea>
      </label>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Add</button>
      </div>
    </form>
  </article>

  <article class="card list-card">
    <header class="card-head">
      <h2 class="card-title">
        <?php if ($selectedDay === $today): ?>
          Today
        <?php else: ?>
          <?= View::escape((new \DateTimeImmutable($selectedDay))->format('D, M j')) ?>
        <?php endif; ?>
      </h2>
      <div class="list-head-actions">
        <?php if ($selectedDay !== $today): ?>
          <a class="link subtle" href="?month=<?= View::escape($selectedMonth) ?>">Back to today</a>
        <?php endif; ?>
        <label class="check-toggle">
          <input type="checkbox" id="show-completed" <?= $showCompleted ? 'checked' : '' ?>>
          <span>Show completed</span>
        </label>
      </div>
    </header>

    <?php if (empty($todos)): ?>
      <?php
        $isHabits = !empty($isFirstRunHabits ?? false);
      ?>
      <div class="empty-slate" role="status">
        <span class="empty-slate__icon" aria-hidden="true">
          <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <rect x="9" y="13" width="30" height="28" rx="3"/>
            <path d="M14 9h20"/>
            <path d="M16 22h16M16 28h10M16 34h6"/>
          </svg>
        </span>
        <h3 class="empty-slate__title">An empty list is an open question.</h3>
        <p class="empty-slate__body">What would help future-you today? A small task or a steady habit. Both fit here. No need to be impressive.</p>
        <a class="btn btn-secondary empty-slate__cta" href="#todo-form">Add the first item &rarr;</a>
      </div>
    <?php else: ?>
      <ul class="todo-list" id="todo-list">
        <?php foreach ($todos as $t):
          $completed = (int) $t['is_completed_today'] === 1;
          $priority = $t['priority'];
          $isHabit = $t['type'] === 'habit';
          $period = $isHabit ? ($t['recurrence_period'] ?? 'daily') : null;
        ?>
          <li class="todo-item <?= $completed ? 'is-done' : '' ?>"
              data-id="<?= (int) $t['id'] ?>"
              draggable="true">
            <button class="todo-check" role="checkbox" aria-checked="<?= $completed ? 'true' : 'false' ?>" type="button" data-action="toggle">
              <span class="todo-check-icon" aria-hidden="true">
                <svg viewBox="0 0 16 16" width="14" height="14"><path d="M3 8 L7 12 L13 4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </span>
            </button>

            <div class="todo-body">
              <div class="todo-title-row">
                <span class="todo-title"><?= View::escape($t['title']) ?></span>
                <?php if ($isHabit): ?>
                  <span class="chip <?= $period === 'weekly' ? 'chip-habit-weekly' : 'chip-habit-daily' ?>">
                    habit · <?= View::escape($period) ?>
                  </span>
                <?php elseif (!empty($t['due_date'])): ?>
                  <span class="chip"><?= View::escape($t['due_date']) ?></span>
                <?php endif; ?>
                <span class="chip chip-<?= View::escape($priority) ?>"><?= View::escape($priority) ?></span>
              </div>
              <?php if (!empty($t['note'])): ?>
                <p class="todo-note"><?= nl2br(View::escape($t['note'])) ?></p>
              <?php endif; ?>
            </div>

            <button class="todo-more" type="button" aria-haspopup="true" aria-expanded="false" data-action="edit">
              <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="3" cy="8" r="1"/><circle cx="8" cy="8" r="1"/><circle cx="13" cy="8" r="1"/></svg>
            </button>
            <div class="todo-actions" hidden role="menu">
              <button class="link-btn" type="button" data-action="move-up" aria-label="Move up">↑ Move up</button>
              <button class="link-btn" type="button" data-action="move-down" aria-label="Move down">↓ Move down</button>
              <button class="link-btn link-danger" type="button" data-action="delete">Delete</button>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="muted-inline small">Tip: drag to reorder. A habit marked done today quietly rolls to the next scheduled day.</p>
    <?php endif; ?>
  </article>
</section>

<script id="todos-data" type="application/json"><?= json_encode([
  'csrf' => \App\Csrf::token(),
]) ?></script>
