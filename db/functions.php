<?php
// ============================================================
// db/functions.php — TalebDZ Database Helper Functions
// Covers every table in COMPLETE_DATABASE_SCHEMA.sql
// All admin operations use the service-role key (bypasses RLS)
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// ╔══════════════════════════════════════════════════════════╗
// ║  SECTION 1 — ADMIN AUTHENTICATION                       ║
// ╚══════════════════════════════════════════════════════════╝

/**
 * Find an admin account by email.
 * Returns the full row (including password_hash) or null.
 */
function admin_findByEmail(string $email): ?array {
    $row = DB::fetchOne(
        'SELECT * FROM public.admin_accounts WHERE email = :email AND is_active = TRUE LIMIT 1',
        [':email' => strtolower(trim($email))]
    );
    return $row ?: null;
}

/**
 * Verify a plain-text password against the stored bcrypt hash.
 */
function admin_verifyPassword(string $plain, string $hash): bool {
    return password_verify($plain, $hash);
}

/**
 * Update last_login_at for an admin.
 */
function admin_touchLogin(string $adminId): void {
    DB::execute(
        'UPDATE public.admin_accounts SET last_login_at = NOW() WHERE id = :id',
        [':id' => $adminId]
    );
}

/**
 * Change an admin's password (hashes automatically).
 */
function admin_changePassword(string $adminId, string $newPlain): bool {
    $hash = password_hash($newPlain, PASSWORD_BCRYPT, ['cost' => 12]);
    $rows = DB::execute(
        'UPDATE public.admin_accounts SET password_hash = :hash, updated_at = NOW() WHERE id = :id',
        [':hash' => $hash, ':id' => $adminId]
    );
    return $rows > 0;
}

/**
 * List all admin accounts (without exposing password_hash).
 */
function admin_listAll(): array {
    return DB::fetchAll(
        'SELECT id, email, full_name, role, is_active, last_login_at, created_at
           FROM public.admin_accounts
          ORDER BY created_at DESC'
    );
}


// ╔══════════════════════════════════════════════════════════╗
// ║  SECTION 2 — USERS                                      ║
// ╚══════════════════════════════════════════════════════════╝

/**
 * Count total active users.
 */
function users_count(): int {
    return (int) DB::fetchOne('SELECT COUNT(*) AS n FROM public.users WHERE is_active = TRUE')['n'];
}

/**
 * Get paginated user list with optional search and subscription status.
 * Automatically falls back to REST API if database connection fails.
 *
 * @param int    $page    1-based page number
 * @param int    $limit   rows per page
 * @param string $search  search term for email/username/full_name
 * @param string $status  '' | 'active' | 'warned' | 'banned'
 */
function users_list(int $page = 1, int $limit = 25, string $search = '', string $status = ''): array {
    // Check if we should use REST API
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return users_list_rest($page, $limit, $search);
    }
    
    try {
        // Test database connection first
        DB::connection();
        
        $offset = ($page - 1) * $limit;
        $params = [];
        $where  = ['u.is_active IS NOT NULL']; // Changed from = TRUE to allow both active and banned users

        if ($search !== '') {
            $where[]           = "(u.email ILIKE :search OR u.username ILIKE :search OR u.full_name ILIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        if ($status === 'active') {
            $where[] = 'u.is_active = TRUE';
        } elseif ($status === 'banned') {
            $where[] = 'u.is_active = FALSE';
        }

        // Main query with LEFT JOINs to handle users without profiles
        $sql = "
            SELECT
                u.id, u.email, u.username, u.full_name, u.student_id,
                u.department, u.is_active, u.created_at,
                p.faculty, p.speciality, p.level, p.study_system, p.university,
                -- active subscription info
                (SELECT sp.name
                   FROM public.user_subscriptions us
                   JOIN public.subscription_plans sp ON us.plan_id = sp.id
                  WHERE us.user_id = u.id AND us.status = 'active' AND us.expires_at > NOW()
                  ORDER BY us.expires_at DESC LIMIT 1) AS plan_name,
                (SELECT us.expires_at
                   FROM public.user_subscriptions us
                  WHERE us.user_id = u.id AND us.status = 'active' AND us.expires_at > NOW()
                  ORDER BY us.expires_at DESC LIMIT 1) AS plan_expires_at
            FROM public.users u
            LEFT JOIN public.profiles p ON p.id = u.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY u.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $params[':limit']  = $limit;
        $params[':offset'] = $offset;

        $rows = DB::fetchAll($sql, $params);

        // Count query
        $countSql = "SELECT COUNT(*) AS n FROM public.users u WHERE " . implode(' AND ', $where);
        unset($params[':limit'], $params[':offset']);
        $total = (int) DB::fetchOne($countSql, $params)['n'];

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'limit' => $limit];
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] users_list PDO error, falling back to REST API: ' . $e->getMessage());
        // Auto-fallback to REST API
        return users_list_rest($page, $limit, $search);
    }
}

/**
 * REST API fallback for users_list
 */
function users_list_rest(int $page = 1, int $limit = 25, string $search = ''): array {
    try {
        $offset = ($page - 1) * $limit;
        
        $query = [
            'select' => '*',
            'limit' => (string)$limit,
            'offset' => (string)$offset,
            'order' => 'created_at.desc'
        ];
        
        if ($search !== '') {
            $query['or'] = "(email.ilike.*{$search}*,username.ilike.*{$search}*,full_name.ilike.*{$search}*)";
        }
        
        $response = Supabase::get('/rest/v1/users', $query, true);
        
        if (isset($response['error'])) {
            error_log('[TalebDZ REST] users_list error: ' . ($response['message'] ?? 'Unknown error'));
            return ['data' => [], 'total' => 0, 'page' => $page, 'limit' => $limit];
        }
        
        // Get total count (Supabase returns content-range header)
        $total = is_array($response) ? count($response) : 0;
        
        return [
            'data' => $response,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'source' => 'rest_api'
        ];
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] users_list error: ' . $e->getMessage());
        return ['data' => [], 'total' => 0, 'page' => $page, 'limit' => $limit, 'error' => $e->getMessage()];
    }
}

/**
 * Fetch a single user by UUID with their profile.
 */
function users_getById(string $userId): ?array {
    $row = DB::fetchOne(
        "SELECT u.*, p.faculty, p.speciality, p.level, p.study_system, p.university, p.avatar_url
           FROM public.users u
           LEFT JOIN public.profiles p ON p.id = u.id
          WHERE u.id = :id",
        [':id' => $userId]
    );
    return $row ?: null;
}

/**
 * Set a user's is_active flag (ban / unban).
 * Automatically falls back to REST API if database connection fails.
 */
function users_setActive(string $userId, bool $active): bool {
    // Check if we should use REST API
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return users_setActive_rest($userId, $active);
    }
    
    try {
        // Test database connection first
        DB::connection();
        
        $result = DB::execute(
            'UPDATE public.users SET is_active = :active, updated_at = NOW() WHERE id = :id',
            [':active' => $active ? 'true' : 'false', ':id' => $userId]
        );
        return $result > 0;
    } catch (Throwable $e) {
        error_log('[TalebDZ] users_setActive PDO error, falling back to REST API: ' . $e->getMessage());
        // Auto-fallback to REST API
        return users_setActive_rest($userId, $active);
    }
}

/**
 * REST API fallback for users_setActive
 */
function users_setActive_rest(string $userId, bool $active): bool {
    try {
        $response = Supabase::patch(
            '/rest/v1/users',
            ['is_active' => $active, 'updated_at' => date('c')],
            true,
            ['id' => "eq.{$userId}"]
        );
        
        return !isset($response['error']);
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] users_setActive error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Users active in the last 24 hours (approximated via last message).
 */
function users_activeToday(): int {
    return (int) DB::fetchOne(
        "SELECT COUNT(DISTINCT user_id) AS n FROM public.chat_sessions
          WHERE last_message_at > NOW() - INTERVAL '24 hours'"
    )['n'];
}

/**
 * New user registrations per day for the last N days.
 */
function users_registrationsPerDay(int $days = 7): array {
    return DB::fetchAll(
        "SELECT DATE(created_at) AS day, COUNT(*) AS count
           FROM public.users
          WHERE created_at > NOW() - INTERVAL ':days days'
          GROUP BY day ORDER BY day ASC",
        [':days' => $days]
    );
}


// ╔══════════════════════════════════════════════════════════╗
// ║  SECTION 3 — CHAT / RAG SESSIONS                        ║
// ╚══════════════════════════════════════════════════════════╝

/**
 * Total questions asked today.
 */
function chat_questionsToday(): int {
    return (int) DB::fetchOne(
        "SELECT COUNT(*) AS n FROM public.chat_messages
          WHERE role = 'user' AND created_at > CURRENT_DATE"
    )['n'];
}

/**
 * Questions per day for a date range.
 */
function chat_questionsPerDay(int $days = 7): array {
    return DB::fetchAll(
        "SELECT DATE(created_at) AS day, COUNT(*) AS count
           FROM public.chat_messages
          WHERE role = 'user' AND created_at > NOW() - INTERVAL '1 day' * :days
          GROUP BY day ORDER BY day ASC",
        [':days' => $days]
    );
}

/**
 * Average session duration in minutes.
 */
function chat_avgSessionMinutes(): float {
    $row = DB::fetchOne(
        "SELECT COALESCE(
            AVG(EXTRACT(EPOCH FROM (last_message_at - first_message_at)) / 60), 0
         ) AS avg_min
           FROM public.chat_sessions
          WHERE first_message_at IS NOT NULL AND last_message_at IS NOT NULL
            AND last_message_at > first_message_at"
    );
    return round((float)($row['avg_min'] ?? 0), 1);
}

