<?php use App\View; ?>
<?php use App\Csrf; ?>
<section class="mindful-page">
  <article class="card timer-card">
    <header class="card-head">
      <h2 class="card-title">Mindfulness timer</h2>
      <p class="muted-inline">Find a quiet place. The circle moves; you breathe.</p>
    </header>

    <div class="picker-row">
      <label class="field">
        <span class="field-label">Duration</span>
        <select id="duration-select">
          <option value="120">2 minutes</option>
          <option value="300" selected>5 minutes</option>
          <option value="600">10 minutes</option>
          <option value="900">15 minutes</option>
          <option value="custom">Custom…</option>
        </select>
      </label>
      <label class="field" id="custom-duration-wrap" hidden>
        <span class="field-label">Custom minutes</span>
        <input id="custom-duration" class="field-input" type="number" min="1" max="60" value="7">
      </label>
      <label class="field">
        <span class="field-label">Pattern</span>
        <select id="pattern-select">
          <option value="box">Box (4–4–4–4)</option>
          <option value="4-7-8">4–7–8</option>
          <option value="equal">Equal (4–4)</option>
        </select>
      </label>
    </div>

    <div class="breath-stage" aria-live="polite">
      <div class="breath-circle" id="breath-circle">
        <div class="breath-inner">
          <p class="breath-phase" id="breath-phase">Ready</p>
          <p class="breath-clock" id="breath-clock">--:--</p>
        </div>
      </div>
    </div>

    <div class="timer-controls">
      <button class="btn btn-primary" id="timer-start" type="button">Begin</button>
      <button class="btn btn-ghost" id="timer-pause" type="button" hidden>Pause</button>
      <button class="btn btn-ghost" id="timer-resume" type="button" hidden>Resume</button>
      <button class="btn btn-link" id="timer-cancel" type="button" hidden>Cancel</button>
    </div>

    <p class="muted-inline small">Cancelled sessions aren't counted toward your streak. Completed ones are.</p>
  </article>

  <article class="card">
    <header class="card-head">
      <h2 class="card-title">Recent sessions</h2>
      <p class="muted-inline">Streak: <strong><?= (int) $streak ?> day<?= $streak === 1 ? '' : 's' ?></strong></p>
    </header>
    <?php if (empty($recent)): ?>
      <div class="empty-slate" role="status">
        <span class="empty-slate__icon" aria-hidden="true">
          <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="24" cy="24" r="14"/>
            <path d="M24 14v20M14 24h20"/>
          </svg>
        </span>
        <h3 class="empty-slate__title">No sessions yet.</h3>
        <p class="empty-slate__body">Two minutes counts. The first session is the easiest to skip. Set it small and let it be enough.</p>
      </div>
    <?php else: ?>
      <ul class="recent-list">
        <?php foreach ($recent as $r): ?>
          <li>
            <span class="recent-date"><?= View::escape(\App\View::prettyDate($r['started_at'], $tz)) ?></span>
            <span><strong><?= (int) round($r['duration_seconds']/60) ?> min</strong> · <?= View::escape($r['pattern']) ?></span>
            <span class="muted"><?= $r['completed'] ? 'completed' : 'cancelled' ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </article>
</section>

<script id="mindful-data" type="application/json"><?= json_encode([
  'csrf' => \App\Csrf::token(),
]) ?></script>
