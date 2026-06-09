<?php
// ============================================================
// admin/auth-api.php — Alternative Authentication using Supabase REST API
// This bypasses direct PostgreSQL connection issues
// ============================================================

declare(strict_types=1);

$_rootDir = dirname(__DIR__);
require_once $_rootDir . '/db/config.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// Parse JSON body
$raw  = file_get_contents('php://input');
$body = json_decode($raw ?: '{}', true);

$email    = trim((string)($body['email']    ?? ''));
$password =       (string)($body['password'] ?? '');

// Validation
if ($email === '' || $password === '') {
    jsonResponse(['error' => 'Email and password are required.'], 400);
}

// Use Supabase REST API to find admin
$result = Supabase::get(
    '/rest/v1/admin_accounts',
    [
        'select' => '*',
        'email' => 'eq.' . strtolower($email),
        'is_active' => 'eq.true',
        'limit' => '1'
    ],
    true // Use service_role key
);

if (empty($result) || !is_array($result) || isset($result['error'])) {
    jsonResponse(['error' => 'Invalid email or password.'], 401);
}

$admin = $result[0] ?? null;

if (!$admin || !password_verify($password, $admin['password_hash'] ?? '')) {
    jsonResponse(['error' => 'Invalid email or password.'], 401);
}

// Success — create session
session_regenerate_id(true);
$_SESSION['talebdz_admin_id']    = $admin['id'];
$_SESSION['talebdz_admin_email'] = $admin['email'];
$_SESSION['talebdz_admin_role']  = $admin['role'];
$_SESSION['talebdz_admin_name']  = $admin['full_name'];
$_SESSION['talebdz_csrf_token']  = bin2hex(random_bytes(32));
$_SESSION['_last_regen']         = time();

// Update last login via REST API
Supabase::patch(
    '/rest/v1/admin_accounts',
    ['last_login_at' => date('c')],
    true,
    ['id' => 'eq.' . $admin['id']]
);

jsonResponse([
    'admin' => [
        'id'         => $admin['id'],
        'email'      => $admin['email'],
        'full_name'  => $admin['full_name'],
        'role'       => $admin['role'],
    ],
    'csrf_token' => $_SESSION['talebdz_csrf_token'],
    'redirect'   => dirname($_SERVER['SCRIPT_NAME']) . '/index.php',
]);
