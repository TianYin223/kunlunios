<?php

require_once __DIR__ . '/../../includes/api_auth.php';

apiRequireMethod('GET');
$auth = apiRequireAuth('inspector');
$user = $auth['user'];

$today = date('Y-m-d');
$todayCountRow = db()->fetch(
    "SELECT COUNT(*) AS c
     FROM score_records
     WHERE inspector_id = ?
       AND DATE(created_at) = ?",
    [$user['id'], $today]
);
$todayCount = intval($todayCountRow['c'] ?? 0);

$recentRecords = db()->fetchAll(
    "SELECT id, dormitory_no, score_type, score, month_year, created_at, images_json
     FROM score_records
     WHERE inspector_id = ?
     ORDER BY created_at DESC
     LIMIT 5",
    [$user['id']]
);

$recent = [];
foreach ($recentRecords as $record) {
    $images = getScoreRecordImages($record);
    $recent[] = [
        'id' => intval($record['id']),
        'dormitory_no' => (string) $record['dormitory_no'],
        'score_type' => (string) $record['score_type'],
        'score' => round(floatval($record['score']), 2),
        'signed_score' => $record['score_type'] === 'subtract'
            ? -round(floatval($record['score']), 2)
            : round(floatval($record['score']), 2),
        'period' => formatPeriodDisplay((string) $record['month_year'], strtotime((string) $record['created_at'])),
        'created_at' => (string) $record['created_at'],
        'image_count' => count($images),
    ];
}

apiSuccess('获取成功', [
    'user' => $user,
    'settings' => [
        'current_week' => getCurrentWeekdayCn(),
        'current_month' => getCurrentMonthPeriod(),
        'daily_limit' => getDailyLimit(),
        'score_options' => getScoreOptionValues(),
        'weekly_max_score' => getWeeklyMaxScore(),
    ],
    'today_submit_count' => $todayCount,
    'recent_records' => $recent,
]);
