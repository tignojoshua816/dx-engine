<?php
/**
 * DX-Engine — AdmissionDX
 * -----------------------------------------------------------------------
 * Namespace  : DXEngine\App\DX
 * Registered : 'admission' in public/api/dx.php
 *
 * Defines the "Admission Case" Digital Experience — a 2-step form:
 *   Step 1  patient_info   — Patient personal and contact details
 *   Step 2  clinical_data  — Triage, department, complaint, insurance
 *
 * Namespace change (consolidation):
 *   Was:  App\DX\AdmissionDX       (app/DX/ — now retired)
 *   Now:  DXEngine\App\DX\AdmissionDX  (src/App/DX/)
 *
 * All model use-statements updated to DXEngine\App\Models\*.
 * post_endpoint is always read from $context['dx_api_endpoint'], which
 * is set in public/api/dx.php from config/app.php — never hardcoded.
 */

declare(strict_types=1);

namespace DXEngine\App\DX;

use DXEngine\App\Models\AdmissionModel;
use DXEngine\App\Models\DepartmentModel;
use DXEngine\App\Models\InsuranceModel;
use DXEngine\App\Models\PatientModel;
use DXEngine\Core\DXController;

class AdmissionDX extends DXController
{
    private PatientModel    $patientModel;
    private AdmissionModel  $admissionModel;
    private DepartmentModel $deptModel;
    private InsuranceModel  $insuranceModel;

    public function __construct()
    {
        $this->patientModel   = new PatientModel();
        $this->admissionModel = new AdmissionModel();
        $this->deptModel      = new DepartmentModel();
        $this->insuranceModel = new InsuranceModel();
    }

    /* ------------------------------------------------------------------ */
    /*  Pre-process                                                         */
    /* ------------------------------------------------------------------ */

