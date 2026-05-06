/**
 * assets/js/app.js
 * Handles auth state, tab routing, sync button, 5-min auto-sync.
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
      this.startAutoSync();
    }
  },

  showTab(tab) {
    this.currentTab = tab;
    $('.tab-panel').addClass('d-none');
    $('#panel-' + tab).removeClass('d-none');
    $('.tab-link').removeClass('active');
    $(`.tab-link[data-tab="${tab}"]`).addClass('active');

    // Lazy-load each tab on first activation
    const loaders = {
      'report':       () => Report.load(),
      'ac-members':   () => AcMembers.load(),
      'ac-projects':  () => AcProjects.load(),
      'ac-managers':  () => AcManagers.load(),
      'ac-clients':   () => AcClients.load(),
    };
    if (loaders[tab]) loaders[tab]();
  },

  startAutoSync() {
    clearInterval(this._syncInterval);
    this._syncInterval = setInterval(() => {
      API.syncTrello()
        .then(() => API.syncHubstaff())
        .then(() => {
          Report.load(true);
          showToast('Auto-synced ✓', 'bg-success');
        })
        .catch(() => {});
    }, 5 * 60 * 1000);
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

  /* ── Manual sync ─────────────────────────────────────────── */
  $('#sync-btn').on('click', function () {
    $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Syncing…');
    showToast('Syncing all sources…', 'bg-primary');

    API.syncAll()
      .done(function (data) {
        const t = (data.trello?.synced || 0) + (data.mantis?.synced || 0);
        showToast(`Sync complete — ${t} tasks`, 'bg-success');
        if (App.currentTab === 'report') Report.load(true);
      })
      .fail(function () {
        showToast('Sync failed', 'bg-danger');
      })
      .always(function () {
        $('#sync-btn').prop('disabled', false).html('<i class="bi bi-arrow-repeat me-1"></i>Sync');
      });
  });

});
