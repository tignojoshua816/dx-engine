<?php
declare(strict_types=1);
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Portal Reviewer</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/dx-engine.css">
  <style>
    body { background:#f4f7fb; }
    .portal-navbar { background: linear-gradient(90deg,#0f172a,#1e293b); }
    pre { background:#0b1020; color:#d6e2ff; padding:10px; border-radius:6px; overflow:auto; }
  </style>
</head>
<body>
<nav class="navbar navbar-dark portal-navbar">
  <div class="container-fluid">
    <span class="navbar-brand mb-0 h1">Reviewer Portal</span>
    <div class="d-flex align-items-center gap-2 text-white">
      <span id="who" class="small"></span>
      <button id="logoutBtn" class="btn btn-sm btn-light">Logout</button>
    </div>
  </div>
</nav>
<main class="container py-4">
  <div id="alertBox" class="alert alert-info d-none"></div>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <h4 class="card-title">Queue (DX Worklist)</h4>
      <div id="worklist-root"></div>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <h4 class="card-title">Stage Action Console</h4>
      <div class="row g-2 align-items-center">
        <div class="col-md-4">
          <label class="form-label">Assignment ID</label>
          <input id="assignmentId" class="form-control" type="number" value="">
        </div>
        <div class="col-md-8 d-flex gap-2 mt-4">
          <button id="btnClaim" class="btn btn-success">Claim</button>
          <button id="btnForward" class="btn btn-primary">Forward</button>
          <button id="btnDecide" class="btn btn-dark">Registrar Decide</button>
        </div>
      </div>
      <pre id="out" class="mt-3 mb-0"></pre>
    </div>
  </div>
</main>

<script src="../js/dx-worklist.js"></script>
<script>
(async function(){
  const out = document.getElementById('out');
  const who = document.getElementById('who');
  const alertBox = document.getElementById('alertBox');
  let roles = [];

  function showAlert(type, msg){
    alertBox.className = 'alert alert-' + type;
    alertBox.textContent = msg;
    alertBox.classList.remove('d-none');
  }

  async function call(url, opts){
    const r = await fetch(url, opts || {});
    const j = await r.json();
    if(j.status !== 'success') throw new Error(j.message || 'Request failed');
    return j;
  }

  const sess = await fetch('../api/portal_session.php').then(r=>r.json());
  if(sess.status !== 'success'){ location.href='index.php'; return; }
  roles = sess.data.roles || [];
  who.textContent = sess.data.user.display_name + ' [' + roles.join(', ') + ']';

  if(roles.includes('applicant') && !roles.includes('super_admin')){
    location.href='applicant.php'; return;
  }
  if(roles.includes('super_admin')){
    location.href='admin.php'; return;
  }

  document.getElementById('logoutBtn').onclick = async function(){
    await fetch('../api/portal_auth.php?action=logout',{method:'POST'});
    location.href='index.php';
  };

  try {
    const wl = new DXWorklist('#worklist-root', { endpoint: '../api/worklist.php' });
    wl.load();
    showAlert('success', 'Worklist loaded successfully.');
  } catch (e) {
    out.textContent = 'Worklist init warning: ' + e.message;
    showAlert('warning', 'Worklist init warning: ' + e.message);
  }

  document.getElementById('btnClaim').onclick = async function(){
    try{
      const id = parseInt(document.getElementById('assignmentId').value,10);
      const j = await call('../api/portal_workflow.php?action=claim',{
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({assignment_id:id, lock_case:true})
      });
      out.textContent = JSON.stringify(j,null,2);
    }catch(e){ out.textContent = e.message; }
  };

  document.getElementById('btnForward').onclick = async function(){
    try{
      const id = parseInt(document.getElementById('assignmentId').value,10);
      const payload = { reviewer_note:'forwarded', ts:new Date().toISOString() };
      const j = await call('../api/portal_workflow.php?action=process',{
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({assignment_id:id, action_key:'forward', payload})
      });
      out.textContent = JSON.stringify(j,null,2);
    }catch(e){ out.textContent = e.message; }
  };

  document.getElementById('btnDecide').onclick = async function(){
    try{
      const id = parseInt(document.getElementById('assignmentId').value,10);
      const payload = { decision:'approved', decided_at:new Date().toISOString() };
      const j = await call('../api/portal_workflow.php?action=process',{
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({assignment_id:id, action_key:'decide', payload})
      });
      out.textContent = JSON.stringify(j,null,2);
    }catch(e){ out.textContent = e.message; }
  };
})();
</script>
</body>
</html>
