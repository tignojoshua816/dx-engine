<?php
declare(strict_types=1);

namespace DXEngine\App\Models;

use DXEngine\Core\DataModel;

class DxGroupModel extends DataModel
{
    protected function table(): string
    {
        return 'dx_groups';
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function fieldMap(): array
    {
        return [
            'id'          => ['column' => 'id',          'type' => 'int'],
            'group_key'   => ['column' => 'group_key',   'type' => 'string', 'required' => true, 'max' => 80],
            'group_name'  => ['column' => 'group_name',  'type' => 'string', 'required' => true, 'max' => 120],
            'description' => ['column' => 'description', 'type' => 'text',   'required' => false],
            'is_active'   => ['column' => 'is_active',   'type' => 'int',    'required' => false],
        ];
    }

    public function findByKey(string $groupKey): ?array
    {
        $rows = $this->where(['group_key' => $groupKey], '', 1);
        return $rows[0] ?? null;
    }
}
