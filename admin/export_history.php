<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$period = trim($_GET['period'] ?? ($_GET['month'] ?? ''));

$where = "WHERE 1=1";
$params = [];

if ($period) {
    $where .= " AND month_year = ?";
    $params[] = $period;
}

$history = db()->fetchAll(
    "SELECT * FROM history_rankings {$where} ORDER BY month_year DESC, ranking ASC",
    $params
);

$data = [];
foreach ($history as $record) {
    $data[] = [
        $record['month_year'],
        $record['ranking'],
        $record['dormitory_no'],
        $record['final_score']
    ];
}

$filename = '历史排行_' . ($period ? $period : '全部') . '_' . date('Ymd');
$headers = ['周期', '排名', '宿舍号', '分数'];

exportExcel($data, $filename, $headers);
