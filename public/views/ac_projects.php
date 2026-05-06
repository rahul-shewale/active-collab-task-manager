<!-- views/ac_projects.php -->
<div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
  <h5 class="fw-bold mb-0">📂 ActiveCollab Projects</h5>
  <button class="btn btn-outline-secondary btn-sm" id="ac-projects-reload"><i class="bi bi-arrow-repeat"></i> Reload</button>
</div>
<div id="ac-projects-loading" class="text-center py-5">
  <div class="spinner-border text-primary mb-2"></div>
  <div class="text-muted">Loading projects…</div>
</div>
<div id="ac-projects-error" class="alert alert-warning d-none"></div>
<div id="ac-projects-list"></div>
