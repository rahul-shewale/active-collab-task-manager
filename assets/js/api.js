/**
 * assets/js/api.js
 * Centralised API helper — mirrors the original React api.js using jQuery AJAX.
 */

const API_BASE = '/api';

// Read token from localStorage (same keys the login sets)
function getToken() {
  return localStorage.getItem('auth_token') || '';
}

function authHeader() {
  const t = getToken();
  return t ? { Authorization: 'Bearer ' + t } : {};
}

function apiRequest(method, url, data) {
  return $.ajax({
    url: API_BASE + url,
    method: method,
    contentType: 'application/json',
    headers: Object.assign({ Accept: 'application/json' }, authHeader()),
    data: data ? JSON.stringify(data) : undefined,
  }).fail(function (xhr) {
    if (xhr.status === 401) {
      localStorage.removeItem('auth_token');
      localStorage.removeItem('auth_user');
      if (!window.location.hash.includes('login')) {
        App.showPage('login');
      }
    }
  });
}

const API = {
  // Auth
  login:  (email, password) => apiRequest('POST', '/login', { email, password }),
  logout: ()                => apiRequest('POST', '/logout'),
  me:     ()                => apiRequest('GET',  '/me'),

  // Users
  getUsers:   ()     => apiRequest('GET',    '/users'),
  getUser:    (id)   => apiRequest('GET',    '/users/' + id),
  createUser: (data) => apiRequest('POST',   '/users', data),
  deleteUser: (id)   => apiRequest('DELETE', '/users/' + id),

  // Tasks
  getTasks:  (params) => $.ajax({
    url: API_BASE + '/tasks',
    method: 'GET',
    data: params,
    headers: Object.assign({ Accept: 'application/json' }, authHeader()),
  }),
  getTaskStats: () => apiRequest('GET', '/tasks/stats'),
  createTask:   (d) => apiRequest('POST', '/tasks', d),

  // Reports
  getProjectStats: () => apiRequest('GET', '/reports/project-stats'),
  getDueDateStats: () => apiRequest('GET', '/reports/due-date-stats'),
  getHubstaff: (tf)   => apiRequest('GET', '/reports/hubstaff' + (tf ? '?timeframe=' + tf : '')),

  // Sync
  syncTrello:   () => apiRequest('POST', '/sync/trello'),
  syncMantis:   () => apiRequest('POST', '/sync/mantis'),
  syncHubstaff: () => apiRequest('POST', '/sync/hubstaff'),
  syncAll:      () => apiRequest('POST', '/sync/all'),
  getSyncLogs:  () => apiRequest('GET',  '/sync/logs'),

  // ActiveCollab
  acTeams:    () => apiRequest('GET', '/active-collab/teams-view'),
  acProjects: () => apiRequest('GET', '/active-collab/projects-view'),
  acManagers: () => apiRequest('GET', '/active-collab/managers-view'),
  acClients:  () => apiRequest('GET', '/active-collab/clients-view'),
};
