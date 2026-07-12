<?php use App\View; ?>
<?php /** @var ?string $csrf */ ?>
<?php /** @var ?array $user */ ?>
<?php /** @var ?string $flash */ ?>
<?php /** @var ?string $pageTitle */ ?>
<?php
  $theme   = $user['theme'] ?? 'light';
  $csrfTok = \App\Csrf::token();
?>
<!doctype html>
<html lang="en" data-theme="<?= View::escape($theme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="csrf" content="<?= View::escape($csrfTok) ?>">
<title><?= View::escape($pageTitle ?? 'SentinelStack') ?> · SentinelStack</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<link rel="stylesheet" href="/assets/css/tokens.css">
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/components.css">
<link rel="stylesheet" href="/assets/css/layout.css">
<link rel="stylesheet" href="/assets/css/pages.css">
<link rel="stylesheet" href="/assets/css/chrome.css">

<script src="/assets/js/vendor/chart.umd.min.js" defer></script>
<script defer src="/assets/js/app.js"></script>
<script defer src="/assets/js/theme.js"></script>
<?php
  // Page-specific JS modules, loaded conditionally by the page slug.
  // Bare module names — the <script> tag below appends `.js`. Don't include
  // the extension here, or the browser will request `todos.js.js` (404).
  $jsMod = match ($pageTitle ?? '') {
      'Hydration'   => 'hydration',
      'Intentions'  => 'todos',
      'Mindfulness' => 'mindfulness',
      'Mood & gratitude' => 'mood',
      'Movement'    => 'movement',
      'Sleep'       => 'sleep',
      'Stats'       => 'stats',
      default       => null,
  };
  if ($jsMod): ?>
<script defer src="/assets/js/<?= View::escape($jsMod) ?>.js"></script>
<?php endif; ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<div id="sr-announce" class="sr-only" aria-live="polite" aria-atomic="true"></div>
<div id="top-progress" class="top-progress" hidden aria-hidden="true"></div>
<div id="offline-banner" class="offline-banner" hidden role="status">
  <span class="offline-banner__icon" aria-hidden="true">
    <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 1.5C4.5 1.5 1.5 4 1.5 7.5M14.5 7.5C14.5 4 11.5 1.5 8 1.5M8 5.5C6.5 5.5 5.5 6.5 5.5 8M10.5 8C10.5 6.5 9.5 5.5 8 5.5"/><circle cx="8" cy="11" r="0.9" fill="currentColor"/></svg>
  </span>
  You&rsquo;re offline &mdash; your actions will retry when you reconnect.
</div>

