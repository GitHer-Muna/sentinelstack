<?php use App\Csrf; ?>
<?php use App\View; ?>
<?php
  // Human-friendly weekday/date headers for the trends below the cards.
  $todayLabel = date('D, M j');
  $weekStart  = (new \DateTime('-6 days'))->format('M j');
?>
<section class="stats-page">
  <?php if (empty($hasData)): ?>
    <article class="card stats-onboarding" role="region" aria-label="Get started with SentinelStack">
      <div class="stats-onboarding__copy">
        <p class="stats-onboarding__kicker">Your story starts with the first log.</p>
        <h2 class="stats-onboarding__title">A single sip. A two-minute breath. A checked-in mood.</h2>
        <p class="stats-onboarding__body">Any of these starts a streak and fills this page with your shape of the week. There&rsquo;s no correct order. Pick whichever is closest to what you actually do today.</p>
        <div class="stats-onboarding__ctas">
          <a class="btn btn-primary" href="/hydration">Log a glass of water</a>
          <a class="btn btn-ghost" href="/mindfulness">Start a 2-minute breath</a>
          <a class="btn btn-ghost" href="/mood">Check in on your mood</a>
        </div>
      </div>
      <div class="stats-onboarding__art" aria-hidden="true">
        <svg viewBox="0 0 160 120" width="160" height="120" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M28 96V58a14 14 0 0 1 28 0v38" />
          <path d="M70 96V40a14 14 0 0 1 28 0v56" />
          <path d="M112 96V70a14 14 0 0 1 28 0v26" />
          <path d="M14 96h140" />
          <circle cx="42" cy="46" r="3" fill="currentColor" stroke="none" />
          <circle cx="84" cy="28" r="3" fill="currentColor" stroke="none" />
          <circle cx="126" cy="58" r="3" fill="currentColor" stroke="none" />
        </svg>
      </div>
    </article>
  <?php endif; ?>
  <article class="grid-4">
    <article class="card">
      <p class="stat-label">Water goal streak</p>
      <p class="stat-number"><?= (int) $waterStreak ?></p>
      <p class="muted-inline small">day<?= $waterStreak === 1 ? '' : 's' ?> hitting your daily water goal.</p>
    </article>
    <article class="card">
      <p class="stat-label">Mindful streak</p>
      <p class="stat-number"><?= (int) $mindfulCurrent ?></p>
      <p class="muted-inline small">longest ever: <?= (int) $mindfulLongest ?>. Streak counts sessions.</p>
    </article>
    <article class="card">
      <p class="stat-label">Movement streak</p>
      <p class="stat-number"><?= (int) $moveCurrent ?></p>
      <p class="muted-inline small">longest ever: <?= (int) $moveLongest ?>. Routines count here.</p>
    </article>
    <article class="card">
      <p class="stat-label">Sleep streak</p>
      <p class="stat-number"><?= (int) $sleepCurrent ?></p>
      <p class="muted-inline small">avg 14 nights: <?= $sleepAvg14 !== null ? number_format($sleepAvg14, 1) . 'h' : 'no data yet' ?>.</p>
    </article>
  </article>

  <article class="card">
    <header class="card-head">
      <h2 class="card-title">Last 14 days</h2>
      <p class="muted-inline">Three small storylines, side by side.</p>
    </header>
    <canvas id="chart-trends" height="220" aria-label="Trends across the last 14 days"></canvas>
  </article>

  <article class="card">
    <header class="card-head">
      <h2 class="card-title">Sleep, last 14 nights</h2>
      <p class="muted-inline">Hours per night. Empty nights are zero.</p>
    </header>
    <canvas id="chart-sleep" height="180" aria-label="Hours of sleep per night for the last 14 nights"></canvas>
  </article>

  <article class="card review-card">
    <header class="card-head">
      <h2 class="card-title">This week in review</h2>
      <p class="muted-inline small"><?= View::escape($weekStart) ?> &ndash; <?= View::escape($todayLabel) ?></p>
    </header>
    <p class="review-body">
      <?php
        $hits = (int) $review['water_hits'];
        $total = (int) $review['water_total_days'];
        $mindful = (int) $review['mindful_count'];
        $mindfulMin = (int) $review['mindful_minutes'];
        $mov = (int) $review['movement_count'];
      ?>
      <?php if ($total === 0): ?>
        No water logged this week. Today&rsquo;s as good a day to start as any.
      <?php elseif ($hits === $total): ?>
        You hit your water goal every single day this week. That&rsquo;s a quiet kind of discipline.
      <?php else: ?>
        You hit your water goal on <strong><?= $hits ?> of <?= $total ?></strong> days this week.
      <?php endif; ?>
      <?php if ($mindful > 0): ?>
        You sat down to breathe <strong><?= $mindful ?> time<?= $mindful === 1 ? '' : 's' ?></strong> for a total of about <strong><?= $mindfulMin ?> minute<?= $mindfulMin === 1 ? '' : 's' ?></strong>.
      <?php else: ?>
        No mindfulness sessions this week. Even a two-minute reset would round it out. No streak to protect.
      <?php endif; ?>
      <?php if ($mov > 0): ?>
        You moved with intention on <strong><?= $mov ?> session<?= $mov === 1 ? '' : 's' ?></strong>.
      <?php else: ?>
        A short stretch today would close the loop on movement. Nothing fancy. Just showing up.
      <?php endif; ?>
    </p>
  </article>
</section>

<script id="stats-data" type="application/json"><?= json_encode([
  'csrf'     => \App\Csrf::token(),
  'water14'  => $water14['series'],
  'mindful14'=> $mindful14,
  'move14'   => $move14,
  'sleep14'  => $sleep14,
  'goal'     => (int) ($user['water_goal'] ?? 0),
  'unit'     => $user['water_unit'] ?? 'ml',
]) ?></script>
