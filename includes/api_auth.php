<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/logger.php';

if (!defined('API_TOKEN_TTL_DAYS')) {
    define('API_TOKEN_TTL_DAYS', 30);
}

function apiJsonResponse($success, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode([
        'success' => (bool) $success,
        'message' => (string) $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function apiSuccess($message = 'OK', $data = []) {
    apiJsonResponse(true, $message, $data, 200);
}

function apiError($message = '请求失败', $statusCode = 400, $data = []) {
    apiJsonResponse(false, $message, $data, $statusCode);
}

function apiRequireMethod($method) {
    $expected = strtoupper($method);
    $actual = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($expected !== $actual) {
        apiError('请求方法不允许', 405);
    }
}

function apiIsJsonRequest() {
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    return strpos($contentType, 'application/json') !== false;
}

function apiGetJsonBody() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    if (!apiIsJsonRequest()) {
        $cached = [];
        return $cached;
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        $cached = [];
        return $cached;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        apiError('请求体必须是合法 JSON', 400);
    }

    $cached = $decoded;
    return $cached;
}

function apiInput($key, $default = '') {
    if (array_key_exists($key, $_POST)) {
        return $_POST[$key];
    }

    $body = apiGetJsonBody();
    if (is_array($body) && array_key_exists($key, $body)) {
        return $body[$key];
    }

    return $default;
}

function apiGetAuthorizationHeader() {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return trim($_SERVER['HTTP_AUTHORIZATION']);
    }

    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                return trim($value);
            }
        }
    }

    return '';
}

function apiGetBearerToken() {
    $header = apiGetAuthorizationHeader();
    if ($header === '') {
        return '';
    }

    if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
        return trim($matches[1]);
    }

    return '';
}

function apiTokenHash($plainToken) {
    return hash('sha256', $plainToken);
}

