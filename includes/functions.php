<?php

require_once __DIR__ . '/../config/config.php';

function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function jsonResponse($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function setFlashMessage($key, $message) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    if (!isset($_SESSION['flash_messages']) || !is_array($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }

    $_SESSION['flash_messages'][$key] = $message;
}

function pullFlashMessage($key, $default = '') {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return $default;
    }

    if (!isset($_SESSION['flash_messages']) || !is_array($_SESSION['flash_messages'])) {
        return $default;
    }

    if (!array_key_exists($key, $_SESSION['flash_messages'])) {
        return $default;
    }

    $value = $_SESSION['flash_messages'][$key];
    unset($_SESSION['flash_messages'][$key]);
    return $value;
}

function success($message = '鎿嶄綔鎴愬姛', $data = []) {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data]);
}

function error($message = '鎿嶄綔澶辫触', $code = 400) {
    http_response_code($code);
    jsonResponse(['success' => false, 'message' => $message]);
}

function getSetting($key, $default = null) {
    $result = db()->fetch("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
    return $result ? $result['setting_value'] : $default;
}

function updateSetting($key, $value) {
    return db()->query(
        "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
        [$key, $value]
    );
}

function getCurrentWeekKey($timestamp = null) {
    $time = $timestamp === null ? time() : intval($timestamp);
    return date('o-\WW', $time);
}

function getCurrentMonthKey($timestamp = null) {
    $time = $timestamp === null ? time() : intval($timestamp);
    return date('Y-m', $time);
}

function isValidWeekKey($period) {
    return is_string($period) && preg_match('/^\d{4}-W(0[1-9]|[1-4][0-9]|5[0-3])$/', $period);
}

function isValidMonthKey($period) {
    return is_string($period) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period);
}

function isValidPeriodKey($period) {
    return isValidWeekKey($period);
}

function getCurrentWeekPeriod() {
    $period = trim((string) getSetting('current_week', ''));
    if (!isValidWeekKey($period)) {
        $legacyPeriod = trim((string) getSetting('current_month', ''));
        if (isValidWeekKey($legacyPeriod)) {
            $period = $legacyPeriod;
        }
    }

    if (!isValidWeekKey($period)) {
        $period = getCurrentWeekKey();
        setCurrentWeekPeriod($period);
    }

    return $period;
}

function setCurrentWeekPeriod($period) {
    if (!isValidWeekKey($period)) {
        return false;
    }

    updateSetting('current_week', $period);
    return true;
}

function getCurrentMonthPeriod() {
    $period = trim((string) getSetting('current_month', ''));
    if (!isValidMonthKey($period)) {
        $period = getCurrentMonthKey();
        setCurrentMonthPeriod($period);
    }

    return $period;
}

function setCurrentMonthPeriod($period) {
    if (!isValidMonthKey($period)) {
        return false;
    }

    updateSetting('current_month', $period);
    return true;
}

function getCurrentPeriod() {
    return getCurrentWeekPeriod();
}

function setCurrentPeriod($period) {
    return setCurrentWeekPeriod($period);
}

function getCurrentWeek() {
    return getCurrentWeekPeriod();
}

function getCurrentMonth() {
    return getCurrentMonthPeriod();
}

function getCurrentMonthLabel() {
    return getCurrentMonthPeriod();
}

function getCurrentWeekLabel() {
    return getCurrentWeekPeriod();
}

function getCurrentWeekdayCn($timestamp = null) {
    $time = $timestamp === null ? time() : intval($timestamp);
    $weekdays = json_decode('["\u661f\u671f\u65e5","\u661f\u671f\u4e00","\u661f\u671f\u4e8c","\u661f\u671f\u4e09","\u661f\u671f\u56db","\u661f\u671f\u4e94","\u661f\u671f\u516d"]', true);
    return $weekdays[intval(date('w', $time))];
}

function weekKeyToTimestamp($period, $isoDay = 1) {
    if (!isValidWeekKey($period)) {
        return null;
    }

    if (!is_int($isoDay)) {
        $isoDay = intval($isoDay);
    }
    if ($isoDay < 1 || $isoDay > 7) {
        $isoDay = 1;
    }

    if (!preg_match('/^(\d{4})-W(\d{2})$/', $period, $matches)) {
        return null;
    }

    $year = intval($matches[1]);
    $week = intval($matches[2]);

    $dt = new DateTimeImmutable('now');
    $dt = $dt->setISODate($year, $week, $isoDay);
    return $dt->getTimestamp();
}

function formatPeriodDisplay($period, $timestamp = null) {
    $period = trim((string) $period);
    if ($period === '') {
        return $period;
    }

    if (!isValidWeekKey($period)) {
        return $period;
    }

    if ($timestamp !== null) {
        $parsedTimestamp = is_numeric($timestamp) ? intval($timestamp) : 0;
        if ($parsedTimestamp > 0) {
            return getCurrentWeekdayCn($parsedTimestamp);
        }
    }

    if ($period === getCurrentWeekPeriod()) {
        return getCurrentWeekdayCn();
    }

    $mondayTs = weekKeyToTimestamp($period, 1);
    return $mondayTs === null ? getCurrentWeekdayCn() : getCurrentWeekdayCn($mondayTs);
}