    protected function preProcess(array $context): array
    {
        // ── Load active departments for the SELECT component ─────────────
        // DataModel::where() uses raw SQL column names as keys.
        // 'is_active' is the physical column name in departments — correct.
        $deptOptions = [];
        try {
            $deptOptions = $this->optionsFromModel(
                $this->deptModel,
                'id',     // value column (physical name)
                'name',   // label column (physical name)
                ['is_active' => 1]
            );
        } catch (\Throwable $e) {
            // Surface as a clear RuntimeException so dx.php's outer catch
            // returns a JSON error describing the missing table.
            throw new \RuntimeException(
                'Failed to load departments. '
                . 'Have you run database/migrations/003_schema.sql? '
                . 'Original error: ' . $e->getMessage(),
                0,
                $e
            );
        }

        // ── Hydrate initial state for edit mode ───────────────────────────
        $admissionId  = (int) ($context['params']['admission_id'] ?? 0);
        $initialState = [];

        if ($admissionId > 0) {
            $admission = $this->admissionModel->find($admissionId);
            if ($admission) {
                $patient   = $this->patientModel->find($admission['patient_id']) ?? [];
                $insurance = $this->insuranceModel->where(
                    ['admission_id' => $admissionId], '', 1
                )[0] ?? [];

                // Merge order: patient → admission → insurance.
                // Admission keys override patient keys (avoids 'id' clash).
                $initialState = array_merge($patient, $admission, $insurance);

                // Restore the UI toggle flag from insurance row presence.
                $initialState['has_insurance'] = $insurance ? '1' : '0';
            }
        }

        return array_merge($context, [
            'dept_options'  => $deptOptions,
            'initial_state' => $initialState,
            'admission_id'  => $admissionId,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Flow Definition  (Metadata Bridge JSON)                             */
    /* ------------------------------------------------------------------ */

    protected function getFlow(array $context): array
    {
        $deptOptions  = $context['dept_options']  ?? [];
        $initialState = $context['initial_state'] ?? [];
        $admissionId  = $context['admission_id']  ?? 0;

        // post_endpoint comes from config/app.php via dx.php → Router → $context.
        // This is always the correct absolute URL for the current environment,
        // whether on XAMPP (/dx-engine/...) or a production server.
        $base         = rtrim($context['dx_api_endpoint'] ?? '/dx-engine/public/api/dx.php', '?&');
        $sep          = str_contains($base, '?') ? '&' : '?';
        $postEndpoint = $base . $sep . 'dx=admission';

        return [
            'dx_id'         => 'admission',
            'title'         => 'Patient Admission',
            'description'   => 'Register a new patient admission in two steps.',
            'version'       => '1.0',
            'post_endpoint' => $postEndpoint,
            'initial_state' => $initialState,
            'context'       => ['admission_id' => $admissionId],
            'steps'         => [
                $this->buildStepPatientInfo($initialState),
                $this->buildStepClinicalData($initialState, $deptOptions),
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Step Builders                                                        */
    /* ------------------------------------------------------------------ */

    private function buildStepPatientInfo(array $state): array
    {
        return $this->step('patient_info', 'Patient Information', [

            $this->component('heading', [
                'label'    => 'Personal Details',
                'col_span' => 12,
            ]),

            $this->component('text_input', [
                'field_key'        => 'first_name',
                'label'            => 'First Name',
                'placeholder'      => 'Enter first name',
                'required'         => true,
                'value'            => $state['first_name'] ?? '',
                'col_span'         => 6,
                'validation_rules' => [
                    'min'     => 2,
                    'max'     => 80,
                    'pattern' => "^[a-zA-Z\\s\\-\\']+$",
                    'message' => 'First name may only contain letters, spaces, hyphens or apostrophes.',
                ],
            ]),

            $this->component('text_input', [
                'field_key'        => 'last_name',
                'label'            => 'Last Name',
                'placeholder'      => 'Enter last name',
                'required'         => true,
                'value'            => $state['last_name'] ?? '',
                'col_span'         => 6,
                'validation_rules' => [
                    'min'     => 2,
                    'max'     => 80,
                    'pattern' => "^[a-zA-Z\\s\\-\\']+$",
                    'message' => 'Last name may only contain letters, spaces, hyphens or apostrophes.',
                ],
            ]),

            $this->component('date_input', [
                'field_key' => 'date_of_birth',
                'label'     => 'Date of Birth',
                'required'  => true,
                'value'     => $state['date_of_birth'] ?? '',
                'col_span'  => 6,
                'attrs'     => ['max' => date('Y-m-d')],
            ]),

            $this->component('select', [
                'field_key' => 'gender',
                'label'     => 'Gender',
                'required'  => true,
                'value'     => $state['gender'] ?? '',
                'col_span'  => 6,
                'options'   => [
                    ['value' => '',           'label' => '— Select —'],
                    ['value' => 'male',       'label' => 'Male'],
                    ['value' => 'female',     'label' => 'Female'],
                    ['value' => 'other',      'label' => 'Other'],
                    ['value' => 'prefer_not', 'label' => 'Prefer not to say'],
                ],
            ]),

            $this->component('divider', ['col_span' => 12]),

            $this->component('heading', [
                'label'    => 'Contact Information',
                'col_span' => 12,
            ]),

            $this->component('text_input', [
                'field_key'        => 'contact_phone',
                'label'            => 'Phone Number',
                'placeholder'      => '+1 (555) 000-0000',
                'required'         => true,
                'value'            => $state['contact_phone'] ?? '',
                'col_span'         => 6,
                'validation_rules' => [
                    'pattern' => '^[0-9\\+\\-\\(\\)\\s]{7,20}$',
                    'message' => 'Enter a valid phone number.',
                ],
            ]),

            $this->component('email_input', [
                'field_key'        => 'contact_email',
                'label'            => 'Email Address',
                'placeholder'      => 'patient@example.com',
                'required'         => false,
                'value'            => $state['contact_email'] ?? '',
                'col_span'         => 6,
                'validation_rules' => [
                    'pattern' => '^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$',
                    'message' => 'Enter a valid email address.',
                ],
            ]),

            $this->component('textarea', [
                'field_key'   => 'address',
                'label'       => 'Home Address',
                'placeholder' => 'Street, City, State, ZIP',
                'required'    => false,
                'value'       => $state['address'] ?? '',
                'col_span'    => 12,
                'attrs'       => ['rows' => 3],
            ]),

        ], [
            'submit_label' => 'Next: Clinical Data',
            'cancel_label' => null,
            'is_final'     => false,
        ]);
    }

    private function buildStepClinicalData(array $state, array $deptOptions): array
    {
        return $this->step('clinical_data', 'Clinical Data', [

            $this->component('heading', [
                'label'    => 'Triage & Admission Details',
                'col_span' => 12,
            ]),

            $this->component('radio', [
                'field_key' => 'triage_level',
                'label'     => 'Triage Level',
                'required'  => true,
                'value'     => (string) ($state['triage_level'] ?? ''),
                'col_span'  => 12,
                'options'   => [
                    ['value' => '1', 'label' => 'Level 1 — Immediate (Resuscitation)'],
                    ['value' => '2', 'label' => 'Level 2 — Emergent'],
                    ['value' => '3', 'label' => 'Level 3 — Urgent'],
                    ['value' => '4', 'label' => 'Level 4 — Less Urgent'],
                    ['value' => '5', 'label' => 'Level 5 — Non-Urgent'],
                ],
            ]),

            $this->component('select', [
                'field_key' => 'department_id',
                'label'     => 'Admitting Department',
                'required'  => true,
                'value'     => (string) ($state['department_id'] ?? ''),
                'col_span'  => 6,
                'options'   => array_merge(
                    [['value' => '', 'label' => '— Select Department —']],
                    $deptOptions
                ),
            ]),

            $this->component('text_input', [
                'field_key'   => 'attending_physician',
                'label'       => 'Attending Physician',
                'placeholder' => 'Dr. Full Name',
                'required'    => false,
                'value'       => $state['attending_physician'] ?? '',
                'col_span'    => 6,
            ]),

            $this->component('textarea', [
                'field_key'        => 'chief_complaint',
                'label'            => 'Chief Complaint',
                'placeholder'      => 'Describe the primary reason for admission...',
                'required'         => true,
                'value'            => $state['chief_complaint'] ?? '',
                'col_span'         => 12,
                'attrs'            => ['rows' => 3],
                'validation_rules' => ['min' => 3, 'max' => 255],
            ]),

            $this->component('textarea', [
                'field_key'   => 'notes',
                'label'       => 'Clinical Notes',
                'placeholder' => 'Additional observations...',
                'required'    => false,
                'value'       => $state['notes'] ?? '',
                'col_span'    => 12,
                'attrs'       => ['rows' => 4],
            ]),

            $this->component('divider', ['col_span' => 12]),

            // ── Insurance toggle ────────────────────────────────────────────
            // 'has_insurance' is a UI-only field. It is NOT a column in the
            // admissions table. It drives client-side visibility rules and is
            // used in postProcess to decide whether to write insurance_details.
            $this->component('select', [
                'field_key' => 'has_insurance',
                'label'     => 'Insurance Coverage',
                'required'  => true,
                'value'     => $state['has_insurance'] ?? '0',
                'col_span'  => 12,
                'options'   => [
                    ['value' => '0', 'label' => 'No Insurance / Self-Pay'],
                    ['value' => '1', 'label' => 'Has Insurance'],
                ],
            ]),

            // ── Insurance detail fields — shown only when has_insurance='1' ─
            $this->component('heading', [
                'label'           => 'Insurance Details',
                'col_span'        => 12,
                'visibility_rule' => ['field' => 'has_insurance', 'operator' => 'eq', 'value' => '1'],
            ]),

            $this->component('text_input', [
                'field_key'        => 'provider_name',
                'label'            => 'Insurance Provider',
                'placeholder'      => 'e.g. Blue Cross Blue Shield',
                'required'         => false,
                'value'            => $state['provider_name'] ?? '',
                'col_span'         => 6,
                'visibility_rule'  => ['field' => 'has_insurance', 'operator' => 'eq', 'value' => '1'],
                'validation_rules' => ['max' => 120],
            ]),

            $this->component('text_input', [
                'field_key'        => 'policy_number',
                'label'            => 'Policy Number',
                'placeholder'      => 'Policy #',
                'required'         => false,
                'value'            => $state['policy_number'] ?? '',
                'col_span'         => 6,
                'visibility_rule'  => ['field' => 'has_insurance', 'operator' => 'eq', 'value' => '1'],
                'validation_rules' => ['max' => 60],
            ]),

            $this->component('text_input', [
                'field_key'        => 'group_number',
                'label'            => 'Group Number',
                'placeholder'      => 'Group #',
                'required'         => false,
                'value'            => $state['group_number'] ?? '',
                'col_span'         => 6,
                'visibility_rule'  => ['field' => 'has_insurance', 'operator' => 'eq', 'value' => '1'],
                'validation_rules' => ['max' => 60],
            ]),

            $this->component('text_input', [
                'field_key'        => 'holder_name',
                'label'            => 'Policy Holder Name',
                'placeholder'      => 'Full name on policy',
                'required'         => false,
                'value'            => $state['holder_name'] ?? '',
                'col_span'         => 6,
                'visibility_rule'  => ['field' => 'has_insurance', 'operator' => 'eq', 'value' => '1'],
                'validation_rules' => ['max' => 120],
            ]),

            $this->component('date_input', [
                'field_key'      => 'holder_dob',
                'label'          => 'Holder Date of Birth',
                'required'       => false,
                'value'          => $state['holder_dob'] ?? '',
                'col_span'       => 6,
                'visibility_rule' => ['field' => 'has_insurance', 'operator' => 'eq', 'value' => '1'],
            ]),

            $this->component('date_input', [
                'field_key'      => 'expiry_date',
                'label'          => 'Policy Expiry Date',
                'required'       => false,
                'value'          => $state['expiry_date'] ?? '',
                'col_span'       => 6,
                'visibility_rule' => ['field' => 'has_insurance', 'operator' => 'eq', 'value' => '1'],
            ]),

        ], [
            'submit_label' => 'Complete Admission',
            'cancel_label' => 'Back: Patient Info',
            'is_final'     => true,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Post-process                                                         */
    /* ------------------------------------------------------------------ */

    protected function postProcess(string $step, array $payload, array $context): array
    {
        return match ($step) {
            'patient_info'  => $this->savePatientInfo($payload),
            'clinical_data' => $this->saveClinicalData($payload),
            default         => $this->fail(
                [],
                "Unknown step: '{$step}'. Expected 'patient_info' or 'clinical_data'."
            ),
        };
    }

    /* ── Step 1: Patient info ─────────────────────────────────────────── */

    private function savePatientInfo(array $payload): array
    {
        // Server-side validation via PatientModel field rules.
        $result = $this->patientModel->validate($payload);
        if (!$result['valid']) {
            return $this->fail($result['errors']);
        }

        $patientId = (int) ($payload['patient_id'] ?? 0);

        if ($patientId > 0) {
            $this->patientModel->update($patientId, $payload);
        } else {
            // insert() only writes columns present in fieldMap writableColumns().
            $patientId = (int) $this->patientModel->insert($payload);
        }

        return $this->success(
            'Patient information saved.',
            ['patient_id' => $patientId],
            'clinical_data'  // advance to step 2
        );
    }

    /* ── Step 2: Admission + optional insurance ───────────────────────── */

    private function saveClinicalData(array $payload): array
    {
        $patientId   = (int) ($payload['patient_id']   ?? 0);
        $admissionId = (int) ($payload['admission_id'] ?? 0);

        if ($patientId === 0) {
            return $this->fail(
                ['patient_id' => 'Patient ID is missing.'],
                'Patient ID is missing. Please restart the admission process.'
            );
        }

        // ── Validate clinical fields ───────────────────────────────────────
        $admResult = $this->admissionModel->validate($payload);
        if (!$admResult['valid']) {
            return $this->fail($admResult['errors']);
        }

        // ── Build admissions insert payload ────────────────────────────────
        // 'has_insurance' is UI-only — strip it explicitly so it never touches
        // DataModel::insert(). The writableColumns() guard would also drop it,
        // but this is more explicit and prevents future confusion.
        $admData = $payload;
        unset(
            $admData['has_insurance'],
            $admData['provider_name'],
            $admData['policy_number'],
            $admData['group_number'],
            $admData['holder_name'],
            $admData['holder_dob'],
            $admData['coverage_type'],
            $admData['expiry_date']
        );

        // ── Insert or update the admissions row ────────────────────────────
        if ($admissionId > 0) {
            $this->admissionModel->update($admissionId, $admData);
        } else {
            $admissionId = (int) $this->admissionModel->insert($admData);
        }

        // ── Optional: insurance_details ────────────────────────────────────
        // Only write when the user has confirmed they have insurance coverage.
        $hasInsurance = ($payload['has_insurance'] ?? '0') === '1';

        if ($hasInsurance) {
            $insuranceData = array_merge($payload, ['admission_id' => $admissionId]);

            // Validate required insurance fields BEFORE hitting MySQL.
            // InsuranceModel has required:true for provider_name, policy_number,
            // holder_name, holder_dob — this returns a clean validation_error
            // JSON response rather than a PDOException.
            $insResult = $this->insuranceModel->validate($insuranceData);
            if (!$insResult['valid']) {
                return $this->fail(
                    $insResult['errors'],
                    'Please complete the insurance details.'
                );
            }

            // Delete any existing insurance row for this admission (edit mode).
            $existing = $this->insuranceModel->where(['admission_id' => $admissionId]);
            foreach ($existing as $row) {
                $this->insuranceModel->delete($row['id']);
            }

            $this->insuranceModel->insert($insuranceData);
        }

        return $this->success(
            'Admission recorded successfully.',
            [
                'admission_id' => $admissionId,
                'patient_id'   => $patientId,
            ],
            null  // null next_step → render completion screen
        );
    }
}
