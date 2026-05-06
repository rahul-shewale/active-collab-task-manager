<!-- views/ac_managers.php -->
<div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
  <h5 class="fw-bold mb-0">🧑‍💼 AC Project Managers</h5>
  <input type="text" class="form-control form-control-sm w-auto" id="ac-managers-search" placeholder="Search manager…"/>
  <button class="btn btn-outline-secondary btn-sm" id="ac-managers-reload"><i class="bi bi-arrow-repeat"></i> Reload</button>
</div>
<div id="ac-managers-loading" class="text-center py-5">
  <div class="spinner-border text-primary mb-2"></div>
  <div class="text-muted">Loading managers…</div>
</div>
<div id="ac-managers-error" class="alert alert-warning d-none"></div>
<div id="ac-managers-list"></div>
