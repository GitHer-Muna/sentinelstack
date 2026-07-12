/* Wellspring — theme toggle (light/dark).
   Smoothly crossfades layouts thanks to chrome surface transitions defined
   in layout.css. Persists to localStorage AND syncs to the server, with a
   failure-revert path so a stuck request never strands the toggle.
*/
(function () {
  'use strict';
  const KEY = 'wellspring_theme';
  const readPreferred = () => {
    try {
      const v = localStorage.getItem(KEY);
      if (v === 'dark' || v === 'light') return v;
    } catch (_) {}
    return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
  };
  const apply = (theme) => {
    document.documentElement.setAttribute('data-theme', theme);
    const btn = document.getElementById('theme-toggle');
    if (btn) {
      btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
      const sun  = btn.querySelector('[data-icon-sun]');
      const moon = btn.querySelector('[data-icon-moon]');
      if (sun)  sun.hidden  = (theme === 'dark');
      if (moon) moon.hidden = (theme !== 'dark');
    }
  };
  const persist = (theme) => {
    try { localStorage.setItem(KEY, theme); } catch (_) {}
  };

  // Decide the initial theme:
  // 1. If localStorage has an explicit choice, use it (overrides server pre-render).
  // 2. Otherwise, trust the server's `<html data-theme>` pre-render (avoid OS flash).
  let stored = null;
  try { stored = localStorage.getItem(KEY); } catch (_) {}
  if (stored === 'dark' || stored === 'light') apply(stored);

  document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('theme-toggle');
    if (!btn) return;
    btn.addEventListener('click', () => {
      const current = document.documentElement.getAttribute('data-theme');
      const next = current === 'dark' ? 'light' : 'dark';
      const prev = current;
      apply(next);
      persist(next);
      // Sync to server — failure-silent except revert on hard error.
      try {
        const csrf = window.W && window.W.csrfToken ? window.W.csrfToken() : '';
        const fd = new FormData();
        fd.append('_csrf', csrf);
        fd.append('theme', next);
        fetch('/settings/update', { method: 'POST', body: fd, credentials: 'same-origin' })
          .catch(() => { apply(prev); persist(prev); if (window.W && window.W.toast) window.W.toast('Couldn\u2019t save the theme change.', 'error'); });
      } catch (_) {}
      const label = next === 'dark' ? 'Dark mode.' : 'Light mode.';
      if (window.W) window.W.announce(label);
    });
  });

  // Cross-tab sync.
  window.addEventListener('storage', (e) => {
    if (e.key === KEY && e.newValue) apply(e.newValue);
  });
})();
