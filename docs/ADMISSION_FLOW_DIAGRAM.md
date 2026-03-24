# Admission Portal - Flow Diagram

## Complete User Journey

```
┌─────────────────────────────────────────────────────────────────────┐
│                    STUDENT ADMISSION JOURNEY                         │
└─────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│ PHASE 1: PUBLIC ACCESS (No Login Required)                          │
└──────────────────────────────────────────────────────────────────────┘

    Student visits:
    http://localhost/dx-engine/public/portal/
           │
           ▼
    ┌─────────────────────┐
    │  Landing Page       │
    │  (index.php)        │
    │                     │
    │  • Hero Section     │
    │  • Feature Cards    │
    │  • Login Form       │
    │  • [Start Admission]│
    └─────────────────────┘
           │
           │ Click "Start Your Admission Now"
           ▼
    ┌─────────────────────┐
    │  Modal Opens        │
    │  (Bootstrap Modal)  │
    │                     │
    │  DX Interpreter     │
    │  loads first step   │
    └─────────────────────┘
           │
           │ Loads from AdmissionCaseDX.php
           ▼
    ┌─────────────────────────────────────┐
    │  STEP 1: Personal Information       │
    │  ─────────────────────────────────  │
    │  • First Name                       │
    │  • Last Name                        │
    │  • Email                            │
    │  • Phone                            │
    │  • Date of Birth                    │
    │  • Gender                           │
    │  • Address                          │
    │                                     │
    │  [Next: Academic Background]        │
    └─────────────────────────────────────┘
           │
           │ Submit form data
           ▼
    ┌─────────────────────────────────────┐
    │  POST to admission_public.php       │
    │  ?action=start_admission            │
    │                                     │
    │  Backend Processing:                │
    │  1. Validate email (unique check)   │
    │  2. Generate username               │
    │     → jsmith1a2b                    │
    │  3. Generate password               │
    │     → Xy9kL2mP4q                    │
    │  4. Hash password (BCrypt)          │
    │  5. Create user account             │
    │  6. Assign to 'portal_applicant'    │
    │  7. Create case instance            │
    │     → EDU-ADM-20260324-A1B2C3       │
    │  8. Save payload to database        │
    │  9. Log event                       │
    └─────────────────────────────────────┘
           │
           │ Return credentials
           ▼
    ┌─────────────────────────────────────┐
    │  ✅ Credentials Display             │
    │  ─────────────────────────────────  │
    │  Username: jsmith1a2b               │
    │  Password: Xy9kL2mP4q               │
    │  Case Ref: EDU-ADM-20260324-A1B2C3  │
    │                                     │
    │  ⚠️ Save these credentials!         │
    │                                     │
    │  [Go to Login]                      │
    └─────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│ PHASE 2: AUTHENTICATED ACCESS (Login Required)                      │
└──────────────────────────────────────────────────────────────────────┘

    Student clicks "Go to Login"
           │
           ▼
    ┌─────────────────────────────────────┐
    │  Login Form                         │
    │  ─────────────────────────────────  │
    │  Username: jsmith1a2b               │
    │  Password: Xy9kL2mP4q               │
    │                                     │
    │  [Login to Dashboard]               │
    └─────────────────────────────────────┘
           │
           │ POST to portal_auth.php?action=login
           ▼
    ┌─────────────────────────────────────┐
    │  Authentication                     │
    │  ─────────────────────────────────  │
    │  1. Find user by username           │
    │  2. Verify password (BCrypt)        │
    │  3. Create session                  │
    │  4. Return user data + roles        │
    └─────────────────────────────────────┘
           │
           │ Redirect based on role
           ▼
    ┌─────────────────────────────────────┐
    │  Applicant Dashboard                │
    │  (applicant.php)                    │
    │  ─────────────────────────────────  │
    │  • Case Status Card                 │
    │    - Case Reference                 │
    │    - Current Stage                  │
    │    - Status Badge                   │
    │                                     │
    │  • Progress Indicators              │
    │    ✓ Personal Info (completed)      │
    │    → Academic Background (active)   │
    │    ○ Program Selection (pending)    │
    │                                     │
    │  • DX Interpreter (continues form)  │
    └─────────────────────────────────────┘
           │
           │ Load case data
           ▼
    ┌─────────────────────────────────────┐
    │  GET portal_workflow.php            │
    │  ?action=case_status&case_id=123    │
    │                                     │
    │  Returns:                           │
    │  • Case details                     │
    │  • Current payload                  │
    │  • Current step                     │
    └─────────────────────────────────────┘
           │
           │ Initialize DX Interpreter with case_id
           ▼
    ┌─────────────────────────────────────┐
    │  STEP 2: Academic Background        │
    │  ─────────────────────────────────  │
    │  • High School Name                 │
    │  • Graduation Year                  │
    │  • GPA                              │
    │  • Test Scores                      │
    │  • Achievements                     │
    │                                     │
    │  [Back] [Next: Program Selection]   │
    └─────────────────────────────────────┘
           │
           │ Submit step data
           ▼
    ┌─────────────────────────────────────┐
    │  POST portal_workflow.php           │
    │  ?action=update_payload             │
    │                                     │
    │  Merge new data with existing:      │
    │  {                                  │
    │    ...personal_info,                │
    │    ...academic_background,          │
    │    current_step: "program_selection"│
    │  }                                  │
    └─────────────────────────────────────┘
           │
           │ Reload page / Continue
           ▼
    ┌─────────────────────────────────────┐
    │  STEP 3: Program Selection          │
    │  ─────────────────────────────────  │
    │  • Desired Program                  │
    │  • Enrollment Term                  │
    │  • Statement of Purpose             │
    │  • Financial Aid                    │
    │                                     │
    │  [Back] [Submit Application]        │
    └─────────────────────────────────────┘
           │
           │ Submit final step
           ▼
    ┌─────────────────────────────────────┐
    │  POST portal_workflow.php           │
    │  ?action=complete_case              │
    │                                     │
    │  1. Update case status: "completed" │
    │  2. Update stage: "completed"       │
    │  3. Log completion event            │
    └─────────────────────────────────────┘
           │
           │ Success!
           ▼
    ┌─────────────────────────────────────┐
    │  ✅ Application Submitted           │
    │  ─────────────────────────────────  │
    │  Your application has been          │
    │  submitted successfully!            │
    │                                     │
    │  You will be notified of the        │
    │  admission decision.                │
    └─────────────────────────────────────┘
```

