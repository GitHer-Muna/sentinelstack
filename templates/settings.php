<?php use App\View; ?>
<?php /** @var ?string $flash */ ?>
<?php /** @var ?string $error */ ?>
<?php /** @var ?array $reminders */ ?>
<?php /** @var ?\DateTimeImmutable $pausedUntil */ ?>
<?php /** @var ?string $pausedHuman */ ?>
<?php /** @var bool $emailEnabled */ ?>
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
      <h2 class="card-title">Reminders</h2>
      <p class="muted-inline">In-app nudges. Email optional.</p>
    </header>

    <p class="muted small reminder-cron-note">
      A background check runs each minute to see if you're due for a nudge.
      The chips above silence everything for a stretch when you want a quiet day.
    </p>

    <div class="pause-bar" role="group" aria-label="Master pause">
      <?php if (!empty($pausedUntil)): ?>
        <div class="pause-bar__status">
          <span class="pause-bar__badge">Paused</span>
          <span class="muted small">until <strong><?= View::escape($pausedHuman ?? 'later') ?></strong></span>
        </div>
        <form method="post" action="/settings/pause" class="inline-form">
          <input type="hidden" name="_csrf" value="<?= View::escape(\App\Csrf::token()) ?>">
          <input type="hidden" name="duration" value="off">
          <button class="btn btn-ghost btn-sm" type="submit">Resume</button>
        </form>
      <?php else: ?>
        <span class="muted small pause-bar__label">Pause all reminders:</span>
        <form method="post" action="/settings/pause" class="pause-bar__chips">
          <input type="hidden" name="_csrf" value="<?= View::escape(\App\Csrf::token()) ?>">
          <button class="pause-chip" type="submit" name="duration" value="1h">1 hour</button>
          <button class="pause-chip" type="submit" name="duration" value="3h">3 hours</button>
          <button class="pause-chip" type="submit" name="duration" value="evening">Until evening</button>
          <button class="pause-chip" type="submit" name="duration" value="bedtime">Until bedtime</button>
          <button class="pause-chip" type="submit" name="duration" value="tomorrow">Until tomorrow</button>
        </form>
      <?php endif; ?>
    </div>

    <form method="post" action="/settings/reminders" class="reminders-form" novalidate>
      <input type="hidden" name="_csrf" value="<?= View::escape(\App\Csrf::token()) ?>">

      <div class="reminder-row" data-kind="drinking">
        <label class="reminder-row__main">
          <input type="checkbox" name="reminders[drinking][enabled]" value="1" <?= !empty($reminders['drinking']['enabled']) ? 'checked' : '' ?>>
          <span>
            <strong>Drink a glass of water</strong>
            <span class="muted small d-block">A sip every couple of hours is plenty.</span>
          </span>
        </label>
        <div class="reminder-row__controls">
          <label class="field-inline">
            <span class="field-label">Every</span>
            <input class="field-input" type="number" name="reminders[drinking][threshold_minutes]" min="15" max="360" step="15" value="<?= (int) ($reminders['drinking']['threshold_minutes'] ?? 120) ?>">
            <span class="muted small">min</span>
          </label>
          <label class="reminder-row__email <?= empty($emailEnabled) ? 'is-disabled' : '' ?>" title="<?= empty($emailEnabled) ? 'Email side-channel is off — set SEND_NOTIFICATIONS_EMAIL=true in .env' : 'Also email this reminder' ?>">
            <input type="checkbox" name="reminders[drinking][notify_email]" value="1" <?= !empty($reminders['drinking']['notify_email']) ? 'checked' : '' ?> <?= empty($emailEnabled) ? 'disabled' : '' ?>>
            <span>Email me too</span>
          </label>
        </div>
      </div>

      <?php foreach (['mindful', 'intentions', 'mood', 'sleep'] as $kind):
        $r = $reminders[$kind] ?? null;
        $label = ['mindful' => 'Sit still for two minutes', 'intentions' => 'Add today’s intention', 'mood' => 'Check in on your mood', 'sleep' => 'Wind down for the night'][$kind];
        $hint  = ['mindful' => 'When the day feels loud.', 'intentions' => 'A small item fits.', 'mood' => 'A line is plenty. Honest is fine.', 'sleep' => 'Twenty minutes before bed, give the screen a rest.'][$kind];
      ?>
      <div class="reminder-row" data-kind="<?= View::escape($kind) ?>">
        <label class="reminder-row__main">
          <input type="checkbox" name="reminders[<?= View::escape($kind) ?>][enabled]" value="1" <?= !empty($r['enabled']) ? 'checked' : '' ?>>
          <span>
            <strong><?= View::escape($label) ?></strong>
            <span class="muted small d-block"><?= View::escape($hint) ?></span>
          </span>
        </label>
        <div class="reminder-row__controls">
          <label class="field-inline">
            <span class="field-label">At</span>
            <input class="field-input" type="time" name="reminders[<?= View::escape($kind) ?>][scheduled_time]" step="60" value="<?= View::escape(substr((string) ($r['scheduled_time'] ?? '09:00'), 0, 5)) ?>">
          </label>
          <label class="reminder-row__email <?= empty($emailEnabled) ? 'is-disabled' : '' ?>" title="<?= empty($emailEnabled) ? 'Email side-channel is off — set SEND_NOTIFICATIONS_EMAIL=true in .env' : 'Also email this reminder' ?>">
            <input type="checkbox" name="reminders[<?= View::escape($kind) ?>][notify_email]" value="1" <?= !empty($r['notify_email']) ? 'checked' : '' ?> <?= empty($emailEnabled) ? 'disabled' : '' ?>>
            <span>Email me too</span>
          </label>
        </div>
      </div>
      <?php endforeach; ?>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Save reminders</button>
      </div>
    </form>

    <div class="reminder-test-row">
      <span class="muted small">Confirm the channels work without waiting for the cron:</span>
      <form method="post" action="/settings/test-notification" class="inline-form">
        <input type="hidden" name="_csrf" value="<?= View::escape(\App\Csrf::token()) ?>">
        <button class="btn btn-ghost btn-sm" type="submit">Test bell</button>
      </form>
      <?php if (!empty($emailEnabled)): ?>
        <form method="post" action="/settings/test-email" class="inline-form">
          <input type="hidden" name="_csrf" value="<?= View::escape(\App\Csrf::token()) ?>">
          <button class="btn btn-ghost btn-sm" type="submit">Test email</button>
        </form>
      <?php else: ?>
        <span class="muted small" title="Set SEND_NOTIFICATIONS_EMAIL=true in .env to enable">Test email (enable in .env)</span>
      <?php endif; ?>
    </div>
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
        <input class="field-input" type="text" name="confirm" required pattern="(?i)^delete$">
      </label>
      <button class="btn btn-danger" type="submit">Delete account</button>
    </form>
  </article>
