# Educational Admission Portal - User Guide

## Overview

The DX-Engine Educational Admission Portal is a complete workflow system that allows students to apply for admission through a multi-step form process. The system automatically generates login credentials after the first step, enabling students to save progress and continue their application later.

## Features

✅ **Public Landing Page** - Beautiful, modern interface with gradient design  
✅ **Multi-Step Admission Form** - 3-step application process  
✅ **Automatic Credential Generation** - Username and password created after first step  
✅ **Secure Login System** - Password-based authentication  
✅ **Progress Tracking** - Visual progress indicators  
✅ **Case Management** - Full workflow tracking and event logging  
✅ **Responsive Design** - Works on desktop, tablet, and mobile  

---

## How to Use the System

### For Students (Applicants)

#### Step 1: Start Your Application

1. **Visit the Portal**
   - Open your browser and navigate to: `http://localhost/dx-engine/public/portal/`
   - You'll see the landing page with a "Start Your Admission Now" button

2. **Click "Start Your Admission Now"**
   - A modal will open with the first step of the admission form
   - Fill in your personal information:
     - First Name
     - Last Name
     - Email Address
     - Phone Number
     - Date of Birth
     - Gender
     - Home Address

3. **Submit First Step**
   - Click "Next: Academic Background"
   - The system will:
     - Create your admission case
     - Generate a unique username and password
     - Display your credentials on screen

4. **Save Your Credentials**
   - **IMPORTANT**: Write down or screenshot your credentials:
     - Username (e.g., `jsmith1a2b`)
     - Password (e.g., `Xy9kL2mP4q`)
     - Case Reference (e.g., `EDU-ADM-20260324-A1B2C3`)
   - You'll need these to login and continue your application

#### Step 2: Login and Continue

1. **Click "Go to Login"** or refresh the page
2. **Enter Your Credentials**
   - Username: (the one generated for you)
   - Password: (the one generated for you)
3. **Click "Login to Dashboard"**
   - You'll be redirected to the Applicant Portal

#### Step 3: Complete Your Application

1. **Applicant Dashboard**
   - You'll see your application status card
   - Progress indicators showing completed and pending steps
   - The admission form will continue from where you left off

2. **Step 2: Academic Background**
   - High School Name
   - Graduation Year
   - GPA / Grade Average
   - Test Scores (SAT/ACT)
   - Academic Achievements & Awards
   - Click "Next: Program Selection"

3. **Step 3: Program Selection**
   - Desired Program (Computer Science, Engineering, etc.)
   - Intended Enrollment Term
   - Statement of Purpose (50-1000 characters)
   - Financial Aid preference
   - Click "Submit Application"

4. **Application Submitted**
   - You'll see a success message
   - Your case status will update to "Completed"
   - You'll be notified of the admission decision

---

## System Architecture

### File Structure

```
dx-engine/
├── public/
│   ├── portal/
│   │   ├── index.php           # Public landing page
│   │   ├── applicant.php       # Applicant dashboard
│   │   ├── reviewer.php        # Reviewer dashboard
│   │   └── admin.php           # Admin dashboard
│   ├── api/
│   │   ├── dx.php              # DX API entry point
│   │   ├── admission_public.php # Public admission endpoint
│   │   ├── portal_auth.php     # Authentication API
│   │   └── portal_workflow.php # Workflow API
│   └── js/
│       └── dx-interpreter.js   # DX form renderer
├── src/
│   └── App/
│       └── DX/
│           └── AdmissionCaseDX.php # Admission workflow controller
└── docs/
    └── ADMISSION_PORTAL_GUIDE.md # This file
```

### API Endpoints

#### Public Endpoints (No Login Required)

**POST** `/public/api/admission_public.php?action=start_admission`
- Creates admission case
- Generates user credentials
- Returns username, password, and case reference

**POST** `/public/api/portal_auth.php?action=login`
- Authenticates user with username and password
- Returns user data and roles

