/**
 * assets/js/ac_clients.js
 * Renders the AC Clients tab.
 */

const AcClients = {
  loaded: false,
  data: [],

  load(force = false) {
    if (this.loaded && !force) return;
    this.loaded = true;

    $('#ac-clients-loading').removeClass('d-none');
    $('#ac-clients-list').empty();
    $('#ac-clients-error').addClass('d-none');

    acFetch('ac_clients', API.acClients, force)
      .done(data => {
        this.data = data;
        this.render(data);
      })
      .fail(() => {
        $('#ac-clients-error').text('Failed to load clients from ActiveCollab.').removeClass('d-none');
      })
      .always(() => {
        $('#ac-clients-loading').addClass('d-none');
      });
  },

  render(data) {
    const search = $('#ac-clients-search').val().toLowerCase();
    const filtered = search
      ? data.filter(g => g.client.name.toLowerCase().includes(search))
      : data;

    if (!filtered.length) {
      $('#ac-clients-list').html('<div class="text-muted py-4 text-center">No clients match your search.</div>');
      return;
    }

    const html = filtered.map(({ client, projects }) => {
      const pal = seedColor(client.id);
      const ini = (client.name || '?').split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase();

      const projectCards = projects.map(p => {
        const viewLink = p.url
          ? `<a href="https://designer.edeveloperz.com${p.url}" target="_blank" class="btn btn-sm btn-outline-primary">View ↗</a>`
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
                </div>
              </div>
            </div>
          </div>`;
      }).join('');

      return `
        <section class="mb-4">
          <div class="d-flex align-items-center gap-3 p-3 rounded-3 mb-2" style="background:${pal.bg};border:2px solid ${pal.border}">
            <div class="ac-manager-avatar" style="background:${pal.dot}">${ini}</div>
            <div class="fw-bold flex-grow-1" style="color:${pal.text}">${escHtml(client.name)}</div>
            <span class="badge rounded-pill" style="background:${pal.dot}">${projects.length} project${projects.length !== 1 ? 's' : ''}</span>
          </div>
          <div class="row g-2">${projectCards}</div>
        </section>`;
    }).join('');

    $('#ac-clients-list').html(html);
  },
};

$(document).on('input', '#ac-clients-search', function () {
  AcClients.render(AcClients.data);
});

$(document).on('click', '#ac-clients-reload', function () {
  delete acCache['ac_clients'];
  AcClients.loaded = false;
  AcClients.load(true);
});
