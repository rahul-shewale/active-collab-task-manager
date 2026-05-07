<!-- views/pending.php -->
<div class="report-topbar mb-3">
  <div class="report-top-left">
    <span class="report-top-icon">🧭</span>
    <span class="report-top-title">All Tasks</span>
    <span class="report-top-pill" id="pending-total">—</span>
  </div>
  <div class="report-top-right">
    <span class="report-chip is-neutral" id="pending-trello-total">Trello: —</span>
    <span class="report-chip is-neutral" id="pending-ac-total">ActiveCollab: —</span>
  </div>
</div>

<div class="row g-3">
  <!-- ActiveCollab slider -->
  <div class="col-12 col-xl-6">
    <div class="report-panel is-purple">
      <div class="report-panel-head">
        <div class="report-panel-title">
          <span class="report-panel-icon">👥</span>
          <span>ActiveCollab (Pending)</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-outline-secondary btn-sm" id="pending-ac-reload" title="Reload">
            <i class="bi bi-arrow-repeat"></i>
          </button>
          <button class="btn btn-sm btn-outline-secondary" id="pending-ac-prev" title="Previous">
            <i class="bi bi-chevron-left"></i>
          </button>
          <button class="btn btn-sm ac-slider-toggle-btn" id="pending-ac-toggle" title="Toggle auto-slide">
            <i class="bi bi-pause-fill"></i> <span>Pause</span>
          </button>
          <button class="btn btn-sm btn-outline-secondary" id="pending-ac-next" title="Next">
            <i class="bi bi-chevron-right"></i>
          </button>
        </div>
      </div>

      <div class="p-2">
        <div id="pending-ac-dots" class="d-flex gap-1 mb-2 flex-wrap"></div>
        <div id="pending-ac-loading" class="text-center py-3 text-muted d-none">
          <div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading…
        </div>
        <div id="pending-ac-error" class="alert alert-warning d-none mb-2"></div>
        <div id="pending-ac-wrap" class="ac-slider-wrap">
          <div id="pending-ac-track" class="ac-slider-track"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Trello slider -->
  <div class="col-12 col-xl-6">
    <div class="report-panel is-info">
      <div class="report-panel-head">
        <div class="report-panel-title">
          <span class="report-panel-icon">🗂</span>
          <span>Trello (Pending)</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-outline-secondary btn-sm" id="pending-trello-reload" title="Reload">
            <i class="bi bi-arrow-repeat"></i>
          </button>
          <button class="btn btn-sm btn-outline-secondary" id="pending-trello-prev" title="Previous">
            <i class="bi bi-chevron-left"></i>
          </button>
          <button class="btn btn-sm ac-slider-toggle-btn" id="pending-trello-toggle" title="Toggle auto-slide">
            <i class="bi bi-pause-fill"></i> <span>Pause</span>
          </button>
          <button class="btn btn-sm btn-outline-secondary" id="pending-trello-next" title="Next">
            <i class="bi bi-chevron-right"></i>
          </button>
        </div>
      </div>

      <div class="p-2">
        <div id="pending-trello-dots" class="d-flex gap-1 mb-2 flex-wrap"></div>
        <div id="pending-trello-loading" class="text-center py-3 text-muted d-none">
          <div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading…
        </div>
        <div id="pending-trello-error" class="alert alert-warning d-none mb-2"></div>
        <div id="pending-trello-wrap" class="ac-slider-wrap">
          <div id="pending-trello-track" class="ac-slider-track"></div>
        </div>
      </div>
    </div>
  </div>
</div>