<div class="app-shell">
  <aside class="sidebar" aria-label="Primary">
    <div class="brand">
      <span class="brand-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 2c-3 4-6 8-6 11a6 6 0 0 0 12 0c0-3-3-7-6-11z"/>
        </svg>
      </span>
      <span class="brand-name">SentinelStack</span>
    </div>

    <nav class="primary-nav" aria-label="Sections">
      <a href="/dashboard"   <?= ($pageTitle ?? '')==='Today' ? 'aria-current="page"' : '' ?>>
        <?= navIcon('home') ?><span>Today</span>
      </a>
      <a href="/hydration"   <?= ($pageTitle ?? '')==='Hydration' ? 'aria-current="page"' : '' ?>>
        <?= navIcon('drop') ?><span>Hydration</span>
      </a>
      <a href="/todos"       <?= ($pageTitle ?? '')==='Intentions' ? 'aria-current="page"' : '' ?>>
        <?= navIcon('check') ?><span>Intentions</span>
      </a>
      <a href="/mindfulness" <?= ($pageTitle ?? '')==='Mindfulness' ? 'aria-current="page"' : '' ?>>
        <?= navIcon('breath') ?><span>Mindfulness</span>
      </a>
      <a href="/mood"        <?= ($pageTitle ?? '')==='Mood & gratitude' ? 'aria-current="page"' : '' ?>>
        <?= navIcon('heart') ?><span>Mood &amp; gratitude</span>
      </a>
      <a href="/sleep"       <?= ($pageTitle ?? '')==='Sleep' ? 'aria-current="page"' : '' ?>>
        <?= navIcon('moon') ?><span>Sleep</span>
      </a>
      <a href="/movement"    <?= ($pageTitle ?? '')==='Movement' ? 'aria-current="page"' : '' ?>>
        <?= navIcon('body') ?><span>Movement</span>
      </a>
      <a href="/stats"       <?= ($pageTitle ?? '')==='Stats' ? 'aria-current="page"' : '' ?>>
        <?= navIcon('chart') ?><span>Stats</span>
      </a>
      <a href="/settings"    <?= ($pageTitle ?? '')==='Settings' ? 'aria-current="page"' : '' ?>>
        <?= navIcon('gear') ?><span>Settings</span>
      </a>
    </nav>

    <div class="sidebar-foot">
      <a class="footer-link" href="/settings">Settings</a>
      <form method="post" action="/logout" class="logout-form">
        <input type="hidden" name="_csrf" value="<?= View::escape($csrfTok) ?>">
        <button class="link-btn" type="submit">Sign out</button>
      </form>
    </div>
  </aside>

  <main id="main" class="app-main">
    <header class="page-head">
      <div>
        <?php if (($pageTitle ?? '') === 'Today'):
          // Smart time-of-day greeting — picked by the hour, served by the server so it doesn't flash.
          // Validating the timezone is required: DateTimeZone throws on garbage.
          try {
              $tzForGreeting = new \DateTimeZone($user['timezone'] ?? 'UTC');
          } catch (\Exception $_) {
              $tzForGreeting = new \DateTimeZone('UTC');
          }
          $hour = (int) (new \DateTime('now', $tzForGreeting))->format('G');
          // match(true) reads as: for the first arm whose expression is true, return its value.
          // Cleaner than a nested ternary (which PHP 8+ refuses to parse without parens).
          $tod = match (true) {
              $hour <  5 => 'late',
              $hour < 12 => 'morning',
              $hour < 17 => 'afternoon',
              $hour < 22 => 'evening',
              default    => 'late',
          };
          $salutation = match ($tod) {
              'morning'   => 'Good morning',
              'afternoon' => 'Good afternoon',
              'evening'   => 'Good evening',
              'late'      => 'Up late',
          };
        ?>
        <p class="greeting"><?= $salutation ?>, <?= View::escape($user['display_name'] ?? 'friend') ?>.</p>
        <?php endif; ?>
        <h1 class="page-title">
          <?php
            // "You are here" icon next to the page title. Same SVG set as
            // the sidebar + bottom nav, so the visual vocabulary stays
            // consistent across all three places that name the page.
            $pageIcons = [
              'Today'             => 'home',
              'Hydration'         => 'drop',
              'Intentions'        => 'check',
              'Mindfulness'       => 'breath',
              'Mood & gratitude'  => 'heart',
              'Sleep'             => 'moon',
              'Movement'          => 'body',
              'Stats'             => 'chart',
              'Settings'          => 'gear',
            ];
            $iconKey = $pageIcons[$pageTitle ?? ''] ?? null;
          ?>
          <?php if ($iconKey): ?>
            <span class="page-title__icon" aria-hidden="true"><?= navIcon($iconKey) ?></span>
          <?php endif; ?>
          <?= View::escape($pageTitle ?? 'SentinelStack') ?>
        </h1>
      </div>
      <button id="theme-toggle" class="theme-toggle" aria-pressed="<?= $theme === 'dark' ? 'true' : 'false' ?>" aria-label="Toggle theme">
        <span class="theme-toggle-icon" data-icon-sun>
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M5.6 18.4l1.4-1.4M17 7l1.4-1.4"/></svg>
        </span>
        <span class="theme-toggle-icon" data-icon-moon hidden>
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
        </span>
      </button>
    </header>

    <?php if (!empty($flash)): ?>
      <div class="form-flash" role="status"><?= View::escape($flash) ?></div>
    <?php endif; ?>

    <?= $content ?? '' ?>
  </main>
</div>

