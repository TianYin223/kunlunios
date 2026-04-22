<?php
/**
 * 安全功能模块
 * 包含 CSRF 防护、输入验证、登录限制等
 */

// CSRF Token 生成和验证
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . h(generateCsrfToken()) . '">';
}

function requireCsrfToken() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) {
            http_response_code(403);
            die('CSRF 验证失败，请刷新页面重试');
        }
    }
}

// 登录失败次数限制
function getLoginAttempts($username) {
    $key = 'login_attempts_' . md5($username);
    return $_SESSION[$key] ?? 0;
}

function incrementLoginAttempts($username) {
    $key = 'login_attempts_' . md5($username);
    $_SESSION[$key] = getLoginAttempts($username) + 1;
    $_SESSION[$key . '_time'] = time();
}

function resetLoginAttempts($username) {
    $key = 'login_attempts_' . md5($username);
    unset($_SESSION[$key]);
    unset($_SESSION[$key . '_time']);
}

function isLoginBlocked($username) {
    $attempts = getLoginAttempts($username);
    if ($attempts >= 5) {
        $key = 'login_attempts_' . md5($username) . '_time';
        $lastAttempt = $_SESSION[$key] ?? 0;
        // 锁定 15 分钟
        if (time() - $lastAttempt < 900) {
            return true;
        } else {
            resetLoginAttempts($username);
            return false;
        }
    }
    return false;
}

function getRemainingLockTime($username) {
    $key = 'login_attempts_' . md5($username) . '_time';
    $lastAttempt = $_SESSION[$key] ?? 0;
    $remaining = 900 - (time() - $lastAttempt);
    return max(0, ceil($remaining / 60));
}

// 输入验证类
class Validator {
    private $errors = [];
    private $data = [];
    
    public function __construct($data) {
        $this->data = $data;
    }
    
    public function required($field, $message = null) {
        if (!isset($this->data[$field]) || trim($this->data[$field]) === '') {
            $this->errors[$field] = $message ?? "{$field} 不能为空";
        }
        return $this;
    }
    
    public function minLength($field, $length, $message = null) {
        if (isset($this->data[$field]) && mb_strlen($this->data[$field]) < $length) {
            $this->errors[$field] = $message ?? "{$field} 至少需要 {$length} 个字符";
        }
        return $this;
    }
    
    public function maxLength($field, $length, $message = null) {
        if (isset($this->data[$field]) && mb_strlen($this->data[$field]) > $length) {
            $this->errors[$field] = $message ?? "{$field} 不能超过 {$length} 个字符";
        }
        return $this;
    }
    
    public function numeric($field, $message = null) {
        if (isset($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field] = $message ?? "{$field} 必须是数字";
        }
        return $this;
    }
    
    public function min($field, $min, $message = null) {
        if (isset($this->data[$field]) && floatval($this->data[$field]) < $min) {
            $this->errors[$field] = $message ?? "{$field} 不能小于 {$min}";
        }
        return $this;
    }
    
    public function max($field, $max, $message = null) {
        if (isset($this->data[$field]) && floatval($this->data[$field]) > $max) {
            $this->errors[$field] = $message ?? "{$field} 不能大于 {$max}";
        }
        return $this;
    }
    
    public function in($field, $values, $message = null) {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $values)) {
            $this->errors[$field] = $message ?? "{$field} 值无效";
        }
        return $this;
    }
    
    public function email($field, $message = null) {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message ?? "{$field} 格式无效";
        }
        return $this;
    }
    
    public function unique($field, $table, $column, $excludeId = null, $message = null) {
        if (isset($this->data[$field])) {
            $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?";
            $params = [$this->data[$field]];
            
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $result = db()->fetch($sql, $params);
            if ($result['count'] > 0) {
                $this->errors[$field] = $message ?? "{$field} 已存在";
            }
        }
        return $this;
    }
    
    public function fails() {
        return !empty($this->errors);
    }
    
    public function passes() {
        return empty($this->errors);
    }
    
    public function errors() {
        return $this->errors;
    }
    
    public function firstError() {
        return !empty($this->errors) ? reset($this->errors) : null;
    }
}

// XSS 防护增强
function cleanInput($data) {
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// 安全的文件上传验证
function validateUpload($file, $allowedTypes = [], $maxSize = 5242880) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => '无效的文件'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => '文件上传失败'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => '文件大小超过限制'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!empty($allowedTypes) && !in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'message' => '不支持的文件类型'];
    }
    
    return ['success' => true];
}

// IP 获取
function getClientIp() {
    $ip = '';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}
