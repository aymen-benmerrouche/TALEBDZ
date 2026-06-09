<?php
// ============================================================
// api/users.php — User management actions
// GET  ?page=1&search=&plan=&export=csv  → users list or CSV export
// POST { user_id, action: 'ban'|'unban' }
// ============================================================
declare(strict_types=1);
$_rootDir = dirname(__DIR__);
require_once $_rootDir . '/db/config.php';
require_once $_rootDir . '/db/functions.php';
require_once $_rootDir . '/admin/auth.php';

setCorsHeaders();
require_admin_auth();

// ── GET: List users or export to CSV ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $page   = max(1, (int)($_GET['page']   ?? 1));
    $search =          (string)($_GET['search'] ?? '');
    $plan   =          (string)($_GET['plan']   ?? '');
    $export =          (string)($_GET['export'] ?? '');
    $limit  = min(100, max(10, (int)($_GET['limit'] ?? 25)));
    
    try {
        $result = users_list($page, $limit, $search);
        
        // CSV export
        if ($export === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="users_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'Name', 'Email', 'Username', 'Plan', 'University', 'Department', 'Status', 'Joined']);
            
            foreach ($result['data'] as $user) {
                fputcsv($output, [
                    $user['id'],
                    $user['full_name'] ?? '',
                    $user['email'],
                    $user['username'] ?? '',
                    $user['plan_name'] ?? 'Free',
                    $user['university'] ?? '',
                    $user['department'] ?? '',
                    $user['is_active'] ? 'Active' : 'Banned',
                    $user['created_at'] ?? ''
                ]);
            }
            
            fclose($output);
            exit;
        }
        
        // JSON response
        jsonResponse($result);
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] users.php GET error: ' . $e->getMessage());
        jsonResponse(['error' => 'Failed to load users: ' . $e->getMessage()], 500);
    }
}

// ── POST: Ban/Unban user ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $body   = json_decode(file_get_contents('php://input') ?: '{}', true);
        
        if (!$body) {
            jsonResponse(['error' => 'Invalid JSON payload'], 400);
        }
        
        $userId = trim((string)($body['user_id'] ?? ''));
        $action = trim((string)($body['action']  ?? ''));

        if (!$userId) {
            jsonResponse(['error' => 'Missing user_id'], 400);
        }
        
        if (!in_array($action, ['ban','unban'], true)) {
            jsonResponse(['error' => 'Invalid action. Must be "ban" or "unban"'], 400);
        }

        $setActive = ($action === 'unban');
        $ok = users_setActive($userId, $setActive);

        if (!$ok) {
            jsonResponse(['error' => 'User not found or update failed'], 404);
        }

        jsonResponse([
            'success' => true, 
            'action' => $action, 
            'user_id' => $userId,
            'is_active' => $setActive
        ]);
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] users.php POST error: ' . $e->getMessage());
        jsonResponse(['error' => 'Failed to update user: ' . $e->getMessage()], 500);
    }
}

// ── Invalid method ───────────────────────────────────────────
jsonResponse(['error' => 'Method not allowed'], 405);