function apiEnsureTokenTable() {
    static $ready = false;
    if ($ready) {
        return;
    }

    db()->query("
        CREATE TABLE IF NOT EXISTS `api_tokens` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `token_hash` CHAR(64) NOT NULL,
            `device_name` VARCHAR(100) NOT NULL DEFAULT '',
            `last_used_at` DATETIME NULL,
            `expires_at` DATETIME NOT NULL,
            `revoked_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_token_hash` (`token_hash`),
            KEY `idx_user_id` (`user_id`),
            KEY `idx_expires_at` (`expires_at`),
            KEY `idx_revoked_at` (`revoked_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='APP API Token';
    ");

    $ready = true;
}

function apiIssueToken($userId, $deviceName = '') {
    apiEnsureTokenTable();

    $plainToken = bin2hex(random_bytes(32));
    $tokenHash = apiTokenHash($plainToken);
    $expiresAt = date('Y-m-d H:i:s', time() + API_TOKEN_TTL_DAYS * 86400);
    $deviceName = trim((string) $deviceName);
    if ($deviceName === '') {
        $deviceName = 'android';
    }

    db()->insert('api_tokens', [
        'user_id' => intval($userId),
        'token_hash' => $tokenHash,
        'device_name' => mb_substr($deviceName, 0, 100),
        'last_used_at' => date('Y-m-d H:i:s'),
        'expires_at' => $expiresAt,
    ]);

    return [
        'token' => $plainToken,
        'expires_at' => $expiresAt,
    ];
}

function apiGetTokenPayload($plainToken) {
    apiEnsureTokenTable();
    $tokenHash = apiTokenHash($plainToken);

    return db()->fetch(
        "SELECT 
            t.id AS token_id,
            t.user_id AS token_user_id,
            t.expires_at,
            t.device_name,
            t.last_used_at,
            u.id,
            u.username,
            u.real_name,
            u.role,
            u.status
        FROM api_tokens t
        INNER JOIN users u ON u.id = t.user_id
        WHERE t.token_hash = ?
          AND t.revoked_at IS NULL
          AND t.expires_at > NOW()
        LIMIT 1",
        [$tokenHash]
    );
}

function apiTouchToken($tokenId, $lastUsedAt = null) {
    if (!$tokenId) {
        return;
    }

    $time = $lastUsedAt ? strtotime((string) $lastUsedAt) : 0;
    if ($time > 0 && (time() - $time) < 300) {
        return;
    }

    db()->update(
        'api_tokens',
        ['last_used_at' => date('Y-m-d H:i:s')],
        'id = ?',
        [intval($tokenId)]
    );
}

function apiRequireAuth($requiredRole = null) {
    $token = apiGetBearerToken();
    if ($token === '') {
        apiError('未登录或登录已过期', 401);
    }

    $payload = apiGetTokenPayload($token);
    if (!$payload || intval($payload['status']) !== 1) {
        apiError('登录状态失效，请重新登录', 401);
    }

    if ($requiredRole !== null && $payload['role'] !== $requiredRole) {
        apiError('权限不足', 403);
    }

    apiTouchToken($payload['token_id'] ?? 0, $payload['last_used_at'] ?? null);

    return [
        'token' => $token,
        'token_id' => intval($payload['token_id']),
        'user' => [
            'id' => intval($payload['id']),
            'username' => (string) $payload['username'],
            'real_name' => (string) $payload['real_name'],
            'role' => (string) $payload['role'],
        ],
    ];
}

function apiRevokeToken($tokenId) {
    apiEnsureTokenTable();
    if (!$tokenId) {
        return;
    }

    db()->update(
        'api_tokens',
        ['revoked_at' => date('Y-m-d H:i:s')],
        'id = ?',
        [intval($tokenId)]
    );
}

function apiBuildRootUrl() {
    $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        $site = parse_url((string) SITE_URL);
        $scheme = $site['scheme'] ?? $scheme;
        $host = $site['host'] ?? 'localhost';
        if (!empty($site['port'])) {
            $host .= ':' . $site['port'];
        }
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $rootPath = preg_replace('#/api/v1/.*$#', '', $scriptName);
    if (!is_string($rootPath)) {
        $rootPath = '';
    }
    $rootPath = rtrim($rootPath, '/');

    return $scheme . '://' . $host . $rootPath;
}

function apiBuildAssetUrl($relativePath) {
    $path = ltrim((string) $relativePath, '/');
    return apiBuildRootUrl() . '/' . $path;
}

function apiPagination($defaultPageSize = 20, $maxPageSize = 100) {
    $page = intval($_GET['page'] ?? 1);
    $pageSize = intval($_GET['page_size'] ?? $defaultPageSize);

    if ($page < 1) {
        $page = 1;
    }
    if ($pageSize < 1) {
        $pageSize = $defaultPageSize;
    }
    if ($pageSize > $maxPageSize) {
        $pageSize = $maxPageSize;
    }

    $offset = ($page - 1) * $pageSize;
    return [
        'page' => $page,
        'page_size' => $pageSize,
        'offset' => $offset,
    ];
}

function apiCollectUploadFiles($fieldName = 'images') {
    if (!isset($_FILES[$fieldName])) {
        return [];
    }

    $fileInfo = $_FILES[$fieldName];
    $files = [];

    if (is_array($fileInfo['name'])) {
        $count = count($fileInfo['name']);
        for ($i = 0; $i < $count; $i++) {
            $error = $fileInfo['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $files[] = [
                'name' => $fileInfo['name'][$i] ?? '',
                'type' => $fileInfo['type'][$i] ?? '',
                'tmp_name' => $fileInfo['tmp_name'][$i] ?? '',
                'error' => $error,
                'size' => $fileInfo['size'][$i] ?? 0,
            ];
        }
        return $files;
    }

    $error = $fileInfo['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) {
        return [];
    }

    $files[] = [
        'name' => $fileInfo['name'] ?? '',
        'type' => $fileInfo['type'] ?? '',
        'tmp_name' => $fileInfo['tmp_name'] ?? '',
        'error' => $error,
        'size' => $fileInfo['size'] ?? 0,
    ];

    return $files;
}

