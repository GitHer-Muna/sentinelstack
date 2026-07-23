/* Notifications — bell icon + drawer polling + mark-read.
   Reuses window.W from app.js (W.api, W.toast, W.confirm).
*/
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', () => {
    const W = window.W;
    const btn    = document.getElementById('notif-toggle');
    const drawer = document.getElementById('notif-drawer');
    if (!btn || !drawer) return;

    const body  = drawer.querySelector('.notif-drawer__body');
    const badge = btn.querySelector('[data-unread-count]');
    let open = false;
    const POLL_MS = 60_000;

    async function refresh() {
      try {
        const r = await W.api('/api/notifications', { action: 'list' });
        if (!r || !r.ok) return;
        render(r.notifications || [], r.unread || 0);
      } catch (_) { /* offline — leave existing state in place */ }
    }

    function render(items, unread) {
      if (badge) {
        if (unread > 0) {
          badge.hidden = false;
          badge.textContent = unread > 99 ? '99+' : String(unread);
        } else {
          badge.hidden = true;
          badge.textContent = '0';
        }
      }
      body.innerHTML = '';
      if (!items.length) {
        const slate = document.createElement('p');
        slate.className = 'muted small';
        slate.textContent = 'Nothing here yet.';
        body.appendChild(slate);
        return;
      }
      items.forEach((n) => {
        const row = document.createElement('article');
        row.className = 'notif-item' + (n.read_at ? ' is-read' : '');
        row.dataset.id = n.id;

        // Color-coded kind label so the user can scan the inbox at a glance.
        const meta = document.createElement('p');
        meta.className = 'notif-item__kind';
        meta.dataset.kind = n.kind;
        const dot = document.createElement('span');
        dot.className = 'notif-item__dot';
        dot.setAttribute('aria-hidden', 'true');
        meta.append(dot, document.createTextNode(n.kind));

        const notifBody = document.createElement('p');
        notifBody.className = 'notif-item__body';
        notifBody.textContent = n.body;

        // Server always populates fired_at_display in the USER's
        // timezone (Notification::recent formats it before sending —
        // fired_at_local has no offset stored, so client-side parsing
        // would silently use the device TZ, which is wrong for a user
        // on a phone that's traveling, running a guest VM, etc.).
        const when = document.createElement('p');
        when.className = 'notif-item__time muted small';
        when.textContent = n.fired_at_display;

        row.append(meta, notifBody, when);

        if (!n.read_at) {
          const readBtn = document.createElement('button');
          readBtn.type = 'button';
          readBtn.className = 'link-btn';
          readBtn.dataset.action = 'mark-read';
          readBtn.dataset.id = n.id;
          readBtn.textContent = 'Mark read';
          row.appendChild(readBtn);
        } else if (n.delivered_email) {
          const tag = document.createElement('span');
          tag.className = 'notif-item__email-tag';
          tag.textContent = 'emailed';
          row.appendChild(tag);
        }
        body.appendChild(row);
      });
    }

    function setOpen(next) {
      open = next;
      drawer.hidden = !next;
      btn.setAttribute('aria-expanded', next ? 'true' : 'false');
      if (next) refresh();
    }

    btn.addEventListener('click', () => setOpen(!open));
    // Close drawer when the user clicks outside it.
    document.addEventListener('click', (ev) => {
      if (!open) return;
      if (drawer.contains(ev.target) || btn.contains(ev.target)) return;
      setOpen(false);
    });

    drawer.addEventListener('click', (ev) => {
      const t = ev.target;
      if (t.closest('[data-action="close"]')) { setOpen(false); return; }
      const readBtn = ev.target.closest('[data-action="mark-read"]');
      if (readBtn) {
        W.loading(readBtn, 'Marking...');
        W.api('/api/notifications', { action: 'mark-read', id: readBtn.dataset.id })
          .then(refresh)
          .catch((e) => { W.toast(e.message, 'error'); W.loaded(readBtn); });
        return;
      }
      const allBtn = ev.target.closest('[data-action="mark-all-read"]');
      if (allBtn) {
        W.loading(allBtn, 'Marking...');
        W.api('/api/notifications', { action: 'mark-all-read' })
          .then(refresh)
          .catch((e) => { W.toast(e.message, 'error'); W.loaded(allBtn); });
        return;
      }
      // Local-only: hide already-read rows with a fade-out. No server
      // call — clearing the inbox view doesn't change read_at in the DB.
      const clearBtn = ev.target.closest('[data-action="clear-read"]');
      if (clearBtn) {
        const read = W.qsa('.notif-item.is-read', drawer);
        if (!read.length) {
          W.toast('Nothing read to clear.', 'info');
          return;
        }
        read.forEach(item => item.classList.add('is-removing'));
        setTimeout(() => read.forEach(item => item.remove()), 280);
        W.toast(`Cleared ${read.length} read notification${read.length === 1 ? '' : 's'}.`, 'success');
        return;
      }
    });

    // Initial badge load + periodic refresh so a new reminder shows up
    // even if the user never opens the drawer.
    refresh();
    setInterval(refresh, POLL_MS);
  });
})();
