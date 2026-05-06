<!-- views/ac_members.php -->
<div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
  <h5 class="fw-bold mb-0">👥 ActiveCollab Members</h5>
  <input type="text" class="form-control form-control-sm w-auto" id="ac-members-search" placeholder="Search member…"/>
  <button class="btn btn-outline-secondary btn-sm" id="ac-members-reload"><i class="bi bi-arrow-repeat"></i> Reload</button>
</div>
<div id="ac-members-loading" class="text-center py-5">
  <div class="spinner-border text-primary mb-2"></div>
  <div class="text-muted">Loading members…</div>
</div>
<div id="ac-members-error" class="alert alert-warning d-none"></div>
<div id="ac-members-list"></div>
