<?php
/**
 * DX-Engine — AdmissionCaseDX
 * -----------------------------------------------------------------------
 * Educational Institution Admission Case Workflow
 * 
 * This DX controller handles the multi-step admission process for students.
 * Step 1: Personal Information (public, creates case + credentials)
 * Step 2+: Additional steps (requires login)
 */

declare(strict_types=1);

namespace DXEngine\App\DX;

use DXEngine\Core\DXController;

class AdmissionCaseDX extends DXController
{
    protected function preProcess(array $context): array
    {
        // Load case data if case_id is provided
        $caseId = (int) ($context['params']['case_id'] ?? 0);
        $initialState = [];
        
        if ($caseId > 0) {
            // TODO: Load case data from database
            // For now, return empty state
        }
        
        return array_merge($context, [
            'initial_state' => $initialState,
            'case_id' => $caseId,
        ]);
    }

    protected function getFlow(array $context): array
    {
        $initialState = $context['initial_state'] ?? [];
        $caseId = $context['case_id'] ?? 0;
        
        $base = rtrim($context['dx_api_endpoint'] ?? '/dx-engine/public/api/dx.php', '?&');
        $sep = str_contains($base, '?') ? '&' : '?';
        $postEndpoint = $base . $sep . 'dx=admission_case';

        return [
            'dx_id' => 'admission_case',
            'title' => 'Educational Admission Application',
            'description' => 'Complete your admission application in simple steps.',
            'version' => '1.0',
            'post_endpoint' => $postEndpoint,
            'initial_state' => $initialState,
            'context' => ['case_id' => $caseId],
            'steps' => [
                $this->buildStepPersonalInfo($initialState),
                $this->buildStepAcademicBackground($initialState),
                $this->buildStepProgramSelection($initialState),
            ],
        ];
    }

    private function buildStepPersonalInfo(array $state): array
    {
        return $this->step('personal_info', 'Personal Information', [
            
            $this->component('heading', [
                'label' => 'Tell us about yourself',
                'col_span' => 12,
            ]),

            $this->component('text_input', [
                'field_key' => 'first_name',
                'label' => 'First Name',
                'placeholder' => 'Enter your first name',
                'required' => true,
                'value' => $state['first_name'] ?? '',
                'col_span' => 6,
                'validation_rules' => [
                    'min' => 2,
                    'max' => 50,
                    'pattern' => "^[a-zA-Z\\s\\-\\']+$",
                    'message' => 'First name may only contain letters, spaces, hyphens or apostrophes.',
                ],
            ]),

            $this->component('text_input', [
                'field_key' => 'last_name',
                'label' => 'Last Name',
                'placeholder' => 'Enter your last name',
                'required' => true,
                'value' => $state['last_name'] ?? '',
                'col_span' => 6,
                'validation_rules' => [
                    'min' => 2,
                    'max' => 50,
                    'pattern' => "^[a-zA-Z\\s\\-\\']+$",
                    'message' => 'Last name may only contain letters, spaces, hyphens or apostrophes.',
                ],
            ]),

            $this->component('email_input', [
                'field_key' => 'email',
                'label' => 'Email Address',
                'placeholder' => 'your.email@example.com',
                'required' => true,
                'value' => $state['email'] ?? '',
                'col_span' => 6,
                'validation_rules' => [
                    'pattern' => '^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$',
                    'message' => 'Enter a valid email address.',
                ],
            ]),

            $this->component('text_input', [
                'field_key' => 'phone',
                'label' => 'Phone Number',
                'placeholder' => '+1 (555) 000-0000',
                'required' => true,
                'value' => $state['phone'] ?? '',
                'col_span' => 6,
                'validation_rules' => [
                    'pattern' => '^[0-9\\+\\-\\(\\)\\s]{7,20}$',
                    'message' => 'Enter a valid phone number.',
                ],
            ]),

            $this->component('date_input', [
                'field_key' => 'date_of_birth',
                'label' => 'Date of Birth',
                'required' => true,
                'value' => $state['date_of_birth'] ?? '',
                'col_span' => 6,
                'attrs' => ['max' => date('Y-m-d')],
            ]),

            $this->component('select', [
                'field_key' => 'gender',
                'label' => 'Gender',
                'required' => true,
                'value' => $state['gender'] ?? '',
                'col_span' => 6,
                'options' => [
                    ['value' => '', 'label' => '— Select —'],
                    ['value' => 'male', 'label' => 'Male'],
                    ['value' => 'female', 'label' => 'Female'],
                    ['value' => 'other', 'label' => 'Other'],
                    ['value' => 'prefer_not', 'label' => 'Prefer not to say'],
                ],
            ]),

            $this->component('textarea', [
                'field_key' => 'address',
                'label' => 'Home Address',
                'placeholder' => 'Street, City, State, ZIP',
                'required' => true,
                'value' => $state['address'] ?? '',
                'col_span' => 12,
                'attrs' => ['rows' => 3],
            ]),

        ], [
            'submit_label' => 'Next: Academic Background',
            'cancel_label' => null,
            'is_final' => false,
        ]);
    }

