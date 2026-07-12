<?php use App\View; ?>
<?php use App\Csrf; ?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · Wellspring</title>
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
  <section class="auth-card" aria-labelledby="signin-heading">
    <header class="auth-head">
      <span class="brand-mark large" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 2c-3 4-6 8-6 11a6 6 0 0 0 12 0c0-3-3-7-6-11z"/>
        </svg>
      </span>
      <h1 id="signin-heading" class="page-title">Welcome back</h1>
      <p class="eyebrow">A small, steady space for the day.</p>
      <p class="auth-soft-note muted">Pick up where you left off. Nothing here is in a hurry.</p>
    </header>

    <?php if (!empty($loginError)): ?>
      <div class="error" role="alert"><?= View::escape($loginError) ?></div>
    <?php endif; ?>

    <form method="post" action="/login" novalidate class="form-stack">
      <input type="hidden" name="_csrf" value="<?= View::escape(\App\Csrf::token()) ?>">

      <label class="field">
        <span class="field-label">Email</span>
        <input class="field-input" type="email" name="email" value="<?= View::escape($email ?? '') ?>" required autocomplete="email" autofocus>
      </label>

      <label class="field">
        <span class="field-label">Password</span>
        <input class="field-input" type="password" name="password" required autocomplete="current-password">
      </label>

      <button class="btn btn-primary btn-block" type="submit">Sign in</button>
    </form>

    <p class="auth-foot">New here? <a class="link" href="/register">Create an account</a></p>
  </section>
</main>
</body>
</html>