/**
 * Top N most frequent topics (intent field from chat_messages).
 */
function chat_topTopics(int $n = 5): array {
    return DB::fetchAll(
        "SELECT intent AS topic, COUNT(*) AS count
           FROM public.chat_messages
          WHERE role = 'user' AND intent IS NOT NULL AND intent <> ''
          GROUP BY intent
          ORDER BY count DESC
          LIMIT :n",
        [':n' => $n]
    );
}

/**
 * Questions where no relevant source was used (unanswered / low-confidence).
 */
function chat_unansweredQuestions(int $limit = 50): array {
    return DB::fetchAll(
        "SELECT content AS question, created_at
           FROM public.chat_messages
          WHERE role = 'user'
            AND (sources_used IS NULL OR array_length(sources_used, 1) = 0)
          ORDER BY created_at DESC
          LIMIT :limit",
        [':limit' => $limit]
    );
}


// ╔══════════════════════════════════════════════════════════╗
// ║  SECTION 4 — EVENTS                                     ║
// ╚══════════════════════════════════════════════════════════╝

/**
 * Get upcoming events (future dates first).
 */
function events_list(int $limit = 50, int $offset = 0): array {
    return DB::fetchAll(
        "SELECT * FROM public.events
          WHERE event_date >= NOW()
          ORDER BY priority DESC, event_date ASC
          LIMIT :limit OFFSET :offset",
        [':limit' => $limit, ':offset' => $offset]
    );
}

/**
 * Create a new event. Returns the new event id.
 */
function events_create(array $data): int {
    return (int) DB::insertReturning(
        "INSERT INTO public.events
            (title, description, location, event_date, organizer, source, image_url, category, priority)
         VALUES
            (:title, :description, :location, :event_date, :organizer, :source, :image_url, :category, :priority)
         RETURNING id",
        [
            ':title'       => $data['title']       ?? '',
            ':description' => $data['description'] ?? null,
            ':location'    => $data['location']    ?? null,
            ':event_date'  => $data['event_date'],
            ':organizer'   => $data['organizer']   ?? null,
            ':source'      => $data['source']      ?? null,
            ':image_url'   => $data['image_url']   ?? null,
            ':category'    => $data['category']    ?? null,
            ':priority'    => (int)($data['priority'] ?? 0),
        ]
    );
}

/**
 * Update an existing event.
 */
function events_update(int $eventId, array $data): bool {
    return DB::execute(
        "UPDATE public.events SET
            title       = :title,
            description = :description,
            location    = :location,
            event_date  = :event_date,
            organizer   = :organizer,
            category    = :category,
            priority    = :priority,
            updated_at  = NOW()
         WHERE id = :id",
        [
            ':title'       => $data['title'],
            ':description' => $data['description'] ?? null,
            ':location'    => $data['location']    ?? null,
            ':event_date'  => $data['event_date'],
            ':organizer'   => $data['organizer']   ?? null,
            ':category'    => $data['category']    ?? null,
            ':priority'    => (int)($data['priority'] ?? 0),
            ':id'          => $eventId,
        ]
    ) > 0;
}

/**
 * Delete an event.
 */
function events_delete(int $eventId): bool {
    return DB::execute('DELETE FROM public.events WHERE id = :id', [':id' => $eventId]) > 0;
}


// ╔══════════════════════════════════════════════════════════╗
// ║  SECTION 5 — COMMUNITY POSTS & MODERATION               ║
// ╚══════════════════════════════════════════════════════════╝

/**
 * List community posts with author details.
 * Automatically falls back to REST API if database connection fails.
 *
 * @param int    $page     1-based page number
 * @param int    $limit    rows per page
 * @param string $filter   '' | 'flagged' | 'hidden'
 * @param string $search   search term for content
 */
function community_posts(int $page = 1, int $limit = 30, string $filter = '', string $search = ''): array {
    // Check if we should use REST API
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return community_posts_rest($page, $limit, $filter, $search);
    }
    
    try {
        // Test database connection first
        DB::connection();
        
        $offset = ($page - 1) * $limit;
        $where  = ['1=1'];
        $params = [':limit' => $limit, ':offset' => $offset];

        if ($filter === 'flagged') {
            $where[] = "pr.id IS NOT NULL";
        }
        if ($search !== '') {
            $where[]           = "cp.content ILIKE :search";
            $params[':search'] = '%' . $search . '%';
        }

        $rows = DB::fetchAll(
            "SELECT
                cp.id, cp.community_id, cp.content, cp.post_type,
                cp.likes_count, cp.comments_count, cp.created_at,
                u.email AS author_email, u.username AS author_username,
                p.full_name AS author_name,
                (SELECT COUNT(*) FROM public.post_reports pr2 WHERE pr2.post_id = cp.id AND pr2.status = 'pending') AS pending_reports
             FROM public.community_posts cp
             LEFT JOIN public.users u ON u.id = cp.user_id
             LEFT JOIN public.profiles p ON p.id = cp.user_id
             LEFT JOIN public.post_reports pr ON pr.post_id = cp.id AND pr.status = 'pending'
             WHERE " . implode(' AND ', $where) . "
             GROUP BY cp.id, u.email, u.username, p.full_name
             ORDER BY cp.created_at DESC
             LIMIT :limit OFFSET :offset",
            $params
        );

        unset($params[':limit'], $params[':offset']);
        $total = (int) DB::fetchOne(
            "SELECT COUNT(DISTINCT cp.id) AS n
               FROM public.community_posts cp
               LEFT JOIN public.post_reports pr ON pr.post_id = cp.id AND pr.status = 'pending'
              WHERE " . implode(' AND ', $where),
            $params
        )['n'];

        return ['data' => $rows, 'total' => $total];
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] community_posts PDO error, falling back to REST API: ' . $e->getMessage());
        // Auto-fallback to REST API
        return community_posts_rest($page, $limit, $filter, $search);
    }
}

/**
 * REST API fallback for community_posts
 */
function community_posts_rest(int $page = 1, int $limit = 30, string $filter = '', string $search = ''): array {
    try {
        $offset = ($page - 1) * $limit;
        
        // Get the posts
        $query = [
            'select' => 'id,community_id,content,post_type,likes_count,comments_count,created_at,user_id',
            'limit' => (string)$limit,
            'offset' => (string)$offset,
            'order' => 'created_at.desc'
        ];
        
        if ($search !== '') {
            $query['content'] = "ilike.*{$search}*";
        }
        
        $response = Supabase::get('/rest/v1/community_posts', $query, true);
        
        if (isset($response['error']) || !is_array($response)) {
            error_log('[TalebDZ REST] community_posts error: ' . ($response['message'] ?? 'Invalid response'));
            return ['data' => [], 'total' => 0];
        }
        
        // Extract unique user IDs
        $userIds = [];
        foreach ($response as $post) {
            if (!empty($post['user_id'])) {
                $userIds[$post['user_id']] = true;
            }
        }
        
        // Fetch all users and profiles in bulk
        $usersData = [];
        $profilesData = [];
        
        if (!empty($userIds)) {
            $userIdsList = array_keys($userIds);
            
            // Fetch users (using OR filter for multiple IDs)
            $usersResp = Supabase::get('/rest/v1/users', [
                'select' => 'id,email,username',
                'id' => 'in.(' . implode(',', $userIdsList) . ')'
            ], true);
            
            if (is_array($usersResp) && !isset($usersResp['error'])) {
                foreach ($usersResp as $user) {
                    if (is_array($user) && isset($user['id'])) {
                        $usersData[$user['id']] = $user;
                    }
                }
            }
            
            // Fetch profiles
            $profilesResp = Supabase::get('/rest/v1/profiles', [
                'select' => 'id,full_name',
                'id' => 'in.(' . implode(',', $userIdsList) . ')'
            ], true);
            
            if (is_array($profilesResp) && !isset($profilesResp['error'])) {
                foreach ($profilesResp as $profile) {
                    if (is_array($profile) && isset($profile['id'])) {
                        $profilesData[$profile['id']] = $profile;
                    }
                }
            }
        }
        
        // Extract post IDs for reports count
        $postIds = array_column($response, 'id');
        $reportsCount = [];
        
        if (!empty($postIds)) {
            // Fetch all pending reports for these posts
            $reportsResp = Supabase::get('/rest/v1/post_reports', [
                'select' => 'post_id',
                'post_id' => 'in.(' . implode(',', $postIds) . ')',
                'status' => 'eq.pending'
            ], true);
            
            if (is_array($reportsResp) && !isset($reportsResp['error'])) {
                // Count reports per post
                foreach ($reportsResp as $report) {
                    if (is_array($report) && isset($report['post_id'])) {
                        $postId = $report['post_id'];
                        $reportsCount[$postId] = ($reportsCount[$postId] ?? 0) + 1;
                    }
                }
            }
        }
        
        // Transform response with joined data
        $data = [];
        foreach ($response as $post) {
            if (!is_array($post)) continue;
            
            $userId = $post['user_id'] ?? null;
            $user = $usersData[$userId] ?? [];
            $profile = $profilesData[$userId] ?? [];
            
            $data[] = [
                'id' => $post['id'] ?? '',
                'community_id' => $post['community_id'] ?? '',
                'content' => $post['content'] ?? '',
                'post_type' => $post['post_type'] ?? 'discussion',
                'likes_count' => (int)($post['likes_count'] ?? 0),
                'comments_count' => (int)($post['comments_count'] ?? 0),
                'created_at' => $post['created_at'] ?? '',
                'author_email' => is_array($user) ? ($user['email'] ?? '') : '',
                'author_username' => is_array($user) ? ($user['username'] ?? '') : '',
                'author_name' => is_array($profile) ? ($profile['full_name'] ?? '') : '',
                'pending_reports' => (int)($reportsCount[$post['id'] ?? ''] ?? 0),
            ];
        }
        
        // Get total count
        $countResp = Supabase::get('/rest/v1/community_posts', [
            'select' => 'count',
            'count' => 'exact'
        ], true);
        
        $total = count($response);
        if (isset($countResp[0]['count'])) {
            $total = (int)$countResp[0]['count'];
        }
        
        return [
            'data' => $data,
            'total' => $total,
            'source' => 'rest_api'
        ];
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] community_posts error: ' . $e->getMessage());
        return ['data' => [], 'total' => 0, 'error' => $e->getMessage()];
    }
}

