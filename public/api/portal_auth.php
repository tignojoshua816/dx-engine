<?php
declare(strict_types=1);

use DXEngine\Core\Autoloader;
use DXEngine\Core\DataModel;
use DXEngine\App\Models\DxUserModel;
use DXEngine\App\Models\DxUserGroupModel;
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

$userModel  = new DxUserModel();
$ugModel    = new DxUserGroupModel();
$groupModel = new DxGroupModel();

function portalRespond(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function portalRoleMap(array $groups): array
{
    $roles = [];
    foreach ($groups as $g) {
        $key = (string)($g['group_key'] ?? '');
        if ($key === '') {
            continue;
        }
        $roles[] = match ($key) {
            'portal_applicant' => 'applicant',
            'portal_admissions_officer' => 'admissions_officer',
            'portal_department_reviewer' => 'department_reviewer',
            'portal_finance_officer' => 'finance_officer',
            'portal_registrar' => 'registrar',
            'portal_super_admin' => 'super_admin',
            default => $key,
        };
    }
    return array_values(array_unique($roles));
}

try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method !== 'POST') {
        portalRespond(405, ['status' => 'error', 'message' => 'Method not allowed.']);
    }

    $action = (string)($_GET['action'] ?? $_POST['action'] ?? 'login');
    if (!in_array($action, ['login', 'logout'], true)) {
        portalRespond(400, ['status' => 'error', 'message' => 'Unsupported auth action.']);
    }

    if ($action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                (bool)($params['secure'] ?? false),
                (bool)($params['httponly'] ?? true)
            );
        }
        session_destroy();
        portalRespond(200, ['status' => 'success', 'message' => 'Logged out.']);
    }

    $raw = file_get_contents('php://input');
    $body = [];
    if ($raw !== false && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $body = $decoded;
        }
    }

    $username = trim((string)($body['username'] ?? ''));
    $password = trim((string)($body['password'] ?? ''));
    
    if ($username === '') {
        portalRespond(400, ['status' => 'error', 'message' => 'Username is required.']);
    }

    $rows = $userModel->where(['username' => $username], '', 1);
    $user = $rows[0] ?? null;
    if (!$user || (int)($user['is_active'] ?? 0) !== 1) {
        portalRespond(401, ['status' => 'error', 'message' => 'Invalid login.']);
    }

    // Verify password if provided
    if ($password !== '') {
        $passwordHash = (string)($user['password_hash'] ?? '');
        if ($passwordHash === '' || !password_verify($password, $passwordHash)) {
            portalRespond(401, ['status' => 'error', 'message' => 'Invalid login.']);
        }
    }

    $memberships = $ugModel->groupsForUser((int)$user['id']);
    $groupIds = array_values(array_unique(array_map(static fn($m) => (int)($m['group_id'] ?? 0), $memberships)));
    $groups = [];
    foreach ($groupIds as $gid) {
        if ($gid <= 0) {
            continue;
        }
        $g = $groupModel->find($gid);
        if ($g && (int)($g['is_active'] ?? 0) === 1) {
            $groups[] = $g;
        }
    }

    $roles = portalRoleMap($groups);
    if (!$roles) {
        portalRespond(403, ['status' => 'error', 'message' => 'User has no portal role.']);
    }

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['portal_roles'] = $roles;
    $_SESSION['portal_display_name'] = (string)($user['display_name'] ?? $user['username']);

    portalRespond(200, [
        'status' => 'success',
        'data' => [
            'user' => [
                'id' => (int)$user['id'],
                'username' => (string)$user['username'],
                'display_name' => (string)($user['display_name'] ?? $user['username']),
                'email' => (string)($user['email'] ?? ''),
            ],
            'roles' => $roles,
            'default_route' => in_array('super_admin', $roles, true) ? 'admin' : $roles[0],
        ],
    ]);
} catch (Throwable $e) {
    portalRespond(400, [
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
