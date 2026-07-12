/* Mindfulness page controller.
   - Holds a state machine: idle -> running -> paused -> finished/cancelled.
   - Drives the breath-circle animation through inhale/hold/exhale/hold2.
   - Logs the session on completion; cancelled sessions are saved with
     completed=false so they don't add to streak, but the user can still see
     what was tried.
*/
(function () {
  'use strict';
  const W = window.W;
  document.addEventListener('DOMContentLoaded', () => {
    const selDuration = document.getElementById('duration-select');
    const selPattern = document.getElementById('pattern-select');
    const wrapCustom  = document.getElementById('custom-duration-wrap');
    const inpCustom   = document.getElementById('custom-duration');

    const btnStart   = document.getElementById('timer-start');
    const btnPause   = document.getElementById('timer-pause');
    const btnResume  = document.getElementById('timer-resume');
    const btnCancel  = document.getElementById('timer-cancel');

    const circle  = document.getElementById('breath-circle');
    const phase   = document.getElementById('breath-phase');
    const clock   = document.getElementById('breath-clock');

    const STATUS = { idle: 'idle', running: 'running', paused: 'paused', done: 'done' };
    let status = STATUS.idle;
    let remaining = 0;
    let totalSelected = 0;
    let intervalId = null;
    let cycleStart = 0;       // outer-scoped so pause() can read the anchor
    let pausedElapsed = 0;    // millis elapsed in the run at pause time — re-anchors cycleStart on resume

    const PATTERNS = {
      'box':   [[0, 4000, 'inhale'], [4000, 4000, 'hold'], [8000, 4000, 'exhale'], [12000, 4000, 'hold']],
      '4-7-8': [[0, 4000, 'inhale'], [4000, 7000, 'hold'], [11000, 8000, 'exhale']],
      'equal': [[0, 4000, 'inhale'], [4000, 4000, 'exhale']],
    };

    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function fmt(seconds) { const m = Math.floor(seconds/60), s = seconds % 60; return `${pad(m)}:${pad(s)}`; }

    function applyVisualState(phaseName) {
      circle.classList.remove('is-inhale','is-hold','is-exhale','is-hold2');
      if (phaseName === 'inhale')  circle.classList.add('is-inhale');
      else if (phaseName === 'hold')  circle.classList.add('is-hold');
      else if (phaseName === 'exhale') circle.classList.add('is-exhale');
      else if (phaseName === 'hold2')  circle.classList.add('is-hold2');
    }

    function getSelectedSeconds() {
      const v = selDuration.value;
      if (v === 'custom') {
        const m = parseInt(inpCustom.value, 10);
        return Math.max(1, Math.min(60, m || 1)) * 60;
      }
      return parseInt(v, 10);
    }

    function setup() {
      selDuration.addEventListener('change', () => {
        wrapCustom.hidden = selDuration.value !== 'custom';
      });
      btnStart.addEventListener('click', () => {
        if (typeof W.toast === 'function') W.announce('Begin.');
        start();
      });
      btnPause.addEventListener('click', () => {
        W.announce('Paused.');
        pause();
      });
      btnResume.addEventListener('click', () => {
        W.announce('Resumed.');
        start();
      });
      btnCancel.addEventListener('click', () => {
        W.announce('Cancelled.');
        cancel();
      });
    }

    function start() {
      if (status === STATUS.running) return;
      if (status === STATUS.idle) {
        totalSelected = getSelectedSeconds();
        remaining = totalSelected;
        pausedElapsed = 0;
      }
      // Resume (paused) or fresh start — anchor so the breath pattern stays where the user left it.
      cycleStart = Date.now() - pausedElapsed;
      status = STATUS.running;

      btnStart.hidden = true;
      btnPause.hidden = false;
      btnResume.hidden = true;
      btnCancel.hidden = false;
      const pattern = PATTERNS[selPattern.value] || PATTERNS.box;
      let phaseIndex = 0;
      let phaseStart = cycleStart;
      const cycleTotal = pattern[pattern.length - 1][0] + pattern[pattern.length - 1][1];

      function tick() {
        if (status !== STATUS.running) return;
        const now = Date.now();
        const elapsed = now - cycleStart;
        remaining = Math.max(0, totalSelected - Math.floor(elapsed / 1000));

        const cyclePos = elapsed % cycleTotal;
        let p = pattern[0];
        for (let i = 0; i < pattern.length; i++) {
          if (cyclePos >= pattern[i][0] && cyclePos < pattern[i][0] + pattern[i][1]) {
            p = pattern[i];
            if (i !== phaseIndex) {
              phaseIndex = i;
              phaseStart = now;
              phase.textContent = p[2];
              applyVisualState(p[2]);
            }
            break;
          }
        }

        clock.textContent = fmt(remaining);

        if (remaining <= 0) {
          finish(true);
          return;
        }
      }

      // initialize first phase immediately
      phase.textContent = pattern[0][2];
      applyVisualState(pattern[0][2]);
      clock.textContent = fmt(remaining);
      intervalId = setInterval(tick, 250);
    }

    function pause() {
      if (status !== STATUS.running) return;
      pausedElapsed = Date.now() - cycleStart;  // remember how far we got, for resume
      status = STATUS.paused;
      clearInterval(intervalId);
      btnStart.hidden = true;
      btnPause.hidden = true;
      btnResume.hidden = false;
      btnCancel.hidden = false;
      phase.textContent = 'paused';
    }

    function cancel() {
      if (status === STATUS.idle || status === STATUS.done) return;
      const elapsed = totalSelected - remaining;
      clearInterval(intervalId);
      pausedElapsed = 0;
      // Save only if at least 20 seconds were attempted (otherwise not worth logging)
      if (elapsed >= 20) {
        W.loading(btnCancel, 'Saving\u2026');
        W.api('/api/mindfulness', {
          action: 'log', duration_seconds: elapsed, pattern: selPattern.value, completed: false,
        })
          .then(() => W.toast('Session cancelled.', 'info'))
          .catch((e) => W.toast(e.message, 'error'))
          .finally(() => W.loaded(btnCancel));
      }
      reset();
    }

    function finish(completed) {
      clearInterval(intervalId);
      status = STATUS.done;
      applyVisualState('inhale');
      phase.textContent = 'Done';
      clock.textContent = '00:00';
      // Always celebrate the user finishing, even if the network save fails.
      // We reload regardless so the streak tile updates; the next page load
      // will also retry the save via the streak-recompute path if the user
      // touches anything.
      W.toast(completed ? 'Session complete. Nicely done.' : 'Session ended.', 'success');
      W.announce(completed ? 'Session complete.' : 'Session ended.');
      W.api('/api/mindfulness', {
        action: 'log', duration_seconds: totalSelected, pattern: selPattern.value, completed,
      })
        .then(() => setTimeout(() => window.location.reload(), 360))
        .catch((e) => { W.toast(e.message, 'error'); setTimeout(() => window.location.reload(), 600); });
    }

    function reset() {
      clearInterval(intervalId);
      status = STATUS.idle;
      remaining = 0;
      pausedElapsed = 0;
      btnStart.hidden = false;
      btnPause.hidden = true;
      btnResume.hidden = true;
      btnCancel.hidden = true;
      phase.textContent = 'Ready';
      applyVisualState('inhale');
      clock.textContent = '--:--';
    }

    // Initialize visual baseline
    applyVisualState('inhale');
    setup();
  });
})();
