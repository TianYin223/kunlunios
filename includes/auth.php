<?php

require_once __DIR__ . '/../config/config.php';

// 安全的 Session 配置
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

session_name(SESSION_NAME);
session_start();

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/functions.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['user_role'] === 'admin';
}

function isInspector() {
    return isLoggedIn() && $_SESSION['user_role'] === 'inspector';
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'real_name' => $_SESSION['real_name'],
        'role' => $_SESSION['user_role']
    ];
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        die('权限不足');
    }
}

function login($username, $password) {
    // 检查是否被锁定
    if (isLoginBlocked($username)) {
        $minutes = getRemainingLockTime($username);
        Logger::security("登录尝试被阻止: {$username}，剩余锁定时间: {$minutes}分钟");
        return ['success' => false, 'message' => "登录失败次数过多，请 {$minutes} 分钟后再试"];
    }
    
    $user = db()->fetch(
        "SELECT * FROM users WHERE username = ? AND status = 1",
        [$username]
    );
    
    if (!$user) {
        incrementLoginAttempts($username);
        Logger::security("登录失败: 用户不存在 - {$username}");
        return ['success' => false, 'message' => '用户名或密码错误'];
    }
    
    if (!verifyPassword($password, $user['password'])) {
        incrementLoginAttempts($username);
        Logger::security("登录失败: 密码错误 - {$username}");
        return ['success' => false, 'message' => '用户名或密码错误'];
    }
    
    // 登录成功
    resetLoginAttempts($username);
    
    // 重新生成 Session ID 防止会话固定攻击
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['real_name'] = $user['real_name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['login_time'] = time();
    
    Logger::info("用户登录成功: {$username}");
    auditLog('login', 'user', $user['id'], ['username' => $username]);
    
    return ['success' => true];
}

function logout() {
    $username = $_SESSION['username'] ?? 'unknown';
    
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    
    Logger::info("用户退出登录: {$username}");
}

function checkSessionTimeout() {
    if (isLoggedIn()) {
        if (time() - $_SESSION['login_time'] > SESSION_LIFETIME) {
            $username = $_SESSION['username'] ?? 'unknown';
            Logger::warning("会话超时: {$username}");
            logout();
            redirect('login.php?timeout=1');
        }
        $_SESSION['login_time'] = time();
    }
}

checkSessionTimeout();

// 密码强度验证
function validatePasswordStrength($password) {
    if (strlen($password) < 6) {
        return ['valid' => false, 'message' => '密码至少需要6个字符'];
    }
    
    return ['valid' => true];
}
