<?php

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'kunlun');
define('DB_USER', 'kunlun');
define('DB_PASS', 'please_change_me');
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME', '学生管理系统');
define('SITE_URL', 'https://example.com');

define('SESSION_NAME', 'dormitory_score_session');
define('SESSION_LIFETIME', 7200);

define('TIMEZONE', 'Asia/Shanghai');
date_default_timezone_set(TIMEZONE);

define('ENVIRONMENT', 'development'); // development or production

if (ENVIRONMENT === 'production') {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

define('DEFAULT_SCORE', 100.00);
define('ITEMS_PER_PAGE', 20);
define('ENABLE_LOGGING', true);
define('LOG_RETENTION_DAYS', 30);

