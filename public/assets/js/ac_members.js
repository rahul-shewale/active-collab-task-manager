/**
 * assets/js/ac_members.js
 * Renders the AC Members tab.
 */

const AcMembers = {
  loaded: false,
  data: [],

  load(force = false) {
    if (this.loaded && !force) return;
    this.loaded = true;

    $('#ac-members-loading').removeClass('d-none');
    $('#ac-members-list').empty();
    $('#ac-members-error').addClass('d-none');

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
    const search = $('#ac-members-search').val().toLowerCase();
    const filtered = search
      ? data.filter(g => g.user.name.toLowerCase().includes(search))
      : data;

    if (!filtered.length) {
      $('#ac-members-list').html('<div class="text-muted py-4 text-center">No members match your search.</div>');
      return;
    }

    const html = filtered.map(group => {
      const u = group.user;
      const avatarHtml = u.avatar
        ? `<img src="${escHtml(u.avatar)}" class="rounded-circle" width="40" height="40" alt="${escHtml(u.name)}">`
        : `<div class="ac-avatar-initial">${escHtml(u.name.charAt(0).toUpperCase())}</div>`;

      const tasks = (group.tasks || []).map(task => acTaskCard(task, true)).join('');

      return `
        <section class="ac-user-section mb-4">
          <div class="ac-user-header d-flex align-items-center gap-2 mb-2 p-3 rounded-3 bg-white shadow-sm">
            <div class="ac-avatar-wrap">${avatarHtml}</div>
            <div class="fw-bold flex-grow-1">${escHtml(u.name)}</div>
            <span class="badge bg-primary rounded-pill">${group.tasks.length} task${group.tasks.length !== 1 ? 's' : ''}</span>
          </div>
          <div class="ac-task-grid row g-2">${tasks}</div>
        </section>`;
    }).join('');

    $('#ac-members-list').html(html);
  },
};

$(document).on('input', '#ac-members-search', function () {
  AcMembers.render(AcMembers.data);
});

$(document).on('click', '#ac-members-reload', function () {
  delete acCache['ac_teams'];
  AcMembers.loaded = false;
  AcMembers.load(true);
});
