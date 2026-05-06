<?php
require_once __DIR__ . '/../app/Core/Bootstrap.php';
use App\Core\Bootstrap;
Bootstrap::init();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Task Manager Dashboard</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"/>
  <link rel="stylesheet" href="/assets/css/app.css"/>
</head>
<body class="bg-light">

<!-- ═══════════════════════════════════════════════════════════ LOGIN PAGE ══ -->
<div id="login-page" class="d-none">
  <div class="min-vh-100 d-flex align-items-center justify-content-center" style="background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 100%)">
    <div class="card shadow-lg" style="width:100%;max-width:420px;border-radius:1.25rem;overflow:hidden;">
      <div class="card-body p-5">
        <div class="text-center mb-4">
          <div class="display-4 mb-2">📋</div>
          <h2 class="fw-bold mb-1">Task Manager</h2>
          <p class="text-muted small">Sign in to your dashboard</p>
        </div>
        <div id="login-error" class="alert alert-danger d-none" role="alert"></div>
        <form id="login-form">
          <div class="mb-3">
            <label class="form-label fw-semibold" for="login-email">Email</label>
            <input id="login-email" type="email" class="form-control form-control-lg" placeholder="you@example.com" required/>
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold" for="login-password">Password</label>
            <input id="login-password" type="password" class="form-control form-control-lg" placeholder="••••••••" required/>
          </div>
          <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold" id="login-btn">
            <span id="login-spinner" class="spinner-border spinner-border-sm me-2 d-none"></span>
            Sign in
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════ DASHBOARD ═════ -->
<div id="dashboard-page" class="d-none">

  <!-- NAVBAR -->
  <nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="background:linear-gradient(135deg,#4f46e5,#7c3aed);z-index:1030;">
    <div class="container-fluid px-3">
      <span class="navbar-brand fw-bold fs-5">📋 Task Dashboard</span>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav-tabs">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="nav-tabs">
        <ul class="navbar-nav me-auto gap-1 mt-2 mt-lg-0" id="main-tabs">
          <li class="nav-item"><a class="nav-link tab-link active" href="#" data-tab="report">📊 Task Report</a></li>
          <li class="nav-item"><a class="nav-link tab-link" href="#" data-tab="ac-members">👥 AC Members</a></li>
          <li class="nav-item"><a class="nav-link tab-link" href="#" data-tab="ac-projects">📂 AC Projects</a></li>
          <li class="nav-item"><a class="nav-link tab-link" href="#" data-tab="ac-managers">🧑‍💼 AC Managers</a></li>
          <li class="nav-item"><a class="nav-link tab-link" href="#" data-tab="ac-clients">🏢 AC Clients1111</a></li>
        </ul>
        <div class="d-flex align-items-center gap-2 mt-2 mt-lg-0">
          <button id="sync-btn" class="btn btn-light btn-sm fw-semibold">
            <i class="bi bi-arrow-repeat me-1"></i>Sync
          </button>
          <span id="nav-user" class="text-white small opacity-75"></span>
          <button id="logout-btn" class="btn btn-outline-light btn-sm">Logout</button>
        </div>
      </div>
    </div>
  </nav>

  <!-- TAB PANELS -->
  <div class="container-fluid py-3 px-3">

    <!-- REPORT TAB -->
    <div class="tab-panel" id="panel-report">
      <?php include __DIR__ . '/../views/report.php'; ?>
    </div>

    <!-- AC MEMBERS TAB -->
    <div class="tab-panel d-none" id="panel-ac-members">
      <?php include __DIR__ . '/../views/ac_members.php'; ?>
    </div>

    <!-- AC PROJECTS TAB -->
    <div class="tab-panel d-none" id="panel-ac-projects">
      <?php include __DIR__ . '/../views/ac_projects.php'; ?>
    </div>

    <!-- AC MANAGERS TAB -->
    <div class="tab-panel d-none" id="panel-ac-managers">
      <?php include __DIR__ . '/../views/ac_managers.php'; ?>
    </div>

    <!-- AC CLIENTS TAB -->
    <div class="tab-panel d-none" id="panel-ac-clients">
      <?php include __DIR__ . '/../views/ac_clients.php'; ?>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════ TASK DETAIL MODAL ═══════════ -->
<div class="modal fade" id="task-detail-modal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold" id="task-modal-title"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="task-modal-body"></div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════ SYNC TOAST ════════════════ -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="sync-toast" class="toast align-items-center border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body fw-semibold" id="sync-toast-body">Syncing…</div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="/assets/js/api.js"></script>
<script src="/assets/js/ac_shared.js"></script>
<script src="/assets/js/report.js"></script>
<script src="/assets/js/ac_members.js"></script>
<script src="/assets/js/ac_projects.js"></script>
<script src="/assets/js/ac_managers.js"></script>
<script src="/assets/js/ac_clients.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
