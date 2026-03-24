<?php
declare(strict_types=1);

use DXEngine\Core\Autoloader;
use DXEngine\Core\DataModel;

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!defined('DX_ROOT')) {
    define('DX_ROOT', dirname(__FILE__, 3));
}

require_once DX_ROOT . '/src/Core/Autoloader.php';
Autoloader::register(DX_ROOT . '/src');

$pdo = require DX_ROOT . '/config/database.php';
DataModel::boot($pdo);

try {
    $pdo->beginTransaction();

    $pdo->exec("
        INSERT IGNORE INTO dx_users (id, username, email, display_name, is_active) VALUES
        (1101, 'portal_applicant', 'portal_applicant@example.local', 'Applicant User', 1),
        (1102, 'portal_admissions', 'portal_admissions@example.local', 'Admissions Officer User', 1),
        (1103, 'portal_department', 'portal_department@example.local', 'Department Reviewer User', 1),
        (1104, 'portal_finance', 'portal_finance@example.local', 'Finance Officer User', 1),
        (1105, 'portal_registrar', 'portal_registrar@example.local', 'Registrar User', 1),
        (1106, 'portal_admin', 'portal_admin@example.local', 'Super Admin User', 1)
    ");

    $pdo->exec("
        INSERT IGNORE INTO dx_groups (id, group_key, group_name, description, is_active) VALUES
        (1201, 'portal_applicant', 'Applicant', 'Applicant persona', 1),
        (1202, 'portal_admissions_officer', 'Admissions Officer', 'Admissions review persona', 1),
        (1203, 'portal_department_reviewer', 'Department Reviewer', 'Department review persona', 1),
        (1204, 'portal_finance_officer', 'Finance Officer', 'Finance review persona', 1),
        (1205, 'portal_registrar', 'Registrar', 'Registrar decision persona', 1),
        (1206, 'portal_super_admin', 'Super Admin', 'Portal administration persona', 1)
    ");

    $pdo->exec("
        INSERT IGNORE INTO dx_user_groups (user_id, group_id, is_primary) VALUES
        (1101, 1201, 1),
        (1102, 1202, 1),
        (1103, 1203, 1),
        (1104, 1204, 1),
        (1105, 1205, 1),
        (1106, 1206, 1)
    ");

    $pdo->exec("
        INSERT IGNORE INTO dx_case_types (id, case_type_key, title, description, is_active) VALUES
        (1301, 'educational_institution_admission', 'Educational Institution Admission',
         'Applicant -> Admissions -> Department -> Finance -> Registrar', 1)
    ");

    $pdo->exec("
        INSERT IGNORE INTO dx_case_stages (case_type_id, stage_key, title, sequence_no, is_terminal) VALUES
        (1301, 'applicant_submission', 'Applicant Submission', 1, 0),
        (1301, 'admissions_review', 'Admissions Review', 2, 0),
        (1301, 'department_review', 'Department Review', 3, 0),
        (1301, 'finance_clearance', 'Finance Clearance', 4, 0),
        (1301, 'registrar_decision', 'Registrar Decision', 5, 1)
    ");

    $stmtRule = $pdo->prepare("
        INSERT IGNORE INTO dx_routing_rules
          (case_type_id, from_stage_key, action_key, priority, condition_json, route_to_type, route_to_user_id, route_to_group_id, next_stage_key, lock_case_on_claim, is_active)
        VALUES
          (1301, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $rules = [
        ['applicant_submission', 'submit', 10, null, 'group', null, 1202, 'admissions_review', 1, 1],
        ['admissions_review', 'forward', 10, null, 'group', null, 1203, 'department_review', 1, 1],
        ['department_review', 'forward', 10, null, 'group', null, 1204, 'finance_clearance', 1, 1],
        ['finance_clearance', 'forward', 10, null, 'group', null, 1205, 'registrar_decision', 1, 1],
        ['registrar_decision', 'decide', 10, null, 'group', null, 1205, '', 0, 1],
    ];
    foreach ($rules as $r) {
        $stmtRule->execute($r);
    }

    $pdo->exec("
        INSERT IGNORE INTO dx_case_instances
          (id, case_ref, case_type_id, business_key, status, current_stage_key, initiator_user_id, current_assignee_user_id, current_assignee_group_id, is_locked, payload_json)
        VALUES
          (1401, 'EDU-ADM-0001', 1301, 'APPLICANT-1101', 'active', 'applicant_submission', 1101, NULL, 1201, 0,
           JSON_OBJECT('applicant_name','Applicant User','program','Computer Science','status_note','initial'))
    ");

    $pdo->exec("
        INSERT IGNORE INTO dx_assignments
          (id, case_instance_id, stage_key, status, assigned_to_type, assigned_user_id, assigned_group_id, priority, is_locked)
        VALUES
          (1501, 1401, 'applicant_submission', 'ready', 'user', 1101, NULL, 50, 0)
    ");

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Portal admission seed completed.',
        'data' => [
            'users' => [1101,1102,1103,1104,1105,1106],
            'groups' => [1201,1202,1203,1204,1205,1206],
            'case_type_id' => 1301,
            'case_id' => 1401,
            'assignment_id' => 1501,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
