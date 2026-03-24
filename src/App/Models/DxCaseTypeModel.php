<?php
declare(strict_types=1);

namespace DXEngine\App\Models;

use DXEngine\Core\DataModel;

class DxCaseTypeModel extends DataModel
{
    protected function table(): string
    {
        return 'dx_case_types';
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function fieldMap(): array
    {
        return [
            'id'            => ['column' => 'id',            'type' => 'int'],
            'case_type_key' => ['column' => 'case_type_key', 'type' => 'string', 'required' => true, 'max' => 120],
            'title'         => ['column' => 'title',         'type' => 'string', 'required' => true, 'max' => 150],
            'description'   => ['column' => 'description',   'type' => 'text',   'required' => false],
            'is_active'     => ['column' => 'is_active',     'type' => 'int',    'required' => false],
        ];
    }
}
