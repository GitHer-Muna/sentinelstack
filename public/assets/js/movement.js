/* Movement page controller. */
(function () {
  'use strict';
  const W = window.W;
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-action="mark"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const key = btn.dataset.key;
        const done = btn.dataset.done === 'true';
        const action = done ? 'unmark' : 'mark';
        const label = done ? 'Unmarking\u2026' : 'Marking done\u2026';
        W.loading(btn, label);
        W.api('/api/movement', { action, routine_key: key })
          .then(() => {
            W.toast(done ? 'Unmarked for today.' : 'Nice. One down for today.', 'success');
            W.announce(done ? 'Unmarked for today.' : 'Marked done.');
            setTimeout(() => window.location.reload(), 280);
          })
          .catch((e) => W.toast(e.message, 'error'))
          .finally(() => W.loaded(btn));
      });
    });
  });
})();
