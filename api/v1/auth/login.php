<?php

require_once __DIR__ . '/../../../includes/api_auth.php';

apiRequireMethod('POST');

$username = trim((string) apiInput('username', ''));
$password = (string) apiInput('password', '');
$deviceName = trim((string) apiInput('device_name', 'android'));

if ($username === '' || $password === '') {
    apiError('请输入用户名和密码', 422);
}

$user = db()->fetch(
    "SELECT id, username, password, real_name, role, status
     FROM users
     WHERE username = ?
     LIMIT 1",
    [$username]
);

if (!$user || intval($user['status']) !== 1 || !verifyPassword($password, $user['password'])) {
    Logger::security("APP 登录失败: {$username}");
    apiError('用户名或密码错误', 401);
}

if ($user['role'] !== 'inspector') {
    apiError('当前账号不支持 APP 端登录', 403);
}

apiEnsureTokenTable();
db()->query(
    "DELETE FROM api_tokens
     WHERE user_id = ?
       AND (revoked_at IS NOT NULL OR expires_at < NOW())",
    [intval($user['id'])]
);

$tokenData = apiIssueToken($user['id'], $deviceName);

Logger::info("APP 登录成功: {$username}", ['device' => $deviceName]);

apiSuccess('登录成功', [
    'token' => $tokenData['token'],
    'token_type' => 'Bearer',
    'expires_at' => $tokenData['expires_at'],
    'user' => [
        'id' => intval($user['id']),
        'username' => (string) $user['username'],
        'real_name' => (string) $user['real_name'],
        'role' => (string) $user['role'],
    ],
]);

