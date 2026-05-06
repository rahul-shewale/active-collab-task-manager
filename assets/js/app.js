/**
 * assets/js/app.js
 * Auth state, tab routing, "Sync Data" button, last-synced indicator.
 *
 * The browser no longer triggers periodic syncs — that's now the
 * cron job's responsibility (bin/cron.php). The Sync Data button
 * manually invokes the same SyncRunner via POST /api/sync/cron.
 */

/* ── helpers ──────────────────────────────────────────────── */
const PALETTE = [
  { bg:'#eef2ff',border:'#818cf8',dot:'#6366f1',text:'#3730a3',avatar:'#6366f1' },
  { bg:'#f0fdf4',border:'#34d399',dot:'#10b981',text:'#065f46',avatar:'#10b981' },
  { bg:'#fff7ed',border:'#fb923c',dot:'#f97316',text:'#9a3412',avatar:'#f97316' },
  { bg:'#fdf4ff',border:'#c084fc',dot:'#a855f7',text:'#6b21a8',avatar:'#a855f7' },
  { bg:'#eff6ff',border:'#60a5fa',dot:'#3b82f6',text:'#1e40af',avatar:'#3b82f6' },
  { bg:'#fff1f2',border:'#fb7185',dot:'#f43f5e',text:'#9f1239',avatar:'#f43f5e' },
  { bg:'#ecfdf5',border:'#6ee7b7',dot:'#059669',text:'#064e3b',avatar:'#059669' },
  { bg:'#fefce8',border:'#fde047',dot:'#ca8a04',text:'#713f12',avatar:'#ca8a04' },
];

function seedColor(id) {
  return PALETTE[parseInt(id, 10) % PALETTE.length];
}

function initials(name) {
  return (name || '?').split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
}

function showToast(msg, type = 'bg-primary') {
  const $toast = $('#sync-toast');
  $toast.removeClass('bg-primary bg-success bg-danger bg-warning').addClass(type);
  $('#sync-toast-body').text(msg);
  const toast = bootstrap.Toast.getOrCreateInstance($toast[0], { delay: 3000 });
  toast.show();
}

/* ── AC cache (session-level) ─────────────────────────────── */
const acCache = {};
function acFetch(key, apiFn, force = false) {
  if (!force && acCache[key]) return $.Deferred().resolve(acCache[key]).promise();
  return apiFn().done(d => { acCache[key] = d; });
}

/* ── Last-synced label helpers ────────────────────────────── */
function fmtRelative(timestamp) {
  if (!timestamp) return 'never';
  const then = new Date(timestamp.replace(' ', 'T'));
  const diff = Math.max(0, (Date.now() - then.getTime()) / 1000);
  if (diff < 60)        return 'just now';
  if (diff < 3600)      return Math.floor(diff / 60)   + ' min ago';
  if (diff < 86400)     return Math.floor(diff / 3600) + ' h ago';
  return then.toLocaleString();
}

function refreshSyncStatus() {
  return API.syncStatus()
    .done(s => {
      const label = s.last_run_at
        ? `Last synced: ${fmtRelative(s.last_run_at)}`
        : 'Not synced yet';
      $('#last-synced').text(label).attr('title', s.last_run_at || '');
    })
    .fail(() => {
      $('#last-synced').text('');
    });
}

