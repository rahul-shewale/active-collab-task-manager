/**
 * assets/js/ac_managers.js
 * Renders the AC Managers tab.
 */

const AcManagers = {
  loaded: false,
  data: [],

  load(force = false) {
    if (this.loaded && !force) return;
    this.loaded = true;

    $('#ac-managers-loading').removeClass('d-none');
    $('#ac-managers-list').empty();
    $('#ac-managers-error').addClass('d-none');

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
    const filtered = search
      ? data.filter(g => g.manager.name.toLowerCase().includes(search))
      : data;

    if (!filtered.length) {
      $('#ac-managers-list').html('<div class="text-muted py-4 text-center">No managers match your search.</div>');
      return;
    }

    const html = filtered.map(({ manager, projects }) => {
      const pal = seedColor(manager.id);
      const ini = initials(manager.name);

      if (!projects.length) return '';

      const projectCards = projects.map(p => {
        const viewLink = p.url
          ? `<a href="https://designer.edeveloperz.com${p.url}" target="_blank" class="btn btn-sm btn-outline-primary">View ↗</a>`
          : '';
        const billable = p.is_billable
          ? `<span class="badge bg-success ms-1">Billable</span>`
          : '';

        return `
          <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left:3px solid ${pal.dot}!important">
              <div class="card-body py-2 px-3">
                <div class="d-flex align-items-start justify-content-between gap-2">
                  <div class="fw-semibold small flex-grow-1" style="color:${pal.text}">${escHtml(p.name)}</div>
                  ${viewLink}
                </div>
                <div class="mt-1">
                  <span class="badge bg-light text-dark border">${p.task_count} tasks</span>
                  ${billable}
                </div>
              </div>
            </div>
          </div>`;
      }).join('');

      return `
        <section class="mb-4">
          <div class="d-flex align-items-center gap-3 p-3 rounded-3 mb-2" style="background:${pal.bg};border:2px solid ${pal.border}">
            <div class="ac-manager-avatar" style="background:${pal.avatar}">${ini}</div>
            <div class="fw-bold flex-grow-1" style="color:${pal.text}">${escHtml(manager.name)}</div>
            <span class="badge rounded-pill" style="background:${pal.dot}">${projects.length} project${projects.length !== 1 ? 's' : ''}</span>
          </div>
          <div class="row g-2">${projectCards}</div>
        </section>`;
    }).join('');

    $('#ac-managers-list').html(html || '<div class="text-muted py-4 text-center">No managers with projects found.</div>');
  },
};

$(document).on('input', '#ac-managers-search', function () {
  AcManagers.render(AcManagers.data);
});

$(document).on('click', '#ac-managers-reload', function () {
  delete acCache['ac_managers'];
  AcManagers.loaded = false;
  AcManagers.load(true);
});
