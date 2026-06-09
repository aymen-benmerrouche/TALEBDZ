<?php
/**
 * REST API Fallback Functions
 * Use these if direct PostgreSQL connection fails
 * These use Supabase REST API instead of PDO
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Get paginated user list via REST API
 */
function users_list_rest(int $page = 1, int $limit = 25, string $search = ''): array {
    try {
        $offset = ($page - 1) * $limit;
        
        $query = [
            'select' => 'id,email,username,full_name,student_id,department,is_active,created_at',
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
        
        // Get total count
        $countQuery = ['select' => 'count', 'head' => 'true'];
        if ($search !== '') {
            $countQuery['or'] = $query['or'];
        }
        $countResponse = Supabase::get('/rest/v1/users', $countQuery, true);
        $total = isset($countResponse['count']) ? (int)$countResponse['count'] : count($response);
        
        return [
            'data' => $response,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ];
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] users_list error: ' . $e->getMessage());
        return ['data' => [], 'total' => 0, 'page' => $page, 'limit' => $limit, 'error' => $e->getMessage()];
    }
}

/**
 * Set user active status via REST API
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
 * Count total users via REST API
 */
function users_count_rest(): int {
    try {
        $response = Supabase::get(
            '/rest/v1/users',
            ['select' => 'count'],
            true
        );
        
        if (is_array($response) && isset($response[0]['count'])) {
            return (int)$response[0]['count'];
        }
        
        return 0;
        
    } catch (Throwable $e) {
        error_log('[TalebDZ REST] users_count error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Auto-detect and use REST API if PDO fails
 */
function users_list_auto(int $page = 1, int $limit = 25, string $search = ''): array {
    // Try PDO first
    try {
        DB::connection(); // Test connection
        return users_list($page, $limit, $search);
    } catch (Throwable $e) {
        error_log('[TalebDZ] PDO failed, falling back to REST API: ' . $e->getMessage());
        return users_list_rest($page, $limit, $search);
    }
}

function users_setActive_auto(string $userId, bool $active): bool {
    try {
        DB::connection();
        return users_setActive($userId, $active);
    } catch (Throwable $e) {
        error_log('[TalebDZ] PDO failed, falling back to REST API: ' . $e->getMessage());
        return users_setActive_rest($userId, $active);
    }
}

function users_count_auto(): int {
    try {
        DB::connection();
        return users_count();
    } catch (Throwable $e) {
        error_log('[TalebDZ] PDO failed, falling back to REST API: ' . $e->getMessage());
        return users_count_rest();
    }
}