/**
 * Delete a community post.
 * Automatically falls back to REST API if database connection fails.
 */
function community_deletePost(string $postId): bool {
    // Check if we should use REST API
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return community_deletePost_rest($postId);
    }
    
    try {
        // Test database connection first
        DB::connection();
        
        return DB::execute(
            "DELETE FROM public.community_posts WHERE id = :id",
            [':id' => $postId]
        ) > 0;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] community_deletePost PDO error, falling back to REST API: ' . $e->getMessage());
        // Auto-fallback to REST API
        return community_deletePost_rest($postId);
    }
}

/**
 * REST API fallback for community_deletePost
 */
function community_deletePost_rest(string $postId): bool {
    try {
        $response = Supabase::delete(
            '/rest/v1/community_posts',
            ['id' => "eq.{$postId}"],
            true
        );
        
        return !isset($response['error']);
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] community_deletePost error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete a specific comment.
 * Automatically falls back to REST API if database connection fails.
 */
function community_deleteComment(string $commentId): bool {
    // Check if we should use REST API
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return community_deleteComment_rest($commentId);
    }
    
    try {
        // Test database connection first
        DB::connection();
        
        return DB::execute(
            "DELETE FROM public.community_comments WHERE id = :id",
            [':id' => $commentId]
        ) > 0;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] community_deleteComment PDO error, falling back to REST API: ' . $e->getMessage());
        // Auto-fallback to REST API
        return community_deleteComment_rest($commentId);
    }
}

/**
 * REST API fallback for community_deleteComment
 */
function community_deleteComment_rest(string $commentId): bool {
    try {
        $response = Supabase::delete(
            '/rest/v1/community_comments',
            ['id' => "eq.{$commentId}"],
            true
        );
        
        return !isset($response['error']);
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] community_deleteComment error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Total flagged posts count (pending reports).
 * Automatically falls back to REST API if database connection fails.
 */
function community_flaggedCount(): int {
    // Check if we should use REST API
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return community_flaggedCount_rest();
    }
    
    try {
        // Test database connection first
        DB::connection();
        
        return (int) DB::fetchOne(
            "SELECT COUNT(DISTINCT post_id) AS n FROM public.post_reports WHERE status = 'pending'"
        )['n'];
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] community_flaggedCount PDO error, falling back to REST API: ' . $e->getMessage());
        // Auto-fallback to REST API
        return community_flaggedCount_rest();
    }
}

/**
 * REST API fallback for community_flaggedCount
 */
function community_flaggedCount_rest(): int {
    try {
        $response = Supabase::get(
            '/rest/v1/post_reports',
            [
                'select' => 'post_id',
                'status' => 'eq.pending'
            ],
            true
        );
        
        if (isset($response['error'])) {
            return 0;
        }
        
        // Count unique post_ids
        $postIds = [];
        if (is_array($response)) {
            foreach ($response as $report) {
                if (isset($report['post_id'])) {
                    $postIds[$report['post_id']] = true;
                }
            }
        }
        
        return count($postIds);
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] community_flaggedCount error: ' . $e->getMessage());
        return 0;
    }
}


// ╔══════════════════════════════════════════════════════════╗
// ║  SECTION 6 — REPORTS (POST / COMMENT)                   ║
// ╚══════════════════════════════════════════════════════════╝

/**
 * Get all reports with reporter and post details.
 * Automatically falls back to REST API if database connection fails.
 */
function reports_list(string $status = 'pending', int $limit = 50): array {
    // Check if we should use REST API
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return reports_list_rest($status, $limit);
    }
    
    try {
        // Test database connection first
        DB::connection();
        
        $params = [':limit' => $limit];
        $where  = [];

        if ($status !== '') {
            $where[]          = "pr.status = :status";
            $params[':status'] = $status;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return DB::fetchAll(
            "SELECT
                pr.id, pr.reason, pr.description, pr.status, pr.created_at,
                pr.post_id,
                cp.content AS post_content,
                reporter.email AS reporter_email,
                reporter.username AS reporter_username,
                author.email  AS post_author_email,
                author.username AS post_author_username
             FROM public.post_reports pr
             JOIN public.community_posts cp ON cp.id = pr.post_id
             JOIN public.users reporter ON reporter.id = pr.reporter_id
             JOIN public.users author   ON author.id  = cp.user_id
             {$whereClause}
             ORDER BY pr.created_at DESC
             LIMIT :limit",
            $params
        );
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] reports_list PDO error, falling back to REST API: ' . $e->getMessage());
        // Auto-fallback to REST API
        return reports_list_rest($status, $limit);
    }
}

/**
 * REST API fallback for reports_list
 */
function reports_list_rest(string $status = 'pending', int $limit = 50): array {
    try {
        // Build query
        $query = [
            'select' => 'id,reason,description,status,created_at,post_id,reporter_id',
            'limit' => (string)$limit,
            'order' => 'created_at.desc'
        ];
        
        if ($status !== '') {
            $query['status'] = "eq.{$status}";
        }
        
        // Get reports
        $response = Supabase::get('/rest/v1/post_reports', $query, true);
        
        if (isset($response['error']) || !is_array($response)) {
            error_log('[TalebDZ REST] reports_list error: ' . ($response['message'] ?? 'Invalid response'));
            return [];
        }
        
        // Extract unique IDs for joins
        $postIds = [];
        $reporterIds = [];
        
        foreach ($response as $report) {
            if (!is_array($report)) continue;
            if (!empty($report['post_id'])) $postIds[$report['post_id']] = true;
            if (!empty($report['reporter_id'])) $reporterIds[$report['reporter_id']] = true;
        }
        
        // Fetch posts
        $postsData = [];
        if (!empty($postIds)) {
            $postsResp = Supabase::get('/rest/v1/community_posts', [
                'select' => 'id,content,user_id',
                'id' => 'in.(' . implode(',', array_keys($postIds)) . ')'
            ], true);
            
            if (is_array($postsResp) && !isset($postsResp['error'])) {
                foreach ($postsResp as $post) {
                    if (is_array($post) && isset($post['id'])) {
                        $postsData[$post['id']] = $post;
                    }
                }
            }
        }
        
        // Extract author IDs from posts
        $authorIds = [];
        foreach ($postsData as $post) {
            if (!empty($post['user_id'])) $authorIds[$post['user_id']] = true;
        }
        
        // Fetch all users (reporters + authors)
        $usersData = [];
        $allUserIds = array_merge(array_keys($reporterIds), array_keys($authorIds));
        
        if (!empty($allUserIds)) {
            $usersResp = Supabase::get('/rest/v1/users', [
                'select' => 'id,email,username',
                'id' => 'in.(' . implode(',', $allUserIds) . ')'
            ], true);
            
            if (is_array($usersResp) && !isset($usersResp['error'])) {
                foreach ($usersResp as $user) {
                    if (is_array($user) && isset($user['id'])) {
                        $usersData[$user['id']] = $user;
                    }
                }
            }
        }
        
        // Assemble final data
        $data = [];
        foreach ($response as $report) {
            if (!is_array($report)) continue;
            
            $postId = $report['post_id'] ?? '';
            $reporterId = $report['reporter_id'] ?? '';
            $post = $postsData[$postId] ?? [];
            $authorId = $post['user_id'] ?? '';
            $reporter = $usersData[$reporterId] ?? [];
            $author = $usersData[$authorId] ?? [];
            
            $data[] = [
                'id' => $report['id'] ?? '',
                'reason' => $report['reason'] ?? '',
                'description' => $report['description'] ?? '',
                'status' => $report['status'] ?? 'pending',
                'created_at' => $report['created_at'] ?? '',
                'post_id' => $postId,
                'post_content' => is_array($post) ? ($post['content'] ?? '') : '',
                'reporter_email' => is_array($reporter) ? ($reporter['email'] ?? '') : '',
                'reporter_username' => is_array($reporter) ? ($reporter['username'] ?? '') : '',
                'post_author_email' => is_array($author) ? ($author['email'] ?? '') : '',
                'post_author_username' => is_array($author) ? ($author['username'] ?? '') : '',
            ];
        }
        
        return $data;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] reports_list error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Resolve a report: 'accepted' removes the post, 'dismissed' keeps it.
 * Updates the report status and optionally deletes the post.
 * Automatically falls back to REST API if database connection fails.
 */
function reports_resolve(string $reportId, string $action): bool {
    // Validate action
    if (!in_array($action, ['accepted', 'dismissed'], true)) {
        return false;
    }

    // Check if we should use REST API
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return reports_resolve_rest($reportId, $action);
    }
    
    try {
        // Test database connection first
        DB::connection();
        
        $report = DB::fetchOne(
            'SELECT * FROM public.post_reports WHERE id = :id',
            [':id' => $reportId]
        );
        if (!$report) return false;

        // Update report status
        DB::execute(
            "UPDATE public.post_reports SET status = :status, updated_at = NOW() WHERE id = :id",
            [':status' => $action === 'accepted' ? 'resolved' : 'dismissed', ':id' => $reportId]
        );

        // If accepted, delete the post (cascades to comments, likes, other reports)
        if ($action === 'accepted') {
            DB::execute(
                'DELETE FROM public.community_posts WHERE id = :id',
                [':id' => $report['post_id']]
            );
        }

        return true;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] reports_resolve PDO error, falling back to REST API: ' . $e->getMessage());
        // Auto-fallback to REST API
        return reports_resolve_rest($reportId, $action);
    }
}

