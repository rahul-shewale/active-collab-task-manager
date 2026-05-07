<!-- views/ac_managers.php -->
<div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
  <h5 class="fw-bold mb-0">🧑‍💼 AC Project Managers</h5>
  <input type="text" class="form-control form-control-sm w-auto" id="ac-managers-search" placeholder="Search manager…"/>
  <div class="d-flex align-items-center gap-2 ms-auto">
    <button class="btn btn-sm btn-outline-secondary" id="ac-managers-prev" title="Previous">
      <i class="bi bi-chevron-left"></i>
    </button>
    <button class="btn btn-sm ac-slider-toggle-btn" id="ac-managers-toggle" title="Toggle auto-slide">
      <i class="bi bi-pause-fill"></i> <span>Pause</span>
    </button>
    <button class="btn btn-sm btn-outline-secondary" id="ac-managers-next" title="Next">
      <i class="bi bi-chevron-right"></i>
    </button>
    <button class="btn btn-outline-secondary btn-sm" id="ac-managers-reload">
      <i class="bi bi-arrow-repeat"></i>
    </button>
  </div>
</div>
<div id="ac-managers-dots" class="d-flex gap-1 mb-3 flex-wrap"></div>
<div id="ac-managers-loading" class="text-center py-5">
  <div class="spinner-border text-primary mb-2"></div>
  <div class="text-muted">Loading managers…</div>
</div>
<div id="ac-managers-error" class="alert alert-warning d-none"></div>
<div id="ac-managers-slider-wrap" class="acx-slider-wrap">
  <div id="ac-managers-track" class="acx-slider-track"></div>
</div>
