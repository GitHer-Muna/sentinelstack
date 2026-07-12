<?php use App\View; ?>
<?php /** @var ?string $flash */ ?>
<?php /** @var ?string $error */ ?>
<section class="settings-page">
  <?php if (!empty($flash)): ?>
    <div class="form-flash" role="status"><?= View::escape($flash) ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="form-flash form-flash--error" role="alert"><?= View::escape($error) ?></div>
  <?php endif; ?>

  <article class="card">
    <header class="card-head">
      <h2 class="card-title">Profile &amp; preferences</h2>
      <p class="muted-inline">These can shift any time.</p>
    </header>

    <form method="post" action="/settings/update" class="form-grid" novalidate>
      <input type="hidden" name="_csrf" value="<?= View::escape(\App\Csrf::token()) ?>">

      <label class="field">
        <span class="field-label">Display name</span>
        <input class="field-input" type="text" name="display_name" value="<?= View::escape($user['display_name'] ?? '') ?>" maxlength="80" required>
      </label>

      <label class="field">
        <span class="field-label">Timezone</span>
        <select name="timezone">
          <?php
          $current = $user['timezone'] ?? 'UTC';
          foreach (timezone_identifiers_list() as $tz):
          ?>
            <option value="<?= View::escape($tz) ?>" <?= $current === $tz ? 'selected' : '' ?>><?= View::escape($tz) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="field">
        <span class="field-label">Theme</span>
        <select name="theme">
          <option value="light" <?= ($user['theme'] ?? '') === 'light' ? 'selected' : '' ?>>Light</option>
          <option value="dark"  <?= ($user['theme'] ?? '') === 'dark'  ? 'selected' : '' ?>>Dark</option>
        </select>
      </label>

      <label class="field">
        <span class="field-label">Water unit</span>
        <select name="water_unit">
          <option value="ml" <?= ($user['water_unit'] ?? '') === 'ml' ? 'selected' : '' ?>>Milliliters (ml)</option>
          <option value="oz" <?= ($user['water_unit'] ?? '') === 'oz' ? 'selected' : '' ?>>Ounces (oz)</option>
        </select>
      </label>

      <label class="field">
        <span class="field-label">Daily water goal (<?= View::escape($user['water_unit']) ?>)</span>
        <input class="field-input" type="number" name="water_goal" min="250" max="8000" value="<?= (int) $user['water_goal'] ?>" required>
      </label>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Save</button>
      </div>
    </form>
  </article>

  <article class="card">
    <header class="card-head">
      <h2 class="card-title">Change password</h2>
      <p class="muted-inline">Used only when you're already signed in.</p>
    </header>
    <form method="post" action="/settings/password" class="form-grid" novalidate>
      <input type="hidden" name="_csrf" value="<?= View::escape(\App\Csrf::token()) ?>">

      <label class="field">
        <span class="field-label">Current password</span>
        <input class="field-input" type="password" name="current_password" required autocomplete="current-password">
      </label>

      <label class="field">
        <span class="field-label">New password</span>
        <input class="field-input" type="password" name="new_password" minlength="8" required autocomplete="new-password" data-pw-strength>
        <span class="pw-strength" data-pw-meter hidden>
          <span class="pw-strength__bar"><span class="pw-strength__fill"></span></span>
          <span class="pw-strength__label">Strength</span>
        </span>
        <span class="field-hint">Eight characters or more. Longer or unusual is better.</span>
      </label>

      <label class="field">
        <span class="field-label">Confirm new password</span>
        <input class="field-input" type="password" name="new_password_confirm" minlength="8" required autocomplete="new-password" data-pw-confirm>
        <span class="field-hint field-confirm" data-confirm-hint hidden>Passwords match.</span>
      </label>

      <div class="form-actions">
        <button class="btn btn-secondary" type="submit">Update password</button>
      </div>
    </form>
  </article>

  <article class="card danger">
    <header class="card-head">
      <h2 class="card-title">Delete account</h2>
      <p class="muted-inline">Removes your account and all logged data. There is no undo.</p>
    </header>
    <form method="post" action="/settings/delete" class="form-row" novalidate data-confirm-on-submit="Type &quot;delete&quot; to confirm. This is permanent and cannot be undone.">
      <input type="hidden" name="_csrf" value="<?= View::escape(\App\Csrf::token()) ?>">
      <label class="field">
        <span class="field-label">Type <code>delete</code> to confirm</span>
        <input class="field-input" type="text" name="confirm" required pattern="^delete$">
      </label>
      <button class="btn btn-danger" type="submit">Delete account</button>
    </form>
  </article>
</section>

<script>
  // Immediate submit feedback for the three settings forms. The server
  // still does the real work and redirects back with a flash; this just
  // stops the user from wondering "did anything happen?" during the
  // roundtrip by marking the button as busy and scrolling the flash into
  // view on the next page.
  (function () {
    'use strict';
    const labels = {
      'Save': 'Saving\u2026',
      'Update password': 'Updating\u2026',
      'Delete account': 'Deleting\u2026',
    };
    document.querySelectorAll('form[action^="/settings/"]').forEach((f) => {
      f.addEventListener('submit', (ev) => {
        // Don't show "Saving…" for forms that already use the confirm
        // dialog (delete) — that flow has its own visible feedback.
        if (f.dataset.confirmOnSubmit) return;
        const btn = f.querySelector('button[type="submit"]');
        if (!btn || btn.dataset.busy === '1') return;
        const original = btn.textContent.trim();
        btn.dataset.busy = '1';
        btn.dataset.busyOriginal = btn.innerHTML;
        const next = labels[original] || 'Saving\u2026';
        btn.innerHTML = '<span class="btn-spinner" aria-hidden="true"></span><span class="btn-spinner-label">' + next + '</span>';
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        // Scroll to the top so the user lands on the flash banner when the
        // page reloads.
        try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch (_) {}
      });
    });

    // Promote the flash banner into a more attention-grabbing state if it
    // exists (the server-rendered one is a small inline box; for settings
    // forms it's the primary success signal after a redirect).
    const flash = document.querySelector('.form-flash');
    if (flash) {
      flash.setAttribute('tabindex', '-1');
      flash.focus({ preventScroll: false });
    }
  })();
</script>