/**
 * REST API fallback for reports_resolve
 */
function reports_resolve_rest(string $reportId, string $action): bool {
    try {
        // Get report first
        $reportResp = Supabase::get(
            '/rest/v1/post_reports',
            [
                'select' => 'id,post_id,status',
                'id' => "eq.{$reportId}"
            ],
            true
        );
        
        if (!is_array($reportResp) || empty($reportResp) || isset($reportResp['error'])) {
            error_log('[TalebDZ REST] reports_resolve: Report not found');
            return false;
        }
        
        $report = $reportResp[0];
        
        // Update report status
        $newStatus = $action === 'accepted' ? 'resolved' : 'dismissed';
        $updateResp = Supabase::patch(
            '/rest/v1/post_reports',
            [
                'status' => $newStatus,
                'updated_at' => date('c')
            ],
            true,
            ['id' => "eq.{$reportId}"]
        );
        
        if (isset($updateResp['error'])) {
            error_log('[TalebDZ REST] reports_resolve: Failed to update report status');
            return false;
        }
        
        // If accepted, delete the post
        if ($action === 'accepted' && !empty($report['post_id'])) {
            $deleteResp = Supabase::delete(
                '/rest/v1/community_posts',
                ['id' => "eq.{$report['post_id']}"],
                true
            );
            
            if (isset($deleteResp['error'])) {
                error_log('[TalebDZ REST] reports_resolve: Failed to delete post');
                // Report is already updated, so still return true
            }
        }
        
        return true;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] reports_resolve error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Count pending reports.
 * Automatically falls back to REST API if database connection fails.
 */
function reports_pendingCount(): int {
    // Check if we should use REST API
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return reports_pendingCount_rest();
    }
    
    try {
        // Test database connection first
        DB::connection();
        
        return (int) DB::fetchOne(
            "SELECT COUNT(*) AS n FROM public.post_reports WHERE status = 'pending'"
        )['n'];
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] reports_pendingCount PDO error, falling back to REST API: ' . $e->getMessage());
        // Auto-fallback to REST API
        return reports_pendingCount_rest();
    }
}

/**
 * REST API fallback for reports_pendingCount
 */
function reports_pendingCount_rest(): int {
    try {
        $response = Supabase::get(
            '/rest/v1/post_reports',
            [
                'select' => 'id',
                'status' => 'eq.pending'
            ],
            true
        );
        
        if (isset($response['error'])) {
            error_log('[TalebDZ REST] reports_pendingCount error: ' . ($response['message'] ?? 'Unknown error'));
            return 0;
        }
        
        return is_array($response) ? count($response) : 0;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] reports_pendingCount error: ' . $e->getMessage());
        return 0;
    }
}


// ╔══════════════════════════════════════════════════════════╗
// ║  SECTION 7 — ANNOUNCEMENTS                              ║
// ╚══════════════════════════════════════════════════════════╝

/**
 * Insert a scheduled or immediate announcement into the events table
 * (or a dedicated announcements table if you add one).
 * Here we store it as an event with category='announcement'.
 */
function announcements_create(array $data): int {
    $sendAt = !empty($data['send_at']) ? $data['send_at'] : date('c');
    return events_create([
        'title'       => $data['title']    ?? 'Announcement',
        'description' => $data['message']  ?? '',
        'event_date'  => $sendAt,
        'category'    => 'announcement',
        'organizer'   => 'Admin',
        'priority'    => 2,
    ]);
}

/**
 * List sent announcements (events with category='announcement').
 */
function announcements_list(int $limit = 30): array {
    return DB::fetchAll(
        "SELECT id, title, description AS message, event_date AS sent_at, created_at
           FROM public.events
          WHERE category = 'announcement'
          ORDER BY event_date DESC
          LIMIT :limit",
        [':limit' => $limit]
    );
}


// ╔══════════════════════════════════════════════════════════╗
// ║  SECTION 14 — ADS MANAGEMENT                            ║
// ╚══════════════════════════════════════════════════════════╝

/**
 * Create a new ad.
 * Automatically falls back to REST API if database connection fails.
 */
function ads_create(array $data): ?string {
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return ads_create_rest($data);
    }
    
    try {
        DB::connection();
        
        // Support both drive_url and google_drive_url for backwards compatibility
        $driveUrl = $data['google_drive_url'] ?? $data['drive_url'] ?? null;
        
        // For ads table: use link_url (not google_drive_url)
        $id = DB::insertReturning(
            "INSERT INTO public.ads (title, description, link_url, start_date, end_date, is_active)
             VALUES (:title, :description, :link_url, :start_date, :end_date, :is_active)
             RETURNING id",
            [
                ':title'        => $data['title'],
                ':description'  => $data['description'] ?? null,
                ':link_url'     => $driveUrl,
                ':start_date'   => $data['start_date'],
                ':end_date'     => $data['end_date'],
                ':is_active'    => $data['is_active'] ?? true,
            ]
        );
        
        return (string)$id;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] ads_create PDO error, falling back to REST API: ' . $e->getMessage());
        return ads_create_rest($data);
    }
}

/**
 * REST API fallback for ads_create
 */
function ads_create_rest(array $data): ?string {
    try {
        // Support both drive_url and google_drive_url for backwards compatibility
        $driveUrl = $data['google_drive_url'] ?? $data['drive_url'] ?? null;
        
        // For ads table: use link_url (not google_drive_url)
        $payload = [
            'title'        => $data['title'],
            'description'  => $data['description'] ?? null,
            'link_url'     => $driveUrl,
            'start_date'   => $data['start_date'],
            'end_date'     => $data['end_date'],
            'is_active'    => $data['is_active'] ?? true,
        ];
        
        error_log('[TalebDZ REST] ads_create payload: ' . json_encode($payload));
        
        $response = Supabase::post('/rest/v1/ads', $payload, true); // true = use service_role key
        
        error_log('[TalebDZ REST] ads_create response: ' . json_encode($response));
        
        if (isset($response['error'])) {
            error_log('[TalebDZ REST] ads_create error: ' . json_encode($response));
            return null;
        }
        
        if (empty($response)) {
            error_log('[TalebDZ REST] ads_create empty response');
            return null;
        }
        
        // Response is an array of created records
        if (is_array($response) && isset($response[0]['id'])) {
            error_log('[TalebDZ REST] ads_create success: ' . $response[0]['id']);
            return $response[0]['id'];
        }
        
        error_log('[TalebDZ REST] ads_create unexpected response format');
        return null;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] ads_create exception: ' . $e->getMessage());
        return null;
    }
}

/**
 * List all ads with optional filters.
 * Automatically falls back to REST API if database connection fails.
 */
function ads_list(bool $includeInactive = false): array {
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return ads_list_rest($includeInactive);
    }
    
    try {
        DB::connection();
        
        $where = $includeInactive ? '' : 'WHERE is_active = TRUE';
        return DB::fetchAll(
            "SELECT id, title, description, link_url, start_date, end_date, 
                    is_active, impressions_count, created_at, updated_at
               FROM public.ads
               $where
              ORDER BY created_at DESC"
        );
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] ads_list PDO error, falling back to REST API: ' . $e->getMessage());
        return ads_list_rest($includeInactive);
    }
}

/**
 * REST API fallback for ads_list
 */
