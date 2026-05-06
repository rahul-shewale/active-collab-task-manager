<!-- views/report.php - rendered inside #panel-report -->
<div class="row g-3 mb-3" id="stats-row">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm rounded-3 stat-card" style="border-left:4px solid #6366f1!important;">
      <div class="card-body py-3">
        <div class="text-muted small mb-1">Open</div>
        <div class="fw-bold fs-4" id="stat-open">—</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm rounded-3 stat-card" style="border-left:4px solid #f59e0b!important;">
      <div class="card-body py-3">
        <div class="text-muted small mb-1">In Progress</div>
        <div class="fw-bold fs-4" id="stat-in_progress">—</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm rounded-3 stat-card" style="border-left:4px solid #10b981!important;">
      <div class="card-body py-3">
        <div class="text-muted small mb-1">Done</div>
        <div class="fw-bold fs-4" id="stat-done">—</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm rounded-3 stat-card" style="border-left:4px solid #ec4899!important;">
      <div class="card-body py-3">
        <div class="text-muted small mb-1">Overdue</div>
        <div class="fw-bold fs-4" id="stat-overdue">—</div>
      </div>
    </div>
  </div>
</div>

<!-- Due-date sections -->
<div id="report-loading" class="text-center py-5 text-muted">
  <div class="spinner-border text-primary mb-2"></div>
  <div>Loading tasks…</div>
</div>

<div id="report-content" class="d-none">

  <!-- OVERDUE -->
  <div class="mb-4" id="section-pending">
    <div class="d-flex align-items-center gap-2 mb-2">
      <span class="badge bg-danger fs-6">⚠ Overdue</span>
      <span class="text-muted small" id="count-pending"></span>
    </div>
    <div id="list-pending"></div>
  </div>

  <!-- TODAY -->
  <div class="mb-4" id="section-today">
    <div class="d-flex align-items-center gap-2 mb-2">
      <span class="badge bg-warning text-dark fs-6">🗓 Due Today</span>
      <span class="text-muted small" id="count-today"></span>
    </div>
    <div id="list-today"></div>
  </div>

  <!-- UPCOMING -->
  <div class="mb-4" id="section-upcoming">
    <div class="d-flex align-items-center gap-2 mb-2">
      <span class="badge bg-info text-dark fs-6">📅 Upcoming</span>
      <span class="text-muted small" id="count-upcoming"></span>
    </div>
    <div id="list-upcoming"></div>
  </div>

  <!-- NO DUE DATE -->
  <div class="mb-4" id="section-open">
    <div class="d-flex align-items-center gap-2 mb-2">
      <span class="badge bg-secondary fs-6">📋 No Due Date</span>
      <span class="text-muted small" id="count-open"></span>
      <button class="btn btn-link btn-sm text-muted p-0 ms-1" id="toggle-open">Show all</button>
    </div>
    <div id="list-open"></div>
  </div>

  <!-- HUBSTAFF -->
  <div class="mb-4">
    <div class="d-flex align-items-center gap-2 mb-3">
      <span class="badge bg-purple fs-6" style="background:#7c3aed;">⏱ Hubstaff Time Tracking</span>
      <div class="btn-group btn-group-sm" id="hs-timeframe-group">
        <button class="btn btn-outline-secondary active" data-tf="day">Today</button>
        <button class="btn btn-outline-secondary" data-tf="week">Week</button>
        <button class="btn btn-outline-secondary" data-tf="month">Month</button>
      </div>
    </div>
    <div id="hubstaff-loading" class="text-center py-3 text-muted">
      <div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading tracking data…
    </div>
    <div id="hubstaff-cards" class="row g-3 d-none"></div>
  </div>

</div>
