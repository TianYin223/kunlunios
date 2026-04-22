<?php

require_once __DIR__ . '/../../../includes/api_auth.php';

apiRequireMethod('GET');
$auth = apiRequireAuth('inspector');
$user = $auth['user'];

$pagination = apiPagination(20, 100);
$page = $pagination['page'];
$pageSize = $pagination['page_size'];
$offset = $pagination['offset'];

$totalRow = db()->fetch(
    "SELECT COUNT(*) AS c
     FROM score_records
     WHERE inspector_id = ?",
    [$user['id']]
);
$total = intval($totalRow['c'] ?? 0);

$records = db()->fetchAll(
    "SELECT id, dormitory_no, score_type, score, reason, images_json, month_year, created_at
     FROM score_records
     WHERE inspector_id = ?
     ORDER BY created_at DESC
     LIMIT {$offset}, {$pageSize}",
    [$user['id']]
);

$items = [];
foreach ($records as $record) {
    $images = getScoreRecordImages($record);
    $imageUrls = array_map('apiBuildAssetUrl', $images);
    $score = round(floatval($record['score']), 2);
    $items[] = [
        'id' => intval($record['id']),
        'dormitory_no' => (string) $record['dormitory_no'],
        'score_type' => (string) $record['score_type'],
        'score' => $score,
        'signed_score' => $record['score_type'] === 'subtract' ? -$score : $score,
        'reason' => (string) $record['reason'],
        'period' => formatPeriodDisplay((string) $record['month_year'], strtotime((string) $record['created_at'])),
        'created_at' => (string) $record['created_at'],
        'images' => $imageUrls,
        'image_count' => count($imageUrls),
    ];
}

$totalPages = $pageSize > 0 ? intval(ceil($total / $pageSize)) : 1;

apiSuccess('获取成功', [
    'items' => $items,
    'page' => $page,
    'page_size' => $pageSize,
    'total' => $total,
    'total_pages' => $totalPages,
]);
