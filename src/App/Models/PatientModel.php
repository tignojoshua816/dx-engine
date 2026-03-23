<?php
/**
 * DX-Engine — PatientModel
 * -----------------------------------------------------------------------
 * Namespace : DXEngine\App\Models
 * Table     : patients
 *
 * Column alignment  →  fieldMap key  →  SQL type
 * ──────────────────────────────────────────────
 * first_name        → first_name     → VARCHAR(80)  NOT NULL
 * last_name         → last_name      → VARCHAR(80)  NOT NULL
 * date_of_birth     → date_of_birth  → DATE         NOT NULL
 * gender            → gender         → ENUM(...)    NOT NULL
 * contact_phone     → contact_phone  → VARCHAR(20)  NOT NULL
 * contact_email     → contact_email  → VARCHAR(120) NULL
 * address           → address        → TEXT         NULL
 * created_at        → created_at     → DATETIME     (readonly)
 * updated_at        → updated_at     → DATETIME     (readonly)
 *
 * gender ENUM values must match AdmissionDX::buildStepPatientInfo() options
 * and the SQL ENUM in 003_schema.sql:
 *   'male' | 'female' | 'other' | 'prefer_not'
 */

declare(strict_types=1);

namespace DXEngine\App\Models;

use DXEngine\Core\DataModel;

class PatientModel extends DataModel
{
    protected function table(): string
    {
        return 'patients';
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function fieldMap(): array
    {
        return [

            // ── Personal details ──────────────────────────────────────────
            'first_name' => [
                'column'   => 'first_name',
                'type'     => 'string',
                'label'    => 'First Name',
                'required' => true,
                'rules'    => ['min:2', 'max:80', 'regex:/^[a-zA-Z\s\-\']+$/'],
            ],
            'last_name' => [
                'column'   => 'last_name',
                'type'     => 'string',
                'label'    => 'Last Name',
                'required' => true,
                'rules'    => ['min:2', 'max:80', 'regex:/^[a-zA-Z\s\-\']+$/'],
            ],
            'date_of_birth' => [
                'column'   => 'date_of_birth',
                'type'     => 'date',
                'label'    => 'Date of Birth',
                'required' => true,
                'rules'    => [],
            ],
            'gender' => [
                'column'   => 'gender',
                'type'     => 'string',
                'label'    => 'Gender',
                'required' => true,
                // Regex must match the SQL ENUM values exactly.
                'rules'    => ['regex:/^(male|female|other|prefer_not)$/'],
            ],

            // ── Contact ───────────────────────────────────────────────────
            'contact_phone' => [
                'column'   => 'contact_phone',
                'type'     => 'phone',
                'label'    => 'Contact Phone',
                'required' => true,
                'rules'    => [],
            ],
            'contact_email' => [
                'column'   => 'contact_email',
                'type'     => 'email',
                'label'    => 'Contact Email',
                'required' => false,
                'rules'    => ['max:120'],
            ],
            'address' => [
                'column'   => 'address',
                'type'     => 'text',
                'label'    => 'Address',
                'required' => false,
                'rules'    => ['max:500'],
            ],

            // ── Auto-managed timestamps (never written by app code) ────────
            'created_at' => [
                'column'   => 'created_at',
                'type'     => 'datetime',
                'label'    => 'Created At',
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
