/**
 * assets/js/ac_projects.js
 * Renders the AC Projects tab.
 */

const AcProjects = {
  loaded: false,

  load(force = false) {
    if (this.loaded && !force) return;
    this.loaded = true;

    $('#ac-projects-loading').removeClass('d-none');
    $('#ac-projects-list').empty();
    $('#ac-projects-error').addClass('d-none');

    acFetch('ac_projects', API.acProjects, force)
      .done(data => {
        if (!data.length) {
          $('#ac-projects-list').html('<div class="text-muted py-4 text-center"><div class="fs-2">📂</div>No ongoing projects found.</div>');
          return;
        }

        const html = data.map(group => {
          const p = group.project || {};
          const tasks = Array.isArray(group.tasks) ? group.tasks : [];
          const viewLink = p.url
            ? `<a href="https://designer.edeveloperz.com${p.url}" target="_blank" class="btn btn-sm btn-outline-primary">View ↗</a>`
            : '';

          return `
            <section class="ac-project-section mb-3 bg-white border rounded-3 shadow-sm p-3">
              <div class="d-flex align-items-center gap-2 mb-2">
                <span class="fs-6">📂</span>
                <div class="fw-bold flex-grow-1">${escHtml(p.name || 'Project')}</div>
                <span class="badge bg-light text-dark border">${tasks.length} task${tasks.length !== 1 ? 's' : ''}</span>
                ${viewLink}
              </div>
              <div class="row g-2">
                ${tasks.length
                  ? tasks.slice(0, 12).map(task => `<div class="col-md-6 col-xl-4">${acTaskCard(task, false)}</div>`).join('')
                  : '<div class="text-muted small">No open tasks</div>'}
              </div>
            </section>`;
        }).join('');

        $('#ac-projects-list').html(html);
      })
      .fail(() => {
        $('#ac-projects-error').text('Failed to load projects from ActiveCollab.').removeClass('d-none');
      })
      .always(() => {
        $('#ac-projects-loading').addClass('d-none');
      });
  },
};

$(document).on('click', '#ac-projects-reload', function () {
  delete acCache['ac_projects'];
  AcProjects.loaded = false;
  AcProjects.load(true);
});