/* ── App namespace ────────────────────────────────────────── */
const App = {
  currentTab: 'report',

  showPage(page) {
    $('#login-page, #dashboard-page').addClass('d-none');
    if (page === 'login') {
      $('#login-page').removeClass('d-none');
    } else {
      $('#dashboard-page').removeClass('d-none');
      const user = JSON.parse(localStorage.getItem('auth_user') || '{}');
      $('#nav-user').text(user.name || '');
      this.showTab('report');
      refreshSyncStatus();
    }
  },

  showTab(tab) {
    this.currentTab = tab;
    $('.tab-panel').addClass('d-none');
    $('#panel-' + tab).removeClass('d-none');
    $('.tab-link').removeClass('active');
    $(`.tab-link[data-tab="${tab}"]`).addClass('active');

    const loaders = {
      'report':       () => Report.load(),
      'ac-members':   () => AcMembers.load(),
      'ac-projects':  () => AcProjects.load(),
      'ac-managers':  () => AcManagers.load(),
      'ac-clients':   () => AcClients.load(),
    };
    if (loaders[tab]) loaders[tab]();
  },

  reloadCurrentTab() {
    Object.keys(acCache).forEach(k => delete acCache[k]);
    const reloaders = {
      'report':       () => Report.load(true),
      'ac-members':   () => { AcMembers.loaded = false; AcMembers.load(true); },
      'ac-projects':  () => { AcProjects.loaded = false; AcProjects.load(true); },
      'ac-managers':  () => { AcManagers.loaded = false; AcManagers.load(true); },
      'ac-clients':   () => { AcClients.loaded  = false; AcClients.load(true); },
    };
    (reloaders[this.currentTab] || (() => {}))();
  },
};

/* ── Boot ─────────────────────────────────────────────────── */
$(function () {

  /* ── Auth check ──────────────────────────────────────────── */
  const token = localStorage.getItem('auth_token');
  if (token) {
    App.showPage('dashboard');
  } else {
    App.showPage('login');
  }

  /* ── Login form ──────────────────────────────────────────── */
  $('#login-form').on('submit', function (e) {
    e.preventDefault();
    const email = $('#login-email').val();
    const pass  = $('#login-password').val();

    $('#login-error').addClass('d-none');
    $('#login-btn').prop('disabled', true);
    $('#login-spinner').removeClass('d-none');

    API.login(email, pass)
      .done(function (data) {
        localStorage.setItem('auth_token', data.token);
        localStorage.setItem('auth_user',  JSON.stringify(data.user));
        App.showPage('dashboard');
      })
      .fail(function (xhr) {
        const msg = xhr.responseJSON?.errors?.email?.[0]
          || xhr.responseJSON?.message
          || 'Login failed. Check your credentials.';
        $('#login-error').text(msg).removeClass('d-none');
      })
      .always(function () {
        $('#login-btn').prop('disabled', false);
        $('#login-spinner').addClass('d-none');
      });
  });

  /* ── Tab nav ─────────────────────────────────────────────── */
  $(document).on('click', '.tab-link', function (e) {
    e.preventDefault();
    App.showTab($(this).data('tab'));
  });

  /* ── Logout ──────────────────────────────────────────────── */
  $('#logout-btn').on('click', function () {
    API.logout().always(function () {
      localStorage.removeItem('auth_token');
      localStorage.removeItem('auth_user');
      App.showPage('login');
    });
  });

  /* ── Manual cron trigger (Sync Data button) ──────────────── */
  $('#sync-btn').on('click', function () {
    const $btn = $(this);
    $btn.prop('disabled', true)
        .html('<span class="spinner-border spinner-border-sm me-1"></span>Syncing…');
    showToast('Running sync — this can take up to a minute…', 'bg-primary');

    API.syncCron()
      .done(function (summary) {
        const t  = summary.trello?.synced   || 0;
        const m  = summary.mantis?.synced   || 0;
        const h  = summary.hubstaff?.synced || 0;
        const a  = summary.ac?.synced       || 0;
        const ok = summary.status === 'success';
        showToast(
          `Sync ${ok ? 'complete' : 'finished with issues'} — Trello ${t}, Mantis ${m}, Hubstaff ${h}, AC ${a}`,
          ok ? 'bg-success' : 'bg-warning'
        );
        refreshSyncStatus();
        App.reloadCurrentTab();
      })
      .fail(function (xhr) {
        const msg = xhr.responseJSON?.message || 'Sync failed';
        showToast(msg, 'bg-danger');
      })
      .always(function () {
        $btn.prop('disabled', false)
            .html('<i class="bi bi-arrow-repeat me-1"></i>Sync Data');
      });
  });

});
