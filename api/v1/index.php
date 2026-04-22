<?php

require_once __DIR__ . '/../../includes/api_auth.php';

apiRequireMethod('GET');

apiSuccess('API 服务正常', [
    'version' => 'v1',
    'endpoints' => [
        'POST /api/v1/auth/login.php',
        'POST /api/v1/auth/logout.php',
        'GET /api/v1/me.php',
        'GET /api/v1/dormitories.php',
        'GET /api/v1/score/options.php',
        'POST /api/v1/score/submit.php',
        'GET /api/v1/score/records.php',
    ],
]);