function ads_list_rest(bool $includeInactive = false): array {
    try {
        // Use link_url and impressions_count to match ads table schema
        $query = [
            'select' => 'id,title,description,link_url,start_date,end_date,is_active,impressions_count,created_at,updated_at',
            'order' => 'created_at.desc'
        ];
        
        if (!$includeInactive) {
            $query['is_active'] = 'eq.true';
        }
        
        $response = Supabase::get('/rest/v1/ads', $query, true);
        
        if (isset($response['error'])) {
            error_log('[TalebDZ REST] ads_list error: ' . ($response['message'] ?? 'Unknown error'));
            return [];
        }
        
        // Remove _http_status from response
        unset($response['_http_status']);
        
        // Re-index array to ensure numeric keys starting from 0
        return is_array($response) ? array_values($response) : [];
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] ads_list error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get a single ad by ID.
 * Automatically falls back to REST API if database connection fails.
 */
function ads_get(string $id): ?array {
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return ads_get_rest($id);
    }
    
    try {
        DB::connection();
        
        $row = DB::fetchOne(
            "SELECT * FROM public.ads WHERE id = :id",
            [':id' => $id]
        );
        return $row ?: null;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] ads_get PDO error, falling back to REST API: ' . $e->getMessage());
        return ads_get_rest($id);
    }
}

/**
 * REST API fallback for ads_get
 */
function ads_get_rest(string $id): ?array {
    try {
        $response = Supabase::get('/rest/v1/ads', [
            'select' => '*',
            'id' => "eq.{$id}"
        ], true);
        
        if (isset($response['error'])) {
            return null;
        }
        
        // Remove _http_status
        unset($response['_http_status']);
        
        // Get first result
        return is_array($response) && isset($response[0]) ? $response[0] : null;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] ads_get error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Update an ad.
 * Automatically falls back to REST API if database connection fails.
 */
function ads_update(string $id, array $data): bool {
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return ads_update_rest($id, $data);
    }
    
    try {
        DB::connection();
        
        $fields = [];
        $params = [':id' => $id];
        
        // For ads table: use link_url (not google_drive_url)
        $allowedFields = ['title', 'description', 'link_url', 'start_date', 'end_date', 'is_active'];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        // Handle drive_url and google_drive_url as aliases for link_url
        if (array_key_exists('drive_url', $data) && !array_key_exists('link_url', $data)) {
            $fields[] = "link_url = :link_url";
            $params[":link_url"] = $data['drive_url'];
        }
        if (array_key_exists('google_drive_url', $data) && !array_key_exists('link_url', $data)) {
            $fields[] = "link_url = :link_url";
            $params[":link_url"] = $data['google_drive_url'];
        }
        
        if (empty($fields)) return false;
        
        $fields[] = "updated_at = NOW()";
        $sql = "UPDATE public.ads SET " . implode(', ', $fields) . " WHERE id = :id";
        
        return DB::execute($sql, $params) > 0;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] ads_update PDO error, falling back to REST API: ' . $e->getMessage());
        return ads_update_rest($id, $data);
    }
}

/**
 * REST API fallback for ads_update
 */
function ads_update_rest(string $id, array $data): bool {
    try {
        $updateData = [];
        
        // For ads table: use link_url (not google_drive_url)
        foreach (['title', 'description', 'link_url', 'start_date', 'end_date', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }
        
        // Support drive_url and google_drive_url as aliases for link_url
        if (array_key_exists('drive_url', $data) && !array_key_exists('link_url', $updateData)) {
            $updateData['link_url'] = $data['drive_url'];
        }
        if (array_key_exists('google_drive_url', $data) && !array_key_exists('link_url', $updateData)) {
            $updateData['link_url'] = $data['google_drive_url'];
        }
        
        if (empty($updateData)) return false;
        
        $updateData['updated_at'] = date('c');
        
        $response = Supabase::patch('/rest/v1/ads', $updateData, true, ['id' => "eq.{$id}"]);
        
        return !isset($response['error']);
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] ads_update error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete an ad.
 * Automatically falls back to REST API if database connection fails.
 */
function ads_delete(string $id): bool {
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return ads_delete_rest($id);
    }
    
    try {
        DB::connection();
        
        return DB::execute(
            "DELETE FROM public.ads WHERE id = :id",
            [':id' => $id]
        ) > 0;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] ads_delete PDO error, falling back to REST API: ' . $e->getMessage());
        return ads_delete_rest($id);
    }
}

/**
 * REST API fallback for ads_delete
 */
function ads_delete_rest(string $id): bool {
    try {
        $response = Supabase::delete('/rest/v1/ads', ['id' => "eq.{$id}"], true);
        return !isset($response['error']);
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] ads_delete error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get active ads within date range.
 * Automatically falls back to REST API if database connection fails.
 */
function ads_active(): array {
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return ads_active_rest();
    }
    
    try {
        DB::connection();
        
        return DB::fetchAll(
            "SELECT id, title, description, link_url, start_date, end_date, impressions_count
               FROM public.ads
              WHERE is_active = TRUE
                AND start_date <= NOW()
                AND end_date >= NOW()
              ORDER BY start_date DESC"
        );
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] ads_active PDO error, falling back to REST API: ' . $e->getMessage());
        return ads_active_rest();
    }
}

/**
 * REST API fallback for ads_active
 */
function ads_active_rest(): array {
    try {
        $now = date('c');
        $response = Supabase::get('/rest/v1/ads', [
            'select' => 'id,title,description,link_url,start_date,end_date,impressions_count',
            'is_active' => 'eq.true',
            'start_date' => "lte.{$now}",
            'end_date' => "gte.{$now}",
            'order' => 'start_date.desc'
        ], true);
        
        if (isset($response['error'])) {
            return [];
        }
        
        // Remove _http_status and re-index
        unset($response['_http_status']);
        return is_array($response) ? array_values($response) : [];
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] ads_active error: ' . $e->getMessage());
        return [];
    }
}


// ╔══════════════════════════════════════════════════════════╗
// ║  SECTION 15 — VIDEOS MANAGEMENT                         ║
// ╚══════════════════════════════════════════════════════════╝

/**
 * Create a new video.
 * Automatically falls back to REST API if database connection fails.
 */
function videos_create(array $data): ?string {
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return videos_create_rest($data);
    }
    
    try {
        DB::connection();
        
        $tags = $data['tags'] ?? [];
        if (is_array($tags)) {
            $tags = '{' . implode(',', array_map(function($t) { return '"' . str_replace('"', '\\"', $t) . '"'; }, $tags)) . '}';
        }
        
        $id = DB::insertReturning(
            "INSERT INTO public.videos (title, description, google_drive_url, thumbnail_url, duration, category, tags, is_active, created_by)
             VALUES (:title, :description, :google_drive_url, :thumbnail_url, :duration, :category, :tags, :is_active, :created_by)
             RETURNING id",
            [
                ':title'            => $data['title'],
                ':description'      => $data['description'] ?? null,
                ':google_drive_url' => $data['google_drive_url'],
                ':thumbnail_url'    => $data['thumbnail_url'] ?? null,
                ':duration'         => $data['duration'] ?? null,
                ':category'         => $data['category'] ?? null,
                ':tags'             => $tags,
                ':is_active'        => $data['is_active'] ?? true,
                ':created_by'       => $data['created_by'] ?? null,
            ]
        );
        
        return (string)$id;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] videos_create PDO error, falling back to REST API: ' . $e->getMessage());
        return videos_create_rest($data);
    }
}

/**
 * REST API fallback for videos_create
 */
function videos_create_rest(array $data): ?string {
    try {
        $response = Supabase::post('/rest/v1/videos', [
            'title'            => $data['title'],
            'description'      => $data['description'] ?? null,
            'google_drive_url' => $data['google_drive_url'],
            'thumbnail_url'    => $data['thumbnail_url'] ?? null,
            'duration'         => $data['duration'] ?? null,
            'category'         => $data['category'] ?? null,
            'tags'             => $data['tags'] ?? [],
            'is_active'        => $data['is_active'] ?? true,
            'created_by'       => $data['created_by'] ?? null,
        ], true);
        
        if (isset($response['error']) || empty($response)) {
            error_log('[TalebDZ REST] videos_create error: ' . ($response['message'] ?? 'Unknown error'));
            return null;
        }
        
        return is_array($response) && isset($response[0]['id']) ? $response[0]['id'] : null;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] videos_create error: ' . $e->getMessage());
        return null;
    }
}

/**
 * List all videos with optional filters.
 * Automatically falls back to REST API if database connection fails.
 */
function videos_list(bool $includeInactive = false, ?string $category = null): array {
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return videos_list_rest($includeInactive, $category);
    }
    
    try {
        DB::connection();
        
        $conditions = [];
        $params = [];
        
        if (!$includeInactive) {
            $conditions[] = 'is_active = TRUE';
        }
        
        if ($category) {
            $conditions[] = 'category = :category';
            $params[':category'] = $category;
        }
        
        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        return DB::fetchAll(
            "SELECT id, title, description, google_drive_url, thumbnail_url, duration, 
                    category, tags, is_active, views_count, created_at, updated_at
               FROM public.videos
               $where
              ORDER BY created_at DESC",
            $params
        );
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] videos_list PDO error, falling back to REST API: ' . $e->getMessage());
        return videos_list_rest($includeInactive, $category);
    }
}

