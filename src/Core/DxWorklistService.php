<?php
declare(strict_types=1);

namespace DXEngine\Core;

use DXEngine\App\Models\DxUserModel;
use DXEngine\App\Models\DxGroupModel;
use DXEngine\App\Models\DxUserGroupModel;
use DXEngine\App\Models\DxCaseTypeModel;
use DXEngine\App\Models\DxCaseInstanceModel;
use DXEngine\App\Models\DxAssignmentModel;
use DXEngine\App\Models\DxRoutingRuleModel;
use DXEngine\App\Models\DxCaseEventModel;

/**
 * DX workflow + RBAC service layer for:
 * - session-based current user resolution
 * - queue retrieval (my/group/inactive/history)
 * - claim/release/process assignment lifecycle
 * - runtime routing rule evaluation and stage transition
 */
class DxWorklistService
{
    private DxUserModel $users;
    private DxGroupModel $groups;
    private DxUserGroupModel $memberships;
    private DxCaseTypeModel $caseTypes;
    private DxCaseInstanceModel $cases;
    private DxAssignmentModel $assignments;
    private DxRoutingRuleModel $rules;
    private DxCaseEventModel $events;

    public function __construct()
    {
        $this->users       = new DxUserModel();
        $this->groups      = new DxGroupModel();
        $this->memberships = new DxUserGroupModel();
        $this->caseTypes   = new DxCaseTypeModel();
        $this->cases       = new DxCaseInstanceModel();
        $this->assignments = new DxAssignmentModel();
        $this->rules       = new DxRoutingRuleModel();
        $this->events      = new DxCaseEventModel();
    }

