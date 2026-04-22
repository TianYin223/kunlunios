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

$records = db()->fetchAll(
    "SELECT * FROM score_records {$where} ORDER BY created_at DESC",
    $params
);

$data = [];
foreach ($records as $record) {
    $images = getScoreRecordImages($record);
    $data[] = [
        $record['dormitory_no'],
        $record['inspector_name'],
        $record['score_type'] === 'add' ? '加分' : '减分',
        $record['score_type'] === 'add' ? '+' . $record['score'] : '-' . $record['score'],
        count($images),
        implode("\n", $images),
        $record['month_year'],
        $record['created_at']
    ];
}

$filename = '打分记录_' . ($period ? $period : '全部') . '_' . date('Ymd');
$headers = ['宿舍号', '检查人', '类型', '分数', '图片数量', '图片路径', '周期', '时间'];

exportExcel($data, $filename, $headers);
