/**
 * public/assets/js/pending.js
 * Two sliders on one screen:
 * - ActiveCollab pending tasks (from cached teams view)
 * - Trello pending tasks (from DB tasks + task_user)
 */

function escHtml2(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function initials2(name) {
  return (name || '?').split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
}

function cleanDisplayName(name) {
  const raw = String(name || '').trim();
  if (!raw) return '';
  return raw
    .replace(/\b(LeadDetector|Minute\s*Pages?|RocketSkip|S@geWorkspace|S@geWork)\b/gi, '')
    .replace(/\s{2,}/g, ' ')
    .trim();
}

function pendingTaskCard(task) {
  const openBtn = task.url
    ? `<a href="${escHtml2(task.url)}" target="_blank" rel="noopener" class="ac-card-link" title="Open">↗</a>`
    : '';

  const meta = [];
  if (task.status) meta.push(`<span class="ac-status-pill status-open">${escHtml2(task.status)}</span>`);
  if (task.priority) meta.push(`<span class="ac-prio-pill prio-normal">${escHtml2(task.priority)}</span>`);
  if (task.due_date) meta.push(`<span class="ac-due-pill due-ok">🗓 ${escHtml2(task.due_date)}</span>`);

  return `
    <div class="ac-task-item">
      <div class="ac-task-item-top">
        <span class="ac-task-item-title">${escHtml2(task.title || '')}</span>
        ${openBtn}
      </div>
      <div class="ac-task-item-meta">${meta.join('')}</div>
      ${task.board_name ? `<div class="ac-task-item-board">${escHtml2(task.board_name)}</div>` : ''}
    </div>`;
}

function createPendingSlider(cfg) {
  const state = {
    loaded: false,
    data: [],
    filtered: [],
    idx: 0,
    timer: null,
    running: false,
    visible: 3,
    taskPreview: 15,
  };

  function $id(s) { return $('#' + s); }

  function calcVisible() {
    const w = $id(cfg.wrapId).width();
    if (w >= 1200) return 5;
    if (w >= 900)  return 4;
    if (w >= 640)  return 3;
    if (w >= 400)  return 2;
    return 1;
  }

  function maxIdx() {
    return Math.max(0, state.filtered.length - state.visible);
  }

  function applyPosition(animate=true) {
    const $track = $id(cfg.trackId);
    const colW = 100 / state.visible;
    const pct = state.idx * colW;
    $(`#${cfg.trackId} .ac-member-col`).css('flex', `0 0 ${colW}%`);
    $track.css('transition', animate ? 'transform 0.45s cubic-bezier(.4,0,.2,1)' : 'none');
    $track.css('transform', `translateX(-${pct}%)`);
    $(`#${cfg.dotsId} .ac-dot`).removeClass('active');
    $(`#${cfg.dotsId} .ac-dot[data-dot="${state.idx}"]`).addClass('active');
  }

  function stop() {
    clearInterval(state.timer);
    state.timer = null;
    state.running = false;
    $id(cfg.toggleId).html('<i class="bi bi-play-fill"></i> <span>Play</span>')
      .removeClass('btn-primary').addClass('btn-outline-secondary');
  }

  function start() {
    stop();
    state.running = true;
    $id(cfg.toggleId).html('<i class="bi bi-pause-fill"></i> <span>Pause</span>')
      .removeClass('btn-outline-secondary').addClass('btn-primary');
    state.timer = setInterval(() => next(), 2000);
  }

  function goTo(i) {
    state.idx = Math.max(0, Math.min(i, maxIdx()));
    applyPosition(true);
  }

  function next() {
    const n = state.idx >= maxIdx() ? 0 : state.idx + 1;
    goTo(n);
  }

  function prev() {
    const p = state.idx <= 0 ? maxIdx() : state.idx - 1;
    goTo(p);
  }

  function render(data) {
    state.filtered = Array.isArray(data) ? data : [];
    const $track = $id(cfg.trackId);
    const $dots  = $id(cfg.dotsId);
    $track.empty();
    $dots.empty();

    if (!state.filtered.length) {
      $track.html('<div class="text-muted py-4 text-center w-100">No pending tasks.</div>');
      return;
    }

    state.filtered.forEach((group, idx) => {
      const u = group?.user || {};
      const displayName = cleanDisplayName(u.name || 'Unknown') || 'Unknown';
      const tasks = Array.isArray(group?.tasks) ? group.tasks : [];

      const avatarHtml = u.avatar
        ? `<img src="${escHtml2(u.avatar)}" class="rounded-circle" width="44" height="44" alt="${escHtml2(displayName)}">`
        : `<div class="ac-col-avatar">${escHtml2(initials2(displayName))}</div>`;

      const preview = tasks.slice(0, state.taskPreview).map(pendingTaskCard).join('');
      const moreBtn = tasks.length > state.taskPreview
        ? `<button class="btn btn-link btn-sm p-0 mt-2 pending-more" data-idx="${idx}" data-expanded="0">Show all ${tasks.length}</button>`
        : '';

      $track.append(`
        <div class="ac-member-col" data-col-idx="${idx}">
          <div class="ac-col-header">
            <div class="ac-col-avatar-wrap">${avatarHtml}</div>
            <div class="ac-col-meta">
              <div class="ac-col-name">${escHtml2(displayName)}</div>
              <div class="ac-col-sub">${escHtml2(u.email || '')}</div>
            </div>
            <span class="ac-col-badge">${tasks.length}</span>
          </div>
          <div class="ac-col-body" data-idx="${idx}">
            ${preview || '<div class="ac-no-tasks">No pending tasks</div>'}
            ${moreBtn}
          </div>
        </div>
      `);

      $dots.append(`<button class="ac-dot${idx===0?' active':''}" data-dot="${idx}" title="${escHtml2(displayName)}"></button>`);
    });

    state.idx = 0;
    state.visible = calcVisible();
    applyPosition(false);
    start();
  }

  function load(force=false) {
    if (state.loaded && !force) return;
    state.loaded = true;
    $id(cfg.loadingId).removeClass('d-none');
    $id(cfg.errorId).addClass('d-none');
    $id(cfg.trackId).empty();
    stop();

    if (typeof cfg.api !== 'function') {
      $id(cfg.errorId).text('Pending API is not available. Please hard refresh (Ctrl+Shift+R).').removeClass('d-none');
      $id(cfg.loadingId).addClass('d-none');
      return;
    }

    cfg.api()
      .done(d => {
        state.data = d;
        render(d);
        cfg.onCount && cfg.onCount(d);
      })
      .fail(() => {
        $id(cfg.errorId).text('Failed to load data.').removeClass('d-none');
      })
      .always(() => $id(cfg.loadingId).addClass('d-none'));
  }

  // Bind controls
  $(document)
    .on('click', '#' + cfg.prevId, () => { stop(); prev(); })
    .on('click', '#' + cfg.nextId, () => { stop(); next(); })
    .on('click', '#' + cfg.toggleId, () => { state.running ? stop() : start(); })
    .on('click', '#' + cfg.reloadId, () => { state.loaded = false; load(true); })
    .on('click', '#' + cfg.dotsId + ' .ac-dot', function () { stop(); goTo(+$(this).data('dot')); })
    .on('mouseenter', '#' + cfg.wrapId, () => stop())
    .on('mouseleave', '#' + cfg.wrapId, () => { if (!state.running) start(); })
    .on('click', '#' + cfg.wrapId + ' .pending-more', function () {
      const idx = +$(this).data('idx');
      const expanded = $(this).data('expanded') === 1;
      const group = state.filtered[idx];
      const tasks = Array.isArray(group?.tasks) ? group.tasks : [];
      const $body = $(`#${cfg.wrapId} .ac-col-body[data-idx="${idx}"]`);
      if (!$body.length) return;

      if (!expanded) {
        $body.html(tasks.map(pendingTaskCard).join('') + `<button class="btn btn-link btn-sm p-0 mt-2 pending-more" data-idx="${idx}" data-expanded="1">Show less</button>`);
      } else {
        const preview = tasks.slice(0, state.taskPreview).map(pendingTaskCard).join('');
        const btn = tasks.length > state.taskPreview
          ? `<button class="btn btn-link btn-sm p-0 mt-2 pending-more" data-idx="${idx}" data-expanded="0">Show all ${tasks.length}</button>`
          : '';
        $body.html(preview || '<div class="ac-no-tasks">No pending tasks</div>');
        if (btn) $body.append(btn);
      }
    });

  $(window).on('resize', () => {
    if (!state.filtered.length) return;
    state.visible = calcVisible();
    applyPosition(false);
  });

  return { load };
}

const Pending = {
  loaded: false,
  _ac: null,
  _trello: null,

  load(force=false) {
    if (this.loaded && !force) return;
    this.loaded = true;

    const updateTotals = (trelloGroups, acGroups) => {
      const trelloCount = (trelloGroups || []).reduce((n,g)=> n + (g.tasks?.length||0), 0);
      const acCount     = (acGroups || []).reduce((n,g)=> n + (g.tasks?.length||0), 0);
      $('#pending-trello-total').text(`Trello: ${trelloCount}`);
      $('#pending-ac-total').text(`ActiveCollab: ${acCount}`);
      $('#pending-total').text(trelloCount + acCount);
    };

    let lastTrello = null;
    let lastAc = null;

    this._ac = createPendingSlider({
      wrapId: 'pending-ac-wrap',
      trackId: 'pending-ac-track',
      dotsId: 'pending-ac-dots',
      loadingId: 'pending-ac-loading',
      errorId: 'pending-ac-error',
      prevId: 'pending-ac-prev',
      nextId: 'pending-ac-next',
      toggleId: 'pending-ac-toggle',
      reloadId: 'pending-ac-reload',
      api: API.pendingAc,
      onCount: d => { lastAc = d; updateTotals(lastTrello, lastAc); },
    });

    this._trello = createPendingSlider({
      wrapId: 'pending-trello-wrap',
      trackId: 'pending-trello-track',
      dotsId: 'pending-trello-dots',
      loadingId: 'pending-trello-loading',
      errorId: 'pending-trello-error',
      prevId: 'pending-trello-prev',
      nextId: 'pending-trello-next',
      toggleId: 'pending-trello-toggle',
      reloadId: 'pending-trello-reload',
      api: API.pendingTrello,
      onCount: d => { lastTrello = d; updateTotals(lastTrello, lastAc); },
    });

    this._ac.load(true);
    this._trello.load(true);
  },
};

