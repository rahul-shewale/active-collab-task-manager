/**
 * assets/js/report.js
 * Renders the Task Report tab: due-date sections + Hubstaff cards.
 */

const BOARD_PALETTE = {
  LeadDetector: { bg:'#eef2ff',border:'#818cf8',dot:'#6366f1',text:'#3730a3' },
  MinutePages:  { bg:'#f0fdf4',border:'#34d399',dot:'#10b981',text:'#065f46' },
  RocketSkip:   { bg:'#fff7ed',border:'#fb923c',dot:'#f97316',text:'#9a3412' },
  'S@geWork':   { bg:'#fdf4ff',border:'#c084fc',dot:'#a855f7',text:'#6b21a8' },
};
const DEFAULT_PAL = { bg:'#f8fafc',border:'#94a3b8',dot:'#64748b',text:'#334155' };

function shortBoard(name) {
  const map = { leaddetector:'LeadDetector','minute pages':'MinutePages',rocketskip:'RocketSkip','s@geworkspace':'S@geWork' };
  const l = (name||'').toLowerCase();
  for (const [k,v] of Object.entries(map)) if (l.includes(k)) return v;
  return (name||'').split(' ')[0];
}

function getDue(dateStr) {
  if (!dateStr) return null;
  const d = new Date(dateStr), t = new Date();
  d.setHours(0,0,0,0); t.setHours(0,0,0,0);
  const diff = Math.ceil((d - t) / 86400000);
  if (diff === 0) return { label:'Today', urgent:true };
  if (diff === 1) return { label:'Tomorrow', urgent:false };
  if (diff < 0)  return { label:`${Math.abs(diff)}d overdue`, urgent:true };
  return { label:`In ${diff}d`, urgent:false };
}

function fmtDate(d) {
  return d ? new Date(d).toLocaleDateString('en-IN', { day:'numeric', month:'short' }) : '';
}

function cleanMemberName(name) {
  const raw = String(name || '').trim();
  if (!raw) return '';
  return raw
    .replace(/\b(LeadDetector|Minute\s*Pages?|RocketSkip|S@geWorkspace|S@geWork)\b/gi, '')
    .replace(/\s{2,}/g, ' ')
    .trim();
}

function taskCard(task, showDue = true) {
  const board = shortBoard(task.board_name);
  const pal   = BOARD_PALETTE[board] || DEFAULT_PAL;
  const due   = getDue(task.due_date);

  let dueBadge = '';
  if (showDue && due) {
    const cls = due.urgent ? 'badge-overdue' : 'badge-ok';
    const ico = due.urgent ? '⚠' : '🗓';
    dueBadge = `<span class="due-badge ${cls}">${ico} ${due.label} · ${fmtDate(task.due_date)}</span>`;
  } else if (showDue && !task.due_date) {
    dueBadge = `<span class="due-badge badge-neutral">No due date</span>`;
  }

  const members = (task.programmers || [])
    .map(cleanMemberName)
    .filter(Boolean)
    .map(n => `<span class="member-chip">${escHtml(n)}</span>`)
    .join('');

  const openBtn = task.url
    ? `<a href="${task.url}" target="_blank" rel="noopener" class="btn btn-sm btn-trello">🔗 Open ↗</a>`
    : '';

  return `
    <div class="task-row" style="border-left:3px solid ${pal.dot}">
      <div class="task-meta">
        <span class="board-dot" style="background:${pal.dot}"></span>
        <span class="board-label" style="background:${pal.bg};color:${pal.text};border-color:${pal.border}">${board}</span>
        ${task.list_name ? `<span class="list-name">${escHtml(task.list_name)}</span>` : ''}
      </div>
      <div class="task-title">${escHtml(task.title)}</div>
      <div class="task-footer">
        ${dueBadge}
        <div class="task-members">${members}</div>
        ${openBtn}
      </div>
    </div>`;
}

function groupByBoard(tasks) {
  const map = {};
  tasks.forEach(t => {
    const k = shortBoard(t.board_name);
    (map[k] = map[k]||[]).push(t);
  });
  return Object.entries(map).sort((a,b) => b[1].length - a[1].length);
}

function renderSection(listId, tasks, showDue = true) {
  if (!tasks.length) {
    const allClear = (listId === 'list-today' || listId === 'list-upcoming');
    $(`#${listId}`).html(
      allClear
        ? '<div class="report-empty"><div class="report-empty-icon">✅</div><div class="report-empty-text">All clear!</div></div>'
        : '<div class="report-empty"><div class="report-empty-text text-muted">None</div></div>'
    );
    return;
  }
  const groups = groupByBoard(tasks);
  let html = '';
  groups.forEach(([board, bTasks]) => {
    const pal = BOARD_PALETTE[board] || DEFAULT_PAL;
    html += `
      <div class="board-group mb-3">
        <div class="board-group-header" style="background:${pal.bg};border:1.5px solid ${pal.border}">
          <span class="board-dot" style="background:${pal.dot}"></span>
          <strong style="color:${pal.text}">${escHtml(board)}</strong>
          <span class="badge rounded-pill ms-auto" style="background:${pal.dot}">${bTasks.length}</span>
        </div>
        <div class="board-group-body">
          ${bTasks.map(t => taskCard(t, showDue)).join('')}
        </div>
      </div>`;
  });
  $(`#${listId}`).html(html);
}

