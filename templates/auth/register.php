<?php use App\View; ?>
<?php use App\Csrf; ?>
<?php use App\Session; ?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create an account · SentinelStack</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/tokens.css">
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/components.css">
<link rel="stylesheet" href="/assets/css/auth.css">
<link rel="stylesheet" href="/assets/css/chrome.css">
</head>
<body class="auth-body">
<a class="skip-link" href="#main">Skip to main content</a>

<main id="main" class="auth-shell">
  <section class="auth-card wide" aria-labelledby="signup-heading">
    <header class="auth-head">
      <span class="brand-mark large" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 2c-3 4-6 8-6 11a6 6 0 0 0 12 0c0-3-3-7-6-11z"/>
        </svg>
      </span>
      <h1 id="signup-heading" class="page-title">Make it yours</h1>
      <p class="eyebrow">You only answer these once.</p>
      <p class="auth-soft-note muted">A small space that gets to know you. Nothing tracked unless it helps.</p>
    </header>

    <?php if (!empty($registerError)): ?>
      <div class="error" role="alert"><?= View::escape($registerError) ?></div>
    <?php endif; ?>

    <?php $old = \App\Session::get('_old') ?? []; ?>
    <form method="post" action="/register" novalidate class="form-stack">
      <input type="hidden" name="_csrf" value="<?= View::escape(\App\Csrf::token()) ?>">

      <label class="field">
        <span class="field-label">Display name</span>
        <input class="field-input" type="text" name="display_name" value="<?= View::escape($old['display_name'] ?? '') ?>" required maxlength="80" autocomplete="nickname">
      </label>

      <label class="field">
        <span class="field-label">Email</span>
        <input class="field-input" type="email" name="email" value="<?= View::escape($old['email'] ?? '') ?>" required autocomplete="email">
      </label>

      <label class="field">
        <span class="field-label">Timezone</span>
        <select class="field-input" name="timezone" required>
          <?php
          $tzList = timezone_identifiers_list();
          $current = $old['timezone'] ?? 'UTC';
          foreach ($tzList as $tz):
              $label = $tz;
          ?>
            <option value="<?= View::escape($tz) ?>" <?= $current === $tz ? 'selected' : '' ?>><?= View::escape($label) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="field-hint">Your days roll over at midnight in this zone.</span>
      </label>

      <label class="field">
        <span class="field-label">Password</span>
        <input class="field-input" type="password" name="password" required minlength="8" autocomplete="new-password">
        <span class="field-hint">Eight characters or more.</span>
      </label>

      <label class="field">
        <span class="field-label">Confirm password</span>
        <input class="field-input" type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
      </label>

      <button class="btn btn-primary btn-block" type="submit">Create account</button>
    </form>

    <p class="auth-foot">Already have one? <a class="link" href="/login">Sign in</a></p>
  </section>
</main>
</body>
</html>
