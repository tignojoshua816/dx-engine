<?php
declare(strict_types=1);

namespace DXEngine\App\Models;

use DXEngine\Core\DataModel;

class DxCaseEventModel extends DataModel
{
    protected function table(): string
    {
        return 'dx_case_events';
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function fieldMap(): array
    {
        return [
            'id'               => ['column' => 'id',               'type' => 'int'],
            'case_instance_id' => ['column' => 'case_instance_id', 'type' => 'int',    'required' => true],
            'assignment_id'    => ['column' => 'assignment_id',    'type' => 'int',    'required' => false],
            'event_type'       => ['column' => 'event_type',       'type' => 'string', 'required' => true, 'max' => 80],
            'actor_user_id'    => ['column' => 'actor_user_id',    'type' => 'int',    'required' => false],
            'details_json'     => ['column' => 'details_json',     'type' => 'text',   'required' => false],
        ];
    }
}
