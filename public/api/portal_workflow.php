<?php
declare(strict_types=1);

use DXEngine\Core\Autoloader;
use DXEngine\Core\DataModel;
use DXEngine\Core\DxWorklistService;
use DXEngine\App\Models\DxCaseEventModel;
use DXEngine\App\Models\DxCaseInstanceModel;
use DXEngine\App\Models\DxAssignmentModel;
use DXEngine\App\Models\DxCaseTypeModel;
use DXEngine\App\Models\DxRoutingRuleModel;
use DXEngine\App\Models\DxUserModel;
use DXEngine\App\Models\DxGroupModel;

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!defined('DX_ROOT')) {
    define('DX_ROOT', dirname(__FILE__, 3));
}

require_once DX_ROOT . '/src/Core/Autoloader.php';
Autoloader::register(DX_ROOT . '/src');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('DXSID');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => 1,
    ]);
}

$pdo = require DX_ROOT . '/config/database.php';
DataModel::boot($pdo);

$service = new DxWorklistService();
$events  = new DxCaseEventModel();
$cases   = new DxCaseInstanceModel();
$assigns = new DxAssignmentModel();
$caseTypes = new DxCaseTypeModel();
$rules = new DxRoutingRuleModel();
$users = new DxUserModel();
$groups = new DxGroupModel();