## Technical Flow

### Frontend Components

```
index.php (Landing Page)
    ├── Bootstrap 5.3.3 (UI Framework)
    ├── dx-interpreter.js (Form Renderer)
    └── Custom JavaScript
        ├── Start Admission Handler
        ├── Login Handler
        └── Credential Display Logic

applicant.php (Dashboard)
    ├── Bootstrap 5.3.3
    ├── dx-interpreter.js
    ├── dx-worklist.js
    └── Custom JavaScript
        ├── Session Check
        ├── Case Loader
        ├── Progress Tracker
        └── Step Completion Handler
```

### Backend Components

```
admission_public.php
    ├── User Creation
    │   ├── generateUsername()
    │   ├── generatePassword()
    │   └── password_hash()
    ├── Case Creation
    │   ├── Generate case_ref
    │   ├── Create case instance
    │   └── Save payload
    └── Event Logging

portal_auth.php
    ├── Login
    │   ├── Find user
    │   ├── password_verify()
    │   └── Create session
    └── Logout
        └── Destroy session

portal_workflow.php
    ├── overview (Get user's cases)
    ├── case_status (Get case details)
    ├── update_payload (Save progress)
    └── complete_case (Mark as done)

AdmissionCaseDX.php
    ├── getFlow() (Define 3 steps)
    ├── buildStepPersonalInfo()
    ├── buildStepAcademicBackground()
    ├── buildStepProgramSelection()
    └── postProcess() (Handle submissions)
```

### Database Flow

```
User Registration:
    dx_users
        ├── INSERT new user
        └── password_hash stored

    dx_user_groups
        └── Link to 'portal_applicant' group

Case Creation:
    dx_case_instances
        ├── INSERT new case
        ├── case_ref: EDU-ADM-20260324-A1B2C3
        ├── status: active
        ├── current_stage_key: student_application
        └── payload_json: {...}

    dx_assignments
        ├── INSERT assignment
        ├── assigned_user_id: student
        └── status: ready

    dx_case_events
        └── INSERT event: case.started_public

Progress Updates:
    dx_case_instances
        └── UPDATE payload_json (merge new data)

    dx_case_events
        └── INSERT event: step.completed

Completion:
    dx_case_instances
        ├── UPDATE status: completed
        └── UPDATE current_stage_key: completed

    dx_case_events
        └── INSERT event: case.completed
```

## Security Flow

```
Password Security:
    Plain Password → password_hash(BCrypt) → Database
    Login Attempt → password_verify() → Session

Session Security:
    Login → session_start()
        ├── cookie_httponly: true
        ├── cookie_samesite: Lax
        └── use_strict_mode: 1

API Security:
    All Requests → Check session
        ├── Authenticated → Process
        └── Not Authenticated → 401 Redirect

Input Validation:
    Client-Side → HTML5 + JavaScript
    Server-Side → PHP validation
        ├── Required fields
        ├── Email format
        ├── Phone format
        └── Length limits
```

## Data Flow Example

### Complete Journey Data

```json
// After Step 1 (Personal Info)
{
  "first_name": "John",
  "last_name": "Smith",
  "email": "john.smith@example.com",
  "phone": "+1 (555) 123-4567",
  "date_of_birth": "2005-01-15",
  "gender": "male",
  "address": "123 Main St, City, State, 12345",
  "started_public": true,
  "started_at": "2026-03-24T10:30:00+00:00",
  "current_step": "academic_background"
}

// After Step 2 (Academic Background)
{
  ...previous_data,
  "high_school_name": "Springfield High School",
  "graduation_year": "2023",
  "gpa": "3.8",
  "test_scores": "SAT: 1350",
  "achievements": "Honor Roll, Science Fair Winner",
  "current_step": "program_selection"
}

// After Step 3 (Program Selection)
{
  ...previous_data,
  "program": "computer_science",
  "enrollment_term": "fall_2026",
  "statement_of_purpose": "I am passionate about...",
  "financial_aid": "yes",
  "current_step": "completed",
  "completed_at": "2026-03-24T10:45:00+00:00"
}
```

---

## Summary

This admission portal provides a **seamless, secure, and user-friendly** experience for students to:

1. ✅ Start applications without creating an account first
2. ✅ Receive auto-generated credentials after initial submission
3. ✅ Save progress and continue later
4. ✅ Track application status in real-time
5. ✅ Complete multi-step forms with validation
6. ✅ Submit applications with full audit trail

All while maintaining **enterprise-grade security** and **professional UX design**.
