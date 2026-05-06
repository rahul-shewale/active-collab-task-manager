/**
 * assets/js/ac_members.js
 * Trello-like vertical member cards with auto-slider (2s interval).
 */

const AcMembers = {
  loaded: false,
  data: [],
  filtered: [],
  _taskPreviewLimit: 20,

  /* ── slider state ───────────────────────────────────────── */
  _sliderIdx:      0,
  _sliderTimer:    null,
  _sliderRunning:  false,
  _visibleCount:   3,   // how many columns fit at once

  load(force = false) {
    // Already loaded — just re-render from cache (restarts slider) without a network call
    if (this.loaded && !force) {
      if (this.data.length) this.render(this.data);
      return;
    }
    this.loaded = true;

    $('#ac-members-loading').removeClass('d-none');
    $('#ac-members-track').empty();
    $('#ac-members-error').addClass('d-none');
    this._stopSlider();

    acFetch('ac_teams', API.acTeams, force)
      .done(data => {
        this.data = data;
        this.render(data);
      })
      .fail(() => {
        $('#ac-members-error').text('Failed to load members from ActiveCollab.').removeClass('d-none');
      })
      .always(() => {
        $('#ac-members-loading').addClass('d-none');
      });
  },

  render(data) {
    const search = ($('#ac-members-search').val() || '').toLowerCase();
    this.filtered = search
      ? (Array.isArray(data) ? data : []).filter(g => (g?.user?.name || '').toLowerCase().includes(search))
      : (Array.isArray(data) ? data : []);

    const $track = $('#ac-members-track');
    $track.empty();
    $('#ac-slider-dots').empty();
    this._stopSlider();

    if (!this.filtered.length) {
      $track.html('<div class="text-muted py-4 text-center">No members match your search.</div>');
      return;
    }

    /* Build one vertical column card per member */
    this.filtered.forEach((group, idx) => {
      const u = group?.user || {};
      const memberTasks = Array.isArray(group?.tasks) ? group.tasks : [];

      const avatarHtml = u.avatar
        ? `<img src="${escHtml(u.avatar)}" class="rounded-circle" width="44" height="44" alt="${escHtml(u.name)}">`
        : `<div class="ac-col-avatar">${escHtml(initials(u.name))}</div>`;

      const previewTasks = memberTasks.slice(0, this._taskPreviewLimit);
      const taskCards = previewTasks.map(task => acMemberTaskCard(task)).join('');
      const moreBtn = memberTasks.length > this._taskPreviewLimit
        ? `<button class="btn btn-link btn-sm p-0 mt-2 ac-member-more"
             data-member-idx="${idx}"
             data-expanded="0">
             Show all ${memberTasks.length} tasks
           </button>`
        : '';

      const col = `
        <div class="ac-member-col" data-col-idx="${idx}">
          <!-- Column header -->
          <div class="ac-col-header">
            <div class="ac-col-avatar-wrap">${avatarHtml}</div>
            <div class="ac-col-meta">
              <div class="ac-col-name">${escHtml(u.name || 'Unknown')}</div>
              <div class="ac-col-sub">${escHtml(u.email || '')}</div>
            </div>
            <span class="ac-col-badge">${memberTasks.length}</span>
          </div>
          <!-- Task list -->
          <div class="ac-col-body" data-member-idx="${idx}">
            ${taskCards || '<div class="ac-no-tasks">No open tasks</div>'}
            ${moreBtn}
          </div>
        </div>`;

      $track.append(col);

      /* Dot indicator */
      $('#ac-slider-dots').append(
        `<button class="ac-dot${idx === 0 ? ' active' : ''}" data-dot="${idx}" title="${escHtml(u.name)}"></button>`
      );
    });

    this._sliderIdx = 0;
    this._visibleCount = this._calcVisible();
    this._applyPosition(false);
    this._startSlider();
  },

  /* ── Slider mechanics ───────────────────────────────────── */
  _calcVisible() {
    const w = $('#ac-members-slider-wrap').width();
    if (w >= 1200) return 5;
    if (w >= 900)  return 4;
    if (w >= 640)  return 3;
    if (w >= 400)  return 2;
    return 1;
  },

  _maxIdx() {
    return Math.max(0, this.filtered.length - this._visibleCount);
  },

  _applyPosition(animate = true) {
    const $track = $('#ac-members-track');
    const colW   = 100 / this._visibleCount;   // percent per column
    const pct    = this._sliderIdx * colW;

    // Set each column width
    $('.ac-member-col').css('flex', `0 0 ${colW}%`);

    if (animate) {
      $track.css('transition', 'transform 0.45s cubic-bezier(.4,0,.2,1)');
    } else {
      $track.css('transition', 'none');
    }
    $track.css('transform', `translateX(-${pct}%)`);

    // Dots
    $('.ac-dot').removeClass('active');
    $(`.ac-dot[data-dot="${this._sliderIdx}"]`).addClass('active');
  },

  goTo(idx) {
    const max = this._maxIdx();
    this._sliderIdx = Math.max(0, Math.min(idx, max));
    this._applyPosition(true);
  },

  next() {
    const next = this._sliderIdx >= this._maxIdx() ? 0 : this._sliderIdx + 1;
    this.goTo(next);
  },

  prev() {
    const prev = this._sliderIdx <= 0 ? this._maxIdx() : this._sliderIdx - 1;
    this.goTo(prev);
  },

  _startSlider() {
    this._stopSlider();
    this._sliderRunning = true;
    this._updateToggleBtn();
    this._sliderTimer = setInterval(() => this.next(), 2000);
  },

  _stopSlider() {
    clearInterval(this._sliderTimer);
    this._sliderTimer   = null;
    this._sliderRunning = false;
    this._updateToggleBtn();
  },

  _toggleSlider() {
    if (this._sliderRunning) {
      this._stopSlider();
    } else {
      this._startSlider();
    }
  },

  _updateToggleBtn() {
    if (this._sliderRunning) {
      $('#ac-slider-toggle').html('<i class="bi bi-pause-fill"></i> <span>Pause</span>')
        .removeClass('btn-outline-secondary').addClass('btn-primary');
    } else {
      $('#ac-slider-toggle').html('<i class="bi bi-play-fill"></i> <span>Play</span>')
        .removeClass('btn-primary').addClass('btn-outline-secondary');
    }
  },
};

