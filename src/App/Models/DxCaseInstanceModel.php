<?php
declare(strict_types=1);

namespace DXEngine\App\Models;

use DXEngine\Core\DataModel;

class DxCaseInstanceModel extends DataModel
{
    protected function table(): string
    {
        return 'dx_case_instances';
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function fieldMap(): array
    {
        return [
            'id'                        => ['column' => 'id',                        'type' => 'int'],
            'case_ref'                  => ['column' => 'case_ref',                  'type' => 'string', 'required' => true, 'max' => 40],
            'case_type_id'              => ['column' => 'case_type_id',              'type' => 'int',    'required' => true],
            'business_key'              => ['column' => 'business_key',              'type' => 'string', 'required' => false, 'max' => 120],
            'status'                    => ['column' => 'status',                    'type' => 'string', 'required' => true, 'max' => 20],
            'current_stage_key'         => ['column' => 'current_stage_key',         'type' => 'string', 'required' => true, 'max' => 100],
            'initiator_user_id'         => ['column' => 'initiator_user_id',         'type' => 'int',    'required' => false],
            'current_assignee_user_id'  => ['column' => 'current_assignee_user_id',  'type' => 'int',    'required' => false],
            'current_assignee_group_id' => ['column' => 'current_assignee_group_id', 'type' => 'int',    'required' => false],
            'is_locked'                 => ['column' => 'is_locked',                 'type' => 'int',    'required' => false],
            'locked_by_user_id'         => ['column' => 'locked_by_user_id',         'type' => 'int',    'required' => false],
            'payload_json'              => ['column' => 'payload_json',              'type' => 'text',   'required' => false],
        ];
    }
}
