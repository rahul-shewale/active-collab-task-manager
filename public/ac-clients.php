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
  <title>AC Clients</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"/>
  <link rel="stylesheet" href="/assets/css/app.css?v=20260513"/>
</head>
<body class="bg-light">
  <nav class="navbar navbar-dark" style="background:linear-gradient(135deg,#4f46e5,#7c3aed);">
    <div class="container-fluid">
      <span class="navbar-brand fw-bold">Task Dashboard - AC Clients</span>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-light btn-sm" href="/trello-report.php">Report</a>
        <a class="btn btn-outline-light btn-sm" href="/all-tasks.php">All Tasks</a>
        <a class="btn btn-outline-light btn-sm" href="/ac-members.php">AC Members</a>
        <a class="btn btn-outline-light btn-sm" href="/ac-projects.php">AC Projects</a>
        <a class="btn btn-outline-light btn-sm" href="/ac-managers.php">AC Managers</a>
        <a class="btn btn-light btn-sm" href="/ac-clients.php">AC Clients</a>
      </div>
    </div>
  </nav>

  <main class="container-fluid py-3">
    <?php include __DIR__ . '/../views/ac_clients.php'; ?>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
  <script src="/assets/js/api.js?v=20260513"></script>
  <script src="/assets/js/standalone_common.js?v=20260513"></script>
  <script src="/assets/js/ac_shared.js?v=20260513"></script>
  <script src="/assets/js/ac_clients.js?v=20260513"></script>
  <script>
    $(function () { AcClients.load(true); });
  </script>
</body>
</html>

