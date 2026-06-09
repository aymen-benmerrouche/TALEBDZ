<?php
// ============================================================
// add-free-plan.php — Add Missing Free Plan
// Quick script to add the free plan via API
// ============================================================
declare(strict_types=1);

$_rootDir = dirname(__DIR__);
require_once $_rootDir . '/db/config.php';
require_once $_rootDir . '/db/functions.php';

header('Content-Type: application/json; charset=utf-8');

$result = ['success' => false, 'message' => '', 'data' => null];

try {
    // Check if free plan already exists
    $existingPlans = plans_list(true);
    $freePlanExists = false;
    
    foreach ($existingPlans as $plan) {
        if ($plan['plan_code'] === 'free') {
            $freePlanExists = true;
            break;
        }
    }
    
    if ($freePlanExists) {
        $result['success'] = true;
        $result['message'] = 'Free plan already exists!';
        $result['data'] = ['status' => 'already_exists'];
    } else {
        // Create free plan
        $freePlanData = [
            'plan_code' => 'free',
            'name' => 'Explorer',
            'description' => 'For prospective students exploring university information',
            'price' => 0.00,
            'currency' => 'DZD',
            'duration_months' => 1,
            'features' => [
                '20 AI questions per month',
                'Admissions & campus information',
                'Public community read access',
                'Basic event notifications'
            ],
            'is_active' => true,
            'is_popular' => false,
            'display_order' => 0
        ];
        
        $planId = plans_create($freePlanData);
        
        if ($planId) {
            $result['success'] = true;
            $result['message'] = 'Free plan created successfully!';
            $result['data'] = [
                'plan_id' => $planId,
                'plan_code' => 'free'
            ];
        } else {
            $result['message'] = 'Failed to create free plan';
        }
    }
    
} catch (Throwable $e) {
    $result['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if ($result['success']) {
    http_response_code(200);
} else {
    http_response_code(500);
}
