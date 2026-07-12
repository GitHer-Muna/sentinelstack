<?php use App\View; ?>

<section class="sleep-page">
  <p class="page-intro">Log the night just ended. One entry per night. Today's number can be re-saved, last week's can't &mdash; that's the journal rule.</p>

  <article class="card">
    <header class="card-head">
      <h2 class="card-title">Last night's sleep</h2>
      <?php if (!empty($today)): ?>
        <p class="muted-inline">
          Logged: <strong><?= number_format(((int) $today['duration_minutes']) / 60.0, 1) ?>h</strong>
        </p>
      <?php else: ?>
        <p class="muted-inline">No entry yet.</p>
      <?php endif; ?>
    </header>

    <form id="sleep-form" class="form-row" novalidate>
      <input type="hidden" name="_csrf" value="<?= View::escape(\App\Csrf::token()) ?>">
      <input type="hidden" name="action" value="log">

      <label class="field-inline">
        <span class="field-label">Hours</span>
        <input class="field-input" type="number" name="hours" min="0" max="24" value="7" inputmode="numeric" required>
      </label>
      <label class="field-inline">
        <span class="field-label">Minutes</span>
        <input class="field-input" type="number" name="minutes" min="0" max="59" value="30" inputmode="numeric">
      </label>
      <button class="btn btn-primary" type="submit">Save</button>
    </form>

    <?php if (!empty($today['note'])): ?>
      <p class="sleep-note muted"><?= View::escape($today['note']) ?></p>
    <?php endif; ?>
  </article>

  <article class="card">
    <header class="card-head">
      <h2 class="card-title">Tendencies</h2>
    </header>
    <ul class="sleep-stats">
      <li>
        <strong><?= (int) $streak ?></strong>
        <?php if ($streak === 0): ?>
          nights in a row. Logging tonight would start one.
        <?php elseif ($streak === 1): ?>
          night. Tomorrow lands it as a streak.
        <?php else: ?>
          nights in a row.
        <?php endif; ?>
      </li>
      <li>
        <strong><?= $avg14 !== null ? number_format($avg14, 1) . 'h avg' : 'no data yet' ?></strong>
        over the last 14 nights.
      </li>
    </ul>
  </article>

  <article class="card">
    <header class="card-head">
      <h2 class="card-title">Recent nights</h2>
    </header>
    <?php if (empty($recent)): ?>
      <div class="empty-slate" role="status">
        <span class="empty-slate__icon" aria-hidden="true">
          <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
        </span>
        <h3 class="empty-slate__title">No nights logged yet.</h3>
        <p class="empty-slate__body">Even rough numbers count. Tomorrow's rough number is better.</p>
        <button class="btn btn-secondary empty-slate__cta" type="button" data-action="focus-first-field">Log last night</button>
      </div>
    <?php else: ?>
      <ul class="sleep-history">
        <?php foreach ($recent as $row): ?>
          <?php $dur = (int) $row['duration_minutes']; ?>
          <?php $h = intdiv($dur, 60); $m = $dur % 60; ?>
          <li class="sleep-history-item" data-id="<?= (int) $row['id'] ?>">
            <span class="sleep-date"><?= View::escape($row['local_date']) ?></span>
            <span class="sleep-duration"><strong><?= (int) $h ?>h <?= (int) $m ?>m</strong></span>
            <?php if (!empty($row['note'])): ?>
              <span class="sleep-note-text muted">&ldquo;<?= View::escape($row['note']) ?>&rdquo;</span>
            <?php endif; ?>
            <button class="link-btn link-danger" type="button" data-action="delete" data-id="<?= (int) $row['id'] ?>">Remove</button>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </article>
</section>

<script id="sleep-data" type="application/json"><?= json_encode([
  'csrf' => \App\Csrf::token(),
]) ?></script>
