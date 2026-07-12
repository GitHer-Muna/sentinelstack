/* Sleep page controller.
   - Submit the form to /api/sleep (action=log) → reload on success.
   - Click a "Remove" button → confirm dialog → /api/sleep (action=delete) → reload.
*/
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', () => {
    const W = window.W;
    const form = document.getElementById('sleep-form');
    if (form) {
      form.addEventListener('submit', (ev) => {
        ev.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        if (!form.checkValidity()) {
          W.qsa('input', form).forEach(el => {
            el.classList.toggle('is-invalid', !el.checkValidity());
          });
          W.toast('Please fix the highlighted fields.', 'error');
          return;
        }
        const data = new FormData(form);
        const payload = Object.fromEntries(data);
        payload.action = 'log';
        W.loading(submitBtn, 'Saving\u2026');
        W.lockForm(form, true);
        W.api('/api/sleep', payload)
          .then(() => {
            W.toast('Sleep logged.', 'success');
            W.announce('Sleep logged.');
            setTimeout(() => window.location.reload(), 320);
          })
          .catch((e) => W.toast(e.message, 'error'))
          .finally(() => {
            W.loaded(submitBtn);
            W.lockForm(form, false);
          });
      });
    }

    document.addEventListener('click', (ev) => {
      const t = ev.target.closest('[data-action="delete"]');
      if (!t) return;
      const id = t.dataset.id;
      const item = t.closest('.sleep-history-item');
      W.confirm({
        title: 'Remove this entry?',
        body: 'It will be gone from your history. Streaks will recompute.',
        confirmLabel: 'Remove',
        danger: true,
      }).then((ok) => {
        if (!ok) return;
        if (item) item.classList.add('is-deleting');
        W.loading(t, 'Removing...');
        W.api('/api/sleep', { action: 'delete', id })
          .then(() => window.location.reload())
          .catch((e) => {
            if (item) item.classList.remove('is-deleting');
            W.toast(e.message, 'error');
            W.loaded(t);
          });
      });
    });
  });
})();
