<?php

require_once __DIR__ . '/../../../includes/api_auth.php';

apiRequireMethod('POST');
$auth = apiRequireAuth('inspector');

apiRevokeToken($auth['token_id']);

Logger::info('APP 退出登录', ['user_id' => $auth['user']['id']]);

apiSuccess('已退出登录');

