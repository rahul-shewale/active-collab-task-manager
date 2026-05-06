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
          const p = group.project;
          const viewLink = p.url
            ? `<a href="https://designer.edeveloperz.com${p.url}" target="_blank" class="btn btn-sm btn-outline-primary ms-auto">View ↗</a>`
            : '';

          const tasks = (group.tasks || []).map(task => acTaskCard(task, false)).join('');

          return `
            <section class="ac-project-section mb-4">
              <div class="d-flex align-items-center gap-2 mb-2 p-3 rounded-3 bg-white shadow-sm">
                <span class="fs-5">📂</span>
                <div class="fw-bold flex-grow-1">${escHtml(p.name)}</div>
                <span class="badge bg-secondary rounded-pill">${group.tasks.length} task${group.tasks.length !== 1 ? 's' : ''}</span>
                ${viewLink}
              </div>
              <div class="ac-task-grid row g-2">${tasks}</div>
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
