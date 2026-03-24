<?php
declare(strict_types=1);

use DXEngine\Core\Autoloader;
use DXEngine\Core\DataModel;
use DXEngine\Core\DxWorklistService;
use DXEngine\App\Models\DxUserModel;
use DXEngine\App\Models\DxGroupModel;
use DXEngine\App\Models\DxUserGroupModel;
use DXEngine\App\Models\DxRoutingRuleModel;

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

$service     = new DxWorklistService();
$userModel   = new DxUserModel();
$groupModel  = new DxGroupModel();
$memberModel = new DxUserGroupModel();
$ruleModel   = new DxRoutingRuleModel();

try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, ['GET', 'POST'], true)) {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
        exit;
    }

    // Basic auth check through session.
    $service->currentUser($_SESSION);

    $action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

    if ($method === 'GET' && ($action === '' || $action === 'summary')) {
        echo json_encode([
            'status' => 'success',
            'data' => [
                'users'       => $userModel->where([], '', 200),
                'groups'      => $groupModel->where([], '', 200),
                'memberships' => $memberModel->where([], '', 500),
                'routing'     => $ruleModel->where([], '', 500),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    $raw = file_get_contents('php://input');
    $body = [];
    if ($raw !== false && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $body = $decoded;
        }
    }

    if ($method === 'POST' && $action === 'create_user') {
        $id = (int)$userModel->insert([
            'username'     => (string)($body['username'] ?? ''),
            'email'        => (string)($body['email'] ?? ''),
            'display_name' => (string)($body['display_name'] ?? ''),
            'is_active'    => (int)($body['is_active'] ?? 1),
        ]);
        echo json_encode(['status' => 'success', 'data' => $userModel->find($id)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    if ($method === 'POST' && $action === 'create_group') {
        $id = (int)$groupModel->insert([
            'group_key'   => (string)($body['group_key'] ?? ''),
            'group_name'  => (string)($body['group_name'] ?? ''),
            'description' => (string)($body['description'] ?? ''),
            'is_active'   => (int)($body['is_active'] ?? 1),
        ]);
        echo json_encode(['status' => 'success', 'data' => $groupModel->find($id)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    if ($method === 'POST' && $action === 'add_membership') {
        $memberModel->insert([
            'user_id'    => (int)($body['user_id'] ?? 0),
            'group_id'   => (int)($body['group_id'] ?? 0),
            'is_primary' => (int)($body['is_primary'] ?? 0),
        ]);
        echo json_encode(['status' => 'success', 'message' => 'Membership added.'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    if ($method === 'POST' && $action === 'create_routing_rule') {
        $id = (int)$ruleModel->insert([
            'case_type_id'       => (int)($body['case_type_id'] ?? 0),
            'from_stage_key'     => (string)($body['from_stage_key'] ?? ''),
            'action_key'         => (string)($body['action_key'] ?? 'submit'),
            'priority'           => (int)($body['priority'] ?? 100),
            'condition_json'     => json_encode((array)($body['condition_json'] ?? []), JSON_UNESCAPED_UNICODE),
            'route_to_type'      => (string)($body['route_to_type'] ?? 'group'),
            'route_to_user_id'   => isset($body['route_to_user_id']) ? (int)$body['route_to_user_id'] : null,
            'route_to_group_id'  => isset($body['route_to_group_id']) ? (int)$body['route_to_group_id'] : null,
            'next_stage_key'     => (string)($body['next_stage_key'] ?? ''),
            'lock_case_on_claim' => (int)($body['lock_case_on_claim'] ?? 1),
            'is_active'          => (int)($body['is_active'] ?? 1),
        ]);
        echo json_encode(['status' => 'success', 'data' => $ruleModel->find($id)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Unsupported RBAC admin action.']);

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