    private function buildStepAcademicBackground(array $state): array
    {
        return $this->step('academic_background', 'Academic Background', [
            
            $this->component('heading', [
                'label' => 'Educational History',
                'col_span' => 12,
            ]),

            $this->component('text_input', [
                'field_key' => 'high_school_name',
                'label' => 'High School Name',
                'placeholder' => 'Name of your high school',
                'required' => true,
                'value' => $state['high_school_name'] ?? '',
                'col_span' => 6,
            ]),

            $this->component('text_input', [
                'field_key' => 'graduation_year',
                'label' => 'Graduation Year',
                'placeholder' => '2024',
                'required' => true,
                'value' => $state['graduation_year'] ?? '',
                'col_span' => 6,
                'validation_rules' => [
                    'pattern' => '^[0-9]{4}$',
                    'message' => 'Enter a valid 4-digit year.',
                ],
            ]),

            $this->component('text_input', [
                'field_key' => 'gpa',
                'label' => 'GPA / Grade Average',
                'placeholder' => '3.5',
                'required' => false,
                'value' => $state['gpa'] ?? '',
                'col_span' => 6,
            ]),

            $this->component('text_input', [
                'field_key' => 'test_scores',
                'label' => 'Standardized Test Scores (SAT/ACT)',
                'placeholder' => 'SAT: 1200, ACT: 28',
                'required' => false,
                'value' => $state['test_scores'] ?? '',
                'col_span' => 6,
            ]),

            $this->component('textarea', [
                'field_key' => 'achievements',
                'label' => 'Academic Achievements & Awards',
                'placeholder' => 'List any honors, awards, or special achievements...',
                'required' => false,
                'value' => $state['achievements'] ?? '',
                'col_span' => 12,
                'attrs' => ['rows' => 4],
            ]),

        ], [
            'submit_label' => 'Next: Program Selection',
            'cancel_label' => 'Back',
            'is_final' => false,
        ]);
    }

    private function buildStepProgramSelection(array $state): array
    {
        return $this->step('program_selection', 'Program Selection', [
            
            $this->component('heading', [
                'label' => 'Choose Your Program',
                'col_span' => 12,
            ]),

            $this->component('select', [
                'field_key' => 'program',
                'label' => 'Desired Program',
                'required' => true,
                'value' => $state['program'] ?? '',
                'col_span' => 6,
                'options' => [
                    ['value' => '', 'label' => '— Select Program —'],
                    ['value' => 'computer_science', 'label' => 'Computer Science'],
                    ['value' => 'engineering', 'label' => 'Engineering'],
                    ['value' => 'business_admin', 'label' => 'Business Administration'],
                    ['value' => 'nursing', 'label' => 'Nursing'],
                    ['value' => 'education', 'label' => 'Education'],
                    ['value' => 'arts', 'label' => 'Arts & Humanities'],
                ],
            ]),

            $this->component('select', [
                'field_key' => 'enrollment_term',
                'label' => 'Intended Enrollment Term',
                'required' => true,
                'value' => $state['enrollment_term'] ?? '',
                'col_span' => 6,
                'options' => [
                    ['value' => '', 'label' => '— Select Term —'],
                    ['value' => 'fall_2026', 'label' => 'Fall 2026'],
                    ['value' => 'spring_2027', 'label' => 'Spring 2027'],
                    ['value' => 'summer_2027', 'label' => 'Summer 2027'],
                ],
            ]),

            $this->component('textarea', [
                'field_key' => 'statement_of_purpose',
                'label' => 'Statement of Purpose',
                'placeholder' => 'Tell us why you want to join this program and what are your career goals...',
                'required' => true,
                'value' => $state['statement_of_purpose'] ?? '',
                'col_span' => 12,
                'attrs' => ['rows' => 6],
                'validation_rules' => [
                    'min' => 50,
                    'max' => 1000,
                    'message' => 'Statement must be between 50 and 1000 characters.',
                ],
            ]),

            $this->component('select', [
                'field_key' => 'financial_aid',
                'label' => 'Do you plan to apply for financial aid?',
                'required' => true,
                'value' => $state['financial_aid'] ?? '',
                'col_span' => 12,
                'options' => [
                    ['value' => '', 'label' => '— Select —'],
                    ['value' => 'yes', 'label' => 'Yes, I plan to apply for financial aid'],
                    ['value' => 'no', 'label' => 'No, I will not apply for financial aid'],
                ],
            ]),

        ], [
            'submit_label' => 'Submit Application',
            'cancel_label' => 'Back',
            'is_final' => true,
        ]);
    }

    protected function postProcess(string $step, array $payload, array $context): array
    {
        return match ($step) {
            'personal_info' => $this->savePersonalInfo($payload),
            'academic_background' => $this->saveAcademicBackground($payload),
            'program_selection' => $this->saveProgramSelection($payload),
            default => $this->fail([], "Unknown step: '{$step}'."),
        };
    }

    private function savePersonalInfo(array $payload): array
    {
        // This will be handled by portal_workflow.php
        // which will create the case and user credentials
        return $this->success(
            'Personal information saved.',
            ['step_data' => $payload],
            'academic_background'
        );
    }

    private function saveAcademicBackground(array $payload): array
    {
        // Save to case payload
        return $this->success(
            'Academic background saved.',
            ['step_data' => $payload],
            'program_selection'
        );
    }

    private function saveProgramSelection(array $payload): array
    {
        // Final step - complete the application
        return $this->success(
            'Application submitted successfully! You will be notified of the decision.',
            ['step_data' => $payload],
            null  // null = completion
        );
    }
}
