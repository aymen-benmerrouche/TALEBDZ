<?php
// ============================================================
// api/announcements.php — Manage announcements, ads, and videos
// GET    → list announcements/ads/videos
// POST   → create announcement/ad/video
// PUT    → update announcement/ad/video
// DELETE → delete announcement/ad/video
// ============================================================
declare(strict_types=1);
$_rootDir = dirname(__DIR__);
require_once $_rootDir . '/db/config.php';
require_once $_rootDir . '/db/functions.php';
require_once $_rootDir . '/admin/auth.php';

setCorsHeaders();
require_admin_auth();

// ── Helper: Parse URL path segments ──────────────────────────
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = array_filter(explode('/', $path));
$segments = array_values($segments);

// Expected format: /api/announcements.php/{type}/{id?}
// type = announcements|ads|videos
$type = null;
$id = null;

foreach ($segments as $i => $seg) {
    if ($seg === 'announcements.php' && isset($segments[$i + 1])) {
        $type = $segments[$i + 1];
        if (isset($segments[$i + 2])) {
            $id = $segments[$i + 2];
        }
        break;
    }
}

// Default to announcements if no type specified
if (!$type || !in_array($type, ['announcements', 'ads', 'videos'])) {
    $type = 'announcements';
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// GET: List items or get single item
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    // ── ANNOUNCEMENTS ──
    if ($type === 'announcements') {
        jsonResponse(announcements_list(50));
    }
    
    // ── ADS ──
    if ($type === 'ads') {
        if ($id) {
            $ad = ads_get($id);
            if (!$ad) {
                jsonResponse(['error' => 'Ad not found'], 404);
            }
            jsonResponse($ad);
        } else {
            $includeInactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] === 'true';
            jsonResponse(ads_list($includeInactive));
        }
    }
    
    // ── VIDEOS ──
    if ($type === 'videos') {
        if ($id) {
            $video = videos_get($id);
            if (!$video) {
                jsonResponse(['error' => 'Video not found'], 404);
            }
            jsonResponse($video);
        } else {
            $includeInactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] === 'true';
            $category = $_GET['category'] ?? null;
            jsonResponse(videos_list($includeInactive, $category));
        }
    }
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// POST: Create new item
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input') ?: '{}', true);
    
    // ── CREATE ANNOUNCEMENT ──
    if ($type === 'announcements') {
        $title   = trim((string)($body['title']    ?? ''));
        $message = trim((string)($body['message']  ?? ''));
        $sendAt  = trim((string)($body['send_at']  ?? ''));

        if (!$title || !$message) {
            jsonResponse(['error' => 'title and message are required.'], 400);
        }

        $id = announcements_create([
            'title'   => $title,
            'message' => $message,
            'send_at' => $sendAt ?: null,
        ]);

        jsonResponse(['success' => true, 'id' => $id]);
    }
    
    // ── CREATE AD ──
    if ($type === 'ads') {
        $title       = trim((string)($body['title'] ?? ''));
        $description = trim((string)($body['description'] ?? ''));
        // Support both drive_url and google_drive_url
        $driveUrl    = trim((string)($body['google_drive_url'] ?? $body['drive_url'] ?? ''));
        $startDate   = trim((string)($body['start_date'] ?? ''));
        $endDate     = trim((string)($body['end_date'] ?? ''));
        
        if (!$title || !$driveUrl || !$startDate || !$endDate) {
            error_log('[TalebDZ API] ads_create validation failed - title: ' . $title . ', drive_url: ' . $driveUrl . ', start_date: ' . $startDate . ', end_date: ' . $endDate);
            jsonResponse(['error' => 'title, drive_url/google_drive_url, start_date, and end_date are required.'], 400);
        }
        
        error_log('[TalebDZ API] Creating ad with title: ' . $title);
        
        $id = ads_create([
            'title'             => $title,
            'description'       => $description,
            'google_drive_url'  => $driveUrl,
            'start_date'        => $startDate,
            'end_date'          => $endDate,
            'is_active'         => $body['is_active'] ?? true,
        ]);
        
        if ($id === null) {
            error_log('[TalebDZ API] ads_create returned null');
            jsonResponse(['error' => 'Failed to create ad. Check server logs for details.'], 500);
        }
        
        error_log('[TalebDZ API] Ad created successfully with ID: ' . $id);
        jsonResponse(['success' => true, 'id' => $id]);
    }
    
    // ── CREATE VIDEO ──
    if ($type === 'videos') {
        $title       = trim((string)($body['title'] ?? ''));
        $description = trim((string)($body['description'] ?? ''));
        $driveUrl    = trim((string)($body['google_drive_url'] ?? ''));
        $thumbnailUrl = trim((string)($body['thumbnail_url'] ?? ''));
        $duration    = isset($body['duration']) ? (int)$body['duration'] : null;
        $category    = trim((string)($body['category'] ?? ''));
        $tags        = $body['tags'] ?? [];
        
        if (!$title || !$driveUrl) {
            jsonResponse(['error' => 'title and google_drive_url are required.'], 400);
        }
        
        // Convert tags array to PostgreSQL array format
        $tagsStr = '{' . implode(',', array_map(function($tag) {
            return '"' . str_replace('"', '\"', trim($tag)) . '"';
        }, $tags)) . '}';
        
        $id = videos_create([
            'title'            => $title,
            'description'      => $description,
            'google_drive_url' => $driveUrl,
            'thumbnail_url'    => $thumbnailUrl ?: null,
            'duration'         => $duration,
            'category'         => $category ?: null,
            'tags'             => $tagsStr,
            'is_active'        => $body['is_active'] ?? true,
        ]);
        
        jsonResponse(['success' => true, 'id' => $id]);
    }
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// PUT: Update existing item
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    if (!$id) {
        jsonResponse(['error' => 'ID is required for update'], 400);
    }
    
    $body = json_decode(file_get_contents('php://input') ?: '{}', true);
    
    // ── UPDATE AD ──
    if ($type === 'ads') {
        $data = [];
        if (isset($body['title'])) $data['title'] = trim($body['title']);
        if (isset($body['description'])) $data['description'] = trim($body['description']);
        // Support both field names for backwards compatibility
        if (isset($body['google_drive_url'])) $data['google_drive_url'] = trim($body['google_drive_url']);
        if (isset($body['drive_url'])) $data['drive_url'] = trim($body['drive_url']);
        if (isset($body['start_date'])) $data['start_date'] = trim($body['start_date']);
        if (isset($body['end_date'])) $data['end_date'] = trim($body['end_date']);
        if (isset($body['is_active'])) $data['is_active'] = (bool)$body['is_active'];
        
        $success = ads_update($id, $data);
        jsonResponse(['success' => $success]);
    }
    
    // ── UPDATE VIDEO ──
    if ($type === 'videos') {
        $data = [];
        if (isset($body['title'])) $data['title'] = trim($body['title']);
        if (isset($body['description'])) $data['description'] = trim($body['description']);
        if (isset($body['google_drive_url'])) $data['google_drive_url'] = trim($body['google_drive_url']);
        if (isset($body['thumbnail_url'])) $data['thumbnail_url'] = trim($body['thumbnail_url']);
        if (isset($body['duration'])) $data['duration'] = (int)$body['duration'];
        if (isset($body['category'])) $data['category'] = trim($body['category']);
        if (isset($body['is_active'])) $data['is_active'] = (bool)$body['is_active'];
        
        if (isset($body['tags']) && is_array($body['tags'])) {
            $data['tags'] = '{' . implode(',', array_map(function($tag) {
                return '"' . str_replace('"', '\"', trim($tag)) . '"';
            }, $body['tags'])) . '}';
        }
        
        $success = videos_update($id, $data);
        jsonResponse(['success' => $success]);
    }
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// DELETE: Remove item
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (!$id) {
        jsonResponse(['error' => 'ID is required for delete'], 400);
    }
    
    // ── DELETE AD ──
    if ($type === 'ads') {
        $success = ads_delete($id);
        jsonResponse(['success' => $success]);
    }
    
    // ── DELETE VIDEO ──
    if ($type === 'videos') {
        $success = videos_delete($id);
        jsonResponse(['success' => $success]);
    }
}

jsonResponse(['error' => 'Invalid request'], 400);
