/**
 * assets/js/ac_managers.js
 * Renders the AC Managers tab.
 */

const AcManagers = {
  loaded: false,
  data: [],
  filtered: [],
  _idx: 0,
  _timer: null,
  _running: false,
  _visible: 3,

  load(force = false) {
    if (this.loaded && !force) {
      if (this.data.length) this.render(this.data);
      return;
    }
    this.loaded = true;

    $('#ac-managers-loading').removeClass('d-none');
    $('#ac-managers-track').empty();
    $('#ac-managers-dots').empty();
    $('#ac-managers-error').addClass('d-none');
    this.stop();

    acFetch('ac_managers', API.acManagers, force)
      .done(data => {
        this.data = data;
        this.render(data);
      })
      .fail(() => {
        $('#ac-managers-error').text('Failed to load managers from ActiveCollab.').removeClass('d-none');
      })
      .always(() => {
        $('#ac-managers-loading').addClass('d-none');
      });
  },

  render(data) {
    const search = $('#ac-managers-search').val().toLowerCase();
    this.filtered = search
      ? data.filter(g => g.manager.name.toLowerCase().includes(search))
      : data;

    const $track = $('#ac-managers-track');
    const $dots = $('#ac-managers-dots');
    $track.empty();
    $dots.empty();
    this.stop();

    if (!this.filtered.length) {
      $track.html('<div class="text-muted py-4 text-center w-100">No managers match your search.</div>');
      return;
    }

    this.filtered.forEach(({ manager, projects }, idx) => {
      const pal = seedColor(manager.id);
      const ini = initials(manager.name);

      const projectCards = (projects || []).map(p => {
        const viewLink = p.url
          ? `<a href="https://designer.edeveloperz.com${p.url}" target="_blank" class="btn btn-sm btn-outline-primary">View ↗</a>`
          : '';
        const billable = p.is_billable
          ? `<span class="badge bg-success ms-1">Billable</span>`
          : '';

        return `
          <div class="acx-mini-item">
            <div class="d-flex align-items-start justify-content-between gap-2">
              <div class="acx-mini-title">${escHtml(p.name)}</div>
              ${viewLink}
            </div>
            <div class="acx-mini-meta mt-1">
              <span>${p.task_count} tasks</span>
              ${billable}
            </div>
          </div>`;
      }).join('');

      $track.append(`
        <div class="acx-col">
          <div class="d-flex align-items-center gap-3 p-3 rounded-3 mb-0" style="background:${pal.bg};border:2px solid ${pal.border}">
            <div class="ac-manager-avatar" style="background:${pal.avatar}">${ini}</div>
            <div class="fw-bold flex-grow-1" style="color:${pal.text}">${escHtml(manager.name)}</div>
            <span class="badge rounded-pill" style="background:${pal.dot}">${projects.length} project${projects.length !== 1 ? 's' : ''}</span>
          </div>
          <div class="ac-col-body">
            <div class="acx-mini-list">${projectCards || '<div class="ac-no-tasks">No projects</div>'}</div>
          </div>
        </div>
      `);
      $dots.append(`<button class="acx-dot${idx===0?' active':''}" data-idx="${idx}"></button>`);
    });

    this._idx = 0;
    this._visible = this.calcVisible();
    this.apply(false);
    this.start();
  },

  calcVisible() {
    const w = $('#ac-managers-slider-wrap').width();
    if (w >= 1200) return 4;
    if (w >= 900) return 3;
    if (w >= 640) return 2;
    return 1;
  },
  maxIdx() { return Math.max(0, this.filtered.length - this._visible); },
  apply(animate = true) {
    const colW = 100 / this._visible;
    $('#ac-managers-track .acx-col').css('flex', `0 0 ${colW}%`);
    $('#ac-managers-track').css('transition', animate ? 'transform 0.45s cubic-bezier(.4,0,.2,1)' : 'none');
    $('#ac-managers-track').css('transform', `translateX(-${this._idx * colW}%)`);
    $('#ac-managers-dots .acx-dot').removeClass('active');
    $(`#ac-managers-dots .acx-dot[data-idx="${this._idx}"]`).addClass('active');
  },
  goTo(i) { this._idx = Math.max(0, Math.min(i, this.maxIdx())); this.apply(true); },
  next() { this.goTo(this._idx >= this.maxIdx() ? 0 : this._idx + 1); },
  prev() { this.goTo(this._idx <= 0 ? this.maxIdx() : this._idx - 1); },
  start() {
    this.stop();
    this._running = true;
    $('#ac-managers-toggle').html('<i class="bi bi-pause-fill"></i> <span>Pause</span>').removeClass('btn-outline-secondary').addClass('btn-primary');
    this._timer = setInterval(() => this.next(), 2000);
  },
  stop() {
    clearInterval(this._timer);
    this._timer = null;
    this._running = false;
    $('#ac-managers-toggle').html('<i class="bi bi-play-fill"></i> <span>Play</span>').removeClass('btn-primary').addClass('btn-outline-secondary');
  },
  toggle() { this._running ? this.stop() : this.start(); },
};

$(document).on('input', '#ac-managers-search', function () {
  AcManagers.render(AcManagers.data);
});
$(document)
  .on('click', '#ac-managers-prev', () => { AcManagers.stop(); AcManagers.prev(); })
  .on('click', '#ac-managers-next', () => { AcManagers.stop(); AcManagers.next(); })
  .on('click', '#ac-managers-toggle', () => AcManagers.toggle())
  .on('click', '#ac-managers-dots .acx-dot', function () { AcManagers.stop(); AcManagers.goTo(+$(this).data('idx')); })
  .on('mouseenter', '#ac-managers-slider-wrap', () => AcManagers.stop())
  .on('mouseleave', '#ac-managers-slider-wrap', () => { if (!AcManagers._running) AcManagers.start(); });

$(window).on('resize', () => {
  if (!AcManagers.filtered.length) return;
  AcManagers._visible = AcManagers.calcVisible();
  AcManagers.apply(false);
});

$(document).on('click', '#ac-managers-reload', function () {
  delete acCache['ac_managers'];
  AcManagers.loaded = false;
  AcManagers.load(true);
});
