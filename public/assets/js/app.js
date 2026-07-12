/* Wellspring — chrome helpers.
   Foundation primitives used by every page module.
   Exposes window.W with toast, confirm, announce, api, passwordStrength, etc.
*/
(function () {
  'use strict';

  const W = {};
  W.qs  = (sel, root = document) => root.querySelector(sel);
  W.qsa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  W.escape = (s) => String(s).replace(/[&<>"']/g, c => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  }[c]));

  // CSRF: prefer <meta name="csrf">, then inline JSON script, then a global.
  W.csrfToken = function () {
    const meta = document.querySelector('meta[name="csrf"]');
    if (meta && meta.content) return meta.content;
    if (window.__WS_CSRF__) return window.__WS_CSRF__;
    const data = document.querySelector('script[id$="-data"]');
    if (data) {
      try { return JSON.parse(data.textContent).csrf || ''; } catch (_) {}
    }
    return '';
  };

  // Generic CSRF-aware API call. Wrapped below to drive the top progress bar
  // and the slow-request hint automatically — every call site gets the
  // visual feedback for free.
  const _apiRaw = async function (path, payload = null, opts = {}) {
    const headers = { 'Accept': 'application/json' };
    let body;
    if (payload instanceof FormData) {
      const csrf = W.csrfToken();
      if (csrf && !payload.has('_csrf')) payload.append('_csrf', csrf);
      body = payload;
    } else if (payload) {
      const data = new FormData();
      Object.entries(payload).forEach(([k, v]) => {
        if (Array.isArray(v)) data.append(k + '[]', v);
        else if (v !== undefined && v !== null) data.append(k, String(v));
      });
      const csrf = W.csrfToken();
      if (csrf) data.append('_csrf', csrf);
      body = data;
    } else {
      const csrf = W.csrfToken();
      if (csrf) headers['X-CSRF-Token'] = csrf;
    }
    const r = await fetch(path, {
      method: 'POST',
      headers,
      credentials: 'same-origin',
      body,
      ...opts,
    });
    let json = null;
    try { json = await r.json(); } catch (_) {}
    if (!r.ok || !json) {
      const msg = (json && json.error) || ('Request failed (' + r.status + ')');
      throw new Error(msg);
    }
    return json;
  };

  // ── Top progress bar (NProgress-style) ────────────────────────────────
  // A 2px sage line at the very top of the viewport that rises as an
  // AJAX request starts and snaps to 100% on completion. Rises quickly
  // to 30%, then creeps to ~75% so the bar keeps moving (the user sees
  // the app isn't frozen) without ever reaching 100% before the request
  // is actually done. On failure, briefly turns terracotta before
  // fading. The auto-wrap below means every W.api call gets this for
  // free, so a slow request has visible feedback even on a fast network.
  W.startProgress = function () {
    const bar = document.getElementById('top-progress');
    if (!bar) return;
    bar._count = (bar._count || 0) + 1;
    if (bar._count > 1) {
      // Another request is already in flight. If the bar is mid-fade
      // (is-done just-completed phase, or is-fail just-failed phase),
      // interrupt the fade and resume the in-flight animation from
      // 60% so the user sees the new work continuing. Otherwise leave
      // the existing animation alone.
      if (bar.classList.contains('is-done') || bar.classList.contains('is-fail')) {
        bar.classList.remove('is-done', 'is-fail');
        bar.style.transition = 'none';
        bar.style.width = '60%';
        void bar.offsetWidth;
        bar.style.transition = 'width 800ms cubic-bezier(.2,.7,.2,1)';
        bar.style.width = '75%';
      }
      return;
    }
    // First request: start the bar fresh.
    bar.classList.remove('is-done', 'is-fail');
    bar.style.transition = 'none';
    bar.style.width = '0%';
    bar.hidden = false;
    // Force a paint so the reset takes effect, then animate to 30%.
    void bar.offsetWidth;
    bar.style.transition = 'width 280ms cubic-bezier(.2,.7,.2,1)';
    requestAnimationFrame(() => { if (bar._count > 0) bar.style.width = '30%'; });
    setTimeout(() => { if (bar._count > 0) { bar.style.transition = 'width 600ms cubic-bezier(.2,.7,.2,1)'; bar.style.width = '60%'; } }, 200);
    setTimeout(() => { if (bar._count > 0) { bar.style.transition = 'width 800ms cubic-bezier(.2,.7,.2,1)'; bar.style.width = '75%'; } }, 800);
  };
  W.doneProgress = function () {
    const bar = document.getElementById('top-progress');
    if (!bar || !bar._count) return;
    bar._count--;
    if (bar._count > 0) return;  // other requests still in flight
    // Last in-flight request just finished.
    bar.classList.add('is-done');
    bar.style.transition = 'width 220ms cubic-bezier(.2,.7,.2,1)';
    bar.style.width = '100%';
    setTimeout(() => {
      if (bar._count > 0) return;  // a new request bumped the count during the fade
      bar.hidden = true;
      bar.classList.remove('is-done');
      bar.style.transition = 'none';
      bar.style.width = '0%';
    }, 360);
  };
  W.failProgress = function () {
    const bar = document.getElementById('top-progress');
    if (!bar || !bar._count) return;
    bar._count--;
    if (bar._count > 0) return;  // other requests still in flight
    // Last in-flight request failed.
    bar.classList.add('is-fail');
    setTimeout(() => {
      if (bar._count > 0) return;  // a new request bumped the count during the fade
      bar.hidden = true;
      bar.classList.remove('is-fail');
      bar.style.transition = 'none';
      bar.style.width = '0%';
    }, 600);
  };

  // Wrap W.api so every AJAX request drives the top bar. Callers see
  // the same signature; the only addition is the visual feedback.
  W.api = async function (path, payload = null, opts = {}) {
    W.startProgress();
    try {
      const r = await _apiRaw(path, payload, opts);
      W.doneProgress();
      return r;
    } catch (e) {
      W.failProgress();
      throw e;
    }
  };

  // ── Toast ────────────────────────────────────────────────────────────────
  // Class-driven animation (see chrome.css .toast / .toast--* keyframes).
  W.toast = function (msg, kind = 'info', opts = {}) {
    const stage = document.body;
    const el = document.createElement('div');
    el.className = 'toast toast--' + kind;
    el.setAttribute('role', kind === 'error' ? 'alert' : 'status');

    const ico = document.createElement('span');
    ico.className = 'toast__icon';
    ico.setAttribute('aria-hidden', 'true');
    ico.innerHTML = (kind === 'success'
      ? '<svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8 L7 12 L13 4"/></svg>'
      : kind === 'error'
        ? '<svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M8 4v5M8 12v0.01"/></svg>'
        : '<svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="8" cy="8" r="6.5"/><path d="M8 7v4M8 4.5v0.01"/></svg>');

    const txt = document.createElement('span');
    txt.className = 'toast__msg';
    txt.textContent = msg;

    const close = document.createElement('button');
    close.className = 'toast__close';
    close.type = 'button';
    close.setAttribute('aria-label', 'Dismiss');
    close.innerHTML = '&times;';

    // Optional inline action button (e.g. "Undo"). Sits between the
    // message and the close X. Clicking the action calls onClick then
    // dismisses the toast immediately so it doesn't linger while the
    // restore request is in flight.
    if (opts.action && opts.action.label && typeof opts.action.onClick === 'function') {
      const actionBtn = document.createElement('button');
      actionBtn.className = 'toast__action';
      actionBtn.type = 'button';
      actionBtn.textContent = opts.action.label;
      actionBtn.addEventListener('click', () => {
        try { opts.action.onClick(); } catch (_) {}
        dismiss();
      });
      el.append(actionBtn);
    }

    el.append(ico, txt, close);
    el.classList.add('toast--stacked');
    // Re-index all current toasts so the oldest sits at the top of the stack
    // and the newest at the bottom (CSS positions them via --i).
    const reindex = () => {
      const existing = stage.querySelectorAll('.toast');
      let i = 0;
      existing.forEach((n) => { n.style.setProperty('--i', String(i++)); });
    };
    reindex();
    el.style.setProperty('--i', String(stage.querySelectorAll('.toast').length - 1));
    stage.appendChild(el);

    let removed = false;
    const finalize = () => {
      // Single cleanup path: remove the node (idempotent if both the
      // animation-end and the safety-net timeout fire) and reindex the
      // remaining siblings. Doing this in one place keeps DOM-query cost
      // bounded on heavy-toast pages.
      el.remove();
      reindex();
    };
    const dismiss = () => {
      if (removed) return;
      removed = true;
      el.classList.add('leaving');
      el.addEventListener('animationend', finalize, { once: true });
      // Safety net in case animationend doesn't fire (e.g. reduced-motion
      // strips the duration to 0.001ms and the event might race).
      setTimeout(() => { if (el.parentNode) finalize(); }, 600);
    };
    close.addEventListener('click', dismiss);

    // Announce to screen readers.
    W.announce(String(msg), kind === 'error' ? 'assertive' : 'polite');

    // Errors are too easy to miss when they auto-dismiss in 4s, so by
    // default they require a manual close. Callers can still pass an
    // explicit duration to override (e.g. a 1.5s inline hint).
    if (kind === 'error' && opts.duration === undefined) {
      opts.duration = 0;
    }
    setTimeout(dismiss, opts.duration ?? 4000);
    return { dismiss };
  };

  // ── Confirm dialog (native <dialog>, themed) ──────────────────────────────
  W.confirm = function ({ title, body, confirmLabel = 'Confirm', cancelLabel = 'Cancel', danger = false } = {}) {
    return new Promise((resolve) => {
      const dlg = document.getElementById('confirm-dialog');
      if (!dlg) { resolve(window.confirm(`${title || ''}\n\n${body || ''}`)); return; }

      const titleEl = dlg.querySelector('#confirm-dialog-title');
      const msgEl   = dlg.querySelector('#confirm-dialog-msg');
      const okBtn   = dlg.querySelector('button[value="confirm"]');
      const cBtn    = dlg.querySelector('button[value="cancel"]');

      if (titleEl) titleEl.textContent = title || 'Are you sure?';
      if (msgEl)   msgEl.textContent   = body  || 'This cannot be undone.';
      if (okBtn)   { okBtn.textContent = confirmLabel; okBtn.classList.toggle('btn-danger', !!danger); okBtn.classList.toggle('btn-primary', !danger); }
      if (cBtn)    cBtn.textContent    = cancelLabel;

      const onClose = () => {
        dlg.removeEventListener('close', onClose);
        resolve(dlg.returnValue === 'confirm');
      };
      dlg.addEventListener('close', onClose);
      dlg.showModal();
      if (okBtn) okBtn.focus();
    });
  };

  // ── Live-region announcer (used by toasts and form save confirmations) ──
  W.announce = function (msg, politeness = 'polite') {
    const root = document.getElementById('sr-announce');
    if (!root) return;
    if (root.getAttribute('aria-live') !== politeness) root.setAttribute('aria-live', politeness);
    root.textContent = '';
    // Force a re-read by toggling.
    setTimeout(() => { root.textContent = String(msg); }, 30);
  };

  // ── Inline form validation (no-reload, no server roundtrip) ──────────────
  W.bindInlineValidation = function (form) {
    if (!form) return;
    form.addEventListener('submit', (ev) => {
      W.qsa('.field-error, .is-invalid', form).forEach(n => n.classList.remove('is-invalid'));
      W.qsa('.field-error', form).forEach(n => n.remove());
      // Validate EVERY form control, not just [required] — so we catch pattern/min/max/minlength
      // on optional fields too. (checkValidity() returns true for empty non-required fields,
      // so this never spuriously flags empty optional inputs.)
      const fields = form.querySelectorAll('input, select, textarea');
      let firstInvalid = null;
      fields.forEach(el => {
        const ok = el.checkValidity();
        if (!ok) {
          ev.preventDefault();
          el.classList.add('is-invalid');
          const host = el.closest('.field') || el.parentNode;
          if (host && !host.querySelector('.field-error')) {
            const err = document.createElement('span');
            err.className = 'field-error';
            err.textContent = el.validationMessage || 'Please correct this field.';
            host.appendChild(err);
          }
          if (!firstInvalid) firstInvalid = el;
        }
      });
      if (firstInvalid) {
        // Scroll the invalid field into view BEFORE focusing, so a long
        // form's error is on-screen when the user reads the toast. The
        // {block: 'center'} keeps the field roughly mid-viewport on
        // mobile, which is the most common place the field is off-screen.
        try { firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (_) {}
        firstInvalid.focus({ preventScroll: false });
        W.toast('Please fix the highlighted fields.', 'error');
      }
    });
  };

  // Bring a specific element (or the first invalid one) into view. Used
  // by per-page error handlers that don't go through bindInlineValidation.
  W.scrollIntoViewIfInvalid = function (el) {
    const target = el || document.querySelector('.is-invalid');
    if (target) {
      try { target.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (_) {}
    }
  };

  // ── Confirm-on-submit guard for destructive forms ─────────────────────────
  W.bindConfirmOnSubmit = function (form, customMessage) {
    if (!form) return;
    const message = customMessage || form.dataset.confirmOnSubmit || 'Are you sure?';
    const submitBtn = form.querySelector('button[type="submit"]');
    form.addEventListener('submit', (ev) => {
      if (form.dataset.confirmed === 'true') return; // already confirmed in this cycle
      // Validate the form BEFORE showing the confirm dialog. checkValidity() works even with
      // novalidate, and honors pattern / minlength / min / max — so a user typing "hello"
      // in a pattern="^delete$" field can't bypass the check by reaching the dialog.
      if (!form.checkValidity()) {
        ev.preventDefault();
        W.qsa('input, select, textarea', form).forEach(el => {
          const ok = el.checkValidity();
          el.classList.toggle('is-invalid', !ok);
          if (!ok) {
            const host = el.closest('.field') || el.parentNode;
            if (host && !host.querySelector('.field-error')) {
              const err = document.createElement('span');
              err.className = 'field-error';
              err.textContent = el.validationMessage || 'Please correct this field.';
              host.appendChild(err);
            }
          }
        });
        W.toast('Please complete the required fields correctly.', 'error');
        return;
      }
      ev.preventDefault();
      W.confirm({
        title: 'Just to confirm…',
        body: message,
        confirmLabel: submitBtn ? submitBtn.textContent.trim() : 'Confirm',
        danger: true,
      }).then((ok) => {
        if (!ok) return;
        form.dataset.confirmed = 'true';
        form.requestSubmit ? form.requestSubmit() : form.submit();
      });
    });
  };

  // ── Password strength meter ──────────────────────────────────────────────
  W.bindPasswordStrength = function (input, meter) {
    if (!input || !meter) return;
    meter.hidden = false;
    const fill = meter.querySelector('.pw-strength__fill');
    const label = meter.querySelector('.pw-strength__label');
    const labels = ['', 'Weak', 'Fair', 'Good', 'Strong', 'Excellent'];
    function score(s) {
      if (!s) return 0;
      let n = 0;
      if (s.length >= 8) n++;
      if (s.length >= 12) n++;
      if (/[A-Z]/.test(s) && /[a-z]/.test(s)) n++;
      if (/\d/.test(s)) n++;
      if (/[^A-Za-z0-9]/.test(s)) n++;
      return Math.min(5, n);
    }
    input.addEventListener('input', () => {
      const v = input.value;
      const s = score(v);
      meter.dataset.score = String(s);
      if (fill) fill.style.width = (s <= 0 ? 0 : s * 20) + '%';
      if (label) label.textContent = labels[s] || '';
    });
    // Initialize with current value (e.g. on autofill).
    input.dispatchEvent(new Event('input'));
  };

  // ── Password-match hint ──────────────────────────────────────────────────
  W.bindPasswordMatch = function (newPw, confirm) {
    if (!newPw || !confirm) return;
    const hint = document.querySelector('[data-confirm-hint]');
    function sync() {
      const ok = newPw.value && newPw.value === confirm.value;
      confirm.classList.toggle('is-invalid', confirm.value && !ok);
      if (hint) { hint.hidden = !ok; hint.textContent = ok ? 'Passwords match.' : 'Passwords don\u2019t match yet.'; }
    }
    newPw.addEventListener('input', sync);
    confirm.addEventListener('input', sync);
  };

  // ── Network offline / online banner ────────────────────────────────────
  // Sticky terracotta banner at the top of the page when the browser
  // detects no connection. Auto-dismisses on reconnect with an
  // announced "Back online." so a user on flaky wifi knows the app
  // is responsive again. Bound once and idempotent — safe to call
  // from both the top of the IIFE (in case the script runs after
  // DOMContentLoaded) and the DOMContentLoaded block.
  W.bindNetworkBanner = function () {
    if (W._netBannerBound) {
      // Just re-sync state in case the script ran before #offline-banner existed.
      const banner0 = document.getElementById('offline-banner');
      if (banner0) banner0.hidden = navigator.onLine;
      return;
    }
    W._netBannerBound = true;
    const update = () => {
      const banner = document.getElementById('offline-banner');
      if (!banner) return;
      if (navigator.onLine) {
        if (!banner.hidden) {
          banner.hidden = true;
          W.announce('Back online.');
        }
      } else {
        banner.hidden = false;
        W.announce('You\u2019re offline.', 'assertive');
      }
    };
    window.addEventListener('online',  update);
    window.addEventListener('offline', update);
    update();
  };

  // Auto-bind everything we know about on DOMContentLoaded.
  // We bind inline validation to any form that EITHER opts in via
  // [data-inline-validate] OR opted out of native validation via [novalidate]
  // (these are exactly the forms whose invalid submits we want to catch).
  document.addEventListener('DOMContentLoaded', () => {
    // Forms with data-confirm-on-submit run their own validation inside bindConfirmOnSubmit;
    // skip inline validation here to avoid double-binding and duplicate error UI.
    W.qsa('form[data-inline-validate], form[novalidate]').forEach(f => {
      if (f.dataset.confirmOnSubmit) return;
      W.bindInlineValidation(f);
    });
    W.qsa('form[data-confirm-on-submit]').forEach(f => W.bindConfirmOnSubmit(f));
    const pwIn = document.querySelector('[data-pw-strength]');
    const pwMe = document.querySelector('[data-pw-meter]');
    if (pwIn && pwMe) W.bindPasswordStrength(pwIn, pwMe);
    const np = document.querySelector('[data-pw-strength]');
    const cf = document.querySelector('[data-pw-confirm]');
    if (np && cf) W.bindPasswordMatch(np, cf);
    W.bindNetworkBanner();
  });

  // ── Button loading state ────────────────────────────────────────────────
  // Marks a submit/click button as busy (disabled + spinner + label) and
  // restores it on W.loaded(btn). Safe to call repeatedly — the second call
  // is a no-op until the button is restored. Without this, a "Save" click
  // looks exactly like "nothing happened" until the roundtrip completes.
  W.loading = function (btn, label) {
    if (!btn || btn.dataset.busy === '1') return;
    btn.dataset.busy = '1';
    btn.dataset.busyOriginal = btn.innerHTML;
    const txt = String(label || 'Working\u2026');
    btn.innerHTML = '<span class="btn-spinner" aria-hidden="true"></span><span class="btn-spinner-label">' + W.escape(txt) + '</span>';
    btn.disabled = true;
    btn.setAttribute('aria-busy', 'true');
    // Slow-request hint: if the button is still busy after 1.6s, swap
    // the label to "Still working…" so the user knows we didn't freeze.
    // The 1.6s threshold is below the 4s default toast auto-dismiss so
    // a healthy fast network never sees this; only slow links do.
    btn._slowTimer = setTimeout(() => {
      if (btn.dataset.busy === '1') {
        const lbl = btn.querySelector('.btn-spinner-label');
        if (lbl) lbl.textContent = 'Still working\u2026';
        W.announce('Still working on it.', 'polite');
      }
    }, 1600);
  };

  W.loaded = function (btn) {
    if (!btn || btn.dataset.busy !== '1') return;
    if (btn._slowTimer) { clearTimeout(btn._slowTimer); delete btn._slowTimer; }
    btn.innerHTML = btn.dataset.busyOriginal;
    btn.disabled = false;
    btn.removeAttribute('aria-busy');
    delete btn.dataset.busy;
    delete btn.dataset.busyOriginal;
  };

  // Disable every form control in a form (or re-enable them) so a half-sent
  // submission can't be edited underneath the in-flight request.
  W.lockForm = function (form, locked) {
    if (!form) return;
    W.qsa('input, select, textarea, button', form).forEach((el) => {
      if (el.dataset.keepEnabled === '1') return;
      el.disabled = !!locked;
    });
  };

  // ── Global error handlers ───────────────────────────────────────────────
  // Surface unhandled JS errors and promise rejections as visible toasts.
  // Without this, a TypeError in some page's click handler looks identical
  // to "I clicked the button and nothing happened" to the user.
  //
  // We only surface errors that originate in OUR scripts (same-origin, or
  // anything under /assets/ when the page is served from the forwarder).
  // Third-party scripts (Chart.js, Google Fonts, etc.) throw benign errors
  // for font-loading races and CDN quirks that we cannot fix; firing a
  // scary toast for them would be a worse UX than the silent failure they
  // would otherwise cause.
  const isOurScript = (filename) => {
    if (!filename) return true; // no filename (synthetic errors) — assume ours
    try {
      const u = new URL(filename, window.location.href);
      if (u.origin === window.location.origin) return true;
      // Forwarder-relayed assets come through our own origin even when the
      // request is technically from another host. Treat anything same-origin
      // (or under our /assets/ path) as ours.
      if (u.pathname.indexOf('/assets/') === 0) return true;
    } catch (_) {}
    return false;
  };
  window.addEventListener('error', (e) => {
    if (!isOurScript(e && e.filename)) return;
    if (e && e.error) console.error('[wellspring] unhandled error:', e.error);
    else if (e && e.message) console.error('[wellspring] unhandled error:', e.message);
    W.toast('Something went sideways. Please refresh and try again.', 'error');
  });
  window.addEventListener('unhandledrejection', (e) => {
    // Rejections from our W.api already throw an Error we catch in the
    // page handler; anything reaching here is a real bug we want to know
    // about. No filename to filter on for rejections, so we gate on shape
    // instead: our code always throws `new Error(msg)`, so a non-Error
    // rejection (string, undefined, object) is almost certainly from a
    // third-party script (Chart.js, vendor callbacks, etc.). Log those
    // and skip the toast — a scary "request didn't finish" for a benign
    // rejection is a worse UX than the silent failure it would otherwise
    // cause.
    if (!(e && e.reason instanceof Error)) {
      console.warn('[wellspring] non-Error unhandled rejection (suppressed):', e && e.reason);
      return;
    }
    console.error('[wellspring] unhandled promise rejection:', e.reason);
    W.toast('A request didn\u2019t finish. Please try again in a moment.', 'error');
  });

  window.W = W;
})();
