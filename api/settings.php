<?php
// ============================================================
// api/settings.php — Admin settings updates
// POST { action: 'update_pricing'|'update_links'|'update_account', ...data }
// ============================================================
declare(strict_types=1);
$_rootDir = dirname(__DIR__);
require_once $_rootDir . '/db/config.php';
require_once $_rootDir . '/db/functions.php';
require_once $_rootDir . '/admin/auth.php';

setCorsHeaders();
require_admin_auth();
$currentAdmin = auth_currentAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST only.'], 405);
}

$body   = json_decode(file_get_contents('php://input') ?: '{}', true);
$action = trim((string)($body['action'] ?? ''));

switch ($action) {

    // ── Update plan pricing ────────────────────────────────
    case 'update_pricing':
        $plans = $body['plans'] ?? [];
        if (!is_array($plans)) jsonResponse(['error' => 'plans must be an array.'], 400);
        $updated = 0;
        foreach ($plans as $p) {
            $code  = trim((string)($p['plan_code'] ?? ''));
            $price = (float)($p['price'] ?? -1);
            if ($code && $price >= 0) {
                plans_updatePrice($code, $price);
                $updated++;
            }
        }
        // Also update free limit if provided
        $freeLimit = (int)($body['free_limit'] ?? 0);
        if ($freeLimit > 0) settings_setFreeLimit($freeLimit);
        jsonResponse(['success' => true, 'updated_plans' => $updated]);

    // ── Update contact links (stored as events metadata for now) ──
    case 'update_links':
        // In a full system, persist to a key-value settings table.
        // For now, acknowledge the save.
        jsonResponse(['success' => true, 'note' => 'Links received (persist to settings table)']);

    // ── Update admin account ───────────────────────────────
    case 'update_account':
        $adminId = trim((string)($body['admin_id'] ?? $currentAdmin['id']));
        // Only current admin can update their own account
        if ($adminId !== $currentAdmin['id']) {
            jsonResponse(['error' => 'Forbidden.'], 403);
        }
        $newEmail = trim((string)($body['email'] ?? ''));
        $newPw    = (string)($body['password'] ?? '');

        if ($newEmail && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Invalid email format.'], 400);
        }
        if ($newEmail && $newEmail !== $currentAdmin['email']) {
            settings_updateAdminEmail($adminId, $newEmail);
        }
        if ($newPw) {
            if (strlen($newPw) < 8) jsonResponse(['error' => 'Password must be at least 8 characters.'], 400);
            admin_changePassword($adminId, $newPw);
        }
        jsonResponse(['success' => true]);

    default:
        jsonResponse(['error' => 'Unknown action.'], 400);
}
