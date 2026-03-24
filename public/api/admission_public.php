<?php
declare(strict_types=1);
/**
 * DX-Engine — Public Admission API
 * -----------------------------------------------------------------------
 * Handles public admission case initiation with automatic credential generation.
 * 
 * POST /admission_public.php?action=start_admission
 *   - Creates case instance
 *   - Creates user account with auto-generated credentials
 *   - Returns username, password, and case reference
 */

use DXEngine\Core\Autoloader;
use DXEngine\Core\DataModel;
use DXEngine\App\Models\DxCaseInstanceModel;
use DXEngine\App\Models\DxCaseTypeModel;
use DXEngine\App\Models\DxAssignmentModel;
use DXEngine\App\Models\DxCaseEventModel;
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

$cases = new DxCaseInstanceModel();
$caseTypes = new DxCaseTypeModel();
$assigns = new DxAssignmentModel();
$events = new DxCaseEventModel();
$users = new DxUserModel();
$userGroups = new DxUserGroupModel();
$groups = new DxGroupModel();

function respond(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function generateUsername(string $firstName, string $lastName): string
{
    $base = strtolower(substr($firstName, 0, 1) . $lastName);
    $base = preg_replace('/[^a-z0-9]/', '', $base);
    $suffix = substr(md5((string)microtime(true)), 0, 4);
    return $base . $suffix;
}

function generatePassword(int $length = 8): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    return $password;
}

try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method !== 'POST') {
        respond(405, ['status' => 'error', 'message' => 'Method not allowed.']);
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

    if ($action === 'start_admission') {
        // Extract first step data
        $firstName = trim((string)($body['first_name'] ?? ''));
        $lastName = trim((string)($body['last_name'] ?? ''));
        $email = trim((string)($body['email'] ?? ''));
        $phone = trim((string)($body['phone'] ?? ''));
        $dateOfBirth = trim((string)($body['date_of_birth'] ?? ''));
        $gender = trim((string)($body['gender'] ?? ''));
        $address = trim((string)($body['address'] ?? ''));

        if ($firstName === '' || $lastName === '' || $email === '') {
            respond(400, ['status' => 'error', 'message' => 'First name, last name, and email are required.']);
        }

        // Check if user with this email already exists
        $existingUsers = $users->where(['email' => $email], '', 1);
        if (!empty($existingUsers)) {
            respond(400, [
                'status' => 'error',
                'message' => 'An account with this email already exists. Please login instead.',
            ]);
        }

        // Get case type
        $typeRows = $caseTypes->where(['case_type_key' => 'educational_institution_admission'], '', 1);
        $caseType = $typeRows[0] ?? null;
        if (!$caseType) {
            respond(400, ['status' => 'error', 'message' => 'Educational admission case type is not provisioned.']);
        }

        $caseTypeId = (int)$caseType['id'];
        
        // Generate credentials
        $username = generateUsername($firstName, $lastName);
        $password = generatePassword(10);
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        // Create user account
        $userId = (int)$users->insert([
            'username' => $username,
            'password_hash' => $passwordHash,
            'email' => $email,
            'display_name' => $firstName . ' ' . $lastName,
            'is_active' => 1,
        ]);

        // Assign user to applicant group
        $applicantGroups = $groups->where(['group_key' => 'portal_applicant'], '', 1);
        if (!empty($applicantGroups)) {
            $applicantGroupId = (int)$applicantGroups[0]['id'];
            $userGroups->insert([
                'user_id' => $userId,
                'group_id' => $applicantGroupId,
            ]);
        }

        // Create case reference
        $ref = 'EDU-ADM-' . date('Ymd') . '-' . strtoupper(substr(md5((string)microtime(true) . $email), 0, 6));
        $businessKey = preg_replace('/[^A-Z0-9\\-]/', '-', strtoupper($email));

        // Create case instance
        $caseId = (int)$cases->insert([
            'case_ref' => $ref,
            'case_type_id' => $caseTypeId,
            'business_key' => $businessKey,
            'status' => 'active',
            'current_stage_key' => 'student_application',
            'initiator_user_id' => $userId,
            'current_assignee_user_id' => $userId,
            'current_assignee_group_id' => null,
            'is_locked' => 0,
            'payload_json' => json_encode([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'date_of_birth' => $dateOfBirth,
                'gender' => $gender,
                'address' => $address,
                'started_public' => true,
                'started_at' => date('c'),
                'current_step' => 'academic_background',
            ], JSON_UNESCAPED_UNICODE),
        ]);

        // Create assignment for the student
        $assignmentId = (int)$assigns->insert([
            'case_instance_id' => $caseId,
            'stage_key' => 'student_application',
            'status' => 'ready',
            'assigned_to_type' => 'user',
            'assigned_user_id' => $userId,
            'assigned_group_id' => null,
            'priority' => 50,
            'is_locked' => 0,
            'claimed_by_user_id' => $userId,
            'lock_owner_user_id' => null,
        ]);

        // Log event
        $events->insert([
            'case_instance_id' => $caseId,
            'assignment_id' => $assignmentId,
            'event_type' => 'case.started_public',
            'actor_user_id' => $userId,
            'details_json' => json_encode([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'current_stage' => 'student_application',
            ], JSON_UNESCAPED_UNICODE),
        ]);

        respond(200, [
            'status' => 'success',
            'data' => [
                'case_id' => $caseId,
                'case_ref' => $ref,
                'user_id' => $userId,
                'credentials' => [
                    'username' => $username,
                    'password' => $password,
                ],
                'message' => 'Admission case initiated successfully. Please save your credentials and login to continue.',
            ],
        ]);
    }

    respond(400, ['status' => 'error', 'message' => 'Unsupported action.']);
} catch (Throwable $e) {
    respond(400, [
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
