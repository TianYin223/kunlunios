<?php

require_once __DIR__ . '/../../../includes/api_auth.php';
require_once __DIR__ . '/../../../includes/security.php';

function apiRollbackSavedImages($paths) {
    foreach ($paths as $savedPath) {
        $fullPath = __DIR__ . '/../../../' . ltrim($savedPath, '/');
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}

apiRequireMethod('POST');
$auth = apiRequireAuth('inspector');
$currentUser = $auth['user'];

$dormitoryNo = trim((string) apiInput('dormitory_no', ''));
$scoreType = trim((string) apiInput('score_type', ''));
$score = round(floatval(apiInput('score', 0)), 2);
$scoreOptions = getScoreOptionValues();
$imageFiles = apiCollectUploadFiles('images');
if (empty($imageFiles)) {
    $imageFiles = apiCollectUploadFiles('images[]');
}

$validator = new Validator([
    'dormitory_no' => $dormitoryNo,
    'score_type' => $scoreType,
]);
$validator->required('dormitory_no', '请输入宿舍号')
    ->required('score_type', '请选择加分或减分')
    ->in('score_type', ['add', 'subtract'], '打分类型无效');

if ($validator->fails()) {
    apiError($validator->firstError(), 422);
}

if ($scoreType === 'add') {
    $score = 0;
}

if ($scoreType === 'subtract' && !isScoreInOptions($score, $scoreOptions)) {
    apiError('请选择系统设置的扣分分值', 422);
}

$imageCount = count($imageFiles);
if ($imageCount < 4 || $imageCount > 10) {
    apiError('请上传4到10张图片', 422);
}

$dormitory = db()->fetch(
    "SELECT * FROM dormitories WHERE dormitory_no = ? AND status = 1",
    [$dormitoryNo]
);

if (!$dormitory) {
    Logger::warning("APP 打分失败: 宿舍不存在 - {$dormitoryNo}");
    apiError('宿舍号不存在或已禁用', 404);
}

$dailyLimit = getDailyLimit();
$todayCount = getTodayScoreCount($dormitory['id']);
if ($todayCount >= $dailyLimit) {
    apiError("该宿舍今日打分次数已达上限({$dailyLimit}次)", 422);
}

$currentWeeklyScore = getWeeklyScore($dormitory);
$currentMonthlyScore = getMonthlyScore($dormitory);

if ($scoreType === 'subtract') {
    $maxSubtractable = min($currentWeeklyScore, $currentMonthlyScore);
    if ($score > $maxSubtractable) {
        apiError("减分后将低于0分，当前最多可减 {$maxSubtractable} 分", 422);
    }
}

$actualScore = $scoreType === 'subtract' ? -$score : $score;
$newWeeklyScore = $currentWeeklyScore + $actualScore;
$newMonthlyScore = $currentMonthlyScore + $actualScore;

$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$maxFileSize = 5 * 1024 * 1024;
$mimeToExt = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];
$savedImages = [];
$uploadDay = date('Ymd');
$uploadDir = __DIR__ . '/../../../uploads/score_images/' . $uploadDay;

if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    apiError('创建图片目录失败，请联系管理员', 500);
}

foreach ($imageFiles as $file) {
    $check = validateUpload($file, $allowedTypes, $maxFileSize);
    if (!$check['success']) {
        apiRollbackSavedImages($savedImages);
        apiError($check['message'], 422);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($mimeToExt[$mimeType])) {
        apiRollbackSavedImages($savedImages);
        apiError('仅支持 JPG/PNG/WEBP/GIF 图片', 422);
    }

    $filename = date('His') . '_' . bin2hex(random_bytes(8)) . '.' . $mimeToExt[$mimeType];
    $targetPath = $uploadDir . '/' . $filename;
    $relativePath = 'uploads/score_images/' . $uploadDay . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        apiRollbackSavedImages($savedImages);
        apiError('图片保存失败，请重试', 500);
    }

    $savedImages[] = $relativePath;
}

try {
    db()->beginTransaction();

    $recordId = db()->insert('score_records', [
        'dormitory_id' => $dormitory['id'],
        'dormitory_no' => $dormitory['dormitory_no'],
        'inspector_id' => $currentUser['id'],
        'inspector_name' => $currentUser['real_name'],
        'score_type' => $scoreType,
        'score' => $score,
        'reason' => '',
        'images_json' => json_encode($savedImages, JSON_UNESCAPED_UNICODE),
        'month_year' => getCurrentPeriod(),
    ]);

    setDormitoryScores($dormitory['id'], $newWeeklyScore, $newMonthlyScore);
    db()->commit();

    Logger::info('APP 打分成功', [
        'record_id' => $recordId,
        'user_id' => $currentUser['id'],
        'dormitory_no' => $dormitoryNo,
        'score_type' => $scoreType,
        'score' => $score,
        'image_count' => count($savedImages),
    ]);

    $imageUrls = array_map('apiBuildAssetUrl', $savedImages);

    apiSuccess('打分上报成功', [
        'record_id' => intval($recordId),
        'dormitory_no' => (string) $dormitory['dormitory_no'],
        'score_type' => $scoreType,
        'score' => $score,
        'signed_score' => $scoreType === 'subtract' ? -$score : $score,
        'current_weekly_score' => normalizeWeeklyScore($newWeeklyScore),
        'current_monthly_score' => normalizeMonthlyScore($newMonthlyScore),
        'period' => formatPeriodDisplay(getCurrentPeriod(), time()),
        'images' => $imageUrls,
    ]);
} catch (Exception $e) {
    db()->rollBack();
    apiRollbackSavedImages($savedImages);
    Logger::error('APP 打分异常: ' . $e->getMessage());
    apiError('打分失败，请稍后重试', 500);
}
