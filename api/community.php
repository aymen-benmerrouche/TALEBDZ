<?php
// ============================================================
// api/community.php — Community post moderation
// POST { post_id, action: 'delete'|'hide' }
// GET  ?page=&filter=flagged  → posts list
// ============================================================
declare(strict_types=1);
$_rootDir = dirname(__DIR__);
require_once $_rootDir . '/db/config.php';
require_once $_rootDir . '/db/functions.php';
require_once $_rootDir . '/admin/auth.php';

setCorsHeaders();
require_admin_auth();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $page   = max(1, (int)($_GET['page']   ?? 1));
    $filter = (string)($_GET['filter'] ?? '');
    $search = (string)($_GET['search'] ?? '');
    jsonResponse(community_posts($page, 30, $filter, $search));
}

$body   = json_decode(file_get_contents('php://input') ?: '{}', true);
$postId = trim((string)($body['post_id'] ?? ''));
$action = trim((string)($body['action']  ?? ''));

if (!$postId || !in_array($action, ['delete','hide'], true)) {
    jsonResponse(['error' => 'Invalid post_id or action.'], 400);
}

if ($action === 'delete') {
    $ok = community_deletePost($postId);
    if (!$ok) jsonResponse(['error' => 'Post not found.'], 404);
    jsonResponse(['success' => true, 'action' => 'deleted']);
}

// hide — future: set is_hidden flag
jsonResponse(['success' => true, 'action' => 'hidden']);
