<!-- views/ac_members.php -->
<div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
  <h5 class="fw-bold mb-0">👥 ActiveCollab Members</h5>
  <input type="text" class="form-control form-control-sm w-auto" id="ac-members-search" placeholder="Search member…"/>

  <!-- Slider controls -->
  <div class="d-flex align-items-center gap-2 ms-auto">
    <button class="btn btn-sm btn-outline-secondary" id="ac-slider-prev" title="Previous">
      <i class="bi bi-chevron-left"></i>
    </button>
    <button class="btn btn-sm ac-slider-toggle-btn" id="ac-slider-toggle" title="Toggle auto-slide">
      <i class="bi bi-pause-fill"></i> <span id="ac-slider-toggle-label">Pause</span>
    </button>
    <button class="btn btn-sm btn-outline-secondary" id="ac-slider-next" title="Next">
      <i class="bi bi-chevron-right"></i>
    </button>
    <button class="btn btn-outline-secondary btn-sm" id="ac-members-reload" title="Reload">
      <i class="bi bi-arrow-repeat"></i>
    </button>
  </div>
</div>

<!-- Slider dots -->
<div id="ac-slider-dots" class="d-flex gap-1 mb-3 flex-wrap"></div>

<div id="ac-members-loading" class="text-center py-5 d-none">
  <div class="spinner-border text-primary mb-2"></div>
  <div class="text-muted">Loading members…</div>
</div>
<div id="ac-members-error" class="alert alert-warning d-none"></div>

<!-- Slider viewport -->
<div id="ac-members-slider-wrap" class="ac-slider-wrap">
  <div id="ac-members-track" class="ac-slider-track"></div>
</div>