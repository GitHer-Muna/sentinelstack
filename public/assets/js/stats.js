/* Stats page — water/mindful/movement trends overlay chart. */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', () => {
    const data = JSON.parse(document.getElementById('stats-data').textContent);
    if (!window.Chart) return;

    const sage  = getCssVar('--sage-500');
    const terr  = getCssVar('--terracotta');
    const sky   = getCssVar('--sky');
    const grid  = getCssVar('--cream-200');
    const text  = getCssVar('--text-muted');

    const labels = data.water14.map(p => shortDate(p.date));
    const ctx = document.getElementById('chart-trends').getContext('2d');

    new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            type: 'bar', label: `Water (${data.unit})`,
            data: data.water14.map(p => p.amount),
            backgroundColor: sage,
            borderRadius: 6, maxBarThickness: 18, yAxisID: 'y',
          },
          {
            type: 'bar', label: 'Mindful minutes',
            data: data.mindful14.map(p => p.minutes),
            backgroundColor: sky,
            borderRadius: 6, maxBarThickness: 18, yAxisID: 'y',
          },
          {
            type: 'bar', label: 'Movement sessions',
            data: data.move14.map(p => p.count),
            backgroundColor: terr,
            borderRadius: 6, maxBarThickness: 18, yAxisID: 'y1',
          },
        ],
      },
      options: {
        plugins: { legend: { position: 'bottom', labels: { color: text, boxWidth: 14 } } },
        responsive: true,
        scales: {
          x: { grid: { display: false }, ticks: { color: text } },
          y: { beginAtZero: true, grid: { color: grid }, ticks: { color: text } , position: 'left' },
          y1: { beginAtZero: true, grid: { display: false }, ticks: { color: text, stepSize: 1 }, position: 'right' },
        },
      },
    });

    // Sleep chart — its own canvas, bar chart, hours on Y (capped at 12 so
    // one oversized night doesn't compress the rest into the floor).
    const sleepLabels = data.sleep14.map(p => shortDate(p.date));
    const sleepCtx = document.getElementById('chart-sleep');
    if (sleepCtx) {
      new Chart(sleepCtx.getContext('2d'), {
        type: 'bar',
        data: {
          labels: sleepLabels,
          datasets: [{
            label: 'Hours',
            data: data.sleep14.map(p => p.hours),
            backgroundColor: sky,
            borderRadius: 6,
            maxBarThickness: 28,
          }],
        },
        options: {
          plugins: { legend: { display: false } },
          responsive: true,
          scales: {
            x: { grid: { display: false }, ticks: { color: text } },
            y: {
              beginAtZero: true, suggestedMax: 12,
              grid: { color: grid },
              ticks: { color: text, stepSize: 2 },
            },
          },
        },
      });
    }

    function shortDate(s) {
      const d = new Date(s + 'T00:00:00');
      return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
    }
    function getCssVar(name) {
      return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    }
  });
})();