</section>

<script>
  // Immediate submit feedback for the settings forms. The server still
  // does the real work and redirects back with a flash; this just stops
  // the user from wondering "did anything happen?" during the roundtrip.
  (function () {
    'use strict';
    const labels = {
      'Save': 'Saving\u2026',
      'Save reminders': 'Saving\u2026',
      'Update password': 'Updating\u2026',
      'Delete account': 'Deleting\u2026',
      'Resume': 'Resuming\u2026',
    };
    document.querySelectorAll('form[action^="/settings/"]').forEach((f) => {
      f.addEventListener('submit', (ev) => {
        if (f.dataset.confirmOnSubmit) return;
        // ev.submitter is the actual button that triggered the submit
        // — much more accurate than f.querySelector('button[type="submit"]'),
        // which always picked the FIRST submit button ("1 hour") and
        // would have shown the spinner on the wrong chip when the user
        // clicked 3h, evening, bedtime, or tomorrow. ev.submitter is
        // null when the form is submitted without an activated button
        // (e.g. Enter pressed in a future text input, or programmatic
        // .requestSubmit()); the querySelector fallback is for that
        // uncommon path. ev.submitter has been Baseline-stable since
        // 2022 (Chrome 99+, Firefox 92+, Safari 15.4+).
        const btn = ev.submitter || f.querySelector('button[type="submit"]');
        if (!btn || btn.dataset.busy === '1') return;
        const original = btn.textContent.trim();
        btn.dataset.busy = '1';
        const next = labels[original] || 'Saving\u2026';
        btn.innerHTML = '<span class="btn-spinner" aria-hidden="true"></span><span class="btn-spinner-label">' + next + '</span>';
        // IMPORTANT: do NOT disable the button synchronously. Per HTML
        // spec, disabled controls are excluded from the submitted form
        // data set, which would strip name="duration" value="1h" off
        // the pause-chip click and turn it into a no-op on the server
        // ("Unknown pause duration."). Defer to the next event-loop
        // tick so the browser has finished constructing the form-data
        // set and dispatched the network request before the button
        // becomes un-submittable. btn.isConnected guards against the
        // form being replaced between the submit event and the tick.
        setTimeout(() => {
          if (btn.isConnected) {
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
          }
        }, 0);
        try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch (_) {}
      });
    });

    const flash = document.querySelector('.form-flash');
    if (flash) {
      flash.setAttribute('tabindex', '-1');
      flash.focus({ preventScroll: false });
    }
  })();
</script>
