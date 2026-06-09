<?php
// ============================================================
// api/admin-plans.php — Admin Subscription Plans Management API
// Requires admin authentication
// Actions: list, create, update, delete
// ============================================================
declare(strict_types=1);

$_rootDir = dirname(__DIR__);
require_once $_rootDir . '/db/config.php';
require_once $_rootDir . '/db/functions.php';
require_once $_rootDir . '/admin/auth.php';

setCorsHeaders();
require_admin_auth();

$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
$action = trim((string)($body['action'] ?? $_GET['action'] ?? ''));

// ═══════════════════════════════════════════════════════════
// ACTION: LIST ALL PLANS (including inactive)
// ═══════════════════════════════════════════════════════════
if ($action === 'list' && $method === 'GET') {
    try {
        $plans = plans_list(true); // Include inactive plans
        jsonResponse(['success' => true, 'data' => $plans]);
    } catch (Throwable $e) {
        error_log('[Admin Plans] List error: ' . $e->getMessage());
        jsonResponse(['error' => 'Failed to fetch plans'], 500);
    }
}

// ═══════════════════════════════════════════════════════════
// ACTION: GET SINGLE PLAN
// ═══════════════════════════════════════════════════════════
if ($action === 'get' && $method === 'GET') {
    $planId = trim((string)($_GET['plan_id'] ?? ''));
    
    if (!$planId) {
        jsonResponse(['error' => 'Missing plan_id'], 400);
    }
    
    try {
        $plan = plans_getById($planId);
        
        if (!$plan) {
            jsonResponse(['error' => 'Plan not found'], 404);
        }
        
        jsonResponse(['success' => true, 'data' => $plan]);
    } catch (Throwable $e) {
        error_log('[Admin Plans] Get error: ' . $e->getMessage());
        jsonResponse(['error' => 'Failed to fetch plan'], 500);
    }
}

// ═══════════════════════════════════════════════════════════
// ACTION: CREATE NEW PLAN
// POST { action: 'create', name, description, price, duration_months, features[], ... }
// ═══════════════════════════════════════════════════════════
if ($action === 'create' && $method === 'POST') {
    // Validate required fields
    if (empty($body['name'])) {
        jsonResponse(['error' => 'Missing required field: name'], 400);
    }
    if (!isset($body['price']) || $body['price'] === '') {
        jsonResponse(['error' => 'Missing required field: price'], 400);
    }
    if (empty($body['duration_months'])) {
        jsonResponse(['error' => 'Missing required field: duration_months'], 400);
    }
    
    try {
        // Parse features if string
        if (isset($body['features']) && is_string($body['features'])) {
            $features = array_filter(array_map('trim', explode(',', $body['features'])));
            $body['features'] = $features;
        }
        
        $planId = plans_create($body);
        
        if (!$planId) {
            jsonResponse(['error' => 'Failed to create plan'], 500);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Plan created successfully',
            'plan_id' => $planId
        ]);
        
    } catch (Throwable $e) {
        error_log('[Admin Plans] Create error: ' . $e->getMessage());
        jsonResponse(['error' => 'Failed to create plan: ' . $e->getMessage()], 500);
    }
}

// ═══════════════════════════════════════════════════════════
// ACTION: UPDATE EXISTING PLAN
// POST { action: 'update', plan_id, name?, price?, ... }
// ═══════════════════════════════════════════════════════════
if ($action === 'update' && $method === 'POST') {
    $planId = trim((string)($body['plan_id'] ?? ''));
    
    if (!$planId) {
        jsonResponse(['error' => 'Missing plan_id'], 400);
    }
    
    // Remove action and plan_id from update data
    unset($body['action'], $body['plan_id']);
    
    if (empty($body)) {
        jsonResponse(['error' => 'No fields to update'], 400);
    }
    
    try {
        // Parse features if string
        if (isset($body['features']) && is_string($body['features'])) {
            $features = array_filter(array_map('trim', explode(',', $body['features'])));
            $body['features'] = $features;
        }
        
        $success = plans_update($planId, $body);
        
        if (!$success) {
            jsonResponse(['error' => 'Failed to update plan'], 500);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Plan updated successfully'
        ]);
        
    } catch (Throwable $e) {
        error_log('[Admin Plans] Update error: ' . $e->getMessage());
        jsonResponse(['error' => 'Failed to update plan: ' . $e->getMessage()], 500);
    }
}

// ═══════════════════════════════════════════════════════════
// ACTION: DELETE PLAN (soft delete)
// POST { action: 'delete', plan_id }
// ═══════════════════════════════════════════════════════════
if ($action === 'delete' && $method === 'POST') {
    $planId = trim((string)($body['plan_id'] ?? ''));
    
    if (!$planId) {
        jsonResponse(['error' => 'Missing plan_id'], 400);
    }
    
    try {
        $success = plans_delete($planId);
        
        if (!$success) {
            jsonResponse(['error' => 'Failed to delete plan'], 500);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Plan deleted successfully (soft delete)'
        ]);
        
    } catch (Throwable $e) {
        error_log('[Admin Plans] Delete error: ' . $e->getMessage());
        jsonResponse(['error' => 'Failed to delete plan: ' . $e->getMessage()], 500);
    }
}

// ═══════════════════════════════════════════════════════════
// ACTION: PERMANENTLY DELETE PLAN
// POST { action: 'delete_permanent', plan_id }
// ═══════════════════════════════════════════════════════════
if ($action === 'delete_permanent' && $method === 'POST') {
    $planId = trim((string)($body['plan_id'] ?? ''));
    
    if (!$planId) {
        jsonResponse(['error' => 'Missing plan_id'], 400);
    }
    
    try {
        $success = plans_deletePermanent($planId);
        
        if (!$success) {
            jsonResponse(['error' => 'Cannot delete plan with existing subscriptions'], 400);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Plan permanently deleted'
        ]);
        
    } catch (Throwable $e) {
        error_log('[Admin Plans] Permanent delete error: ' . $e->getMessage());
        jsonResponse(['error' => 'Failed to delete plan: ' . $e->getMessage()], 500);
    }
}

// Invalid action
jsonResponse(['error' => 'Invalid action or method'], 400);
