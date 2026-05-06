/**
 * assets/js/ac_shared.js
 * Shared helpers used by all AC tab scripts.
 * Must be loaded BEFORE ac_members.js, ac_projects.js, etc.
 */

/* ── escHtml (also used in report.js) ─── */
function escHtml(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

/* ── Priority badge ──────────────────────────────────────── */
const PRIORITY_CFG = {
  urgent: { cls: 'bg-danger',  label: '🔥 Urgent' },
  high:   { cls: 'bg-warning text-dark', label: '⬆ High' },
  normal: { cls: 'bg-info text-dark',    label: 'Normal'  },
  low:    { cls: 'bg-secondary',         label: '⬇ Low'  },
};

function priorityBadge(p) {
  const cfg = PRIORITY_CFG[p] || PRIORITY_CFG.normal;
  return `<span class="badge ${cfg.cls} small">${cfg.label}</span>`;
}

/* ── Status badge ─────────────────────────────────────────── */
function statusBadge(s) {
  const map = {
    done:        'bg-success',
    open:        'bg-primary',
    in_progress: 'bg-warning text-dark',
    on_hold:     'bg-secondary',
  };
  return `<span class="badge ${map[s] || 'bg-secondary'} small">${escHtml(s)}</span>`;
}

/* ── AC task card (used across Members / Projects tabs) ─── */
function acTaskCard(task, showContext = true) {
  const dueStr  = task.due_date
    ? new Date(task.due_date).toLocaleDateString('en-IN', { day:'numeric', month:'short' })
    : null;

  const today    = new Date(); today.setHours(0,0,0,0);
  const dueDate  = task.due_date ? new Date(task.due_date) : null;
  if (dueDate) dueDate.setHours(0,0,0,0);
  const isOverdue = dueDate && dueDate < today && task.status !== 'done';

  const dueBadge = dueStr
    ? `<span class="badge ${isOverdue ? 'bg-danger' : 'bg-light text-dark border'} small">
        ${isOverdue ? '⚠ ' : '🗓 '}${dueStr}
       </span>`
    : '';

  const contextBadge = showContext && task.board_name
    ? `<span class="badge bg-light text-dark border small">${escHtml(task.board_name)}</span>`
    : '';

  const openBtn = task.url
    ? `<a href="${escHtml(task.url)}" target="_blank" rel="noopener"
          class="btn btn-sm btn-outline-secondary stretched-link-manual ms-auto">↗</a>`
    : '';

  return `
    <div class="col-12 col-sm-6 col-lg-4">
      <div class="card border-0 shadow-sm rounded-3 h-100 ac-task-card ${task.status === 'done' ? 'opacity-60' : ''}">
        <div class="card-body py-2 px-3">
          <div class="d-flex justify-content-between align-items-start gap-1 mb-1">
            <div class="ac-task-title small fw-semibold flex-grow-1">${escHtml(task.title)}</div>
            ${openBtn}
          </div>
          <div class="d-flex flex-wrap gap-1 align-items-center">
            ${statusBadge(task.status)}
            ${priorityBadge(task.priority)}
            ${dueBadge}
            ${contextBadge}
          </div>
        </div>
      </div>
    </div>`;
}
