<?php use App\View; ?>
<?php use App\Csrf; ?>
<?php
  $doneCount = count($today);
  $totalCount = count($routines);
  $pct = $totalCount > 0 ? $doneCount / $totalCount : 0;
  // 326.7 ≈ 2 * π * 52 (the ring's circumference, r=52, stroke 8).
  $ringOffset = round(326.7 * (1 - $pct));
?>
<section class="movement-page">
  <p class="page-intro">A small library of text-only routines. Mark one done for today. Short and honest is the whole point.</p>

  <!-- Today's progress card with a circular ring.
       Mirrors the hydration / dashboard ring pattern: a glanceable visual
       so the user sees their daily count without scanning the list. -->
  <article class="card movement-progress-card">
    <div class="movement-progress"
         role="img"
         aria-label="<?= $doneCount ?> of <?= $totalCount ?> routines done today">
      <svg class="movement-ring" viewBox="0 0 120 120" aria-hidden="true">
        <circle cx="60" cy="60" r="52" class="ring-track"/>
        <circle cx="60" cy="60" r="52" class="ring-fill"
                stroke-dasharray="326.7"
                stroke-dashoffset="<?= $ringOffset ?>"
                transform="rotate(-90 60 60)"/>
      </svg>
      <div class="movement-progress__num" aria-hidden="true">
        <strong><?= $doneCount ?></strong><span>/<?= $totalCount ?></span>
      </div>
    </div>
    <div class="movement-progress__meta">
      <p class="eyebrow">Today</p>
      <h2 class="card-title"><?= $doneCount > 0 ? 'Nice. Keep going.' : 'Pick one when you can.' ?></h2>
      <p class="muted-inline small"><?= $doneCount === 0
          ? 'No pressure — even one counts.'
          : 'Movement and meditation share a streak.' ?></p>
    </div>
  </article>

  <ul class="routine-list">
    <?php foreach ($routines as $key => $r):
      $done = !empty($today[$key]);
      $duration = (int) $r['duration'];
      $time = $r['time'] ?? 'anytime';
      $icon = $r['icon'] ?? 'standing';
    ?>
      <li class="card routine-card" data-key="<?= View::escape($key) ?>">
        <header class="card-head">
          <div class="routine-card__head-left">
            <span class="routine-card__icon" aria-hidden="true"><?= routineIcon($icon) ?></span>
            <div>
              <h2 class="card-title"><?= View::escape($r['name']) ?></h2>
              <p class="muted-inline"><?= View::escape($r['target']) ?> · <?= (int) round($duration / 60) ?> min</p>
              <span class="routine-time" data-time="<?= View::escape($time) ?>"><?= View::escape(ucfirst($time)) ?></span>
            </div>
          </div>
          <button class="btn <?= $done ? 'btn-ghost is-done' : 'btn-primary' ?>"
                  type="button" data-action="mark" data-key="<?= View::escape($key) ?>"
                  <?= $done ? 'data-done="true"' : '' ?>>
            <?= $done ? 'Done today' : 'Mark done' ?>
          </button>
        </header>

        <div class="routine-steps">
          <?php foreach (preg_split('/\n(?=\d+\.)/', trim($r['description'])) as $step): ?>
            <?php $step = trim($step); if ($step === '') continue; ?>
            <p class="routine-step"><?= View::escape($step) ?></p>
          <?php endforeach; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>

  <p class="muted-inline small">Movement and meditation share a streak. See how it builds on the Stats page.</p>
</section>

<?php
/**
 * Inline SVG set for the routine body-area icons. Kept here rather than
 * in the global navIcon() helper in layout.php because the routine set
 * is only relevant to the movement page.
 */
function routineIcon(string $name): string {
    $icons = [
      'sun'      => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M5.6 18.4l1.4-1.4M17 7l1.4-1.4"/></svg>',
      'desk'     => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="11" rx="1.5"/><path d="M9 21h6M12 18v3"/><path d="M7 11h4M7 14h2"/></svg>',
      'face'     => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 15c.5 1 2 2 4 2s3.5-1 4-2"/><circle cx="9" cy="10" r="0.8" fill="currentColor"/><circle cx="15" cy="10" r="0.8" fill="currentColor"/></svg>',
      'floor'    => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 20h18"/><circle cx="8" cy="17" r="2.5"/><path d="M10 17h4l3-7"/><circle cx="17" cy="10" r="1.5"/></svg>',
      'standing' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="4.5" r="2"/><path d="M9 9h6l1 6-2 1 .5 5"/><path d="M9 9l-1 5 2 1"/></svg>',
      'moon'     => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>',
      'core'     => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>',
      'walk'     => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="13" cy="4.5" r="2"/><path d="M11 9l-2 5 3 1 1 6"/><path d="M12 9l4 3-2 2"/><path d="M8 21l3-7"/></svg>',
    ];
    return $icons[$name] ?? $icons['standing'];
}
?>

<script id="movement-data" type="application/json"><?= json_encode([
  'csrf' => \App\Csrf::token(),
]) ?></script>
