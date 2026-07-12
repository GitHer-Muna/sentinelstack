<?php use App\View;
  // First-time detection: no data at all = genuinely new user. Returning-but-quiet = no.
  $isFirstVisit = empty($mood)
    && (int) $waterTotal === 0
    && (int) $openTodosCount === 0
    && (int) $mindfulStreak === 0
    && (int) $moveStreak === 0;

  // Milestone detection -- separate copy per kind (so a mindful streak doesn't read "your back knows")
  // and surface the HIGHEST milestone across both kinds (instead of "first match wins").
  $milestonePhrases = [
    'mindful'  => [3 => 'three days in a row', 7 => 'a full week', 14 => 'two weeks steady', 30 => 'a full month', 100 => 'a hundred, no longer dabbling'],
    'movement' => [3 => 'three days of moving', 7 => 'a full week of motion', 14 => 'two weeks, your back knows', 30 => 'a full month of showing up', 100 => 'a hundred, this is a practice'],
  ];
  $milestone = null;
  $milestoneKind = null;
  foreach ($milestonePhrases as $kind => $phrases) {
    foreach ($phrases as $n => $_phrase) {
      $cnt = ($kind === 'mindful') ? (int) $mindfulStreak : (int) $moveStreak;
      if ($cnt === $n && ($milestone === null || $n > $milestone)) {
        $milestone = $n;
        $milestoneKind = $kind;
      }
    }
  }
?>
<section class="dash-hero">
  <?php if ($isFirstVisit): ?>
  <article class="card first-time-card" role="region" aria-label="A short welcome">
    <p class="first-time-card__kicker"><?= View::escape($user['display_name'] ?? 'Hi there') ?>, a small space is yours.</p>
    <h2 class="first-time-card__title">Pick one small thing to start with.</h2>
    <p class="first-time-card__body">
      SentinelStack isn&rsquo;t a checklist. A small, steady practice. A glass of water. A two-minute breath. One intention for the day. Any of those counts.
    </p>
    <div class="first-time-card__ctas">
      <a class="btn btn-primary" href="/hydration">Log your first glass &rarr;</a>
      <a class="btn btn-ghost" href="/mindfulness">Or start a 2-minute breath</a>
    </div>
  </article>
  <?php else: ?>
  <article class="card hero-affirmation">
    <p class="affirmation-label">A reflection for today</p>
    <p class="affirmation-body"><?= View::escape($affirmation) ?></p>
  </article>
  <?php endif; ?>

  <?php if ($milestone !== null): ?>
  <aside class="milestone" role="status" aria-label="A quiet milestone">
    <span class="milestone__pip" aria-hidden="true"></span>
    <p class="milestone__body">
      <strong><?= (int) $milestone ?> <?= $milestone === 1 ? 'day' : 'days' ?> in a row</strong>
      on <?= $milestoneKind === 'mindful' ? 'mindfulness' : 'movement' ?>. <?= View::escape($milestonePhrases[$milestoneKind][$milestone]) ?>.
    </p>
  </aside>
  <?php endif; ?>

  <article class="card hero-strip">
    <div class="hero-strip__inner">
      <div class="hero-tile hero-tile--water">
        <svg class="ring" viewBox="0 0 120 120" width="120" height="120" aria-label="Water progress ring" role="img">
          <circle cx="60" cy="60" r="52" class="ring-track"/>
          <circle cx="60" cy="60" r="52" class="ring-fill" stroke-dasharray="326.7" stroke-dashoffset="<?= (int) round(326.7 * (1 - ($waterPct/100))) ?>"/>
          <g class="ring-text">
            <text x="60" y="56" text-anchor="middle" class="ring-amount"><?= (int) $waterTotal ?></text>
            <text x="60" y="74" text-anchor="middle" class="ring-unit">of <?= (int) $waterGoal ?> <?= View::escape($waterUnit) ?></text>
          </g>
        </svg>
        <div class="hero-tile__copy">
          <p class="eyebrow">Hydration</p>
          <p class="hero-tile__lede">
            <?php if ($waterTotal >= $waterGoal): ?>
              You've reached today's goal. Nicely done.
            <?php elseif ($waterPct >= 50): ?>
              Past the halfway mark. A small pour finishes it.
            <?php elseif ($waterTotal > 0): ?>
              You're already partway there. One more glass lands it.
            <?php else: ?>
              Empty glass is fine. A sip now is worth more than one later.
            <?php endif; ?>
          </p>
          <a class="link subtle" href="/hydration">Open &rarr;</a>
        </div>
      </div>

      <div class="hero-tile hero-tile--todos">
        <p class="eyebrow">Intentions</p>
        <p class="big-number"><?= (int) $openTodosCount ?></p>
        <p class="hero-tile__lede muted">
          <?= $openTodosCount === 0
              ? 'No list yet. What would help future-you today?'
              : 'open on today\'s list.' ?>
        </p>
        <a class="link subtle" href="/todos">Open &rarr;</a>
      </div>

      <div class="hero-tile hero-tile--mood">
        <p class="eyebrow">Mood &amp; gratitude</p>
        <?php if ($mood): ?>
          <p class="mood-state">
            <span class="mood-dot" data-mood="<?= View::escape($mood['mood']) ?>" aria-hidden="true"></span>
            <strong><?= View::escape(ucfirst($mood['mood'])) ?></strong> today
          </p>
          <?php if (!empty($mood['gratitude'])): ?>
            <blockquote class="gratitude">"<?= View::escape($mood['gratitude']) ?>"</blockquote>
          <?php endif; ?>
        <?php else: ?>
          <p class="hero-tile__lede muted">No entry yet. A one-line check-in is enough.</p>
        <?php endif; ?>
        <a class="link subtle" href="/mood">Open &rarr;</a>
      </div>
    </div>
  </article>
