<?php
declare(strict_types=1);

namespace DXEngine\App\Models;

use DXEngine\Core\DataModel;

class DxRoutingRuleModel extends DataModel
{
    protected function table(): string
    {
        return 'dx_routing_rules';
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function fieldMap(): array
    {
        return [
            'id'                 => ['column' => 'id',                 'type' => 'int'],
            'case_type_id'       => ['column' => 'case_type_id',       'type' => 'int',    'required' => true],
            'from_stage_key'     => ['column' => 'from_stage_key',     'type' => 'string', 'required' => true, 'max' => 100],
            'action_key'         => ['column' => 'action_key',         'type' => 'string', 'required' => true, 'max' => 100],
            'priority'           => ['column' => 'priority',           'type' => 'int',    'required' => false],
            'condition_json'     => ['column' => 'condition_json',     'type' => 'text',   'required' => false],
            'route_to_type'      => ['column' => 'route_to_type',      'type' => 'string', 'required' => true, 'max' => 20],
            'route_to_user_id'   => ['column' => 'route_to_user_id',   'type' => 'int',    'required' => false],
            'route_to_group_id'  => ['column' => 'route_to_group_id',  'type' => 'int',    'required' => false],
            'next_stage_key'     => ['column' => 'next_stage_key',     'type' => 'string', 'required' => true, 'max' => 100],
            'lock_case_on_claim' => ['column' => 'lock_case_on_claim', 'type' => 'int',    'required' => false],
            'is_active'          => ['column' => 'is_active',          'type' => 'int',    'required' => false],
        ];
    }

    public function rulesFor(string $caseTypeId, string $fromStage, string $actionKey): array
    {
        $safeCaseTypeId = (int)$caseTypeId;
        $safeFromStage  = addslashes($fromStage);
        $safeActionKey  = addslashes($actionKey);

        $where = "case_type_id = {$safeCaseTypeId}"
               . " AND from_stage_key = '{$safeFromStage}'"
               . " AND action_key = '{$safeActionKey}'"
               . " AND is_active = 1";

        return $this->where([], $where, 500);
    }
}
