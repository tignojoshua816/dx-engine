<?php
declare(strict_types=1);

/**
 * Case Routing API
 * Handles routing cases to different users or groups based on workflow rules
 */

if (!defined('DX_ROOT')) {
    define('DX_ROOT', dirname(__DIR__, 2));
}

require_once DX_ROOT . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

// CORS headers (adjust for production)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Send JSON response
 */
function sendJson(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Get database connection
 */
function getDb(): PDO {
    $config = require DX_ROOT . '/config/database.php';
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'],
        $config['database']
    );
    
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    return $pdo;
}

/**
 * Route case to a specific user
 */
function routeToUser(PDO $pdo, string $caseRef, int $userId, string $reason = ''): array {
    try {
        // Get case instance
        $stmt = $pdo->prepare("
            SELECT id, case_ref, current_step, status 
            FROM dx_case_instances 
            WHERE case_ref = ?
        ");
        $stmt->execute([$caseRef]);
        $case = $stmt->fetch();
        
        if (!$case) {
            return ['status' => 'error', 'message' => 'Case not found'];
        }
        
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id, username FROM dx_users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['status' => 'error', 'message' => 'User not found'];
        }
        
        // Create assignment
        $stmt = $pdo->prepare("
            INSERT INTO dx_assignments 
            (case_instance_id, assigned_to_user_id, assigned_at, status, notes)
            VALUES (?, ?, NOW(), 'pending', ?)
        ");
        $stmt->execute([$case['id'], $userId, $reason]);
        
        // Log event
        $stmt = $pdo->prepare("
            INSERT INTO dx_case_events 
            (case_instance_id, event_type, event_data, created_at)
            VALUES (?, 'case_routed', ?, NOW())
        ");
        $eventData = json_encode([
            'routed_to_user' => $userId,
            'username' => $user['username'],
            'reason' => $reason
        ]);
        $stmt->execute([$case['id'], $eventData]);
        
        return [
            'status' => 'success',
            'message' => 'Case routed to user successfully',
            'data' => [
                'case_ref' => $caseRef,
                'assigned_to' => $user['username'],
                'assignment_id' => $pdo->lastInsertId()
            ]
        ];
        
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

/**
 * Route case to a group
 */
function routeToGroup(PDO $pdo, string $caseRef, string $groupName, string $reason = ''): array {
    try {
        // Get case instance
        $stmt = $pdo->prepare("
            SELECT id, case_ref, current_step, status 
            FROM dx_case_instances 
            WHERE case_ref = ?
        ");
        $stmt->execute([$caseRef]);
        $case = $stmt->fetch();
        
        if (!$case) {
            return ['status' => 'error', 'message' => 'Case not found'];
        }
        
        // Get group
        $stmt = $pdo->prepare("SELECT id, group_name FROM dx_groups WHERE group_name = ?");
        $stmt->execute([$groupName]);
        $group = $stmt->fetch();
        
        if (!$group) {
            return ['status' => 'error', 'message' => 'Group not found'];
        }
        
        // Create assignment to group
        $stmt = $pdo->prepare("
            INSERT INTO dx_assignments 
            (case_instance_id, assigned_to_group_id, assigned_at, status, notes)
            VALUES (?, ?, NOW(), 'pending', ?)
        ");
        $stmt->execute([$case['id'], $group['id'], $reason]);
        
        // Log event
        $stmt = $pdo->prepare("
            INSERT INTO dx_case_events 
            (case_instance_id, event_type, event_data, created_at)
            VALUES (?, 'case_routed', ?, NOW())
        ");
        $eventData = json_encode([
            'routed_to_group' => $group['id'],
            'group_name' => $groupName,
            'reason' => $reason
        ]);
        $stmt->execute([$case['id'], $eventData]);
        
        return [
            'status' => 'success',
            'message' => 'Case routed to group successfully',
            'data' => [
                'case_ref' => $caseRef,
                'assigned_to_group' => $groupName,
                'assignment_id' => $pdo->lastInsertId()
            ]
        ];
        
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

/**
 * Auto-route based on rules
 */
function autoRoute(PDO $pdo, string $caseRef, array $rules): array {
    try {
        // Get case instance with payload
        $stmt = $pdo->prepare("
            SELECT id, case_ref, current_step, status, payload_json 
            FROM dx_case_instances 
            WHERE case_ref = ?
        ");
        $stmt->execute([$caseRef]);
        $case = $stmt->fetch();
        
        if (!$case) {
            return ['status' => 'error', 'message' => 'Case not found'];
        }
        
        $payload = json_decode($case['payload_json'] ?? '{}', true);
        
        // Evaluate routing rules
        foreach ($rules as $rule) {
            $condition = $rule['condition'] ?? null;
            $routeTo = $rule['route_to'] ?? null;
            $routeType = $rule['route_type'] ?? 'group'; // 'user' or 'group'
            
            if (!$condition || !$routeTo) {
                continue;
            }
            
            // Simple condition evaluation
            if (evaluateCondition($condition, $payload)) {
                if ($routeType === 'user') {
                    return routeToUser($pdo, $caseRef, (int)$routeTo, $rule['reason'] ?? 'Auto-routed');
                } else {
                    return routeToGroup($pdo, $caseRef, $routeTo, $rule['reason'] ?? 'Auto-routed');
                }
            }
        }
        
        return ['status' => 'error', 'message' => 'No matching routing rule found'];
        
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

/**
 * Simple condition evaluator
 */
function evaluateCondition(array $condition, array $payload): bool {
    $field = $condition['field'] ?? null;
    $operator = $condition['operator'] ?? '==';
    $value = $condition['value'] ?? null;
    
    if (!$field || !isset($payload[$field])) {
        return false;
    }
    
    $fieldValue = $payload[$field];
    
    switch ($operator) {
        case '==':
            return $fieldValue == $value;
        case '!=':
            return $fieldValue != $value;
        case '>':
            return $fieldValue > $value;
        case '<':
            return $fieldValue < $value;
        case '>=':
            return $fieldValue >= $value;
        case '<=':
            return $fieldValue <= $value;
        case 'contains':
            return str_contains((string)$fieldValue, (string)$value);
        case 'in':
            return in_array($fieldValue, (array)$value);
        default:
            return false;
    }
}

// Main request handling
try {
    $pdo = getDb();
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'route_to_user':
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $caseRef = $input['case_ref'] ?? '';
            $userId = (int)($input['user_id'] ?? 0);
            $reason = $input['reason'] ?? '';
            
            if (!$caseRef || !$userId) {
                sendJson(['status' => 'error', 'message' => 'case_ref and user_id required'], 400);
            }
            
            $result = routeToUser($pdo, $caseRef, $userId, $reason);
            sendJson($result);
            break;
            
        case 'route_to_group':
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $caseRef = $input['case_ref'] ?? '';
            $groupName = $input['group_name'] ?? '';
            $reason = $input['reason'] ?? '';
            
            if (!$caseRef || !$groupName) {
                sendJson(['status' => 'error', 'message' => 'case_ref and group_name required'], 400);
            }
            
            $result = routeToGroup($pdo, $caseRef, $groupName, $reason);
            sendJson($result);
            break;
            
        case 'auto_route':
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $caseRef = $input['case_ref'] ?? '';
            $rules = $input['rules'] ?? [];
            
            if (!$caseRef || empty($rules)) {
                sendJson(['status' => 'error', 'message' => 'case_ref and rules required'], 400);
            }
            
            $result = autoRoute($pdo, $caseRef, $rules);
            sendJson($result);
            break;
            
        default:
            sendJson(['status' => 'error', 'message' => 'Invalid action'], 400);
    }
    
} catch (Exception $e) {
    sendJson(['status' => 'error', 'message' => $e->getMessage()], 500);
}