function pwRespond(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, ['GET', 'POST'], true)) {
        pwRespond(405, ['status' => 'error', 'message' => 'Method not allowed.']);
    }

    $action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

    $raw = file_get_contents('php://input');
    $body = [];
    if ($raw !== false && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $body = $decoded;
        }
    }

    // Public case initiation (no login required).
    if ($method === 'POST' && $action === 'start_case') {
        $applicantName = trim((string)($body['applicant_name'] ?? ''));
        $program = trim((string)($body['program'] ?? ''));
        $email = trim((string)($body['email'] ?? ''));

        if ($applicantName === '' || $program === '') {
            pwRespond(400, ['status' => 'error', 'message' => 'applicant_name and program are required.']);
        }

        $typeRows = $caseTypes->where(['case_type_key' => 'educational_institution_admission'], '', 1);
        $caseType = $typeRows[0] ?? null;
        if (!$caseType) {
            pwRespond(400, ['status' => 'error', 'message' => 'Educational admission case type is not provisioned.']);
        }

        $caseTypeId = (int)$caseType['id'];
        $ref = 'EDU-ADM-' . date('Ymd') . '-' . strtoupper(substr(md5((string)microtime(true) . $applicantName . $program), 0, 6));
        $businessKey = preg_replace('/[^A-Z0-9\-]/', '-', strtoupper($email !== '' ? $email : $applicantName));

        $caseId = (int)$cases->insert([
            'case_ref' => $ref,
            'case_type_id' => $caseTypeId,
            'business_key' => $businessKey,
            'status' => 'active',
            'current_stage_key' => 'admissions_review',
            'initiator_user_id' => null,
            'current_assignee_user_id' => null,
            'current_assignee_group_id' => 1202,
            'is_locked' => 0,
            'payload_json' => json_encode([
                'applicant_name' => $applicantName,
                'program' => $program,
                'email' => $email,
                'started_public' => true,
                'started_at' => date('c'),
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $assignmentId = (int)$assigns->insert([
            'case_instance_id' => $caseId,
            'stage_key' => 'admissions_review',
            'status' => 'ready',
            'assigned_to_type' => 'group',
            'assigned_user_id' => null,
            'assigned_group_id' => 1202,
            'priority' => 50,
            'is_locked' => 0,
            'claimed_by_user_id' => null,
            'lock_owner_user_id' => null,
        ]);

        $events->insert([
            'case_instance_id' => $caseId,
            'assignment_id' => $assignmentId,
            'event_type' => 'case.started_public',
            'actor_user_id' => null,
            'details_json' => json_encode([
                'applicant_name' => $applicantName,
                'program' => $program,
                'email' => $email,
                'current_stage' => 'admissions_review',
            ], JSON_UNESCAPED_UNICODE),
        ]);

        pwRespond(200, [
            'status' => 'success',
            'data' => [
                'case_id' => $caseId,
                'case_ref' => $ref,
                'current_stage_key' => 'admissions_review',
                'message' => 'Admission case initiated successfully. Please login to monitor progress and complete student-required actions.',
            ],
        ]);
    }

    $user = $service->currentUser($_SESSION);

    if ($method === 'GET' && ($action === '' || $action === 'overview')) {
        $queues = $service->queuesForUser((int)$user['id']);
        $caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
        $case   = $caseId > 0 ? $cases->find($caseId) : null;
        $trail  = $caseId > 0 ? $events->where([], "case_instance_id = {$caseId}", 500) : [];
        pwRespond(200, [
            'status' => 'success',
            'data' => [
                'user' => [
                    'id' => (int)$user['id'],
                    'username' => (string)$user['username'],
                    'display_name' => (string)($user['display_name'] ?? $user['username']),
                ],
                'queues' => $queues,
                'case' => $case,
                'events' => $trail,
            ],
        ]);
    }

    if ($method === 'GET' && $action === 'case_status') {
        $caseId = (int)($_GET['case_id'] ?? 0);
        if ($caseId <= 0) {
            pwRespond(400, ['status' => 'error', 'message' => 'case_id is required.']);
        }

        $case = $cases->find($caseId);
        if (!$case) {
            pwRespond(404, ['status' => 'error', 'message' => 'Case not found.']);
        }

        $payload = json_decode((string)($case['payload_json'] ?? '{}'), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $latestRows = $assigns->where(['case_instance_id' => $caseId], 'id DESC', 1);
        $latest = $latestRows[0] ?? null;

        $claimedBy = null;
        if ($latest && !empty($latest['claimed_by_user_id'])) {
            $u = $users->find((int)$latest['claimed_by_user_id']);
            if ($u) {
                $claimedBy = [
                    'id' => (int)$u['id'],
                    'username' => (string)$u['username'],
                    'display_name' => (string)($u['display_name'] ?? $u['username']),
                ];
            }
        }

        $assignedGroup = null;
        if ($latest && !empty($latest['assigned_group_id'])) {
            $g = $groups->find((int)$latest['assigned_group_id']);
            if ($g) {
                $assignedGroup = [
                    'id' => (int)$g['id'],
                    'group_key' => (string)$g['group_key'],
                    'group_name' => (string)$g['group_name'],
                ];
            }
        }

        $trail = $events->where(['case_instance_id' => $caseId], 'id DESC', 50);

        pwRespond(200, [
            'status' => 'success',
            'data' => [
                'case' => [
                    'id' => (int)$case['id'],
                    'case_ref' => (string)$case['case_ref'],
                    'status' => (string)$case['status'],
                    'current_stage_key' => (string)$case['current_stage_key'],
                    'payload' => $payload,
                ],
                'current_assignment' => $latest,
                'current_processor' => [
                    'claimed_by_user' => $claimedBy,
                    'assigned_group' => $assignedGroup,
                ],
                'event_trail' => $trail,
            ],
        ]);
    }

    if ($method === 'POST' && $action === 'claim') {
        $assignmentId = (int)($body['assignment_id'] ?? 0);
        $lockCase = (bool)($body['lock_case'] ?? true);
        $data = $service->claimAssignment($assignmentId, (int)$user['id'], $lockCase);
        pwRespond(200, ['status' => 'success', 'data' => $data]);
    }

    if ($method === 'POST' && $action === 'release') {
        $assignmentId = (int)($body['assignment_id'] ?? 0);
        $data = $service->releaseAssignment($assignmentId, (int)$user['id']);
        pwRespond(200, ['status' => 'success', 'data' => $data]);
    }

    if ($method === 'POST' && $action === 'process') {
        $assignmentId = (int)($body['assignment_id'] ?? 0);
        $actionKey = (string)($body['action_key'] ?? 'submit');
        $payload = (array)($body['payload'] ?? []);
        $data = $service->processAssignment($assignmentId, (int)$user['id'], $actionKey, $payload);
        pwRespond(200, ['status' => 'success', 'data' => $data]);
    }

    if ($method === 'GET' && $action === 'events') {
        $caseId = (int)($_GET['case_id'] ?? 0);
        if ($caseId <= 0) {
            pwRespond(400, ['status' => 'error', 'message' => 'case_id is required.']);
        }
        $trail = $events->where([], "case_instance_id = {$caseId}", 1000);
        pwRespond(200, ['status' => 'success', 'data' => $trail]);
    }

    if ($method === 'GET' && $action === 'assignment') {
        $id = (int)($_GET['assignment_id'] ?? 0);
        if ($id <= 0) {
            pwRespond(400, ['status' => 'error', 'message' => 'assignment_id is required.']);
        }
        $row = $assigns->find($id);
        if (!$row) {
            pwRespond(404, ['status' => 'error', 'message' => 'Assignment not found.']);
        }
        pwRespond(200, ['status' => 'success', 'data' => $row]);
    }

    if ($method === 'POST' && $action === 'update_payload') {
        $caseId = (int)($body['case_id'] ?? 0);
        $payload = (array)($body['payload'] ?? []);
        
        if ($caseId <= 0) {
            pwRespond(400, ['status' => 'error', 'message' => 'case_id is required.']);
        }
        
        $case = $cases->find($caseId);
        if (!$case) {
            pwRespond(404, ['status' => 'error', 'message' => 'Case not found.']);
        }
        
        // Merge existing payload with new data
        $existingPayload = json_decode((string)($case['payload_json'] ?? '{}'), true);
        if (!is_array($existingPayload)) {
            $existingPayload = [];
        }
        
        $mergedPayload = array_merge($existingPayload, $payload);
        
        $cases->update($caseId, [
            'payload_json' => json_encode($mergedPayload, JSON_UNESCAPED_UNICODE),
        ]);
        
        pwRespond(200, ['status' => 'success', 'message' => 'Payload updated.']);
    }

    if ($method === 'POST' && $action === 'complete_case') {
        $caseId = (int)($body['case_id'] ?? 0);
        
        if ($caseId <= 0) {
            pwRespond(400, ['status' => 'error', 'message' => 'case_id is required.']);
        }
        
        $cases->update($caseId, [
            'status' => 'completed',
            'current_stage_key' => 'completed',
        ]);
        
        $events->insert([
            'case_instance_id' => $caseId,
            'assignment_id' => null,
            'event_type' => 'case.completed',
            'actor_user_id' => (int)$user['id'],
            'details_json' => json_encode([
                'completed_by' => $user['username'],
                'completed_at' => date('c'),
            ], JSON_UNESCAPED_UNICODE),
        ]);
        
        pwRespond(200, ['status' => 'success', 'message' => 'Case completed.']);
    }

    pwRespond(400, ['status' => 'error', 'message' => 'Unsupported workflow action.']);
} catch (Throwable $e) {
    pwRespond(400, [
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
