<?php
// ============================================================
// api/plans.php — TalebDZ Subscription Plans API
// Fetch active subscription plans from Supabase
// ============================================================

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../db/config.php';
require_once __DIR__ . '/../db/functions.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use GET.'
    ]);
    exit;
}

try {
    // Fetch active plans from database
    $plans = plans_list();
    
    // Format response
    $formatted = array_map(function($plan) {
        return [
            'id'              => $plan['id'] ?? null,
            'plan_code'       => $plan['plan_code'] ?? '',
            'name'            => $plan['name'] ?? '',
            'description'     => $plan['description'] ?? '',
            'price'           => (float)($plan['price'] ?? 0),
            'currency'        => $plan['currency'] ?? 'DZD',
            'duration_months' => (int)($plan['duration_months'] ?? 1),
            'is_popular'      => (bool)($plan['is_popular'] ?? false),
            'features'        => is_string($plan['features']) 
                ? json_decode($plan['features'], true) 
                : ($plan['features'] ?? []),
        ];
    }, $plans);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data'    => $formatted,
        'count'   => count($formatted)
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Throwable $e) {
    error_log('[TalebDZ Plans API] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Failed to fetch subscription plans',
        'message' => $e->getMessage()
    ]);
}