    public function currentUser(array $session): array
    {
        $userId = (int)($session['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new \RuntimeException('No authenticated session user found.');
        }
        $user = $this->users->find($userId);
        if (!$user || (int)($user['is_active'] ?? 0) !== 1) {
            throw new \RuntimeException('Session user is invalid or inactive.');
        }
        return $user;
    }

    public function queuesForUser(int $userId): array
    {
        $groupRows = $this->memberships->groupsForUser($userId);
        $groupIds  = array_map(static fn($g) => (int)$g['group_id'], $groupRows);

        $my = array_values(array_filter(
            $this->assignments->where([], 'id DESC', 500),
            static fn(array $r): bool =>
                (int)($r['assigned_user_id'] ?? 0) === $userId
                && in_array((string)($r['status'] ?? ''), ['ready','claimed','in_progress'], true)
        ));

        $group = [];
        if ($groupIds) {
            $group = array_values(array_filter(
                $this->assignments->where([], 'id DESC', 500),
                static fn(array $r): bool =>
                    in_array((int)($r['assigned_group_id'] ?? 0), $groupIds, true)
                    && (string)($r['status'] ?? '') === 'ready'
            ));
        }

        $inactive = array_values(array_filter(
            $this->assignments->where([], 'id DESC', 500),
            static fn(array $r): bool =>
                in_array((string)($r['status'] ?? ''), ['completed','cancelled','reassigned'], true)
        ));
        $inactive = array_slice($inactive, 0, 50);

        $history = array_values(array_filter(
            $this->assignments->where([], 'id DESC', 1000),
            static fn(array $r): bool => (int)($r['claimed_by_user_id'] ?? 0) === $userId
        ));
        $history = array_slice($history, 0, 100);

        return [
            'my_queue'       => $my,
            'group_queue'    => $group,
            'inactive_queue' => $inactive,
            'history'        => $history,
        ];
    }

    public function claimAssignment(int $assignmentId, int $userId, bool $lockCase = true): array
    {
        $assignment = $this->assignments->find($assignmentId);
        if (!$assignment) {
            throw new \RuntimeException('Assignment not found.');
        }
        if (($assignment['status'] ?? '') !== 'ready') {
            throw new \RuntimeException('Assignment is not claimable.');
        }

        $this->assignments->update($assignmentId, [
            'status'             => 'claimed',
            'claimed_by_user_id' => $userId,
            'lock_owner_user_id' => $lockCase ? $userId : null,
            'is_locked'          => $lockCase ? 1 : 0,
        ]);

        $caseId = (int)$assignment['case_instance_id'];
        $this->cases->update($caseId, [
            'current_assignee_user_id' => $userId,
            'is_locked'                => $lockCase ? 1 : 0,
            'locked_by_user_id'        => $lockCase ? $userId : null,
            'status'                   => 'active',
        ]);

        $this->logEvent($caseId, $assignmentId, 'assignment.claimed', $userId, [
            'lock_case' => $lockCase,
        ]);

        return $this->assignments->find($assignmentId) ?? [];
    }

    public function releaseAssignment(int $assignmentId, int $userId): array
    {
        $assignment = $this->assignments->find($assignmentId);
        if (!$assignment) {
            throw new \RuntimeException('Assignment not found.');
        }
        if ((int)($assignment['claimed_by_user_id'] ?? 0) !== $userId) {
            throw new \RuntimeException('Only the claiming user can release this assignment.');
        }

        $this->assignments->update($assignmentId, [
            'status'             => 'ready',
            'claimed_by_user_id' => null,
            'is_locked'          => 0,
            'lock_owner_user_id' => null,
        ]);

        $caseId = (int)$assignment['case_instance_id'];
        $this->cases->update($caseId, [
            'current_assignee_user_id' => null,
            'is_locked'                => 0,
            'locked_by_user_id'        => null,
            'status'                   => 'pending',
        ]);

        $this->logEvent($caseId, $assignmentId, 'assignment.released', $userId, []);

        return $this->assignments->find($assignmentId) ?? [];
    }

    public function processAssignment(int $assignmentId, int $userId, string $actionKey, array $payload): array
    {
        $assignment = $this->assignments->find($assignmentId);
        if (!$assignment) {
            throw new \RuntimeException('Assignment not found.');
        }
        if ((int)($assignment['claimed_by_user_id'] ?? 0) !== $userId) {
            throw new \RuntimeException('Assignment is not claimed by the current user.');
        }

        $caseId = (int)$assignment['case_instance_id'];
        $case   = $this->cases->find($caseId);
        if (!$case) {
            throw new \RuntimeException('Case instance not found.');
        }

        $caseTypeId = (int)($case['case_type_id'] ?? 0);
        $fromStage  = (string)($case['current_stage_key'] ?? '');
        $rule       = $this->selectRule($caseTypeId, $fromStage, $actionKey, $payload);

        $nextStage = (string)($rule['next_stage_key'] ?? '');
        $routeType = (string)($rule['route_to_type'] ?? 'group');
        $routeUser = isset($rule['route_to_user_id']) ? (int)$rule['route_to_user_id'] : null;
        $routeGrp  = isset($rule['route_to_group_id']) ? (int)$rule['route_to_group_id'] : null;

        $this->assignments->update($assignmentId, [
            'status'    => 'completed',
            'is_locked' => 0,
        ]);

        $caseUpdate = [
            'current_stage_key'         => $nextStage !== '' ? $nextStage : $fromStage,
            'current_assignee_user_id'  => null,
            'current_assignee_group_id' => null,
            'is_locked'                 => 0,
            'locked_by_user_id'         => null,
            'status'                    => $nextStage !== '' ? 'active' : 'completed',
            'payload_json'              => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ];
        $this->cases->update($caseId, $caseUpdate);

        $newAssignment = null;
        if ($nextStage !== '') {
            $newAssignmentId = (int)$this->assignments->insert([
                'case_instance_id'  => $caseId,
                'stage_key'         => $nextStage,
                'status'            => 'ready',
                'assigned_to_type'  => $routeType === 'user' ? 'user' : 'group',
                'assigned_user_id'  => $routeType === 'user' ? $routeUser : null,
                'assigned_group_id' => $routeType === 'group' ? $routeGrp : null,
                'priority'          => (int)($rule['priority'] ?? 100),
                'is_locked'         => 0,
            ]);
            $newAssignment = $this->assignments->find($newAssignmentId);
        }

        $this->logEvent($caseId, $assignmentId, 'assignment.processed', $userId, [
            'action_key'      => $actionKey,
            'from_stage'      => $fromStage,
            'next_stage'      => $nextStage,
            'created_next_id' => $newAssignment['id'] ?? null,
        ]);

        return [
            'case'            => $this->cases->find($caseId),
            'completed_task'  => $this->assignments->find($assignmentId),
            'next_assignment' => $newAssignment,
        ];
    }

    private function selectRule(int $caseTypeId, string $fromStage, string $actionKey, array $payload): array
    {
        $candidates = $this->rules->rulesFor((string)$caseTypeId, $fromStage, $actionKey);
        usort($candidates, static fn($a, $b) => ((int)$a['priority']) <=> ((int)$b['priority']));

        foreach ($candidates as $rule) {
            $json = $rule['condition_json'] ?? null;
            if ($json === null || $json === '' || $json === 'null') {
                return $rule;
            }
            $decoded = is_array($json) ? $json : json_decode((string)$json, true);
            if (!is_array($decoded)) {
                continue;
            }
            if ($this->matchesConditions($decoded, $payload)) {
                return $rule;
            }
        }

        throw new \RuntimeException("No routing rule matched for action '{$actionKey}' from stage '{$fromStage}'.");
    }

    private function matchesConditions(array $conditions, array $payload): bool
    {
        foreach ($conditions as $rule) {
            $field = (string)($rule['field'] ?? '');
            $op    = (string)($rule['operator'] ?? 'eq');
            $rv    = (string)($rule['value'] ?? '');
            $fv    = (string)($payload[$field] ?? '');

            $ok = match ($op) {
                'eq'  => $fv === $rv,
                'neq' => $fv !== $rv,
                'gt'  => (float)$fv > (float)$rv,
                'lt'  => (float)$fv < (float)$rv,
                'in'  => in_array($fv, array_map('trim', explode(',', $rv)), true),
                'nin' => !in_array($fv, array_map('trim', explode(',', $rv)), true),
                default => true,
            };

            if (!$ok) {
                return false;
            }
        }
        return true;
    }

    private function logEvent(int $caseId, int $assignmentId, string $eventType, int $actorUserId, array $details): void
    {
        $this->events->insert([
            'case_instance_id' => $caseId,
            'assignment_id'    => $assignmentId,
            'event_type'       => $eventType,
            'actor_user_id'    => $actorUserId,
            'details_json'     => json_encode($details, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
