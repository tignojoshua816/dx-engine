<?php
/**
 * DX-Engine — InsuranceModel
 * -----------------------------------------------------------------------
 * Namespace : DXEngine\App\Models
 * Table     : insurance_details
 *
 * Column alignment  →  fieldMap key   →  SQL type
 * ──────────────────────────────────────────────────
 * admission_id       → admission_id   → INT UNSIGNED  NOT NULL  FK → admissions.id
 * provider_name      → provider_name  → VARCHAR(120)  NOT NULL
 * policy_number      → policy_number  → VARCHAR(60)   NOT NULL
 * group_number       → group_number   → VARCHAR(60)   NULL
 * holder_name        → holder_name    → VARCHAR(120)  NOT NULL
 * holder_dob         → holder_dob     → DATE          NOT NULL
 * coverage_type      → coverage_type  → VARCHAR(60)   NULL
 * expiry_date        → expiry_date    → DATE          NULL
 * created_at         → created_at     → DATETIME      (readonly)
 *
 * IMPORTANT — required field alignment:
 *   The DB columns provider_name, policy_number, holder_name, holder_dob are
 *   NOT NULL.  The PHP `required => true` flags here mirror that constraint so
 *   DataModel::validate() catches blank values BEFORE they reach MySQL.
 *
 *   AdmissionDX::saveClinicalData() only calls this model's validate() and
 *   insert() when has_insurance === '1', so the required flags are always
 *   contextually appropriate.
 */

declare(strict_types=1);

namespace DXEngine\App\Models;

use DXEngine\Core\DataModel;

class InsuranceModel extends DataModel
{
    protected function table(): string
    {
        return 'insurance_details';
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function fieldMap(): array
    {
        return [

            // ── Parent FK ─────────────────────────────────────────────────
            'admission_id' => [
                'column'   => 'admission_id',
                'type'     => 'int',
                'label'    => 'Admission',
                'required' => true,
                'rules'    => [],
                'relation' => [
                    'model'       => AdmissionModel::class,
                    'foreign_key' => 'admission_id',
                    'type'        => 'belongs_to',
                ],
            ],

            // ── Required fields (mirror NOT NULL columns in SQL) ──────────
            'provider_name' => [
                'column'   => 'provider_name',
                'type'     => 'string',
                'label'    => 'Insurance Provider',
                'required' => true,
                'rules'    => ['min:2', 'max:120'],
            ],
            'policy_number' => [
                'column'   => 'policy_number',
                'type'     => 'string',
                'label'    => 'Policy Number',
                'required' => true,
                'rules'    => ['min:2', 'max:60'],
            ],
            'holder_name' => [
                'column'   => 'holder_name',
                'type'     => 'string',
                'label'    => 'Policy Holder Name',
                'required' => true,
                'rules'    => ['min:2', 'max:120'],
            ],
            'holder_dob' => [
                'column'   => 'holder_dob',
                'type'     => 'date',
                'label'    => 'Policy Holder Date of Birth',
                'required' => true,
                'rules'    => [],
            ],

            // ── Optional fields (NULL columns in SQL) ─────────────────────
            'group_number' => [
                'column'   => 'group_number',
                'type'     => 'string',
                'label'    => 'Group Number',
                'required' => false,
                'rules'    => ['max:60'],
            ],
            'coverage_type' => [
                'column'   => 'coverage_type',
                'type'     => 'string',
                'label'    => 'Coverage Type',
                'required' => false,
                'rules'    => ['max:60'],
            ],
            'expiry_date' => [
                'column'   => 'expiry_date',
                'type'     => 'date',
                'label'    => 'Policy Expiry Date',
                'required' => false,
                'rules'    => [],
            ],

            // ── Auto-managed timestamp (readonly) ─────────────────────────
            'created_at' => [
                'column'   => 'created_at',
                'type'     => 'datetime',
                'label'    => 'Recorded At',
                'readonly' => true,
            ],
        ];
    }
}