</section>

<section class="tile-grid">
  <article class="card tile-link">
    <a class="tile-link-stretched" href="/mindfulness" aria-label="Open mindfulness"></a>
    <header class="card-head">
      <h2 class="card-title">Mindful streak</h2>
      <span class="link subtle" aria-hidden="true">Open &rarr;</span>
    </header>
    <p class="big-number"><?= (int) $mindfulStreak ?></p>
    <p class="muted">
      <?php if ($mindfulStreak === 0): ?>
        A short session today would begin a new streak.
      <?php elseif ($mindfulStreak === 1): ?>
        First day. Show up again and it becomes a streak.
      <?php else: ?>
        day<?= $mindfulStreak === 1 ? '' : 's' ?> in a row with at least one session.
      <?php endif; ?>
    </p>
  </article>

  <article class="card tile-link">
    <a class="tile-link-stretched" href="/movement" aria-label="Open movement"></a>
    <header class="card-head">
      <h2 class="card-title">Movement streak</h2>
      <span class="link subtle" aria-hidden="true">Open &rarr;</span>
    </header>
    <p class="big-number"><?= (int) $moveStreak ?></p>
    <p class="muted">
      <?php if ($moveStreak === 0): ?>
        A short stretch or walk today would start one.
      <?php elseif ($moveStreak === 1): ?>
        Fresh streak. Your back will be glad tomorrow too.
      <?php else: ?>
        consecutive day<?= $moveStreak === 1 ? '' : 's' ?> of mindful movement.
      <?php endif; ?>
    </p>
  </article>

  <article class="card tile-link">
    <a class="tile-link-stretched" href="/sleep" aria-label="Open sleep"></a>
    <header class="card-head">
      <h2 class="card-title">Sleep streak</h2>
      <span class="link subtle" aria-hidden="true">Open &rarr;</span>
    </header>
    <p class="big-number"><?= (int) $sleepStreak ?></p>
    <p class="muted">
      <?php if ($sleepStreak === 0): ?>
        Logging tonight&rsquo;s sleep would start one.
      <?php elseif ($sleepStreak === 1): ?>
        First night in. Tomorrow lands it as a streak.
      <?php else: ?>
        <?= (int) $sleepStreak ?> nights of sleep logged in a row.
      <?php endif; ?>
    </p>
  </article>

  <article class="card tile-link">
    <a class="tile-link-stretched" href="/stats" aria-label="Open stats"></a>
    <header class="card-head">
      <h2 class="card-title">Last 7 days</h2>
      <span class="link subtle" aria-hidden="true">Open &rarr;</span>
    </header>
    <p class="muted small">Water hit-rates. Mindful minutes. Movement. All of it lives on the stats page.</p>
    <span class="btn btn-ghost btn-block" aria-hidden="true">See trends &rarr;</span>
  </article>
</section>
