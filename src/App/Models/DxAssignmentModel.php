<?php
declare(strict_types=1);

namespace DXEngine\App\Models;

use DXEngine\Core\DataModel;

class DxAssignmentModel extends DataModel
{
    protected function table(): string
    {
        return 'dx_assignments';
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function fieldMap(): array
    {
        return [
            'id'                 => ['column' => 'id',                 'type' => 'int'],
            'case_instance_id'   => ['column' => 'case_instance_id',   'type' => 'int',    'required' => true],
            'stage_key'          => ['column' => 'stage_key',          'type' => 'string', 'required' => true, 'max' => 100],
            'status'             => ['column' => 'status',             'type' => 'string', 'required' => true, 'max' => 20],
            'assigned_to_type'   => ['column' => 'assigned_to_type',   'type' => 'string', 'required' => true, 'max' => 20],
            'assigned_user_id'   => ['column' => 'assigned_user_id',   'type' => 'int',    'required' => false],
            'assigned_group_id'  => ['column' => 'assigned_group_id',  'type' => 'int',    'required' => false],
            'claimed_by_user_id' => ['column' => 'claimed_by_user_id', 'type' => 'int',    'required' => false],
            'priority'           => ['column' => 'priority',           'type' => 'int',    'required' => false],
            'is_locked'          => ['column' => 'is_locked',          'type' => 'int',    'required' => false],
            'lock_owner_user_id' => ['column' => 'lock_owner_user_id', 'type' => 'int',    'required' => false],
        ];
    }
}
