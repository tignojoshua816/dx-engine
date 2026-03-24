# 🎓 DX-Engine Admission Portal - Quick Start Guide

## What You Have Now

A **fully functional educational admission portal** with:

✅ **Public Landing Page** - Students can start applications without login  
✅ **Multi-Step Form** - 3-step admission process (Personal Info → Academic → Program)  
✅ **Auto-Generated Credentials** - System creates username/password after first step  
✅ **Secure Login** - Password-based authentication  
✅ **Progress Tracking** - Visual indicators and case management  
✅ **Applicant Dashboard** - Students can continue and track their applications  

---

## 🚀 How to Use

### Step 1: Start XAMPP

1. Open **XAMPP Control Panel**
2. Start **Apache** (web server)
3. Start **MySQL** (database)

### Step 2: Access the Portal

Open your browser and visit:
```
http://localhost/dx-engine/public/portal/
```

You'll see a beautiful landing page with:
- Hero section with gradient background
- "Start Your Admission Now" button
- Feature cards explaining the process
- Login form for returning users

### Step 3: Start an Application (As a Student)

1. **Click "Start Your Admission Now"**
   - A modal opens with the first form step

2. **Fill Personal Information**
   - First Name: `John`
   - Last Name: `Smith`
   - Email: `john.smith@example.com`
   - Phone: `+1 (555) 123-4567`
   - Date of Birth: `2005-01-15`
   - Gender: `Male`
   - Address: `123 Main St, City, State, 12345`

3. **Click "Next: Academic Background"**
   - System creates your case
   - Generates credentials automatically
   - Displays them on screen:
     ```
     Username: jsmith1a2b
     Password: Xy9kL2mP4q
     Case Reference: EDU-ADM-20260324-A1B2C3
     ```

4. **Save Your Credentials** (Important!)
   - Write them down or take a screenshot
   - You'll need them to login

5. **Click "Go to Login"**

### Step 4: Login and Continue

1. **Enter Your Credentials**
   - Username: `jsmith1a2b`
   - Password: `Xy9kL2mP4q`

2. **Click "Login to Dashboard"**
   - You're redirected to the Applicant Portal
   - See your application status
   - Progress indicators show Step 1 completed

3. **Complete Step 2: Academic Background**
   - High School Name: `Springfield High School`
   - Graduation Year: `2023`
   - GPA: `3.8`
   - Test Scores: `SAT: 1350`
   - Achievements: `Honor Roll, Science Fair Winner`
   - Click "Next: Program Selection"

4. **Complete Step 3: Program Selection**
   - Desired Program: `Computer Science`
   - Enrollment Term: `Fall 2026`
   - Statement of Purpose: (Write 50-1000 characters)
   - Financial Aid: `Yes, I plan to apply for financial aid`
   - Click "Submit Application"

5. **Application Submitted!**
   - Success message appears
   - Case status updates to "Completed"
   - You'll be notified of the decision

---

## 📁 File Structure

```
dx-engine/
├── public/
│   ├── portal/
│   │   ├── index.php           ← Landing page (public)
│   │   ├── applicant.php       ← Student dashboard (login required)
│   │   ├── reviewer.php        ← Admissions officer dashboard
│   │   └── admin.php           ← Admin dashboard
│   ├── api/
│   │   ├── admission_public.php ← Creates cases + credentials
│   │   ├── portal_auth.php     ← Login/logout
│   │   └── portal_workflow.php ← Case management
│   └── js/
│       └── dx-interpreter.js   ← Renders forms dynamically
├── src/
│   └── App/
│       └── DX/
│           └── AdmissionCaseDX.php ← Defines 3-step form
├── config/
│   ├── app.php                 ← API endpoint config
│   └── database.php            ← Database connection
└── docs/
    └── ADMISSION_PORTAL_GUIDE.md ← Detailed documentation
```

---

## 🔧 How It Works

### 1. Public Landing Page (`index.php`)

- **No login required**
- Shows "Start Admission" button
- Opens modal with DX Interpreter
- Loads first step from `AdmissionCaseDX.php`

### 2. First Step Submission

When student submits personal info:

```javascript
// Frontend (index.php)
onStepComplete(stepData) {
  // POST to admission_public.php
  fetch('../api/admission_public.php?action=start_admission', {
    method: 'POST',
    body: JSON.stringify(stepData.payload)
  })
}
```

```php
// Backend (admission_public.php)
1. Validate email (check if already exists)
2. Generate username: first_initial + last_name + random_suffix
3. Generate password: 10 random characters
4. Create user account with hashed password
5. Assign to 'portal_applicant' group
6. Create case instance with payload
7. Return credentials to frontend
```