<nav class="bottom-nav" aria-label="Sections (mobile)">
  <a href="/dashboard"   <?= ($pageTitle ?? '')==='Today' ? 'aria-current="page"' : '' ?> aria-label="Today">     <?= navIcon('home') ?><span class="sr-only">Today</span></a>
  <a href="/hydration"   <?= ($pageTitle ?? '')==='Hydration' ? 'aria-current="page"' : '' ?> aria-label="Hydration"> <?= navIcon('drop') ?><span class="sr-only">Hydration</span></a>
  <a href="/todos"       <?= ($pageTitle ?? '')==='Intentions' ? 'aria-current="page"' : '' ?> aria-label="Intentions">  <?= navIcon('check') ?><span class="sr-only">Intentions</span></a>
  <a href="/mindfulness" <?= ($pageTitle ?? '')==='Mindfulness' ? 'aria-current="page"' : '' ?> aria-label="Mindfulness"><?= navIcon('breath') ?><span class="sr-only">Mindfulness</span></a>
  <a href="/mood"        <?= ($pageTitle ?? '')==='Mood & gratitude' ? 'aria-current="page"' : '' ?> aria-label="Mood">        <?= navIcon('heart') ?><span class="sr-only">Mood &amp; gratitude</span></a>
  <a href="/sleep"       <?= ($pageTitle ?? '')==='Sleep' ? 'aria-current="page"' : '' ?> aria-label="Sleep">       <?= navIcon('moon') ?><span class="sr-only">Sleep</span></a>
  <a href="/movement"    <?= ($pageTitle ?? '')==='Movement' ? 'aria-current="page"' : '' ?> aria-label="Movement">  <?= navIcon('body') ?><span class="sr-only">Movement</span></a>
  <a href="/stats"       <?= ($pageTitle ?? '')==='Stats' ? 'aria-current="page"' : '' ?> aria-label="Stats">         <?= navIcon('chart') ?><span class="sr-only">Stats</span></a>
  <a href="/settings"    <?= ($pageTitle ?? '')==='Settings' ? 'aria-current="page"' : '' ?> aria-label="Settings">  <?= navIcon('gear') ?><span class="sr-only">Settings</span></a>
</nav>

<dialog id="confirm-dialog" class="confirm-dialog" aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-msg">
  <form method="dialog" class="confirm-dialog__body">
    <h2 id="confirm-dialog-title" class="confirm-dialog__title">Are you sure?</h2>
    <p id="confirm-dialog-msg" class="confirm-dialog__msg">This cannot be undone.</p>
    <div class="confirm-dialog__actions">
      <button class="btn btn-ghost" value="cancel" type="submit">Cancel</button>
      <button class="btn btn-danger" value="confirm" type="submit">Confirm</button>
    </div>
  </form>
</dialog>

<script>
  // Hand the CSRF token to app.js without a roundtrip via the inline-data scripts.
  window.__WS_CSRF__ = <?= json_encode($csrfTok) ?>;
</script>

<?php
function navIcon(string $name): string {
    $icons = [
      'home'   => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11.5L12 4l9 7.5"/><path d="M5 10v10h14V10"/></svg>',
      'drop'   => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3c-3 5-6 8-6 12a6 6 0 0 0 12 0c0-4-3-7-6-12z"/></svg>',
      'check'  => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="4.5" width="17" height="16" rx="3"/><path d="M8 12.5l3 3 5-6"/></svg>',
      'breath' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/></svg>',
      'heart'  => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20s-7-4.5-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.5-7 10-7 10z"/></svg>',
      'moon'   => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>',
      'body'   => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="4.8" r="2.3"/><path d="M9 9.5h6l1.5 5-2.5 1.5.8 6"/><path d="M9 9.5l-2 .8L8 16l3 1"/></svg>',
      'chart'  => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19V5"/><path d="M4 19h16"/><rect x="7" y="11" width="3" height="6"/><rect x="12" y="7" width="3" height="10"/><rect x="17" y="13" width="3" height="4"/></svg>',
      'gear'   => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>',
    ];
    return $icons[$name] ?? '';
}
?>
</body>
</html>