/**
 * REST API fallback for videos_list
 */
function videos_list_rest(bool $includeInactive = false, ?string $category = null): array {
    try {
        $query = [
            'select' => 'id,title,description,google_drive_url,thumbnail_url,duration,category,tags,is_active,views_count,created_at,updated_at',
            'order' => 'created_at.desc'
        ];
        
        if (!$includeInactive) {
            $query['is_active'] = 'eq.true';
        }
        
        if ($category) {
            $query['category'] = "eq.{$category}";
        }
        
        $response = Supabase::get('/rest/v1/videos', $query, true);
        
        if (isset($response['error'])) {
            error_log('[TalebDZ REST] videos_list error: ' . ($response['message'] ?? 'Unknown error'));
            return [];
        }
        
        // Remove _http_status and re-index
        unset($response['_http_status']);
        return is_array($response) ? array_values($response) : [];
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] videos_list error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get a single video by ID.
 * Automatically falls back to REST API if database connection fails.
 */
function videos_get(string $id): ?array {
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return videos_get_rest($id);
    }
    
    try {
        DB::connection();
        
        $row = DB::fetchOne(
            "SELECT * FROM public.videos WHERE id = :id",
            [':id' => $id]
        );
        return $row ?: null;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] videos_get PDO error, falling back to REST API: ' . $e->getMessage());
        return videos_get_rest($id);
    }
}

/**
 * REST API fallback for videos_get
 */
function videos_get_rest(string $id): ?array {
    try {
        $response = Supabase::get('/rest/v1/videos', [
            'select' => '*',
            'id' => "eq.{$id}"
        ], true);
        
        if (isset($response['error'])) {
            return null;
        }
        
        // Remove _http_status
        unset($response['_http_status']);
        
        // Get first result
        return is_array($response) && isset($response[0]) ? $response[0] : null;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] videos_get error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Update a video.
 * Automatically falls back to REST API if database connection fails.
 */
function videos_update(string $id, array $data): bool {
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return videos_update_rest($id, $data);
    }
    
    try {
        DB::connection();
        
        $fields = [];
        $params = [':id' => $id];
        
        foreach (['title', 'description', 'google_drive_url', 'thumbnail_url', 'duration', 'category', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        // Handle tags array separately
        if (array_key_exists('tags', $data)) {
            $tags = $data['tags'];
            if (is_array($tags)) {
                $tags = '{' . implode(',', array_map(function($t) { return '"' . str_replace('"', '\\"', $t) . '"'; }, $tags)) . '}';
            }
            $fields[] = "tags = :tags";
            $params[":tags"] = $tags;
        }
        
        if (empty($fields)) return false;
        
        $fields[] = "updated_at = NOW()";
        $sql = "UPDATE public.videos SET " . implode(', ', $fields) . " WHERE id = :id";
        
        return DB::execute($sql, $params) > 0;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] videos_update PDO error, falling back to REST API: ' . $e->getMessage());
        return videos_update_rest($id, $data);
    }
}

/**
 * REST API fallback for videos_update
 */
function videos_update_rest(string $id, array $data): bool {
    try {
        $updateData = [];
        foreach (['title', 'description', 'google_drive_url', 'thumbnail_url', 'duration', 'category', 'tags', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }
        
        if (empty($updateData)) return false;
        
        $updateData['updated_at'] = date('c');
        
        $response = Supabase::patch('/rest/v1/videos', $updateData, true, ['id' => "eq.{$id}"]);
        
        return !isset($response['error']);
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] videos_update error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete a video.
 * Automatically falls back to REST API if database connection fails.
 */
function videos_delete(string $id): bool {
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return videos_delete_rest($id);
    }
    
    try {
        DB::connection();
        
        return DB::execute(
            "DELETE FROM public.videos WHERE id = :id",
            [':id' => $id]
        ) > 0;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] videos_delete PDO error, falling back to REST API: ' . $e->getMessage());
        return videos_delete_rest($id);
    }
}

/**
 * REST API fallback for videos_delete
 */
function videos_delete_rest(string $id): bool {
    try {
        $response = Supabase::delete('/rest/v1/videos', ['id' => "eq.{$id}"], true);
        return !isset($response['error']);
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] videos_delete error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get videos by category.
 * Automatically falls back to REST API if database connection fails.
 */
function videos_by_category(string $category): array {
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return videos_by_category_rest($category);
    }
    
    try {
        DB::connection();
        
        return DB::fetchAll(
            "SELECT id, title, description, google_drive_url, thumbnail_url, duration, 
                    tags, views_count, created_at
               FROM public.videos
              WHERE is_active = TRUE AND category = :category
              ORDER BY created_at DESC",
            [':category' => $category]
        );
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] videos_by_category PDO error, falling back to REST API: ' . $e->getMessage());
        return videos_by_category_rest($category);
    }
}

/**
 * REST API fallback for videos_by_category
 */
function videos_by_category_rest(string $category): array {
    try {
        $response = Supabase::get('/rest/v1/videos', [
            'select' => 'id,title,description,google_drive_url,thumbnail_url,duration,tags,views_count,created_at',
            'is_active' => 'eq.true',
            'category' => "eq.{$category}",
            'order' => 'created_at.desc'
        ], true);
        
        if (isset($response['error'])) {
            return [];
        }
        
        return is_array($response) ? $response : [];
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] videos_by_category error: ' . $e->getMessage());
        return [];
    }
}



// ╔══════════════════════════════════════════════════════════╗
// ║  SECTION 8 — SUBSCRIPTIONS & BILLING                    ║
// ╚══════════════════════════════════════════════════════════╝

/**
 * Get all subscription plans using Supabase REST API (more reliable than direct DB)
 * Automatically falls back to REST API if database connection fails.
 * @param bool $includeInactive - Whether to include inactive plans (default: false)
 */
function plans_list(bool $includeInactive = false): array {
    // Check if we should use REST API
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return plans_list_rest($includeInactive);
    }
    
    try {
        // Test database connection first
        DB::connection();
        
        $where = $includeInactive ? '' : 'WHERE is_active = TRUE';
        $plans = DB::fetchAll(
            "SELECT * FROM public.subscription_plans {$where} ORDER BY display_order ASC, created_at ASC"
        );
        
        // Convert PostgreSQL boolean values (t/f) to PHP booleans for each plan
        foreach ($plans as &$plan) {
            if (isset($plan['is_active'])) {
                $plan['is_active'] = ($plan['is_active'] === 't' || $plan['is_active'] === true || $plan['is_active'] === 1);
            }
            if (isset($plan['is_popular'])) {
                $plan['is_popular'] = ($plan['is_popular'] === 't' || $plan['is_popular'] === true || $plan['is_popular'] === 1);
            }
            // Parse JSON features if it's a string
            if (isset($plan['features']) && is_string($plan['features'])) {
                $decoded = json_decode($plan['features'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $plan['features'] = $decoded;
                }
            }
        }
        unset($plan); // Break reference
        
        return $plans;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] plans_list PDO error, falling back to REST API: ' . $e->getMessage());
        // Auto-fallback to REST API
        return plans_list_rest($includeInactive);
    }
}

/**
 * REST API fallback for plans_list
 */
function plans_list_rest(bool $includeInactive = false): array {
    try {
        $query = [
            'select' => '*',
            'order' => 'display_order.asc,created_at.asc'
        ];
        
        if (!$includeInactive) {
            $query['is_active'] = 'eq.true';
        }
        
        $response = Supabase::get('/rest/v1/subscription_plans', $query, true);
        
        if (isset($response['error']) || !is_array($response)) {
            error_log('[TalebDZ REST] plans_list error: ' . ($response['message'] ?? 'Invalid response'));
            return [];
        }
        
        return $response;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] plans_list error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get a single plan by ID
 */
function plans_getById(string $planId): ?array {
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return plans_getById_rest($planId);
    }
    
    try {
        DB::connection();
        $row = DB::fetchOne(
            'SELECT * FROM public.subscription_plans WHERE id = :id',
            [':id' => $planId]
        );
        
        if (!$row) {
            return null;
        }
        
        // Convert PostgreSQL boolean values (t/f) to PHP booleans
        if (isset($row['is_active'])) {
            $row['is_active'] = ($row['is_active'] === 't' || $row['is_active'] === true || $row['is_active'] === 1);
        }
        if (isset($row['is_popular'])) {
            $row['is_popular'] = ($row['is_popular'] === 't' || $row['is_popular'] === true || $row['is_popular'] === 1);
        }
        
        // Parse JSON features if it's a string
        if (isset($row['features']) && is_string($row['features'])) {
            $decoded = json_decode($row['features'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $row['features'] = $decoded;
            }
        }
        
        return $row;
    } catch (Throwable $e) {
        error_log('[TalebDZ] plans_getById PDO error: ' . $e->getMessage());
        return plans_getById_rest($planId);
    }
}

/**
 * REST API fallback for plans_getById
 */
function plans_getById_rest(string $planId): ?array {
    try {
        $response = Supabase::get(
            '/rest/v1/subscription_plans',
            [
                'select' => '*',
                'id' => "eq.{$planId}"
            ],
            true
        );
        
        if (isset($response['error']) || !is_array($response) || empty($response)) {
            return null;
        }
        
        return $response[0];
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] plans_getById error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Create a new subscription plan
 * @param array $data - Plan data (name, description, price, duration_months, etc.)
 * @return string|false - Plan ID on success, false on failure
 */
function plans_create(array $data) {
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return plans_create_rest($data);
    }
    
    try {
        DB::connection();
        
        // Generate plan_code from name if not provided
        if (empty($data['plan_code'])) {
            $data['plan_code'] = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $data['name'] ?? ''));
        }
        
        // Ensure features is JSON
        $features = $data['features'] ?? [];
        if (is_string($features)) {
            $features = json_decode($features, true) ?? [];
        }
        
        $planId = DB::insertReturning(
            "INSERT INTO public.subscription_plans
                (plan_code, name, description, price, currency, duration_months, features, is_active, is_popular, display_order)
             VALUES
                (:plan_code, :name, :description, :price, :currency, :duration_months, :features, :is_active, :is_popular, :display_order)
             RETURNING id",
            [
                ':plan_code'       => $data['plan_code'],
                ':name'            => $data['name'] ?? '',
                ':description'     => $data['description'] ?? null,
                ':price'           => (float)($data['price'] ?? 0),
                ':currency'        => $data['currency'] ?? 'DZD',
                ':duration_months' => (int)($data['duration_months'] ?? 1),
                ':features'        => json_encode($features),
                ':is_active'       => (bool)($data['is_active'] ?? true),
                ':is_popular'      => (bool)($data['is_popular'] ?? false),
                ':display_order'   => (int)($data['display_order'] ?? 0),
            ]
        );
        
        return $planId ?: false;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] plans_create PDO error: ' . $e->getMessage());
        return plans_create_rest($data);
    }
}

