<?php
// ============================================================
// api/reports.php — Resolve post reports
// POST { report_id, action: 'accepted'|'refused' }
// ============================================================
declare(strict_types=1);
$_rootDir = dirname(__DIR__);
require_once $_rootDir . '/db/config.php';
require_once $_rootDir . '/db/functions.php';
require_once $_rootDir . '/admin/auth.php';

setCorsHeaders();
require_admin_auth();

$body     = json_decode(file_get_contents('php://input') ?: '{}', true);
$reportId = trim((string)($body['report_id'] ?? ''));
$action   = trim((string)($body['action']    ?? ''));

if (!$reportId || !in_array($action, ['accepted','refused'], true)) {
    jsonResponse(['error' => 'Invalid report_id or action.'], 400);
}

// Map UI action to DB action
$dbAction = $action === 'accepted' ? 'accepted' : 'dismissed';

$ok = reports_resolve($reportId, $dbAction);

if (!$ok) {
    jsonResponse(['error' => 'Report not found or already resolved.'], 404);
}

jsonResponse(['success' => true, 'action' => $action]);
