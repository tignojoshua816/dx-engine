<?php
declare(strict_types=1);
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Portal Super Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/dx-engine.css">
  <style>
    body { background:#f4f7fb; }
    .portal-navbar { background: linear-gradient(90deg,#0b1220,#1f2937); }
  </style>
</head>
<body>
<nav class="navbar navbar-dark portal-navbar">
  <div class="container-fluid">
    <span class="navbar-brand mb-0 h1">Super Admin Portal</span>
    <div class="d-flex align-items-center gap-2 text-white">
      <span id="who" class="small"></span>
      <button id="logoutBtn" class="btn btn-sm btn-light">Logout</button>
    </div>
  </div>
</nav>
<main class="container py-4">
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <h4 class="card-title">RBAC Administration (dx-rbac-admin.js)</h4>
      <div id="rbac-root"></div>
    </div>
  </div>
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <h4 class="card-title">Global Worklist (dx-worklist.js)</h4>
      <div id="worklist-root"></div>
    </div>
  </div>
</main>

<script src="../js/dx-rbac-admin.js"></script>
<script src="../js/dx-worklist.js"></script>
<script>
(async function(){
  const sess = await fetch('../api/portal_session.php').then(r=>r.json());
  if(sess.status !== 'success'){ location.href='index.php'; return; }

  const roles = sess.data.roles || [];
  document.getElementById('who').textContent = sess.data.user.display_name + ' [' + roles.join(', ') + ']';

  if(!roles.includes('super_admin')){
    if(roles.includes('applicant')) location.href='applicant.php';
    else location.href='reviewer.php';
    return;
  }

  document.getElementById('logoutBtn').onclick = async function(){
    await fetch('../api/portal_auth.php?action=logout',{method:'POST'});
    location.href='index.php';
  };

  try {
    const admin = new DXRbacAdmin('#rbac-root', { endpoint: '../api/rbac_admin.php' });
    admin.render();
  } catch (e) {
    document.getElementById('rbac-root').innerHTML = '<pre>'+e.message+'</pre>';
  }

  try {
    const wl = new DXWorklist('#worklist-root', { endpoint: '../api/worklist.php' });
    wl.load();
  } catch (e) {
    document.getElementById('worklist-root').innerHTML = '<pre>'+e.message+'</pre>';
  }
})();
</script>
</body>
</html>