#### Protected Endpoints (Login Required)

**GET** `/public/api/portal_workflow.php?action=overview`
- Returns user's cases and queues

**GET** `/public/api/portal_workflow.php?action=case_status&case_id={id}`
- Returns detailed case information

**POST** `/public/api/portal_workflow.php?action=update_payload`
- Updates case payload with new step data

**POST** `/public/api/portal_workflow.php?action=complete_case`
- Marks case as completed

---

## Database Schema

### Key Tables

**dx_case_instances**
- Stores admission cases
- Fields: case_ref, status, current_stage_key, payload_json

**dx_users**
- Stores user accounts
- Fields: username, password_hash, email, display_name

**dx_user_groups**
- Links users to groups (roles)
- Fields: user_id, group_id

**dx_groups**
- Defines user roles
- Key groups: `portal_applicant`, `portal_admissions_officer`

**dx_case_events**
- Audit trail of case activities
- Fields: case_instance_id, event_type, actor_user_id, details_json

---

## Configuration

### App Configuration (`config/app.php`)

```php
return [
    'dx_api_endpoint' => 'http://localhost/dx-engine/public/api/dx.php',
    'debug' => false,
];
```

### Database Configuration (`config/database.php`)

```php
return new PDO(
    'mysql:host=localhost;dbname=dx_engine;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
```

---

## Security Features

✅ **Password Hashing** - BCrypt password hashing  
✅ **Session Management** - Secure session cookies with HttpOnly and SameSite  
✅ **Input Validation** - Server-side validation for all form fields  
✅ **SQL Injection Protection** - Prepared statements via PDO  
✅ **XSS Protection** - Content-Type headers and output escaping  
✅ **CSRF Protection** - Session-based authentication  

---

## Troubleshooting

### Issue: "An account with this email already exists"
**Solution**: The email is already registered. Use the login form instead.

### Issue: "Invalid login"
**Solution**: Check your username and password. They are case-sensitive.

### Issue: "Case not found"
**Solution**: Your session may have expired. Logout and login again.

### Issue: Form not loading
**Solution**: 
1. Check browser console for JavaScript errors
2. Verify `dx_api_endpoint` in `config/app.php`
3. Ensure database migrations are run

### Issue: Credentials not displaying
**Solution**:
1. Check browser console for API errors
2. Verify `admission_public.php` is accessible
3. Check database connection

---

## Development Notes

### Adding New Form Steps

1. Edit `src/App/DX/AdmissionCaseDX.php`
2. Add new step method (e.g., `buildStepDocumentUpload()`)
3. Add step to `getFlow()` method
4. Add handler in `postProcess()` method

### Customizing Credentials Generation

Edit `public/api/admission_public.php`:

```php
function generateUsername(string $firstName, string $lastName): string
{
    // Custom logic here
}

function generatePassword(int $length = 8): string
{
    // Custom logic here
}
```

### Adding Email Notifications

After credential generation in `admission_public.php`:

```php
// Send email with credentials
mail(
    $email,
    'Your Admission Portal Credentials',
    "Username: $username\nPassword: $password"
);
```

---

## Testing Checklist

- [ ] Landing page loads correctly
- [ ] "Start Admission" button opens modal
- [ ] First step form validates required fields
- [ ] Credentials are generated and displayed
- [ ] Login works with generated credentials
- [ ] Applicant dashboard shows case status
- [ ] Progress indicators update correctly
- [ ] Step 2 form loads with correct fields
- [ ] Step 3 form loads with correct fields
- [ ] Application submission completes successfully
- [ ] Case status updates to "Completed"
- [ ] Event trail logs all activities

---

## Support

For issues or questions:
1. Check the browser console for errors
2. Review PHP error logs in XAMPP
3. Verify database schema is up to date
4. Check `config/app.php` and `config/database.php`

---

## License

DX-Engine Educational Admission Portal  
© 2026 - All Rights Reserved
