<?php
/**
 * DX-Engine — DepartmentModel
 * -----------------------------------------------------------------------
 * Namespace : DXEngine\App\Models
 * Table     : departments
 *
 * Column alignment  →  fieldMap key  →  SQL type
 * ──────────────────────────────────────────────
 * code              → code           → VARCHAR(20)  NOT NULL UNIQUE
 * name              → name           → VARCHAR(100) NOT NULL
 * is_active         → is_active      → TINYINT(1)  DEFAULT 1
 *
 * Used by AdmissionDX::preProcess() to populate the department SELECT.
 * DataModel::where(['is_active' => 1]) uses 'is_active' as the raw SQL
 * column name — this matches the physical column above.
 */

declare(strict_types=1);

namespace DXEngine\App\Models;

use DXEngine\Core\DataModel;

class DepartmentModel extends DataModel
{
    protected function table(): string
    {
        return 'departments';
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function fieldMap(): array
    {
        return [
            'code' => [
                'column'   => 'code',
                'type'     => 'string',
                'label'    => 'Department Code',
                'required' => true,
                'rules'    => ['max:20'],
            ],
            'name' => [
                'column'   => 'name',
                'type'     => 'string',
                'label'    => 'Department Name',
                'required' => true,
                'rules'    => ['max:100'],
            ],
            'is_active' => [
                'column'   => 'is_active',
                'type'     => 'bool',
                'label'    => 'Active',
                'required' => false,
                'default'  => true,
            ],
        ];
    }
}