/* ── Task card renderer (compact, Trello-style) ─────────── */
function acMemberTaskCard(task) {
  const today   = new Date(); today.setHours(0,0,0,0);
  const dueDate = task.due_date ? new Date(task.due_date) : null;
  if (dueDate) dueDate.setHours(0,0,0,0);
  const isOverdue = dueDate && dueDate < today && task.status !== 'done';
  const isToday   = dueDate && dueDate.getTime() === today.getTime();

  const dueStr = dueDate
    ? dueDate.toLocaleDateString('en-IN', { day: 'numeric', month: 'short' })
    : null;

  const prioMap = {
    urgent: { cls: 'prio-urgent', icon: '🔥', label: 'Urgent' },
    high:   { cls: 'prio-high',   icon: '⬆',  label: 'High'   },
    normal: { cls: 'prio-normal', icon: '',    label: 'Normal' },
    low:    { cls: 'prio-low',    icon: '⬇',  label: 'Low'    },
  };
  const prio = prioMap[task.priority] || prioMap.normal;

  const statusCls = { done: 'status-done', open: 'status-open', in_progress: 'status-wip', on_hold: 'status-hold' };
  const statusLabel = { done: 'Done', open: 'Open', in_progress: 'In Progress', on_hold: 'On Hold' };

  const openBtn = task.url
    ? `<a href="${escHtml(task.url)}" target="_blank" rel="noopener" class="ac-card-link" title="Open in ActiveCollab">↗</a>`
    : '';

  return `
    <div class="ac-task-item ${task.status === 'done' ? 'ac-task-done' : ''}">
      <div class="ac-task-item-top">
        <span class="ac-task-item-title">${escHtml(task.title)}</span>
        ${openBtn}
      </div>
      <div class="ac-task-item-meta">
        <span class="ac-status-pill ${statusCls[task.status] || 'status-open'}">${statusLabel[task.status] || task.status}</span>
        <span class="ac-prio-pill ${prio.cls}">${prio.icon} ${prio.label}</span>
        ${dueStr ? `<span class="ac-due-pill ${isOverdue ? 'due-overdue' : isToday ? 'due-today' : 'due-ok'}">
          ${isOverdue ? '⚠' : '🗓'} ${dueStr}
        </span>` : ''}
      </div>
      ${task.board_name ? `<div class="ac-task-item-board">${escHtml(task.board_name)}</div>` : ''}
    </div>`;
}

/* ── Event bindings ─────────────────────────────────────── */
$(document)
  .on('input',  '#ac-members-search',   () => AcMembers.render(AcMembers.data))
  .on('click',  '#ac-members-reload',   () => { delete acCache['ac_teams']; AcMembers.loaded = false; AcMembers.load(true); })
  .on('click',  '.ac-member-more',      function () {
    const idx = +$(this).data('member-idx');
    const expanded = $(this).data('expanded') === 1;
    const group = AcMembers.filtered[idx];
    const tasks = Array.isArray(group?.tasks) ? group.tasks : [];
    const $body = $(`.ac-col-body[data-member-idx="${idx}"]`);
    if (!$body.length) return;

    if (!expanded) {
      const cards = tasks.map(task => acMemberTaskCard(task)).join('');
      $body.html(cards + `<button class="btn btn-link btn-sm p-0 mt-2 ac-member-more" data-member-idx="${idx}" data-expanded="1">Show less</button>`);
    } else {
      const preview = tasks.slice(0, AcMembers._taskPreviewLimit).map(task => acMemberTaskCard(task)).join('');
      const btn = tasks.length > AcMembers._taskPreviewLimit
        ? `<button class="btn btn-link btn-sm p-0 mt-2 ac-member-more" data-member-idx="${idx}" data-expanded="0">Show all ${tasks.length} tasks</button>`
        : '';
      $body.html(preview || '<div class="ac-no-tasks">No open tasks</div>');
      if (btn) $body.append(btn);
    }
  })
  .on('click',  '#ac-slider-prev',      () => { AcMembers._stopSlider(); AcMembers.prev(); })
  .on('click',  '#ac-slider-next',      () => { AcMembers._stopSlider(); AcMembers.next(); })
  .on('click',  '#ac-slider-toggle',    () => AcMembers._toggleSlider())
  .on('click',  '.ac-dot',              function() { AcMembers._stopSlider(); AcMembers.goTo(+$(this).data('dot')); })
  .on('mouseenter', '#ac-members-slider-wrap', () => AcMembers._stopSlider())
  .on('mouseleave', '#ac-members-slider-wrap', () => { if (!AcMembers._sliderRunning) AcMembers._startSlider(); });

$(window).on('resize', () => {
  if (!AcMembers.filtered.length) return;
  AcMembers._visibleCount = AcMembers._calcVisible();
  AcMembers._applyPosition(false);
});