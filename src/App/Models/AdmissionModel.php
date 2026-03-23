<?php
/**
 * DX-Engine — AdmissionModel
 * -----------------------------------------------------------------------
 * Namespace : DXEngine\App\Models
 * Table     : admissions
 *
 * Column alignment  →  fieldMap key       →  SQL type
 * ────────────────────────────────────────────────────
 * patient_id         → patient_id         → INT UNSIGNED  NOT NULL  FK → patients.id
 * department_id      → department_id      → INT UNSIGNED  NOT NULL  FK → departments.id
 * triage_level       → triage_level       → TINYINT UNSIGNED NOT NULL (values 1–5)
 * chief_complaint    → chief_complaint    → VARCHAR(255)  NOT NULL
 * attending_physician→ attending_physician→ VARCHAR(120)  NULL
 * status             → status             → ENUM(pending,admitted,discharged,transferred)
 * notes              → notes              → TEXT          NULL
 * admission_date     → admission_date     → DATETIME      (auto / readonly)
 * created_at         → created_at         → DATETIME      (readonly)
 * updated_at         → updated_at         → DATETIME      (readonly)
 *
 * NOTE: `has_insurance` is a UI-only toggle defined in AdmissionDX.
 *       It is NOT a column in this table and NOT in this fieldMap.
 *       AdmissionDX::saveClinicalData() strips it before calling insert/update.
 */

declare(strict_types=1);

namespace DXEngine\App\Models;

use DXEngine\Core\DataModel;

class AdmissionModel extends DataModel
{
    protected function table(): string
    {
        return 'admissions';
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function fieldMap(): array
    {
        return [

            // ── Foreign keys ──────────────────────────────────────────────
            'patient_id' => [
                'column'   => 'patient_id',
                'type'     => 'int',
                'label'    => 'Patient',
                'required' => true,
                'rules'    => [],
                'relation' => [
                    'model'       => PatientModel::class,
                    'foreign_key' => 'patient_id',
                    'type'        => 'belongs_to',
                ],
            ],
            'department_id' => [
                'column'   => 'department_id',
                'type'     => 'int',
                'label'    => 'Department',
                'required' => true,
                'rules'    => [],
                'relation' => [
                    'model'       => DepartmentModel::class,
                    'foreign_key' => 'department_id',
                    'type'        => 'belongs_to',
                ],
            ],

            // ── Clinical fields ───────────────────────────────────────────
            'triage_level' => [
                'column'   => 'triage_level',
                'type'     => 'int',
                'label'    => 'Triage Level',
                'required' => true,
                // Regex enforces values 1–5 before the TINYINT constraint catches it.
                // Gives a friendlier validation message at PHP level.
                'rules'    => ['regex:/^[1-5]$/'],
            ],
            'chief_complaint' => [
                'column'   => 'chief_complaint',
                'type'     => 'string',
                'label'    => 'Chief Complaint',
                'required' => true,
                'rules'    => ['min:3', 'max:255'],
            ],
            'attending_physician' => [
                'column'   => 'attending_physician',
                'type'     => 'string',
                'label'    => 'Attending Physician',
                'required' => false,
                'rules'    => ['max:120'],
            ],
            'status' => [
                'column'   => 'status',
                'type'     => 'string',
                'label'    => 'Status',
                'required' => false,
                'default'  => 'pending',
                // Valid values mirror the ENUM in 003_schema.sql.
                'rules'    => ['regex:/^(pending|admitted|discharged|transferred)$/'],
            ],
            'notes' => [
                'column'   => 'notes',
                'type'     => 'text',
                'label'    => 'Clinical Notes',
                'required' => false,
                'rules'    => ['max:2000'],
            ],

            // ── Auto-managed timestamps (readonly — never written by app code) ──
            'admission_date' => [
                'column'   => 'admission_date',
                'type'     => 'datetime',
                'label'    => 'Admission Date',
                'readonly' => true,
            ],
            'created_at' => [
                'column'   => 'created_at',
                'type'     => 'datetime',
                'label'    => 'Admitted At',
                'readonly' => true,
            ],
            'updated_at' => [
                'column'   => 'updated_at',
                'type'     => 'datetime',
                'label'    => 'Updated At',
                'readonly' => true,
            ],
        ];
    }
}
