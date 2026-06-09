<?php
// ============================================================
// admin/auth.php — TalebDZ Admin Authentication
//
// TWO roles for this file:
//
//  1. GUARD (require_admin_auth)
//     Include at the top of any protected admin page:
//       require_once __DIR__ . '/auth.php';
//       require_admin_auth();
//     Redirects to login.php if session is invalid.
//
//  2. LOGIN HANDLER (POST /admin/auth.php)
//     Called via fetch() from login.html:
//       fetch('/admin/auth.php', { method:'POST', body: JSON })
//     Returns JSON { token, admin } on success or { error } on failure.
// ============================================================

declare(strict_types=1);

// Resolve paths whether this file is called directly or included
$_rootDir = dirname(__DIR__);
require_once $_rootDir . '/db/config.php';
require_once $_rootDir . '/db/functions.php';

// ── Constants ────────────────────────────────────────────────
define('ADMIN_SESSION_KEY',    'talebdz_admin_id');
define('ADMIN_SESSION_EMAIL',  'talebdz_admin_email');
define('ADMIN_SESSION_ROLE',   'talebdz_admin_role');
define('ADMIN_SESSION_NAME',   'talebdz_admin_name');
define('ADMIN_SESSION_CSRF',   'talebdz_csrf_token');

// Use relative paths for XAMPP compatibility
$_baseUrl = dirname($_SERVER['SCRIPT_NAME']);
$_baseUrl = rtrim($_baseUrl, '/');
if (basename($_baseUrl) !== 'admin') {
    $_baseUrl .= '/admin';
}
define('ADMIN_LOGIN_PAGE',     $_baseUrl . '/login.php');
define('ADMIN_DASHBOARD_PAGE', $_baseUrl . '/index.php');

// Rate-limit: max login attempts per IP per window
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_WINDOW_SECONDS', 600);  // 10 minutes


// ╔══════════════════════════════════════════════════════════╗
// ║  SECTION A — SESSION HELPERS                            ║
// ╚══════════════════════════════════════════════════════════╝

/**
 * Return the currently authenticated admin data from session,
 * or null if no valid session exists.
 */
function auth_currentAdmin(): ?array {
    if (empty($_SESSION[ADMIN_SESSION_KEY])) {
        return null;
    }
    return [
        'id'    => $_SESSION[ADMIN_SESSION_KEY],
        'email' => $_SESSION[ADMIN_SESSION_EMAIL] ?? '',
        'role'  => $_SESSION[ADMIN_SESSION_ROLE]  ?? 'admin',
        'name'  => $_SESSION[ADMIN_SESSION_NAME]  ?? 'Admin',
    ];
}

/**
 * Check if admin is authenticated.
 */
function auth_isAuthenticated(): bool {
    return auth_currentAdmin() !== null;
}

/**
 * Require authentication. Redirects to login page if not authenticated.
 * Call this at the top of every protected admin page.
 */
