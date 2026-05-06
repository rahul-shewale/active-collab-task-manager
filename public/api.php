<?php
// ─── public/api.php ───────────────────────────────────────────────────────────
// All /api/* requests are rewritten here by .htaccess (or Nginx).

require_once __DIR__ . '/../app/Core/Bootstrap.php';

use App\Core\Bootstrap;
use App\Core\Router;
use App\Core\Request;

Bootstrap::init();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$router = new Router();

// ── Auth ──────────────────────────────────────────────────────────────────────
$router->post('/login',  [\App\Controllers\AuthController::class, 'login']);
$router->post('/logout', [\App\Controllers\AuthController::class, 'logout']);
$router->get('/me',      [\App\Controllers\AuthController::class, 'me']);

// ── Users ─────────────────────────────────────────────────────────────────────
$router->get('/users',          [\App\Controllers\UserController::class, 'index']);
$router->post('/users',         [\App\Controllers\UserController::class, 'store']);
$router->get('/users/{user}',   [\App\Controllers\UserController::class, 'show']);
$router->delete('/users/{user}',[\App\Controllers\UserController::class, 'destroy']);

// ── Tasks ─────────────────────────────────────────────────────────────────────
$router->get('/tasks/stats',    [\App\Controllers\TaskController::class, 'stats']);
$router->get('/tasks',          [\App\Controllers\TaskController::class, 'index']);
$router->post('/tasks',         [\App\Controllers\TaskController::class, 'store']);
$router->get('/tasks/{task}',   [\App\Controllers\TaskController::class, 'show']);

// ── Reports ───────────────────────────────────────────────────────────────────
$router->get('/reports/project-stats', [\App\Controllers\ReportController::class, 'projectStats']);
$router->get('/reports/due-date-stats',[\App\Controllers\ReportController::class, 'dueDateStats']);
$router->get('/reports/hubstaff',      [\App\Controllers\ReportController::class, 'hubstaffTeam']);

// ── Sync ──────────────────────────────────────────────────────────────────────
$router->post('/sync/trello',   [\App\Controllers\SyncController::class, 'trello']);
$router->post('/sync/mantis',   [\App\Controllers\SyncController::class, 'mantis']);
$router->post('/sync/hubstaff', [\App\Controllers\SyncController::class, 'hubstaff']);
$router->post('/sync/all',      [\App\Controllers\SyncController::class, 'all']);
$router->post('/sync/cron',     [\App\Controllers\SyncController::class, 'cron']);
$router->get('/sync/logs',      [\App\Controllers\SyncController::class, 'logs']);
$router->get('/sync/status',    [\App\Controllers\SyncController::class, 'status']);

// ── ActiveCollab ──────────────────────────────────────────────────────────────
$router->get('/active-collab/teams-view',    [\App\Controllers\ActiveCollabController::class, 'teamsView']);
$router->get('/active-collab/projects-view', [\App\Controllers\ActiveCollabController::class, 'projectsView']);
$router->get('/active-collab/managers-view', [\App\Controllers\ActiveCollabController::class, 'managersView']);
$router->get('/active-collab/clients-view',  [\App\Controllers\ActiveCollabController::class, 'clientsView']);

// ── Dispatch ──────────────────────────────────────────────────────────────────
$router->dispatch(new Request());
