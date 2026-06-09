<?php
// ============================================================
// db/config.php — TalebDZ Database Configuration
// Handles: PDO PostgreSQL connection + Supabase REST helpers
// ============================================================

declare(strict_types=1);

// ── Load .env file ──────────────────────────────────────────
// Walks up from /db/ to find .env in the project root
function loadEnv(string $path): void {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// Resolve .env file location
$_envPath = __DIR__ . '/.env';
loadEnv($_envPath);

// ── Config constants ─────────────────────────────────────────
define('SUPABASE_URL',              getenv('SUPABASE_URL')              ?: '');
define('SUPABASE_ANON_KEY',         getenv('SUPABASE_ANON_KEY')         ?: '');
define('SUPABASE_SERVICE_ROLE_KEY', getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '');
define('DATABASE_URL',              getenv('DATABASE_URL')              ?: '');
define('JWT_SECRET',                getenv('SECRET_KEY')                ?: 'change-me-in-production');
define('JWT_ALGORITHM',             getenv('ALGORITHM')                 ?: 'HS256');
define('JWT_EXPIRE_MINUTES',   (int)(getenv('ACCESS_TOKEN_EXPIRE_MINUTES') ?: 30));

// ── Session configuration ────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,           // browser session
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_name('talebdz_admin');
    session_start();
}

// ── PDO connection (singleton) ───────────────────────────────
class DB {
    private static ?PDO $instance = null;

    public static function connection(): PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $dsn = DATABASE_URL;

        // Check if DATABASE_URL is empty or invalid
        if (empty($dsn)) {
            error_log('[TalebDZ DB] DATABASE_URL is empty or not set');
            throw new RuntimeException('Database connection failed. Check DATABASE_URL in .env');
        }

        // Convert postgres:// URL → PDO DSN if needed
        // Expected format: postgresql://user:password@host:port/dbname
        if (str_starts_with($dsn, 'postgresql://') || str_starts_with($dsn, 'postgres://')) {
            $parsed = parse_url($dsn);
            
            if ($parsed === false) {
                error_log('[TalebDZ DB] Failed to parse DATABASE_URL: ' . $dsn);
                throw new RuntimeException('Database connection failed. Invalid DATABASE_URL format');
            }
            
            $host   = $parsed['host']   ?? 'localhost';
            $port   = $parsed['port']   ?? 5432;
            $dbname = ltrim($parsed['path'] ?? '/postgres', '/');
            $user   = $parsed['user']   ?? 'postgres';
            $pass   = $parsed['pass']   ?? '';

            // Use sslmode=prefer for better compatibility (falls back if SSL fails)
            $pdoDsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=prefer";

            try {
                self::$instance = new PDO($pdoDsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_PERSISTENT         => false,
                    PDO::ATTR_TIMEOUT            => 30,
                ]);
                // Set timezone to UTC for consistency with Supabase
                self::$instance->exec("SET TIME ZONE 'UTC'");
            } catch (PDOException $e) {
                error_log('[TalebDZ DB] Connection failed: ' . $e->getMessage());
                error_log('[TalebDZ DB] DSN used: ' . $pdoDsn);
                throw new RuntimeException('Database connection failed. Check DATABASE_URL in .env');
            }
        } else {
            error_log('[TalebDZ DB] DATABASE_URL does not start with postgresql:// or postgres://');
            throw new RuntimeException('DATABASE_URL must start with postgresql:// or postgres://');
        }

        return self::$instance;
    }

    // Convenience: run a prepared query and return the PDOStatement
    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    // Convenience: fetch a single row
    public static function fetchOne(string $sql, array $params = []): array|false {
        return self::query($sql, $params)->fetch();
    }

    // Convenience: fetch all rows
    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    // Convenience: execute INSERT/UPDATE/DELETE and return affected rows
    public static function execute(string $sql, array $params = []): int {
        return self::query($sql, $params)->rowCount();
    }

    // Convenience: INSERT and return the last inserted id (for SERIAL PKs)
    public static function insertReturning(string $sql, array $params = []): mixed {
        $stmt = self::query($sql, $params);
        return $stmt->fetchColumn();
    }
}

// ── Supabase REST helper ──────────────────────────────────────
// Use this when you want Supabase's REST API instead of direct DB access
// (e.g., for operations that must respect Row Level Security from the client side).
class Supabase {

    /**
     * Make an authenticated request to the Supabase REST API.
     *
     * @param string $method   GET | POST | PATCH | DELETE
     * @param string $endpoint e.g. '/rest/v1/users'
     * @param array  $body     Request body for POST/PATCH
     * @param bool   $asAdmin  true = use service_role key (bypasses RLS)
     * @param array  $query    URL query params (e.g. ['select' => '*', 'limit' => '10'])
     */
    public static function request(
        string $method,
        string $endpoint,
        array  $body    = [],
        bool   $asAdmin = false,
        array  $query   = []
    ): array {
        $apiKey = $asAdmin ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY;
        $url    = rtrim(SUPABASE_URL, '/') . $endpoint;

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $headers = [
            'apikey: '        . $apiKey,
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
            'Prefer: return=representation',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if (in_array(strtoupper($method), ['POST', 'PATCH', 'PUT'], true) && !empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response   = curl_exec($ch);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('[TalebDZ Supabase] cURL error: ' . $curlError);
            return ['error' => 'Network error: ' . $curlError, 'status' => 0];
        }

        $decoded = json_decode($response ?: '{}', true) ?? [];
        $decoded['_http_status'] = $httpCode;

        if ($httpCode >= 400) {
            error_log("[TalebDZ Supabase] HTTP {$httpCode} on {$method} {$endpoint}: {$response}");
        }

        return $decoded;
    }

    // Shorthand methods
    public static function get(string $endpoint, array $query = [], bool $asAdmin = false): array {
        return self::request('GET', $endpoint, [], $asAdmin, $query);
    }

    public static function post(string $endpoint, array $body, bool $asAdmin = false): array {
        return self::request('POST', $endpoint, $body, $asAdmin);
    }

    public static function patch(string $endpoint, array $body, bool $asAdmin = false, array $query = []): array {
        return self::request('PATCH', $endpoint, $body, $asAdmin, $query);
    }

    public static function delete(string $endpoint, array $query = [], bool $asAdmin = false): array {
        return self::request('DELETE', $endpoint, [], $asAdmin, $query);
    }
}

// ── JSON response helper (used by API endpoints) ─────────────
function jsonResponse(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── CORS headers (for any fetch() calls from admin HTML) ─────
function setCorsHeaders(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