function escHtml(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function renderBoardChips(allTasks, openTasks) {
  const boardCounts = {};
  (allTasks || []).forEach(t => {
    const b = shortBoard(t.board_name);
    boardCounts[b] = (boardCounts[b] || 0) + 1;
  });

  const entries = Object.entries(boardCounts).sort((a,b) => b[1] - a[1]);
  const html = entries.map(([board, c]) => {
    const pal = BOARD_PALETTE[board] || DEFAULT_PAL;
    return `<span class="report-chip" style="background:${pal.bg};border-color:${pal.border};color:${pal.text}">
      ${escHtml(board)} <strong class="ms-1" style="color:${pal.text}">${c}</strong>
    </span>`;
  }).join('');

  $('#report-board-chips').html(html);

  const noDeadline = (openTasks || []).length;
  $('#chip-no-deadline').text(`${noDeadline} without deadline`);
  $('#trello-total').text((allTasks || []).length);
}

/* ── Hubstaff cards ───────────────────────────────────────── */
let hsTimeframe = 'day';

function renderHubstaff(members) {
  if (!members.length) {
    $('#hubstaff-cards').html('<p class="text-muted">No tracking data.</p>').removeClass('d-none');
    return;
  }

  const html = members.map(m => {
    const actColor = m.activity >= 70 ? '#10b981' : m.activity >= 40 ? '#f59e0b' : '#ef4444';
    const taskRows = (m.tasks || []).map(t => `
      <tr>
        <td class="small">${escHtml(t.name)}</td>
        <td class="text-center small fw-bold">${t.todayTime}</td>
        <td class="text-center small">${t.todayAct}%</td>
        <td class="text-center small">${t.periodTime}</td>
      </tr>`).join('');

    return `
      <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm rounded-3 h-100">
          <div class="card-header d-flex align-items-center gap-2 border-0 rounded-top-3" style="background:${m.color}22">
            <div class="hs-avatar" style="background:${m.color}">${escHtml(m.initials)}</div>
            <div class="flex-grow-1">
              <div class="fw-bold">${escHtml(m.name)}</div>
              <div class="small text-muted">Today: <strong>${m.todayH}</strong> &bull; Period: <strong>${m.periodH}</strong></div>
            </div>
            <div class="text-end">
              <div class="fw-bold small" style="color:${actColor}">${m.activity}%</div>
              <div class="text-muted" style="font-size:.65rem">activity</div>
            </div>
          </div>
          ${taskRows ? `
          <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th class="small">Task</th>
                  <th class="text-center small">Today</th>
                  <th class="text-center small">Act%</th>
                  <th class="text-center small">Period</th>
                </tr>
              </thead>
              <tbody>${taskRows}</tbody>
            </table>
          </div>` : `<div class="card-body text-muted small">No tasks tracked.</div>`}
        </div>
      </div>`;
  }).join('');

  $('#hubstaff-cards').html(html).removeClass('d-none');
}

function loadHubstaff(tf = 'day') {
  hsTimeframe = tf;
  $('#hubstaff-loading').removeClass('d-none');
  $('#hubstaff-cards').addClass('d-none');

  API.getHubstaff(tf)
    .done(renderHubstaff)
    .fail(() => $('#hubstaff-loading').html('<span class="text-danger">Failed to load Hubstaff data.</span>'))
    .always(() => $('#hubstaff-loading').addClass('d-none'));
}

/* ── Report namespace ─────────────────────────────────────── */
const Report = {
  loaded: false,

  load(force = false) {
    if (this.loaded && !force) return;
    this.loaded = true;

    $('#report-loading').removeClass('d-none');
    $('#report-content').addClass('d-none');

    // Run the two requests independently — historically they were
    // chained with $.when(), so a 401 on /tasks/stats (the only
    // auth-protected one) killed the entire page. Now the report
    // renders even when the stats bar fails.
    const duePromise   = API.getDueDateStats();
    const statsPromise = API.getTaskStats();

    duePromise
      .done(due => {
        const pending  = due.pending  || [];
        const todayArr = due.today    || [];
        const upcoming = due.upcoming || [];
        const openTasks = due.open || [];

        renderSection('list-pending',  due.pending,  true);
        renderSection('list-today',    due.today,    true);
        renderSection('list-upcoming', due.upcoming, true);

        const all = [...pending, ...todayArr, ...upcoming, ...openTasks];
        renderBoardChips(all, openTasks);

        $('#count-open').text(`${openTasks.length} tasks`);
        let openShown = false;
        const render20 = () => renderSection('list-open', openTasks.slice(0, 20), false);
        const renderAll = () => renderSection('list-open', openTasks, false);
        render20();
        $('#toggle-open').off('click.report').on('click.report', function () {
          openShown = !openShown;
          openShown ? renderAll() : render20();
          $(this).text(openShown ? 'Show less' : 'Show all');
        });

        $('#count-pending').text(`${pending.length} tasks`);
        $('#count-today').text(`${todayArr.length} tasks`);
        $('#count-upcoming').text(`${upcoming.length} tasks`);
        $('#stat-overdue').text(pending.length);

        $('#report-loading').addClass('d-none');
        $('#report-content').removeClass('d-none');

        loadHubstaff(hsTimeframe);
      })
      .fail(xhr => {
        const status = xhr.status || '?';
        $('#report-loading').html(
          `<span class="text-danger">Failed to load report data (HTTP ${status}). ` +
          `Please refresh.</span>`
        );
      });

    statsPromise
      .done(stats => {
        $('#stat-open').text(stats.by_status?.open || 0);
        $('#stat-in_progress').text(stats.by_status?.in_progress || 0);
        $('#stat-done').text(stats.by_status?.done || 0);
      })
      .fail(xhr => {
        if (xhr.status !== 401) {
          $('#stat-open, #stat-in_progress, #stat-done').text('—');
        }
      });
  },
};

/* ── Hubstaff timeframe buttons ───────────────────────────── */
$(document).on('click', '#hs-timeframe-group .btn', function () {
  $('#hs-timeframe-group .btn').removeClass('active');
  $(this).addClass('active');
  loadHubstaff($(this).data('tf'));
});
