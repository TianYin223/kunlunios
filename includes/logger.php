<?php
/**
 * 日志系统
 * 记录系统操作、错误和安全事件
 */

class Logger {
    private static $logDir = __DIR__ . '/../logs';
    
    public static function init() {
        if (!file_exists(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
        
        // 创建 .htaccess 保护日志目录
        $htaccess = self::$logDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents(
                $htaccess,
                "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
            );
        }
    }
    
    private static function write($level, $message, $context = []) {
        self::init();
        
        $date = date('Y-m-d');
        $time = date('Y-m-d H:i:s');
        $logFile = self::$logDir . "/{$date}.log";
        
        $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logLine = "[{$time}] [{$level}] {$message}";
        
        if ($contextStr) {
            $logLine .= " | Context: {$contextStr}";
        }
        
        $logLine .= " | IP: " . getClientIp();
        
        if (isLoggedIn()) {
            $user = getCurrentUser();
            $logLine .= " | User: {$user['username']} ({$user['role']})";
        }
        
        $logLine .= PHP_EOL;
        
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    public static function info($message, $context = []) {
        self::write('INFO', $message, $context);
    }
    
    public static function warning($message, $context = []) {
        self::write('WARNING', $message, $context);
    }
    
    public static function error($message, $context = []) {
        self::write('ERROR', $message, $context);
    }
    
    public static function security($message, $context = []) {
        self::write('SECURITY', $message, $context);
    }
    
    public static function operation($action, $target, $details = []) {
        $message = "操作: {$action} | 目标: {$target}";
        self::write('OPERATION', $message, $details);
    }
}

// 数据库操作日志表
function createAuditLogTable() {
    $sql = "
    CREATE TABLE IF NOT EXISTS `audit_logs` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id` INT UNSIGNED NULL COMMENT '操作用户ID',
      `username` VARCHAR(50) NULL COMMENT '用户名',
      `action` VARCHAR(50) NOT NULL COMMENT '操作类型',
      `target_type` VARCHAR(50) NOT NULL COMMENT '目标类型',
      `target_id` INT UNSIGNED NULL COMMENT '目标ID',
      `details` TEXT NULL COMMENT '详细信息',
      `ip_address` VARCHAR(45) NOT NULL COMMENT 'IP地址',
      `user_agent` VARCHAR(255) NULL COMMENT '用户代理',
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_user_id` (`user_id`),
      KEY `idx_action` (`action`),
      KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='审计日志表';
    ";
    
    try {
        db()->query($sql);
    } catch (Exception $e) {
        Logger::error('创建审计日志表失败: ' . $e->getMessage());
    }
}

// 记录审计日志到数据库
function auditLog($action, $targetType, $targetId = null, $details = []) {
    try {
        createAuditLogTable();
        
        $user = getCurrentUser();
        $data = [
            'user_id' => $user ? $user['id'] : null,
            'username' => $user ? $user['username'] : 'guest',
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            'ip_address' => getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        db()->insert('audit_logs', $data);
        Logger::operation($action, $targetType, $details);
    } catch (Exception $e) {
        Logger::error('记录审计日志失败: ' . $e->getMessage());
    }
}