/**
 * REST API fallback for plans_create
 */
function plans_create_rest(array $data) {
    try {
        // Generate plan_code from name if not provided
        if (empty($data['plan_code'])) {
            $data['plan_code'] = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $data['name'] ?? ''));
        }
        
        // Ensure features is array
        $features = $data['features'] ?? [];
        if (is_string($features)) {
            $features = json_decode($features, true) ?? [];
        }
        
        $response = Supabase::post(
            '/rest/v1/subscription_plans',
            [
                'plan_code'       => $data['plan_code'],
                'name'            => $data['name'] ?? '',
                'description'     => $data['description'] ?? null,
                'price'           => (float)($data['price'] ?? 0),
                'currency'        => $data['currency'] ?? 'DZD',
                'duration_months' => (int)($data['duration_months'] ?? 1),
                'features'        => $features,
                'is_active'       => (bool)($data['is_active'] ?? true),
                'is_popular'      => (bool)($data['is_popular'] ?? false),
                'display_order'   => (int)($data['display_order'] ?? 0),
            ],
            true
        );
        
        if (isset($response['error'])) {
            error_log('[TalebDZ REST] plans_create error: ' . ($response['message'] ?? 'Unknown error'));
            return false;
        }
        
        // Supabase returns the created row
        return $response[0]['id'] ?? false;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] plans_create error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update an existing subscription plan
 * @param string $planId - Plan UUID
 * @param array $data - Updated plan data
 * @return bool - Success status
 */
function plans_update(string $planId, array $data): bool {
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return plans_update_rest($planId, $data);
    }
    
    try {
        DB::connection();
        
        // Build dynamic update query
        $fields = [];
        $params = [':id' => $planId];
        
        if (isset($data['name'])) {
            $fields[] = 'name = :name';
            $params[':name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $fields[] = 'description = :description';
            $params[':description'] = $data['description'];
        }
        if (isset($data['price'])) {
            $fields[] = 'price = :price';
            $params[':price'] = (float)$data['price'];
        }
        if (isset($data['duration_months'])) {
            $fields[] = 'duration_months = :duration_months';
            $params[':duration_months'] = (int)$data['duration_months'];
        }
        if (isset($data['features'])) {
            $features = is_string($data['features']) ? $data['features'] : json_encode($data['features']);
            $fields[] = 'features = :features';
            $params[':features'] = $features;
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = :is_active';
            $params[':is_active'] = (bool)$data['is_active'];
        }
        if (isset($data['is_popular'])) {
            $fields[] = 'is_popular = :is_popular';
            $params[':is_popular'] = (bool)$data['is_popular'];
        }
        if (isset($data['display_order'])) {
            $fields[] = 'display_order = :display_order';
            $params[':display_order'] = (int)$data['display_order'];
        }
        
        if (empty($fields)) {
            return false; // Nothing to update
        }
        
        $fields[] = 'updated_at = NOW()';
        $sql = 'UPDATE public.subscription_plans SET ' . implode(', ', $fields) . ' WHERE id = :id';
        
        return DB::execute($sql, $params) > 0;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] plans_update PDO error: ' . $e->getMessage());
        return plans_update_rest($planId, $data);
    }
}

/**
 * REST API fallback for plans_update
 */
function plans_update_rest(string $planId, array $data): bool {
    try {
        $updateData = ['updated_at' => date('c')];
        
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['price'])) $updateData['price'] = (float)$data['price'];
        if (isset($data['duration_months'])) $updateData['duration_months'] = (int)$data['duration_months'];
        if (isset($data['features'])) {
            $updateData['features'] = is_array($data['features']) ? $data['features'] : json_decode($data['features'], true);
        }
        if (isset($data['is_active'])) $updateData['is_active'] = (bool)$data['is_active'];
        if (isset($data['is_popular'])) $updateData['is_popular'] = (bool)$data['is_popular'];
        if (isset($data['display_order'])) $updateData['display_order'] = (int)$data['display_order'];
        
        if (count($updateData) === 1) {
            return false; // Only updated_at, nothing to update
        }
        
        $response = Supabase::patch(
            '/rest/v1/subscription_plans',
            $updateData,
            true,
            ['id' => "eq.{$planId}"]
        );
        
        return !isset($response['error']);
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] plans_update error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete a subscription plan (soft delete by setting is_active = false)
 * @param string $planId - Plan UUID
 * @return bool - Success status
 */
function plans_delete(string $planId): bool {
    // Soft delete: just set is_active = false
    return plans_update($planId, ['is_active' => false]);
}

/**
 * Permanently delete a subscription plan (only if no user subscriptions exist)
 * @param string $planId - Plan UUID
 * @return bool - Success status
 */
function plans_deletePermanent(string $planId): bool {
    $useRestApi = (getenv('USE_REST_API') === 'true');
    
    if ($useRestApi) {
        return plans_deletePermanent_rest($planId);
    }
    
    try {
        DB::connection();
        
        // Check if any user subscriptions exist for this plan
        $count = (int) DB::fetchOne(
            'SELECT COUNT(*) AS n FROM public.user_subscriptions WHERE plan_id = :id',
            [':id' => $planId]
        )['n'];
        
        if ($count > 0) {
            error_log("[TalebDZ] Cannot delete plan {$planId}: {$count} subscriptions exist");
            return false; // Cannot delete plan with existing subscriptions
        }
        
        return DB::execute(
            'DELETE FROM public.subscription_plans WHERE id = :id',
            [':id' => $planId]
        ) > 0;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ] plans_deletePermanent PDO error: ' . $e->getMessage());
        return plans_deletePermanent_rest($planId);
    }
}

/**
 * REST API fallback for plans_deletePermanent
 */
