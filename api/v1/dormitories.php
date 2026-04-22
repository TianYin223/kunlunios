<?php

require_once __DIR__ . '/../../includes/api_auth.php';

apiRequireMethod('GET');
apiRequireAuth('inspector');

$keyword = trim((string) ($_GET['keyword'] ?? ''));
$limit = intval($_GET['limit'] ?? 50);
if ($limit < 1) {
    $limit = 50;
}
if ($limit > 100) {
    $limit = 100;
}

$sql = "SELECT id, dormitory_no, score, monthly_score
        FROM dormitories
        WHERE status = 1";
$params = [];

if ($keyword !== '') {
    $sql .= " AND dormitory_no LIKE ?";
    $params[] = '%' . $keyword . '%';
}

$sql .= " ORDER BY dormitory_no ASC LIMIT {$limit}";

$rows = db()->fetchAll($sql, $params);
$items = [];
foreach ($rows as $row) {
    $items[] = [
        'id' => intval($row['id']),
        'dormitory_no' => (string) $row['dormitory_no'],
        'weekly_score' => getWeeklyScore($row),
        'monthly_score' => getMonthlyScore($row),
    ];
}

apiSuccess('获取成功', [
    'items' => $items,
    'keyword' => $keyword,
]);

