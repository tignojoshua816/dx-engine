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

    // Users
    $pdo->exec("
        INSERT IGNORE INTO dx_users (id, username, email, display_name, is_active)
        VALUES
          (101, 'student_demo', 'student_demo@example.local', 'Student Demo', 1),
          (102, 'admission_officer_demo', 'admission_officer_demo@example.local', 'Admission Officer Demo', 1),
          (103, 'dx_admin_demo', 'dx_admin_demo@example.local', 'DX Admin Demo', 1)
    ");

    // Groups
    $pdo->exec("
        INSERT IGNORE INTO dx_groups (id, group_key, group_name, description, is_active)
        VALUES
          (201, 'students', 'Students', 'Student initiators', 1),
          (202, 'admissions_officers', 'Admissions Officers', 'Admissions review team', 1),
          (203, 'dx_admins', 'DX Admins', 'Framework administrators', 1)
    ");

    // Memberships
    $pdo->exec("
        INSERT IGNORE INTO dx_user_groups (user_id, group_id, is_primary)
        VALUES
          (101, 201, 1),
          (102, 202, 1),
          (103, 203, 1)
    ");

    // Case type
    $pdo->exec("
        INSERT IGNORE INTO dx_case_types (id, case_type_key, title, description, is_active)
        VALUES
          (301, 'admission_applicant_journey', 'Admission Applicant Journey',
           'Demonstrates student initiation, admissions review, resubmission, and completion.', 1)
    ");

    // Stages
    $pdo->exec("
        INSERT IGNORE INTO dx_case_stages (case_type_id, stage_key, title, sequence_no, is_terminal)
        VALUES
          (301, 'student_submission', 'Student Submission', 1, 0),
          (301, 'admissions_review', 'Admissions Review', 2, 0),
          (301, 'student_resubmission', 'Student Resubmission', 3, 0),
          (301, 'completed', 'Completed', 4, 1)
    ");

    // Routing rules
    $rules = [
        [
            'student_submission', 'submit', 10, null, 'group', null, 202, 'admissions_review', 1, 1
        ],
        [
            'admissions_review', 'request_resubmission', 10, null, 'user', 101, null, 'student_resubmission', 0, 1
        ],
        [
            'student_resubmission', 'submit', 10, null, 'group', null, 202, 'admissions_review', 1, 1
        ],
        [
            'admissions_review', 'approve', 10, null, 'group', null, 202, 'completed', 0, 1
        ],
    ];

    $stmtRule = $pdo->prepare("
        INSERT IGNORE INTO dx_routing_rules
          (case_type_id, from_stage_key, action_key, priority, condition_json, route_to_type,
           route_to_user_id, route_to_group_id, next_stage_key, lock_case_on_claim, is_active)
        VALUES
          (301, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($rules as $r) {
        $stmtRule->execute($r);
    }

    // Case instance
    $pdo->exec("
        INSERT IGNORE INTO dx_case_instances
          (id, case_ref, case_type_id, business_key, status, current_stage_key, initiator_user_id,
           current_assignee_user_id, current_assignee_group_id, is_locked, payload_json)
        VALUES
          (401, 'CASE-ADM-0001', 301, 'STUDENT-101', 'active', 'student_submission', 101,
           NULL, 201, 0, JSON_OBJECT('applicant_name','Student Demo','has_missing_docs',1))
    ");

    // Initial assignment for student
    $pdo->exec("
        INSERT IGNORE INTO dx_assignments
          (id, case_instance_id, stage_key, status, assigned_to_type, assigned_user_id, assigned_group_id, priority, is_locked)
        VALUES
          (501, 401, 'student_submission', 'ready', 'user', 101, NULL, 50, 0)
    ");

    $pdo->commit();

    echo json_encode([
        'status'  => 'success',
        'message' => 'Workflow demo seed complete.',
        'data'    => [
            'case_type_id'  => 301,
            'case_id'       => 401,
            'assignment_id' => 501,
            'users'         => [101, 102, 103],
            'groups'        => [201, 202, 203],
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
