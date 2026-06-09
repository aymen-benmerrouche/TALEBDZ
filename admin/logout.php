<?php
// ============================================================
// admin/logout.php — TalebDZ Admin Logout
//
// Usage:
//   <a href="/admin/logout.php">Logout</a>
//   OR via fetch:  fetch('/admin/logout.php', { method: 'POST' })
//
// Always clears the session regardless of request method.
// ============================================================

declare(strict_types=1);

$_rootDir = dirname(__DIR__);
require_once $_rootDir . '/db/config.php';
require_once $_rootDir . '/admin/auth.php';

// ── CSRF check for POST requests from JS ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw ?: '{}', true);
    $csrfSubmitted = (string)($body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    // Only validate CSRF if a session exists (someone is actually logged in)
    if (auth_isAuthenticated() && $csrfSubmitted !== '' && !auth_validateCsrf($csrfSubmitted)) {
        jsonResponse(['error' => 'Invalid CSRF token.'], 403);
    }
}

// ── Destroy session ───────────────────────────────────────────
auth_destroySession();

// ── Respond ───────────────────────────────────────────────────
$isAjax = (
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
    (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) ||
    $_SERVER['REQUEST_METHOD'] === 'POST'
);

if ($isAjax) {
    // Return JSON so the JS can handle the redirect itself
    jsonResponse(['success' => true, 'redirect' => 'login.php']);
}

// Browser GET request — redirect immediately
header('Location: login.php?logged_out=1');
exit;
