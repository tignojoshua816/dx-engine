<?php
declare(strict_types=1);

use DXEngine\Core\Autoloader;
use DXEngine\Core\DataModel;
use DXEngine\Core\DxWorklistService;

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

$config = require DX_ROOT . '/config/app.php';
$pdo    = require DX_ROOT . '/config/database.php';
DataModel::boot($pdo);

$service = new DxWorklistService();

try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, ['GET', 'POST'], true)) {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
        exit;
    }

    $user = $service->currentUser($_SESSION);
    $action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

    if ($method === 'GET' && ($action === '' || $action === 'queues')) {
        echo json_encode([
            'status' => 'success',
            'data'   => $service->queuesForUser((int)$user['id']),
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

    if ($method === 'POST' && $action === 'claim') {
        $assignmentId = (int)($body['assignment_id'] ?? 0);
        $lockCase     = (bool)($body['lock_case'] ?? true);
        $data = $service->claimAssignment($assignmentId, (int)$user['id'], $lockCase);
        echo json_encode(['status' => 'success', 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    if ($method === 'POST' && $action === 'release') {
        $assignmentId = (int)($body['assignment_id'] ?? 0);
        $data = $service->releaseAssignment($assignmentId, (int)$user['id']);
        echo json_encode(['status' => 'success', 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    if ($method === 'POST' && $action === 'process') {
        $assignmentId = (int)($body['assignment_id'] ?? 0);
        $actionKey    = (string)($body['action_key'] ?? 'submit');
        $payload      = (array)($body['payload'] ?? []);
        $data = $service->processAssignment($assignmentId, (int)$user['id'], $actionKey, $payload);
        echo json_encode(['status' => 'success', 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Unsupported worklist action.']);

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
