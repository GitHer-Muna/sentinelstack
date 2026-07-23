<?php use App\View; ?>
<?php use App\Csrf; ?>
<section class="mood-page">
  <article class="card">
    <header class="card-head">
      <h2 class="card-title">Today's check-in</h2>
    </header>

    <form id="mood-form" class="form-stack" novalidate>
      <input type="hidden" name="_csrf" value="<?= View::escape(\App\Csrf::token()) ?>">
      <input type="hidden" name="action" value="save">

      <fieldset class="mood-set" aria-label="Today's mood">
        <legend class="field-label">How are you feeling?</legend>
        <?php foreach ($moods as $m): ?>
          <label class="mood-option">
            <input type="radio" name="mood" value="<?= $m ?>" <?= ($today['mood'] ?? '') === $m ? 'checked' : '' ?> required>
            <span class="mood-face" data-mood="<?= $m ?>"></span>
            <span class="mood-label"><?= ucfirst($m) ?></span>
          </label>
        <?php endforeach; ?>
      </fieldset>

      <label class="field">
        <span class="field-label">One thing you're grateful for</span>
      <textarea class="field-input" name="gratitude" maxlength="240" rows="2" placeholder="e.g. A quiet morning coffee"><?= View::escape($today['gratitude'] ?? '') ?></textarea>
      </label>

      <label class="field">
        <span class="field-label">Note (optional)</span>
        <textarea class="field-input" name="note" rows="3" placeholder="Anything else worth remembering."><?= View::escape($today['note'] ?? '') ?></textarea>
      </label>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Save</button>
      </div>
    </form>
  </article>

  <article class="card">
    <header class="card-head">
      <h2 class="card-title">History</h2>
    </header>
    <?php if (empty($history)): ?>
      <div class="empty-slate" role="status">
        <span class="empty-slate__icon" aria-hidden="true">
          <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="24" cy="24" r="14"/>
            <circle cx="24" cy="22" r="1.5" fill="currentColor"/>
            <path d="M22 30c1 1 6 1 8-1"/>
          </svg>
        </span>
        <h3 class="empty-slate__title">No check-ins yet.</h3>
        <p class="empty-slate__body">A "fine" today is fine. A "rough" today is fine too. Pick whatever is true and move on.</p>
        <button class="btn btn-secondary empty-slate__cta" type="button" data-action="focus-first-mood">Add today’s mood</button>
      </div>
    <?php else: ?>
      <ul class="mood-history">
        <?php foreach ($history as $h): ?>
          <li class="mood-history-item">
            <span class="mood-dot small" data-mood="<?= View::escape($h['mood']) ?>"></span>
            <div class="mood-history-body">
              <div class="mood-history-head">
                <strong><?= View::escape(ucfirst($h['mood'])) ?></strong>
                <span class="muted"><?= View::escape($h['local_date']) ?></span>
              </div>
              <?php if (!empty($h['gratitude'])): ?>
                <p class="mood-grat">"<?= View::escape($h['gratitude']) ?>"</p>
              <?php endif; ?>
              <?php if (!empty($h['note'])): ?>
                <p class="mood-note"><?= nl2br(View::escape($h['note'])) ?></p>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </article>
</section>