function getCurrentDisplayDatetime($timestamp = null) {
    $time = $timestamp === null ? time() : intval($timestamp);
    return date('Y/m/d H:i:s', $time);
}

function getMonthlyScore($dormitory) {
    if (!is_array($dormitory)) {
        return 0;
    }

    if (!array_key_exists('monthly_score', $dormitory)) {
        return normalizeDormitoryScore($dormitory['score'] ?? 0);
    }

    return normalizeDormitoryScore($dormitory['monthly_score']);
}

function getWeeklyScore($dormitory) {
    if (!is_array($dormitory)) {
        return 0;
    }

    return normalizeDormitoryScore($dormitory['score'] ?? 0);
}

function normalizeMonthlyScore($score) {
    return normalizeDormitoryScore($score);
}

function normalizeWeeklyScore($score) {
    return normalizeDormitoryScore($score);
}

function toFloat($value) {
    return floatval($value);
}

function scoreDeltaByType($scoreType, $score) {
    $numericScore = floatval($score);
    return $scoreType === 'subtract' ? -$numericScore : $numericScore;
}

function scoreTypeFromDelta($delta) {
    return $delta < 0 ? 'subtract' : 'add';
}

function validateScoreBound($score) {
    $score = floatval($score);
    $maxScore = getWeeklyMaxScore();
    return $score >= 0 && $score <= $maxScore;
}

function getDormitoryScores($dormitory) {
    $weekly = getWeeklyScore($dormitory);
    $monthly = getMonthlyScore($dormitory);
    return [$weekly, $monthly];
}

