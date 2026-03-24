<?php
declare(strict_types=1);

namespace DXEngine\App\Models;

use DXEngine\Core\DataModel;

class DxUserModel extends DataModel
{
    protected function table(): string
    {
        return 'dx_users';
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function fieldMap(): array
    {
        return [
        'id'            => ['column' => 'id',            'type' => 'int'],
        'username'      => ['column' => 'username',      'type' => 'string', 'required' => true, 'max' => 80],
        'email'         => ['column' => 'email',         'type' => 'string', 'required' => false, 'max' => 190],
        'display_name'  => ['column' => 'display_name',  'type' => 'string', 'required' => false, 'max' => 150],
        'password_hash' => ['column' => 'password_hash', 'type' => 'string', 'required' => false, 'max' => 255],
        'is_active'     => ['column' => 'is_active',     'type' => 'int', 'required' => false],
        ];
    }

    public function findByUsername(string $username): ?array
    {
        $rows = $this->where(['username' => $username], '', 1);
        return $rows[0] ?? null;
    }
}
