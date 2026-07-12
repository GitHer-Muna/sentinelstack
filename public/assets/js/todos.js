/* Todos page controller — optimistic UI. */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', () => {
    const W   = window.W;
    const form = document.getElementById('todo-form');
    const list = document.getElementById('todo-list');
    const showCompleted = document.getElementById('show-completed');
    const previewChip   = document.getElementById('habit-preview-chip');

    // ── Live preview-chip as user picks daily vs weekly ────────────────────
    function syncTypeFields() {
      const type = form.querySelector('input[name="type"]:checked').value;
      const habitOnly = form.querySelector('.habit-only');
      const taskOnly  = form.querySelector('.task-only');
      if (habitOnly) habitOnly.hidden = (type !== 'habit');
      if (taskOnly)  taskOnly.hidden  = (type !== 'task');
      if (type === 'habit' && previewChip) {
        const period = form.querySelector('[name="recurrence_period"]').value;
        previewChip.dataset.period = period;
        const periodLabel = document.getElementById('habit-preview-period');
        if (periodLabel) periodLabel.textContent = period === 'weekly' ? 'week' : 'day';
      }
    }
    if (form) {
      // Note: form[novalidate] auto-binds via W.bindInlineValidation in app.js,
      // so we don't call it explicitly here. Manual native checkValidity() below
      // acts as a belt-and-suspenders guard for required fields.
      syncTypeFields();
      form.querySelectorAll('input[name="type"]').forEach(r => r.addEventListener('change', syncTypeFields));
      const periodSel = form.querySelector('[name="recurrence_period"]');
      if (periodSel) periodSel.addEventListener('change', syncTypeFields);

      // Quick date shortcuts (Today / Tomorrow / Next week / Clear) under
      // the Due date input. They set the input value in the user's
      // timezone so the chip on the rendered todo reads correctly.
      const dueDate = form.querySelector('input[name="due_date"]');
      if (dueDate) {
        form.querySelectorAll('.date-quick__btn').forEach((btn) => {
          btn.addEventListener('click', () => {
            const offset = btn.dataset.dateOffset;
            if (offset === 'clear') {
              dueDate.value = '';
              dueDate.dispatchEvent(new Event('input', { bubbles: true }));
              W.announce('Due date cleared.');
              return;
            }
            const days = parseInt(offset, 10) || 0;
            // Build YYYY-MM-DD in the browser's local timezone. This
            // matches the server's per-user timezone for the vast majority
            // of users (timezone set to local or to a zone that matches
            // their machine clock).
            const d = new Date();
            d.setHours(0, 0, 0, 0);
            d.setDate(d.getDate() + days);
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            dueDate.value = `${yyyy}-${mm}-${dd}`;
            dueDate.dispatchEvent(new Event('input', { bubbles: true }));
            // Parenthesize the WHOLE ternary so every branch gets the
            // "set as due date." suffix. Without the outer parens, the `+`
            // binds only to the inner ternary's false-branch and the
            // offset==='0' case announces just "Today" with no context.
            W.announce((offset === '0' ? 'Today' : offset === '1' ? 'Tomorrow' : 'Next week') + ' set as due date.');
          });
        });
      }

      // Insert new item via API, then animate it into the list (no full reload).
      form.addEventListener('submit', (ev) => {
        ev.preventDefault();
        if (!form.checkValidity()) {
          // Auto-bind in app.js handles error UI (.field-error, .is-invalid, toast).
          return;
        }
        const data = new FormData(form);
        const btn = form.querySelector('button[type="submit"]');
        W.loading(btn, 'Adding...');
        // The API endpoint routes by $_POST['action']; without it, the
        // server returns 400 "Unknown action." Add it explicitly here so
        // the form submission lands on the create branch.
        const payload = Object.fromEntries(data);
        payload.action = 'create';
        W.api('/api/todos', payload)
          .then((r) => {
            // Reset the form, restore defaults
            form.reset();
            syncTypeFields();
            // Insert the new item at the top (or end) of the list.
            if (list && r && r.item) {
              const li = buildItem(r.item);
              li.classList.add('is-inserted');
              list.insertBefore(li, list.firstChild);
              W.toast('Added to today\u2019s list.', 'success');
              W.announce('Added to today\u2019s list.');
            } else {
              // Fallback: full reload (shouldn't usually happen).
              window.location.reload();
            }
          })
          .catch((e) => W.toast(e.message, 'error'))
          .finally(() => W.loaded(btn));
      });
    }

    if (showCompleted) {
      showCompleted.addEventListener('change', () => {
        const url = new URL(window.location.href);
        url.searchParams.set('show_completed', showCompleted.checked ? '1' : '0');
        window.location.href = url.toString();
      });
    }

    // ── Open native date picker from the calendar icon ────────────────────
    // The icon is a <button data-action="open-date-picker"> next to the
    // <input type="date">. Clicking it explicitly calls showPicker() so
    // the picker opens on every browser that supports the API (Chrome/Edge
    // 99+, Firefox 101+, Safari 16+). Falls back to focus() on older
    // browsers so the user can at least keyboard-navigate to a date.
    document.addEventListener('click', (ev) => {
      const opener = ev.target.closest('[data-action="open-date-picker"]');
      if (!opener) return;
      const wrap = opener.closest('.date-input-wrap');
      const input = wrap ? wrap.querySelector('input[type="date"]') : null;
      if (!input) return;
      if (typeof input.showPicker === 'function') {
        try { input.showPicker(); return; } catch (_) { /* fall through */ }
      }
      input.focus();
    });

    // ── Toggle / delete (delegated) ────────────────────────────────────────
    document.addEventListener('click', (ev) => {
      const t = ev.target.closest('[data-action]');
      if (!t) return;
      const item = t.closest('.todo-item');
      if (!item) return;
      const id = item.dataset.id;

      if (t.dataset.action === 'toggle') {
        // Optimistic: flip the visual state immediately.
        const wasDone = item.classList.contains('is-done');
        item.classList.toggle('is-done', !wasDone);
        const check = item.querySelector('.todo-check');
        if (check) check.setAttribute('aria-checked', wasDone ? 'false' : 'true');
        item.classList.add('is-updating');
        W.api('/api/todos', { action: 'toggle', id })
          .catch((e) => {
            // Snap back on error.
            item.classList.toggle('is-done', wasDone);
            if (check) check.setAttribute('aria-checked', wasDone ? 'true' : 'false');
            W.toast(e.message, 'error');
          })
          .finally(() => item.classList.remove('is-updating'));
      } else if (t.dataset.action === 'edit') {
        const actions = item.querySelector('.todo-actions');
        if (actions) {
          const wasHidden = actions.hidden;
          actions.hidden = !wasHidden;
          t.setAttribute('aria-expanded', String(wasHidden));
          // When the menu opens, move focus to the first action so keyboard
          // users can immediately tab to "Move up" / "Delete" etc.
          if (!wasHidden) {
            const first = actions.querySelector('button, a');
            if (first) first.focus({ preventScroll: false });
          }
        }
      } else if (t.dataset.action === 'delete') {
        W.confirm({
          title: 'Delete this?',
          body: 'It will be removed from your list. (Habits keep their schedule; you can recreate them later.)',
          confirmLabel: 'Delete',
          danger: true,
        }).then((ok) => {
          if (!ok) return;
          item.classList.add('is-deleting');
          W.api('/api/todos', { action: 'delete', id })
            .then(() => {
              const title = item.querySelector('.todo-title')?.textContent || 'Item';
              W.toast(`Removed \u201C${title}\u201D.`, 'success');
              W.announce(`${title} removed.`);
              // Listen for the row-out animation end, then remove the node.
              item.addEventListener('animationend', () => item.remove(), { once: true });
              setTimeout(() => { if (item.parentNode) item.remove(); }, 400);
            })
            .catch((e) => {
              item.classList.remove('is-deleting');
              W.toast(e.message, 'error');
            });
        });
      } else if (t.dataset.action === 'move-up' || t.dataset.action === 'move-down') {
        // Keyboard-accessible equivalent of drag-and-drop. Swaps this item
        // with its previous/next sibling, then POSTs the new order. On
        // failure, swap back to the original position so the DOM never
        // diverges from the server's truth.
        if (!list) return;
        const dir = t.dataset.action === 'move-up' ? -1 : 1;
        const sib = dir === -1 ? item.previousElementSibling : item.nextElementSibling;
        if (!sib || !sib.classList.contains('todo-item')) {
          W.toast(dir === -1 ? 'Already at the top.' : 'Already at the bottom.', 'info');
          return;
        }
        // Snapshot pre-swap position so we can roll back on error.
        const originalPrev = item.previousElementSibling;
        const originalNext = item.nextElementSibling;
        const swap = () => {
          if (dir === -1) list.insertBefore(item, sib);
          else list.insertBefore(sib, item);
        };
        swap();
        const ids = Array.from(list.querySelectorAll('.todo-item')).map(x => x.dataset.id);
        W.api('/api/todos/reorder', { ids })
          .then(() => {
            W.announce(dir === -1 ? 'Moved up.' : 'Moved down.');
            // Auto-close the action menu so the user sees the new position
            // without having to dismiss the dropdown themselves.
            const actions = item.querySelector('.todo-actions');
            if (actions) {
              actions.hidden = true;
              const moreBtn = item.querySelector('[data-action="edit"]');
              if (moreBtn) moreBtn.setAttribute('aria-expanded', 'false');
            }
          })
          .catch((e) => {
            // Roll back: if `item` was originally before `sib`, put it back.
            if (originalPrev) list.insertBefore(item, originalPrev);
            else if (originalNext) list.insertBefore(item, originalNext.nextSibling);
            else list.insertBefore(item, list.firstChild);
            W.toast(e.message, 'error');
          });
      }
    });

    // ── Drag-reorder (no full reload) ──────────────────────────────────────
    if (list) {
      let dragId = null;
      list.querySelectorAll('.todo-item').forEach((li) => {
        li.addEventListener('dragstart', (ev) => {
          dragId = li.dataset.id;
          li.classList.add('is-draft');
          try { ev.dataTransfer.setData('text/plain', dragId); } catch (_) {}
          ev.dataTransfer.effectAllowed = 'move';
        });
        li.addEventListener('dragend', () => {
          li.classList.remove('is-draft');
          dragId = null;
        });
        li.addEventListener('dragover', (ev) => { ev.preventDefault(); });
        li.addEventListener('drop', (ev) => {
          ev.preventDefault();
          if (!dragId || dragId === li.dataset.id) return;
          const dragged = list.querySelector(`[data-id="${dragId}"]`);
          if (!dragged) return;
          const rect = li.getBoundingClientRect();
          const before = (ev.clientY - rect.top) < rect.height / 2;
          // Snapshot pre-drop position so we can roll back on error.
          const originalPrev = dragged.previousElementSibling;
          const originalNext = dragged.nextElementSibling;
          list.insertBefore(dragged, before ? li : li.nextSibling);
          const ids = Array.from(list.querySelectorAll('.todo-item')).map(x => x.dataset.id);
          W.api('/api/todos/reorder', { ids }).catch((e) => {
            if (originalPrev) list.insertBefore(dragged, originalPrev);
            else if (originalNext) list.insertBefore(dragged, originalNext.nextSibling);
            else list.insertBefore(dragged, list.firstChild);
            W.toast(e.message, 'error');
          });
        });
      });
    }

    // Helper: build an <li> from the server's JSON item shape.
    function buildItem(t) {
      const completed = Number(t.is_completed_today) === 1;
      const li = document.createElement('li');
      li.className = 'todo-item' + (completed ? ' is-done' : '');
      li.dataset.id = t.id;
      li.setAttribute('draggable', 'true');

      const check = document.createElement('button');
      check.className = 'todo-check';
      check.setAttribute('role', 'checkbox');
      check.setAttribute('aria-checked', completed ? 'true' : 'false');
      check.type = 'button';
      check.dataset.action = 'toggle';
      check.innerHTML = '<span class="todo-check-icon" aria-hidden="true"><svg viewBox="0 0 16 16" width="14" height="14"><path d="M3 8 L7 12 L13 4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';

      const body = document.createElement('div');
      body.className = 'todo-body';
      const titleRow = document.createElement('div');
      titleRow.className = 'todo-title-row';
      const title = document.createElement('span');
      title.className = 'todo-title';
      title.textContent = t.title;
      titleRow.appendChild(title);
      if (t.type === 'habit') {
        const chip = document.createElement('span');
        chip.className = 'chip ' + (t.recurrence_period === 'weekly' ? 'chip-habit-weekly' : 'chip-habit-daily');
        chip.textContent = 'habit \u00B7 ' + (t.recurrence_period || 'daily');
        titleRow.appendChild(chip);
      } else if (t.due_date) {
        const chip = document.createElement('span');
        chip.className = 'chip';
        chip.textContent = t.due_date;
        titleRow.appendChild(chip);
      }
      const prio = document.createElement('span');
      prio.className = 'chip chip-' + (t.priority || 'med');
      prio.textContent = t.priority || 'med';
      titleRow.appendChild(prio);
      body.appendChild(titleRow);
      if (t.note) {
        const note = document.createElement('p');
        note.className = 'todo-note';
        note.textContent = t.note;
        body.appendChild(note);
      }

      const more = document.createElement('button');
      more.className = 'todo-more';
      more.type = 'button';
      more.setAttribute('aria-haspopup', 'true');
      more.setAttribute('aria-expanded', 'false');
      more.dataset.action = 'edit';
      more.innerHTML = '<svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="3" cy="8" r="1"/><circle cx="8" cy="8" r="1"/><circle cx="13" cy="8" r="1"/></svg>';

      const actions = document.createElement('div');
      actions.className = 'todo-actions';
      actions.hidden = true;
      actions.setAttribute('role', 'menu');
      const del = document.createElement('button');
      del.className = 'link-btn link-danger';
      del.type = 'button';
      del.dataset.action = 'delete';
      del.textContent = 'Delete';
      actions.appendChild(del);

      li.append(check, body, more, actions);
      return li;
    }
  });
})();
