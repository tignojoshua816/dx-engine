<?php
declare(strict_types=1);

namespace DXEngine\App\Models;

use DXEngine\Core\DataModel;

class DxUserGroupModel extends DataModel
{
    protected function table(): string
    {
        return 'dx_user_groups';
    }

    protected function primaryKey(): string
    {
        return 'user_id';
    }

    protected function fieldMap(): array
    {
        return [
            'user_id'    => ['column' => 'user_id',    'type' => 'int', 'required' => true],
            'group_id'   => ['column' => 'group_id',   'type' => 'int', 'required' => true],
            'is_primary' => ['column' => 'is_primary', 'type' => 'int', 'required' => false],
        ];
    }

    public function groupsForUser(int $userId): array
    {
        return $this->where(['user_id' => $userId], '', 200);
    }
}
