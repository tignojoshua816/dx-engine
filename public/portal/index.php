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
  <title>Educational Admission Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/dx-engine.css">
  <style>
    body { 
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
      min-height:100vh; 
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }
    .hero-section {
      padding: 4rem 0;
      color: white;
      text-align: center;
    }
    .hero-section h1 {
      font-size: 3rem;
      font-weight: 700;
      margin-bottom: 1rem;
      text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .hero-section p {
      font-size: 1.25rem;
      opacity: 0.95;
      margin-bottom: 2rem;
    }
    .login-card { 
      max-width: 480px; 
      border: none;
      box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    }
    .feature-card {
      background: white;
      border-radius: 12px;
      padding: 2rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      transition: transform 0.2s;
    }
    .feature-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }
    .feature-icon {
      width: 48px;
      height: 48px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 1.5rem;
      margin-bottom: 1rem;
    }
    .btn-start-admission {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border: none;
      padding: 1rem 2.5rem;
      font-size: 1.1rem;
      font-weight: 600;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
      transition: all 0.3s;
    }
    .btn-start-admission:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
    }
    .credentials-box {
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      border: 3px solid #667eea;
      border-radius: 12px;
      padding: 2rem;
      margin: 1.5rem 0;
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
    }
    .credentials-box h5 {
      color: #667eea;
      margin-bottom: 1.5rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .credential-item {
      background: white;
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 0.75rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .credential-label {
      font-weight: 600;
      color: #495057;
      font-size: 0.9rem;
    }
    .credential-value {
      font-family: 'Courier New', monospace;
      color: #667eea;
      font-weight: 700;
      font-size: 1.1rem;
      background: #f8f9fa;
      padding: 0.25rem 0.75rem;
      border-radius: 4px;
    }
    /* Modal customization for DX Interpreter */
    .modal-dialog-dx {
      max-width: 800px;
    }
    .modal-content-dx {
      border: none;
      border-radius: 16px;
      overflow: hidden;
    }
    .modal-body-dx {
      padding: 0;
      background: #f8fafc;
    }
    /* Ensure dx-root styling applies in modal */
    .modal-body-dx .dx-root {
      padding: 1.5rem;
    }
    /* Remove card shadow in modal context */
    .modal-body-dx .dx-card {
      box-shadow: none;
      border: none;
    }
  </style>
</head>
<body>

<!-- Hero Section -->
<div class="hero-section">
  <div class="container">
    <h1>🎓 Educational Admission Portal</h1>
    <p>Start your journey with us today. Simple, fast, and secure admission process.</p>
    <button id="startAdmissionBtn" class="btn btn-light btn-start-admission">
      Start Your Admission Now
    </button>
  </div>
</div>

<!-- Features Section -->
<div class="container pb-5">
  <div class="row">
    <div class="col-md-4">
      <div class="feature-card">
        <div class="feature-icon">📝</div>
        <h5>Easy Application</h5>
        <p class="text-muted mb-0">Complete your admission form in minutes with our intuitive step-by-step process.</p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="feature-card">
        <div class="feature-icon">🔒</div>
        <h5>Secure Portal</h5>
        <p class="text-muted mb-0">Your data is protected with enterprise-grade security and encryption.</p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="feature-card">
        <div class="feature-icon">📊</div>
        <h5>Track Progress</h5>
        <p class="text-muted mb-0">Monitor your application status in real-time through your personal dashboard.</p>
      </div>
    </div>
  </div>

  <!-- Login Card -->
  <div class="card shadow login-card mx-auto mt-4">
    <div class="card-body p-4">
      <h4 class="mb-3">Already Started? Login Here</h4>
      <p class="text-muted small mb-3">Access your dashboard to continue your application or check status.</p>
      
      <div class="mb-3">
        <label for="loginUsername" class="form-label">Username</label>
        <input id="loginUsername" class="form-control" placeholder="Enter your username">
      </div>
      <div class="mb-3">
        <label for="loginPassword" class="form-label">Password</label>
        <input id="loginPassword" type="password" class="form-control" placeholder="Enter your password">
      </div>
      <button id="loginBtn" class="btn btn-primary w-100">Login to Dashboard</button>
      <div id="loginErr" class="text-danger mt-2 small"></div>
    </div>
  </div>
</div>

<!-- Hidden div for DX Interpreter modal rendering -->
<div id="dx-modal-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/dx-interpreter.js"></script>
<script>
(function(){
  const startBtn = document.getElementById('startAdmissionBtn');
  const loginBtn = document.getElementById('loginBtn');
  const loginErr = document.getElementById('loginErr');
  const loginUsername = document.getElementById('loginUsername');
  const loginPassword = document.getElementById('loginPassword');
  
  let dxInterpreter = null;
  let currentModal = null;

  function routeByRole(role){
    if(role === 'super_admin') return 'admin.php';
    if(role === 'applicant') return 'applicant.php';
    return 'reviewer.php';
  }

  function showCredentialsModal(credentials, caseRef) {
    const modalHTML = `
      <div class="modal fade" id="credentialsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-success text-white">
              <h5 class="modal-title">✅ Application Started Successfully!</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="credentials-box">
                <h5>
                  <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
                  </svg>
                  Your Login Credentials
                </h5>
                <p class="mb-3">Please save these credentials to login and continue your admission:</p>
                
                <div class="credential-item">
                  <span class="credential-label">Username:</span>
                  <span class="credential-value">${credentials.username}</span>
                </div>
                <div class="credential-item">
                  <span class="credential-label">Password:</span>
                  <span class="credential-value">${credentials.password}</span>
                </div>
                <div class="credential-item">
                  <span class="credential-label">Case Reference:</span>
                  <span class="credential-value">${caseRef}</span>
                </div>

                <div class="alert alert-warning mt-3 mb-0">
                  <strong>⚠️ Important:</strong> Please save these credentials. You'll need them to login and complete the remaining steps of your admission.
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-primary w-100" onclick="location.reload()">Go to Login</button>
            </div>
          </div>
        </div>
      </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    const credModal = new bootstrap.Modal(document.getElementById('credentialsModal'));
    credModal.show();
    
    document.getElementById('credentialsModal').addEventListener('hidden.bs.modal', function() {
      this.remove();
    });
  }

  // Start Admission Button - Uses DX Interpreter Modal Mode
  startBtn.addEventListener('click', function(){
    // Initialize DX Interpreter in MODAL mode
    dxInterpreter = new DXInterpreter('#dx-modal-container', {
      dx_id: 'admission_case',
      endpoint: '<?= $apiEndpoint ?>',
      renderMode: 'modal',
      modalOptions: {
        size: 'lg',
        backdrop: 'static',
        keyboard: false,
        closeOnComplete: false
      },
      onStepComplete: async function(stepData) {
        console.log('Step completed:', stepData);
        
        // After first step (personal_info), create the case and credentials
        if(stepData.step === 'personal_info') {
          try {
            const res = await fetch('../api/admission_public.php?action=start_admission', {
              method: 'POST',
              headers: {'Content-Type': 'application/json'},
              body: JSON.stringify(stepData.payload)
            });
            const json = await res.json();
            
            if(json.status === 'success' && json.data.credentials) {
              // Close the DX modal
              if(dxInterpreter) {
                dxInterpreter.closeModal();
              }
              
              // Show credentials modal
              setTimeout(() => {
                showCredentialsModal(json.data.credentials, json.data.case_ref || 'N/A');
              }, 300);
            } else {
              alert('Error: ' + (json.message || 'Failed to create admission case'));
            }
          } catch(e) {
            alert('Error: ' + e.message);
          }
        }
      },
      onComplete: function(data) {
        console.log('All steps complete:', data);
      },
      onModalClose: function(state) {
        console.log('Modal closed with state:', state);
        dxInterpreter = null;
      }
    });
    
    dxInterpreter.load();
  });

  // Login Handler
  loginBtn.addEventListener('click', async function(){
    loginErr.textContent = '';
    const username = loginUsername.value.trim();
    const password = loginPassword.value.trim();
    
    if(!username || !password){ 
      loginErr.textContent = 'Username and password are required.'; 
      return; 
    }
    
    try{
      const res = await fetch('../api/portal_auth.php?action=login', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({username, password})
      });
      const json = await res.json();
      
      if(json.status !== 'success'){ 
        throw new Error(json.message || 'Login failed'); 
      }
      
      const role = json.data.default_route || (json.data.roles && json.data.roles[0]) || 'applicant';
      location.href = routeByRole(role);
    }catch(e){
      loginErr.textContent = e.message;
    }
  });

  // Enter key support
  loginPassword.addEventListener('keypress', function(e){
    if(e.key === 'Enter') loginBtn.click();
  });
})();
</script>
</body>
</html>
