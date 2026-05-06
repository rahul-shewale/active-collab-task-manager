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

  <!-- Trello Cards header -->
  <div class="report-topbar mb-3">
    <div class="report-top-left">
      <span class="report-top-icon">🗂</span>
      <span class="report-top-title">Trello Cards</span>
      <span class="report-top-pill" id="trello-total">—</span>
    </div>
    <div class="report-top-right">
      <span class="report-chip is-neutral" id="chip-no-deadline">— without deadline</span>
      <div class="report-chip-row" id="report-board-chips"></div>
    </div>
  </div>

  <!-- Main 3 panels -->
  <div class="row g-3 mb-3">
    <div class="col-12 col-lg-4">
      <div class="report-panel is-danger" id="section-pending">
        <div class="report-panel-head">
          <div class="report-panel-title">
            <span class="report-panel-icon">⚠</span>
            <span>Overdue</span>
          </div>
          <span class="report-panel-count" id="count-pending"></span>
        </div>
        <div class="report-panel-body" id="list-pending"></div>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="report-panel is-warning" id="section-today">
        <div class="report-panel-head">
          <div class="report-panel-title">
            <span class="report-panel-icon">📅</span>
            <span>Today</span>
          </div>
          <span class="report-panel-count" id="count-today"></span>
        </div>
        <div class="report-panel-body" id="list-today"></div>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="report-panel is-info" id="section-upcoming">
        <div class="report-panel-head">
          <div class="report-panel-title">
            <span class="report-panel-icon">🧾</span>
            <span>Upcoming</span>
          </div>
          <span class="report-panel-count" id="count-upcoming"></span>
        </div>
        <div class="report-panel-body" id="list-upcoming"></div>
      </div>
    </div>
  </div>

  <!-- No deadline (open) -->
  <div class="report-panel is-neutral mb-3" id="section-open">
    <div class="report-panel-head">
      <div class="report-panel-title">
        <span class="report-panel-icon">🗒</span>
        <span>No deadline</span>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="report-panel-count" id="count-open"></span>
        <button class="btn btn-link btn-sm text-muted p-0" id="toggle-open">Show all</button>
      </div>
    </div>
    <div class="report-panel-body" id="list-open"></div>
  </div>

  <!-- Hubstaff -->
  <div class="report-panel is-purple mb-4">
    <div class="report-panel-head">
      <div class="report-panel-title">
        <span class="report-panel-icon">⏱</span>
        <span>Hubstaff Activity</span>
        <span class="report-top-pill ms-2">Time tracking</span>
      </div>
      <div class="btn-group btn-group-sm" id="hs-timeframe-group">
        <button class="btn btn-outline-secondary active" data-tf="day">Day</button>
        <button class="btn btn-outline-secondary" data-tf="week">Week</button>
        <button class="btn btn-outline-secondary" data-tf="month">Month</button>
      </div>
    </div>
    <div id="hubstaff-loading" class="text-center py-3 text-muted">
      <div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading tracking data…
    </div>
    <div id="hubstaff-cards" class="row g-3 d-none px-2 pb-2"></div>
  </div>

</div>
