<?php use App\View; ?>
<?php use App\Csrf; ?>
<section class="page-grid hydration-page">
  <article class="card card-water">
    <header class="card-head">
      <h2 class="card-title">Today</h2>
      <p class="muted-inline"><?= (int) $todayTotal ?> of <?= (int) $goal ?> <?= View::escape($unit) ?> · <?= (int) $pct ?>%</p>
    </header>

    <div class="progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $pct ?>">
      <div class="progress-fill" style="width: <?= (int) $pct ?>%"></div>
    </div>

    <div class="quick-adds">
      <?php $presets = $unit === 'oz' ? [8,12,16,24] : [200,350,500,750]; ?>
      <?php foreach ($presets as $amt): ?>
        <button class="quick-add" type="button" data-amount="<?= (int) $amt ?>" data-unit="<?= View::escape($unit) ?>">
          +<?= (int) $amt ?> <?= View::escape($unit) ?>
        </button>
      <?php endforeach; ?>
    </div>

    <form id="hydration-form" class="manual-row" novalidate>
      <input type="hidden" name="_csrf" value="<?= View::escape(\App\Csrf::token()) ?>">
      <input type="hidden" name="action" value="add">
      <label class="field-inline">
        <span class="field-label">Custom amount</span>
        <input class="field-input" type="number" name="amount" min="1" max="8000" placeholder="e.g. 250" inputmode="numeric">
      </label>
      <button class="btn btn-secondary" type="submit">Log it</button>
    </form>

    <p class="muted-inline">All amounts are stored in the unit you choose here. Times are local.</p>
  </article>

  <article class="card">
    <header class="card-head">
      <h2 class="card-title">Today, by entry</h2>
    </header>
    <?php if (empty($entries)): ?>
      <div class="empty-slate" role="status">
        <span class="empty-slate__icon" aria-hidden="true">
          <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M24 6c-4 6-9 12-9 18a9 9 0 0 0 18 0c0-6-5-12-9-18z"/>
          </svg>
        </span>
        <h3 class="empty-slate__title">A dry page so far.</h3>
        <p class="empty-slate__body">The first sip is the day&rsquo;s nudge to start the habit.</p>
        <button class="btn btn-secondary empty-slate__cta" type="button" data-action="focus-first-quickadd">Log your first glass</button>
      </div>
    <?php else: ?>
      <ul class="entries-list">
        <?php foreach ($entries as $e): ?>
          <li>
            <span><strong><?= (int) $e['amount'] ?> <?= View::escape($e['unit']) ?></strong>
              <span class="muted"><?= htmlspecialchars((new DateTime($e['logged_at'], new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone($tz))->format('g:i A'), ENT_QUOTES, 'UTF-8') ?></span>
            </span>
            <button class="link-btn link-danger" type="button" data-action="delete" data-id="<?= (int) $e['id'] ?>">Undo</button>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </article>

  <article class="card">
    <header class="card-head">
      <h2 class="card-title">Last 7 days</h2>
      <p class="muted-inline">Average: <strong><?= (int) $summary7['avg'] ?> <?= View::escape($unit) ?></strong></p>
    </header>
    <canvas id="chart-7" height="180" aria-label="Water over the last 7 days"></canvas>
  </article>

  <article class="card">
    <header class="card-head">
      <h2 class="card-title">Last 30 days</h2>
      <p class="muted-inline">Average: <strong><?= (int) $summary30['avg'] ?> <?= View::escape($unit) ?></strong></p>
    </header>
    <canvas id="chart-30" height="180" aria-label="Water over the last 30 days"></canvas>
  </article>
</section>

<script id="hydration-data" type="application/json"><?= json_encode([
  'csrf'        => \App\Csrf::token(),
  'unit'        => $unit,
  'goal'        => (int) $goal,
  'series7'     => $summary7['series'],
  'series30'    => $summary30['series'],
]) ?></script>