function require_admin_auth(): void {
    if (!auth_isAuthenticated()) {
        // API call — return 401 JSON
        if (
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
        ) {
            jsonResponse(['error' => 'Unauthorized. Please log in.'], 401);
        }
        // Browser request — redirect
        $current = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header("Location: " . ADMIN_LOGIN_PAGE . "?redirect={$current}");
        exit;
    }

    // Regenerate session ID periodically to prevent fixation
    if (empty($_SESSION['_last_regen']) || time() - $_SESSION['_last_regen'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_last_regen'] = time();
    }
}

/**
 * Require a specific role (e.g. 'super_admin').
 */
function require_admin_role(string $requiredRole): void {
    require_admin_auth();
    $admin = auth_currentAdmin();
    $roles = ['moderator' => 1, 'admin' => 2, 'super_admin' => 3];
    if (($roles[$admin['role']] ?? 0) < ($roles[$requiredRole] ?? 99)) {
        jsonResponse(['error' => 'Forbidden. Insufficient privileges.'], 403);
    }
}

/**
 * Store admin data in session after successful login.
 */
function auth_createSession(array $admin): void {
    session_regenerate_id(true);
    $_SESSION[ADMIN_SESSION_KEY]   = $admin['id'];
    $_SESSION[ADMIN_SESSION_EMAIL] = $admin['email'];
    $_SESSION[ADMIN_SESSION_ROLE]  = $admin['role'];
    $_SESSION[ADMIN_SESSION_NAME]  = $admin['full_name'];
    $_SESSION[ADMIN_SESSION_CSRF]  = bin2hex(random_bytes(32));
    $_SESSION['_last_regen']       = time();
}

/**
 * Destroy the admin session.
 */
function auth_destroySession(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Get the CSRF token for the current session (create if missing).
 */
function auth_csrfToken(): string {
    if (empty($_SESSION[ADMIN_SESSION_CSRF])) {
        $_SESSION[ADMIN_SESSION_CSRF] = bin2hex(random_bytes(32));
    }
    return $_SESSION[ADMIN_SESSION_CSRF];
}

/**
 * Validate a submitted CSRF token.
 */
function auth_validateCsrf(string $submitted): bool {
    $expected = $_SESSION[ADMIN_SESSION_CSRF] ?? '';
    return $expected !== '' && hash_equals($expected, $submitted);
}


// ╔══════════════════════════════════════════════════════════╗
// ║  SECTION B — RATE LIMITING (session-based, no Redis)    ║
// ╚══════════════════════════════════════════════════════════╝

function rateLimit_key(): string {
    return 'login_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function rateLimit_isBlocked(): bool {
    $key  = rateLimit_key();
    $data = $_SESSION[$key] ?? null;
    if (!$data) return false;
    if (time() > $data['reset_at']) {
        unset($_SESSION[$key]);
        return false;
    }
    return $data['count'] >= LOGIN_MAX_ATTEMPTS;
}

function rateLimit_increment(): void {
    $key  = rateLimit_key();
    $data = $_SESSION[$key] ?? ['count' => 0, 'reset_at' => time() + LOGIN_WINDOW_SECONDS];
    if (time() > $data['reset_at']) {
        $data = ['count' => 0, 'reset_at' => time() + LOGIN_WINDOW_SECONDS];
    }
    $data['count']++;
    $_SESSION[$key] = $data;
}

function rateLimit_remaining(): int {
    $key  = rateLimit_key();
    $data = $_SESSION[$key] ?? null;
    if (!$data || time() > $data['reset_at']) return LOGIN_MAX_ATTEMPTS;
    return max(0, LOGIN_MAX_ATTEMPTS - $data['count']);
}

function rateLimit_reset(): void {
    unset($_SESSION[rateLimit_key()]);
}


// ╔══════════════════════════════════════════════════════════╗
// ║  SECTION C — LOGIN ENDPOINT (POST handler)              ║
// ╚══════════════════════════════════════════════════════════╝

/**
 * Handle a JSON POST login request.
 * Expected body: { "email": "...", "password": "..." }
 * Returns JSON: { "admin": {...}, "csrf_token": "..." }
 *            or { "error": "..." }
 */
function handleLoginRequest(): void {
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
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Invalid email format.'], 400);
    }

    // Rate limiting
    if (rateLimit_isBlocked()) {
        jsonResponse([
            'error'           => 'Too many failed attempts. Please wait 10 minutes.',
            'retry_after_sec' => LOGIN_WINDOW_SECONDS,
        ], 429);
    }

    // Lookup admin
    $admin = admin_findByEmail($email);

    if (!$admin || !admin_verifyPassword($password, $admin['password_hash'])) {
        rateLimit_increment();
        $remaining = rateLimit_remaining();
        jsonResponse([
            'error'             => 'Invalid email or password.',
            'attempts_remaining' => $remaining,
        ], 401);
    }

    // Success — create session
    rateLimit_reset();
    auth_createSession($admin);
    admin_touchLogin($admin['id']);

    jsonResponse([
        'admin' => [
            'id'         => $admin['id'],
            'email'      => $admin['email'],
            'full_name'  => $admin['full_name'],
            'role'       => $admin['role'],
        ],
        'csrf_token' => auth_csrfToken(),
        'redirect'   => ADMIN_DASHBOARD_PAGE,
    ]);
}


// ╔══════════════════════════════════════════════════════════╗
// ║  SECTION D — DIRECT CALL ROUTER                         ║
// ╚══════════════════════════════════════════════════════════╝
// When auth.php is hit directly via HTTP (from login.html's fetch()),
// process the login. When it's require_once'd as a library, do nothing.

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'auth.php') {
    handleLoginRequest();
}
