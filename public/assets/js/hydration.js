/* Hydration page controller. */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', () => {
    const data = JSON.parse(document.getElementById('hydration-data').textContent);
    const W = window.W;

    function fmtTime(iso) {
      const d = new Date(iso);
      return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }

    function renderEntries(entries, total, goal, pct) {
      const list = document.querySelector('.entries-list');
      if (list) {
        list.innerHTML = '';
        if (!entries.length) {
          // Match the server-rendered empty-slate exactly so a delete-then-empty list
          // doesn't visually regress to a raw paragraph.
          const slate = document.createElement('div');
          slate.className = 'empty-slate';
          slate.setAttribute('role', 'status');
          slate.innerHTML = '<span class="empty-slate__icon" aria-hidden="true">' +
            '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' +
            '<path d="M24 6c-4 6-9 12-9 18a9 9 0 0 0 18 0c0-6-5-12-9-18z"/></svg></span>' +
            '<h3 class="empty-slate__title">A dry page so far.</h3>' +
            '<p class="empty-slate__body">The first sip is the day\u2019s nudge to start the habit.</p>';
          list.replaceWith(slate);
          return;
        }
        entries.forEach((e) => {
          const li = document.createElement('li');
          const left = document.createElement('span');
          left.innerHTML = `<strong>${e.amount}</strong> ${W.escape(e.unit)} <span class="muted">${fmtTime(e.logged_at)}</span>`;
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'link-btn link-danger';
          btn.dataset.action = 'delete';
          btn.dataset.id = e.id;
          btn.textContent = 'Undo';
          li.appendChild(left);
          li.appendChild(btn);
          list.appendChild(li);
        });
      }
      const meta = document.querySelector('.card-water .muted-inline');
      if (meta) meta.textContent = `${total} of ${goal} ${data.unit} · ${pct}%`;
      const fill = document.querySelector('.progress-fill');
      if (fill) fill.style.width = pct + '%';
      const ring = document.querySelector('.ring-fill');
      if (ring) ring.setAttribute('stroke-dashoffset', Math.round(326.7 * (1 - pct/100)));
    }

    async function postAdd(amount, unit, sourceBtn) {
      if (sourceBtn) W.loading(sourceBtn, 'Logging...');
      try {
        const r = await W.api('/api/hydration', { action: 'add', amount, unit });
        // Did this pour take the user AT or OVER today's goal? If so, ring celebrates.
        const wasUnder = (data.goal ? Math.round(((r.today_total - amount) / r.goal) * 100) : 0) < 100;
        const isAt     = r.pct >= 100;
        renderEntries(r.entries, r.today_total, r.goal, r.pct);
        if (wasUnder && isAt) {
          const ring = document.querySelector('.ring');
          if (ring) {
            ring.classList.remove('is-celebrating');
            // force reflow so the animation restarts even on rapid re-triggers
            void ring.offsetWidth;
            ring.classList.add('is-celebrating');
            W.announce('Goal met. Nicely done.');
          }
        }
      } catch (e) { W.toast(e.message, 'error'); }
      finally { if (sourceBtn) W.loaded(sourceBtn); }
    }

    document.querySelectorAll('.quick-add').forEach((b) => {
      b.addEventListener('click', () => {
        postAdd(parseInt(b.dataset.amount, 10), b.dataset.unit, b);
      });
    });

    // Empty-slate CTA hook: jump to the first quick-add and focus it so the
    // user can press Enter to log a glass without reaching for the mouse.
    document.querySelectorAll('[data-action="focus-first-quickadd"]').forEach((a) => {
      a.addEventListener('click', () => {
        const first = document.querySelector('.quick-add');
        if (first) { first.focus({ preventScroll: true }); }
      });
    });

    const form = document.getElementById('hydration-form');
    if (form) {
      form.addEventListener('submit', (ev) => {
        ev.preventDefault();
        const amount = parseInt(form.amount.value, 10);
        if (!amount || amount <= 0) {
          W.toast('Enter a positive amount.', 'error');
          return;
        }
        const submitBtn = form.querySelector('button[type="submit"]');
        postAdd(amount, data.unit, submitBtn);
        form.amount.value = '';
      });
    }

    document.addEventListener('click', (ev) => {
      const t = ev.target.closest('[data-action="delete"]');
      if (!t) return;
      const card = t.closest('article');
      if (card && !card.querySelector('.progress-track')) return;
      W.loading(t, 'Removing...');
      W.api('/api/hydration', { action: 'delete', id: t.dataset.id })
        .then((r) => {
          const ring = document.querySelector('.ring-fill');
          const fill = document.querySelector('.progress-fill');
          const meta = card ? card.querySelector('.muted-inline') : null;
          const pct = r.goal ? Math.min(100, Math.round((r.today_total / r.goal) * 100)) : 0;
          if (meta) meta.textContent = `${r.today_total} of ${r.goal} ${data.unit} · ${pct}%`;
          if (fill) fill.style.width = pct + '%';
          if (ring) ring.setAttribute('stroke-dashoffset', Math.round(326.7 * (1 - pct/100)));
          t.closest('li')?.remove();
        })
        .catch((e) => W.toast(e.message, 'error'))
        .finally(() => W.loaded(t));
    });

    // Charts
    if (window.Chart) {
      const palette = getCssVar('--sage-500');
      const seriesGrid = getCssVar('--cream-200');
      const textColor  = getCssVar('--text-muted');
      const labels = data.series7.map(p => shortDate(p.date));
      const values = data.series7.map(p => p.amount);
      new Chart(document.getElementById('chart-7').getContext('2d'), {
        type: 'bar',
        data: {
          labels,
          datasets: [{
            label: `${data.unit} per day`,
            data: values,
            backgroundColor: palette,
            borderRadius: 6,
            maxBarThickness: 36,
          }],
        },
        options: chartOpts(labels.length, seriesGrid, textColor, data.goal),
      });
      const labels30 = data.series30.map(p => shortDate(p.date));
      const values30 = data.series30.map(p => p.amount);
      new Chart(document.getElementById('chart-30').getContext('2d'), {
        type: 'bar',
        data: {
          labels: labels30,
          datasets: [{
            label: `${data.unit} per day`,
            data: values30,
            backgroundColor: palette,
            borderRadius: 5,
            maxBarThickness: 14,
          }],
        },
        options: chartOpts(labels30.length, seriesGrid, textColor, data.goal),
      });
    }
  });

  function shortDate(s) {
    const d = new Date(s + 'T00:00:00');
    return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
  }
  function getCssVar(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || '#7C9885';
  }
  function chartOpts(nLabels, gridColor, textColor, goal) {
    return {
      plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: 'rgba(43,43,43,0.92)', padding: 10 },
      },
      scales: {
        x: { grid: { display: false }, ticks: { color: textColor, maxRotation: 0, autoSkip: nLabels > 14 } },
        y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor } },
      },
    };
  }
})();