function hasDormitoryMonthlyScoreColumn() {
    static $exists = null;

    if ($exists !== null) {
        return $exists;
    }

    try {
        $result = db()->fetch(
            "SELECT COUNT(*) AS c
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'dormitories'
               AND COLUMN_NAME = 'monthly_score'"
        );
        $exists = intval($result['c'] ?? 0) > 0;
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

function setDormitoryScores($dormitoryId, $weeklyScore, $monthlyScore) {
    $weeklyScore = normalizeWeeklyScore($weeklyScore);
    $monthlyScore = normalizeMonthlyScore($monthlyScore);

    if (hasDormitoryMonthlyScoreColumn()) {
        db()->update('dormitories', [
            'score' => $weeklyScore,
            'monthly_score' => $monthlyScore
        ], 'id = ?', [$dormitoryId]);
    } else {
        db()->update('dormitories', ['score' => $weeklyScore], 'id = ?', [$dormitoryId]);
    }

    return true;
}

function getSiteName() {
    try {
        $siteName = trim((string) getSetting('site_name', SITE_NAME));
    } catch (Exception $e) {
        $siteName = SITE_NAME;
    }

    if ($siteName === '') {
        $siteName = SITE_NAME;
    }

    return strlen($siteName) > 60 ? substr($siteName, 0, 60) : $siteName;
}

function getFixedScore() {
    $score = getSetting('fixed_score', '0');
    return $score === '0' ? 0 : floatval($score);
}

function getDailyLimit() {
    return intval(getSetting('daily_limit', '3'));
}

function parseScoreOptionValues($raw) {
    $raw = str_replace([',', '，', "\r", "\n", "\t"], [',', ',', ',', ',', ','], (string) $raw);
    $parts = array_filter(array_map('trim', explode(',', $raw)), function ($item) {
        return $item !== '';
    });

    $values = [];
    foreach ($parts as $part) {
        if (!is_numeric($part)) {
            continue;
        }
        $value = round(floatval($part), 2);
        if ($value <= 0) {
            continue;
        }
        $values[] = $value;
    }

    if (empty($values)) {
        return [];
    }

    $values = array_values(array_unique($values, SORT_REGULAR));
    sort($values, SORT_NUMERIC);
    return $values;
}

function serializeScoreOptionValues($values) {
    if (!is_array($values) || empty($values)) {
        return '';
    }

    $formatted = array_map(function ($value) {
        $number = round(floatval($value), 2);
        $text = number_format($number, 2, '.', '');
        return rtrim(rtrim($text, '0'), '.');
    }, $values);

    return implode(',', $formatted);
}

function getScoreOptionValues() {
    $raw = (string) getSetting('score_option_values', '0.5,1,1.5,2');
    $values = parseScoreOptionValues($raw);
    if (empty($values)) {
        $values = [0.5, 1, 1.5, 2];
    }
    return $values;
}

function isScoreInOptions($score, $options) {
    $target = round(floatval($score), 2);
    foreach ($options as $option) {
        if (abs($target - round(floatval($option), 2)) < 0.0001) {
            return true;
        }
    }
    return false;
}

function getWeeklyMaxScore() {
    $raw = getSetting('weekly_max_score', null);
    if ($raw === null) {
        $raw = getSetting('monthly_max_score', (string) DEFAULT_SCORE);
    }

    $score = floatval($raw);
    return $score > 0 ? $score : DEFAULT_SCORE;
}

function getMonthlyMaxScore() {
    return getWeeklyMaxScore();
}

function normalizeDormitoryScore($score) {
    $maxScore = getMonthlyMaxScore();
    return max(0, min($maxScore, floatval($score)));
}

function formatDate($date) {
    return date('Y-m-d H:i', strtotime($date));
}

function generatePassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function getTodayScoreCount($dormitoryId) {
    $today = date('Y-m-d');
    $result = db()->fetch(
        "SELECT COUNT(*) as count FROM score_records 
         WHERE dormitory_id = ? AND DATE(created_at) = ?",
        [$dormitoryId, $today]
    );
    return $result['count'];
}

function getScoreRecordImages($record) {
    if (empty($record['images_json'])) {
        return [];
    }

    $images = json_decode($record['images_json'], true);
    if (!is_array($images)) {
        return [];
    }

    $images = array_values(array_filter($images, function ($item) {
        return is_string($item) && $item !== '';
    }));

    return $images;
}

function exportExcel($data, $filename, $headers = []) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo "\xEF\xBB\xBF";
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';
    echo '<table border="1">';
    
    if (!empty($headers)) {
        echo '<tr>';
        foreach ($headers as $header) {
            echo '<th style="background-color: #f0f0f0; font-weight: bold;">' . h($header) . '</th>';
        }
        echo '</tr>';
    }
    
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . h($cell) . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</table></body></html>';
    
    Logger::info("瀵煎嚭Excel: {$filename}");
    auditLog('export', 'excel', null, ['filename' => $filename, 'rows' => count($data)]);
    
    exit;
}

// 鍒嗛〉鍑芥暟
function paginate($sql, $params, $page = 1, $perPage = ITEMS_PER_PAGE) {
    $page = max(1, intval($page));
    $offset = ($page - 1) * $perPage;
    
    // 鑾峰彇鎬绘暟
    $countSql = "SELECT COUNT(*) as total FROM ({$sql}) as count_table";
    $total = db()->fetch($countSql, $params)['total'];
    
    // 鑾峰彇鏁版嵁
    $dataSql = $sql . " LIMIT {$perPage} OFFSET {$offset}";
    $data = db()->fetchAll($dataSql, $params);
    
    return [
        'data' => $data,
        'total' => $total,
        'page' => $page,
        'perPage' => $perPage,
        'totalPages' => ceil($total / $perPage),
        'hasMore' => $page < ceil($total / $perPage)
    ];
}

// 鐢熸垚鍒嗛〉HTML
function renderPagination($pagination, $baseUrl) {
    if ($pagination['totalPages'] <= 1) {
        return '';
    }
    
    $html = '<div class="pagination">';
    
    // 涓婁竴椤?
    if ($pagination['page'] > 1) {
        $prevUrl = $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'page=' . ($pagination['page'] - 1);
        $html .= '<a href="' . h($prevUrl) . '" class="page-link">涓婁竴椤?/a>';
    }
    
    // 椤电爜
    $start = max(1, $pagination['page'] - 2);
    $end = min($pagination['totalPages'], $pagination['page'] + 2);
    
    if ($start > 1) {
        $url = $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'page=1';
        $html .= '<a href="' . h($url) . '" class="page-link">1</a>';
        if ($start > 2) {
            $html .= '<span class="page-ellipsis">...</span>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $url = $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'page=' . $i;
        $active = $i === $pagination['page'] ? ' active' : '';
        $html .= '<a href="' . h($url) . '" class="page-link' . $active . '">' . $i . '</a>';
    }
    
    if ($end < $pagination['totalPages']) {
        if ($end < $pagination['totalPages'] - 1) {
            $html .= '<span class="page-ellipsis">...</span>';
        }
        $url = $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'page=' . $pagination['totalPages'];
        $html .= '<a href="' . h($url) . '" class="page-link">' . $pagination['totalPages'] . '</a>';
    }
    
    // 涓嬩竴椤?
    if ($pagination['page'] < $pagination['totalPages']) {
        $nextUrl = $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'page=' . ($pagination['page'] + 1);
        $html .= '<a href="' . h($nextUrl) . '" class="page-link">涓嬩竴椤?/a>';
    }
    
    $html .= '</div>';
    
    return $html;
}

// 鏁版嵁澶囦唤鍑芥暟
function backupDatabase() {
    try {
        $backupDir = __DIR__ . '/../backups';
        if (!file_exists($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backupDir . '/' . $filename;
        
        $command = sprintf(
            'mysqldump -h%s -u%s -p%s %s > %s',
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME,
            $filepath
        );
        
        exec($command, $output, $returnVar);
        
        if ($returnVar === 0) {
            Logger::info("数据库备份成功: {$filename}");
            return ['success' => true, 'filename' => $filename];
        } else {
            Logger::error("数据库备份失败");
            return ['success' => false, 'message' => '备份失败'];
        }
    } catch (Exception $e) {
        Logger::error("数据库备份异常: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

