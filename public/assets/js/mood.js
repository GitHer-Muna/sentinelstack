/* Mood + gratitude page controller. */
(function () {
  'use strict';
  const W = window.W;
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('mood-form');
    if (!form) return;
    form.addEventListener('submit', (ev) => {
      ev.preventDefault();
      const submitBtn = form.querySelector('button[type="submit"]');
      const data = new FormData(form);
      const payload = Object.fromEntries(data);
      payload.action = 'save';
      W.loading(submitBtn, 'Saving\u2026');
      W.lockForm(form, true);
      W.api('/api/mood', payload)
        .then(() => {
          W.toast('Check-in saved.', 'success');
          W.announce('Check-in saved.');
          setTimeout(() => window.location.reload(), 320);
        })
        .catch((e) => W.toast(e.message, 'error'))
        .finally(() => {
          W.loaded(submitBtn);
          W.lockForm(form, false);
        });
    });
  });
})();
