<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$dormitories = db()->fetchAll(
    "SELECT dormitory_no, score FROM dormitories WHERE status = 1 ORDER BY score DESC, dormitory_no ASC"
);

$data = [];
foreach ($dormitories as $index => $dorm) {
    $data[] = [
        $index + 1,
        $dorm['dormitory_no'],
        $dorm['score']
    ];
}

$filename = '宿舍排行_' . getCurrentPeriod() . '_' . date('Ymd');
$headers = ['排名', '宿舍号', '分数'];

exportExcel($data, $filename, $headers);