### 3. Login System

```php
// portal_auth.php
1. Receive username + password
2. Find user in database
3. Verify password with password_verify()
4. Create session
5. Return user data + roles
```

### 4. Applicant Dashboard (`applicant.php`)

```javascript
1. Check session (redirect if not logged in)
2. Load user's active cases
3. Display case status card
4. Initialize DX Interpreter with case_id
5. Continue from current step
6. Save progress after each step
7. Mark case as completed when done
```

---

## 🎨 Customization

### Change Form Steps

Edit `src/App/DX/AdmissionCaseDX.php`:

```php
protected function getFlow(array $context): array
{
    return [
        'steps' => [
            $this->buildStepPersonalInfo($initialState),
            $this->buildStepAcademicBackground($initialState),
            $this->buildStepProgramSelection($initialState),
            // Add more steps here:
            // $this->buildStepDocumentUpload($initialState),
        ],
    ];
}
```

### Change Credential Generation

Edit `public/api/admission_public.php`:

```php
function generateUsername(string $firstName, string $lastName): string
{
    // Custom format: firstname.lastname
    return strtolower($firstName . '.' . $lastName);
}

function generatePassword(int $length = 12): string
{
    // Use stronger password with symbols
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    // ... generate password
}
```

### Change Colors/Design

Edit `public/portal/index.php` CSS:

```css
body { 
  /* Change gradient colors */
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
}

.btn-start-admission {
  /* Change button colors */
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
```

---

## 🧪 Testing

Run the test script:

```bash
D:\xampp\php\php.exe D:\xampp\htdocs\dx-engine\test_admission.php
```

Expected output:
```
✅ PHP Version: 8.2.12
✅ Landing page
✅ Applicant dashboard
✅ Public admission API
✅ Connected to database
✅ All tables exist
```

---

## 🐛 Troubleshooting

### Issue: "An account with this email already exists"

**Cause**: Email is already registered  
**Solution**: Use a different email or login with existing credentials

### Issue: "Invalid login"

**Cause**: Wrong username or password  
**Solution**: Check credentials (case-sensitive)

### Issue: Form not loading

**Cause**: JavaScript error or API endpoint misconfigured  
**Solution**:
1. Open browser console (F12)
2. Check for errors
3. Verify `config/app.php` has correct `dx_api_endpoint`

### Issue: Database connection failed

**Cause**: MySQL not running or wrong credentials  
**Solution**:
1. Start MySQL in XAMPP
2. Check `config/database.php` credentials
3. Verify database name is `dx_engine`

---

## 📊 Database Tables Used

| Table | Purpose |
|-------|---------|
| `dx_case_instances` | Stores admission cases |
| `dx_users` | User accounts with credentials |
| `dx_groups` | User roles (applicant, reviewer, admin) |
| `dx_user_groups` | Links users to roles |
| `dx_case_events` | Audit trail of all activities |
| `dx_assignments` | Task assignments for reviewers |

---

## 🔐 Security Features

✅ **BCrypt Password Hashing** - Passwords never stored in plain text  
✅ **Session Security** - HttpOnly, SameSite cookies  
✅ **SQL Injection Protection** - PDO prepared statements  
✅ **XSS Protection** - Output escaping and Content-Type headers  
✅ **Input Validation** - Server-side validation for all fields  

---

## 📚 Next Steps

### For Admissions Officers

1. Login as reviewer (create account in database)
2. View submitted applications
3. Review and approve/reject cases

### For Administrators

1. Login as super_admin
2. Manage users and roles
3. Configure case types and workflows
4. View analytics and reports

### Add Features

- [ ] Email notifications with credentials
- [ ] Document upload step
- [ ] Payment integration
- [ ] Application status tracking
- [ ] Reviewer assignment logic
- [ ] Automated decision rules

---

## 📞 Support

For detailed documentation, see:
- `docs/ADMISSION_PORTAL_GUIDE.md` - Complete user guide
- `README.md` - Project overview
- `TODO.md` - Development roadmap

---

## ✨ Summary

You now have a **production-ready admission portal** that:

1. ✅ Allows public access to start applications
2. ✅ Automatically generates secure credentials
3. ✅ Enables students to save progress and continue later
4. ✅ Tracks all activities with audit trail
5. ✅ Provides beautiful, responsive UI
6. ✅ Follows security best practices

**Start using it now:**
```
http://localhost/dx-engine/public/portal/
```

Enjoy! 🎉
