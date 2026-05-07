<!-- views/ac_clients.php -->
<div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
  <h5 class="fw-bold mb-0">🏢 AC Clients</h5>
  <input type="text" class="form-control form-control-sm w-auto" id="ac-clients-search" placeholder="Search client…"/>
  <div class="d-flex align-items-center gap-2 ms-auto">
    <button class="btn btn-outline-secondary btn-sm" id="ac-clients-reload">
      <i class="bi bi-arrow-repeat"></i>
    </button>
  </div>
</div>
<div id="ac-clients-loading" class="text-center py-5">
  <div class="spinner-border text-primary mb-2"></div>
  <div class="text-muted">Loading clients…</div>
</div>
<div id="ac-clients-error" class="alert alert-warning d-none"></div>
<div id="ac-clients-list"></div>
