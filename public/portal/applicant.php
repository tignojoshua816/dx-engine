<?php
declare(strict_types=1);

if (!defined('DX_ROOT')) {
    define('DX_ROOT', dirname(__DIR__, 2));
}
$config = require DX_ROOT . '/config/app.php';
$apiEndpoint = htmlspecialchars($config['dx_api_endpoint'], ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Applicant Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/dx-engine.css">
  <style>
    body { background: #f4f7fb; }
    .portal-navbar { background: linear-gradient(90deg,#0f172a,#1e293b); }
    #dx-root { min-height: 180px; }
    pre { background:#0b1020; color:#d6e2ff; padding:10px; border-radius:6px; overflow:auto; }
    .case-status-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    }
    .case-status-card h5 {
      margin-bottom: 0.5rem;
    }
    .case-status-card .badge {
      font-size: 0.9rem;
      padding: 0.5rem 1rem;
    }
    .progress-steps {
      display: flex;
      justify-content: space-between;
      margin: 2rem 0;
      position: relative;
    }
    .progress-steps::before {
      content: '';
      position: absolute;
      top: 20px;
      left: 0;
      right: 0;
      height: 2px;
      background: #e0e0e0;
      z-index: 0;
    }
    .progress-step {
      flex: 1;
      text-align: center;
      position: relative;
      z-index: 1;
    }
    .progress-step-circle {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: #e0e0e0;
      color: #666;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      margin-bottom: 0.5rem;
    }
    .progress-step.completed .progress-step-circle {
      background: #667eea;
      color: white;
    }
    .progress-step.active .progress-step-circle {
      background: #764ba2;
      color: white;
      box-shadow: 0 0 0 4px rgba(118, 75, 162, 0.2);
    }
    .progress-step-label {
      font-size: 0.85rem;
      color: #666;
    }
    .progress-step.completed .progress-step-label,
    .progress-step.active .progress-step-label {
      color: #333;
      font-weight: 600;
    }
  </style>
</head>
<body>
<nav class="navbar navbar-dark portal-navbar">
  <div class="container-fluid">
    <span class="navbar-brand mb-0 h1">🎓 Applicant Portal</span>
    <div class="d-flex align-items-center gap-2 text-white">
      <span id="who" class="small"></span>
      <button id="logoutBtn" class="btn btn-sm btn-light">Logout</button>
    </div>
  </div>
</nav>

<main class="container py-4">
  <div id="alertBox" class="alert alert-info d-none"></div>

  <!-- Case Status Card -->
  <div id="caseStatusCard" class="case-status-card d-none">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <h5 class="mb-1">Your Application</h5>
        <p class="mb-2 opacity-75" id="caseRef">Case Reference: Loading...</p>
        <span class="badge bg-light text-dark" id="caseStage">Loading...</span>
      </div>
      <div class="text-end">
        <div class="small opacity-75">Status</div>
        <div class="h4 mb-0" id="caseStatus">Active</div>
      </div>
    </div>
  </div>

  <!-- Progress Steps -->
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <h5 class="card-title mb-4">Application Progress</h5>
      <div class="progress-steps">
        <div class="progress-step completed">
          <div class="progress-step-circle">✓</div>
          <div class="progress-step-label">Personal Info</div>
        </div>
        <div class="progress-step" id="step-academic">
          <div class="progress-step-circle">2</div>
          <div class="progress-step-label">Academic Background</div>
        </div>
        <div class="progress-step" id="step-program">
          <div class="progress-step-circle">3</div>
          <div class="progress-step-label">Program Selection</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Admission Form -->
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <h4 class="card-title">Complete Your Application</h4>
      <p class="text-muted">Continue filling out the remaining steps of your admission application.</p>
      <div id="dx-root"></div>
    </div>
  </div>

  <!-- My Cases -->
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <h4 class="card-title">My Applications</h4>
      <div id="worklist-root"></div>
    </div>
  </div>
</main>

<script src="../js/dx-interpreter.js"></script>
<script src="../js/dx-worklist.js"></script>
<script>
(async function(){
  const who = document.getElementById('who');
  const alertBox = document.getElementById('alertBox');
  const caseStatusCard = document.getElementById('caseStatusCard');
  const caseRef = document.getElementById('caseRef');
  const caseStage = document.getElementById('caseStage');
  const caseStatus = document.getElementById('caseStatus');

  function showAlert(type, msg){
    alertBox.className = 'alert alert-' + type;
    alertBox.textContent = msg;
    alertBox.classList.remove('d-none');
  }

  async function api(url, opts){
    const r = await fetch(url, opts || {});
    const j = await r.json();
    if(j.status !== 'success') throw new Error(j.message || 'Request failed');
    return j;
  }

  async function ensureRole(){
    const s = await fetch('../api/portal_session.php');
    const j = await s.json();
    if(j.status !== 'success'){ location.href='index.php'; return null; }
    who.textContent = j.data.user.display_name + ' [' + j.data.roles.join(', ') + ']';
    if(!j.data.roles.includes('applicant') && !j.data.roles.includes('super_admin')){
      location.href = 'reviewer.php';
      return null;
    }
    return j.data.user;
  }

  const user = await ensureRole();
  if(!user) return;

  document.getElementById('logoutBtn').onclick = async function(){
    await fetch('../api/portal_auth.php?action=logout',{method:'POST'});
    location.href = 'index.php';
  };

  // Load user's active case
  try {
    const queues = await api('../api/portal_workflow.php?action=overview');
    const myCases = queues.data.queues.find(q => q.queue_name === 'My Cases');
    
    if(myCases && myCases.assignments && myCases.assignments.length > 0) {
      const activeCase = myCases.assignments[0];
      const caseId = activeCase.case_instance_id;
      
      // Load case details
      const caseData = await api('../api/portal_workflow.php?action=case_status&case_id=' + caseId);
      
      caseStatusCard.classList.remove('d-none');
      caseRef.textContent = 'Case Reference: ' + caseData.data.case.case_ref;
      caseStage.textContent = caseData.data.case.current_stage_key.replace(/_/g, ' ').toUpperCase();
      caseStatus.textContent = caseData.data.case.status.toUpperCase();

      // Update progress steps based on payload
      const payload = caseData.data.case.payload || {};
      const currentStep = payload.current_step || 'academic_background';
      
      if(currentStep === 'program_selection' || payload.high_school_name) {
        document.getElementById('step-academic').classList.add('completed');
        document.getElementById('step-program').classList.add('active');
      } else {
        document.getElementById('step-academic').classList.add('active');
      }

      // Initialize DX Interpreter to continue from current step
      const dxInterpreter = new DXInterpreter('#dx-root', {
        dx_id: 'admission_case',
        endpoint: '<?= $apiEndpoint ?>',
        params: { case_id: caseId },
        async onStepComplete(stepData) {
          console.log('Step completed:', stepData);
          
          // Update case payload with new step data
          try {
            const updateRes = await fetch('../api/portal_workflow.php?action=update_payload', {
              method: 'POST',
              headers: {'Content-Type': 'application/json'},
              body: JSON.stringify({
                case_id: caseId,
                payload: Object.assign({}, payload, stepData.payload, {
                  current_step: stepData.next_step || 'completed'
                })
              })
            });
            
            if(stepData.next_step) {
              showAlert('success', 'Progress saved! Continue to the next step.');
              location.reload();
            }
          } catch(e) {
            console.error('Failed to update payload:', e);
          }
        },
        onComplete(data) {
          console.log('Application complete:', data);
          showAlert('success', 'Application submitted successfully! You will be notified of the decision.');
          
          // Mark case as completed
          fetch('../api/portal_workflow.php?action=complete_case', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ case_id: caseId })
          }).then(() => {
            setTimeout(() => location.reload(), 2000);
          });
        }
      });
      
      dxInterpreter.load();
    } else {
      showAlert('info', 'No active application found. Please start a new admission from the home page.');
    }
  } catch (e) {
    console.error('Error loading case:', e);
    showAlert('warning', 'Could not load your application. ' + e.message);
  }

  // Load worklist
  try {
    const wl = new DXWorklist('#worklist-root', { endpoint: '../api/worklist.php' });
    wl.load();
  } catch (e) {
    console.error('Worklist error:', e);
  }
})();
</script>
</body>
</html>