function plans_deletePermanent_rest(string $planId): bool {
    try {
        // Check if any user subscriptions exist for this plan
        $subs = Supabase::get(
            '/rest/v1/user_subscriptions',
            [
                'select' => 'id',
                'plan_id' => "eq.{$planId}"
            ],
            true
        );
        
        if (is_array($subs) && count($subs) > 0) {
            error_log("[TalebDZ REST] Cannot delete plan {$planId}: " . count($subs) . " subscriptions exist");
            return false;
        }
        
        $response = Supabase::delete(
            '/rest/v1/subscription_plans',
            ['id' => "eq.{$planId}"],
            true
        );
        
        return !isset($response['error']);
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] plans_deletePermanent error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update pricing for a plan.
 */
function plans_updatePrice(string $planCode, float $price): bool {
    return DB::execute(
        'UPDATE public.subscription_plans SET price = :price, updated_at = NOW() WHERE plan_code = :code',
        [':price' => $price, ':code' => $planCode]
    ) > 0;
}

/**
 * Summary stats per plan: count and total revenue.
 */
function billing_planSummary(): array {
    return DB::fetchAll(
        "SELECT
            sp.plan_code, sp.name, sp.price, sp.duration_months,
            COUNT(us.id) FILTER (WHERE us.status = 'active') AS active_count,
            COALESCE(SUM(pt.amount) FILTER (WHERE pt.status = 'paid'), 0) AS total_revenue
         FROM public.subscription_plans sp
         LEFT JOIN public.user_subscriptions us ON us.plan_id = sp.id
         LEFT JOIN public.payment_transactions pt ON pt.subscription_id = us.id
         WHERE sp.is_active = TRUE
         GROUP BY sp.id, sp.plan_code, sp.name, sp.price, sp.duration_months
         ORDER BY sp.display_order ASC"
    );
}

/**
 * Total Monthly Recurring Revenue (active monthly subs).
 */
function billing_mrr(): float {
    $row = DB::fetchOne(
        "SELECT COALESCE(SUM(sp.price), 0) AS mrr
           FROM public.user_subscriptions us
           JOIN public.subscription_plans sp ON sp.id = us.plan_id
          WHERE us.status = 'active'
            AND us.expires_at > NOW()
            AND sp.duration_months = 1"
    );
    return (float)($row['mrr'] ?? 0);
}

/**
 * Recent payment transactions with user details.
 */
function billing_recentTransactions(int $limit = 30): array {
    return DB::fetchAll(
        "SELECT
            pt.id, pt.payment_reference, pt.amount, pt.currency,
            pt.status, pt.payment_method, pt.created_at, pt.completed_at,
            u.email, u.username,
            sp.name AS plan_name
         FROM public.payment_transactions pt
         JOIN public.users u  ON u.id  = pt.user_id
         LEFT JOIN public.user_subscriptions us ON us.id = pt.subscription_id
         LEFT JOIN public.subscription_plans  sp ON sp.id = us.plan_id
         ORDER BY pt.created_at DESC
         LIMIT :limit",
        [':limit' => $limit]
    );
}

/**
 * Count active Pro subscribers (any paid plan).
 */
function billing_activeProCount(): int {
    return (int) DB::fetchOne(
        "SELECT COUNT(*) AS n FROM public.user_subscriptions
          WHERE status = 'active' AND expires_at > NOW()"
    )['n'];
}

/**
 * Count churned (cancelled or expired) subscribers this month.
 */
function billing_churnedThisMonth(): int {
    return (int) DB::fetchOne(
        "SELECT COUNT(*) AS n FROM public.user_subscriptions
          WHERE status IN ('expired','cancelled')
            AND updated_at > DATE_TRUNC('month', NOW())"
    )['n'];
}


// ╔══════════════════════════════════════════════════════════╗
// ║  SECTION 9 — DASHBOARD OVERVIEW STATS                   ║
// ╚══════════════════════════════════════════════════════════╝

/**
 * Collect all metrics needed for the admin overview panel in one call.
 * Returns an associative array consumed directly by the frontend.
 * Gracefully handles errors and returns default values on failure.
 */
function dashboard_stats(): array {
    $stats = [
        'total_users'      => 0,
        'active_today'     => 0,
        'questions_today'  => 0,
        'flagged_posts'    => 0,
        'pending_reports'  => 0,
        'active_pro'       => 0,
        'mrr'              => 0,
        'churned_month'    => 0,
        'avg_session_min'  => 0,
        'top_topics'       => [],
        'daily_questions'  => [],
        'weekly_signups'   => 0,
        'monthly_signups'  => 0,
        'total_messages'   => 0,
        'avg_messages_per_user' => 0,
    ];
    
    try { $stats['total_users'] = users_count(); } catch (Throwable $e) { error_log('[Stats] users_count: ' . $e->getMessage()); }
    try { $stats['active_today'] = users_activeToday(); } catch (Throwable $e) { error_log('[Stats] users_activeToday: ' . $e->getMessage()); }
    try { $stats['questions_today'] = chat_questionsToday(); } catch (Throwable $e) { error_log('[Stats] chat_questionsToday: ' . $e->getMessage()); }
    try { $stats['flagged_posts'] = community_flaggedCount(); } catch (Throwable $e) { error_log('[Stats] community_flaggedCount: ' . $e->getMessage()); }
    try { $stats['pending_reports'] = reports_pendingCount(); } catch (Throwable $e) { error_log('[Stats] reports_pendingCount: ' . $e->getMessage()); }
    try { $stats['active_pro'] = billing_activeProCount(); } catch (Throwable $e) { error_log('[Stats] billing_activeProCount: ' . $e->getMessage()); }
    try { $stats['mrr'] = billing_mrr(); } catch (Throwable $e) { error_log('[Stats] billing_mrr: ' . $e->getMessage()); }
    try { $stats['churned_month'] = billing_churnedThisMonth(); } catch (Throwable $e) { error_log('[Stats] billing_churnedThisMonth: ' . $e->getMessage()); }
    try { $stats['avg_session_min'] = chat_avgSessionMinutes(); } catch (Throwable $e) { error_log('[Stats] chat_avgSessionMinutes: ' . $e->getMessage()); }
    try { $stats['top_topics'] = chat_topTopics(5); } catch (Throwable $e) { error_log('[Stats] chat_topTopics: ' . $e->getMessage()); }
    try { $stats['daily_questions'] = chat_questionsPerDay(7); } catch (Throwable $e) { error_log('[Stats] chat_questionsPerDay: ' . $e->getMessage()); }
    try { $stats['weekly_signups'] = users_signupsInDays(7); } catch (Throwable $e) { error_log('[Stats] weekly_signups: ' . $e->getMessage()); }
    try { $stats['monthly_signups'] = users_signupsInDays(30); } catch (Throwable $e) { error_log('[Stats] monthly_signups: ' . $e->getMessage()); }
    try { $stats['total_messages'] = chat_totalMessages(); } catch (Throwable $e) { error_log('[Stats] total_messages: ' . $e->getMessage()); }
    try { 
        if ($stats['total_users'] > 0) {
            $stats['avg_messages_per_user'] = round($stats['total_messages'] / $stats['total_users'], 1);
        }
    } catch (Throwable $e) { error_log('[Stats] avg_messages_per_user: ' . $e->getMessage()); }
    
    return $stats;
}

/**
 * Get user signups in the last N days
 */
function users_signupsInDays(int $days = 7): int {
    try {
        $result = DB::fetchOne(
            "SELECT COUNT(*) AS n FROM public.users 
             WHERE created_at > NOW() - INTERVAL '1 day' * :days",
            [':days' => $days]
        );
        return (int)($result['n'] ?? 0);
    } catch (Throwable $e) {
        error_log('[TalebDZ] users_signupsInDays error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Get total chat messages count
 */
function chat_totalMessages(): int {
    try {
        $result = DB::fetchOne(
            "SELECT COUNT(*) AS n FROM public.chat_messages WHERE role = 'user'"
        );
        return (int)($result['n'] ?? 0);
    } catch (Throwable $e) {
        error_log('[TalebDZ] chat_totalMessages error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Recent activity feed for the overview panel.
 * Merges new users, new reports, and new subscriptions.
 */
function dashboard_recentActivity(int $limit = 10): array {
    // Recent registrations
    $users = DB::fetchAll(
        "SELECT 'new_user' AS type, u.email AS subject, 'registered as a new student' AS action, u.created_at
           FROM public.users u
          ORDER BY u.created_at DESC LIMIT 5"
    );

    // Recent reports
    $reports = DB::fetchAll(
        "SELECT 'report' AS type, u.email AS subject, 'reported a post: ' || pr.reason AS action, pr.created_at
           FROM public.post_reports pr
           JOIN public.users u ON u.id = pr.reporter_id
          ORDER BY pr.created_at DESC LIMIT 5"
    );

    // Recent subscriptions
    $subs = DB::fetchAll(
        "SELECT 'subscription' AS type, u.email AS subject,
                'subscribed to ' || sp.name AS action, us.created_at
           FROM public.user_subscriptions us
           JOIN public.users u ON u.id = us.user_id
           JOIN public.subscription_plans sp ON sp.id = us.plan_id
          ORDER BY us.created_at DESC LIMIT 5"
    );

    $all = array_merge($users, $reports, $subs);
    usort($all, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
    return array_slice($all, 0, $limit);
}


// ╔══════════════════════════════════════════════════════════╗
// ║  SECTION 10 — SETTINGS                                  ║
// ╚══════════════════════════════════════════════════════════╝

/**
 * Update the free tier question limit.
 * Stored as a custom metadata row in subscription_plans (plan_code='free').
 * If you store it elsewhere, adjust accordingly.
 */
function settings_setFreeLimit(int $questionsPerMonth): bool {
    // We store free quota in the features JSON of the 'free' plan (or a settings table).
    // Here we just update the plan description for demo; replace with a proper settings table.
    return DB::execute(
        "UPDATE public.subscription_plans
            SET features = jsonb_set(features, '{0}', to_jsonb(:quota || ' AI questions / month'))
          WHERE plan_code = 'free'",
        [':quota' => $questionsPerMonth]
    ) >= 0; // 0 rows updated is acceptable if plan doesn't exist yet
}

/**
 * Get the current admin account record by id.
 */
function settings_getAdmin(string $adminId): ?array {
    $row = DB::fetchOne(
        'SELECT id, email, full_name, role, last_login_at FROM public.admin_accounts WHERE id = :id',
        [':id' => $adminId]
    );
    return $row ?: null;
}

/**
 * Update admin email.
 */
function settings_updateAdminEmail(string $adminId, string $newEmail): bool {
    return DB::execute(
        'UPDATE public.admin_accounts SET email = :email, updated_at = NOW() WHERE id = :id',
        [':email' => strtolower(trim($newEmail)), ':id' => $adminId]
    ) > 0;
}
