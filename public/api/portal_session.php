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

function psRespond(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function psRoleMap(array $groups): array
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
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        psRespond(401, ['status' => 'error', 'message' => 'Unauthenticated.']);
    }

    $user = $userModel->find($uid);
    if (!$user || (int)($user['is_active'] ?? 0) !== 1) {
        psRespond(401, ['status' => 'error', 'message' => 'Invalid session user.']);
    }

    $memberships = $ugModel->groupsForUser($uid);
    $groupIds = array_values(array_unique(array_map(static fn($m) => (int)($m['group_id'] ?? 0), $memberships)));
    $groups = [];
    foreach ($groupIds as $gid) {
        if ($gid <= 0) continue;
        $g = $groupModel->find($gid);
        if ($g && (int)($g['is_active'] ?? 0) === 1) {
            $groups[] = $g;
        }
    }

    $roles = psRoleMap($groups);
    if (!$roles) {
        psRespond(403, ['status' => 'error', 'message' => 'No active portal roles.']);
    }

    psRespond(200, [
        'status' => 'success',
        'data' => [
            'authenticated' => true,
            'user' => [
                'id' => (int)$user['id'],
                'username' => (string)$user['username'],
                'display_name' => (string)($user['display_name'] ?? $user['username']),
                'email' => (string)($user['email'] ?? ''),
            ],
            'roles' => $roles,
        ],
    ]);
} catch (Throwable $e) {
    psRespond(400, ['status' => 'error', 'message' => $e->getMessage()]);
}
