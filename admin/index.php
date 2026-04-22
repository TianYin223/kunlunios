<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';

requireAdmin();

$currentUser = getCurrentUser();

$totalDormitories = db()->fetch("SELECT COUNT(*) as count FROM dormitories WHERE status = 1")['count'];
$totalInspectors = db()->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'inspector' AND status = 1")['count'];
$todayRecords = db()->fetch("SELECT COUNT(*) as count FROM score_records WHERE DATE(created_at) = CURDATE()")['count'];
$weekRecords = db()->fetch(
    "SELECT COUNT(*) as count FROM score_records WHERE month_year = ?",
    [getCurrentPeriod()]
)['count'];

$recentRecords = db()->fetchAll(
    "SELECT * FROM score_records ORDER BY created_at DESC LIMIT 10"
);

renderHeader('管理后台');
?>

<div class="container" style="padding-top: 20px;">
    <div style="display: flex;">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="index.php" class="active">仪表盘</a></li>
                <li><a href="dormitories.php">宿舍管理</a></li>
                <li><a href="users.php">账号管理</a></li>
                <li><a href="settings.php">打分设置</a></li>
                <li><a href="records.php">打分记录</a></li>
                <li><a href="reset.php">分数重置</a></li>
                <li><a href="history.php">历史记录</a></li>
                <li><a href="export.php">数据导出</a></li>
                <li><a href="system.php">系统设置</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="page-header">
                <h2>仪表盘</h2>
            </div>
            
            <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                <div class="card" style="flex: 1; text-align: center;">
                    <div style="font-size: 32px; color: #667eea; font-weight: bold;"><?= $totalDormitories ?></div>
                    <div style="color: #666; margin-top: 5px;">宿舍总数</div>
                </div>
                <div class="card" style="flex: 1; text-align: center;">
                    <div style="font-size: 32px; color: #28a745; font-weight: bold;"><?= $totalInspectors ?></div>
                    <div style="color: #666; margin-top: 5px;">检查人数</div>
                </div>
                <div class="card" style="flex: 1; text-align: center;">
                    <div style="font-size: 32px; color: #ffc107; font-weight: bold;"><?= $todayRecords ?></div>
                    <div style="color: #666; margin-top: 5px;">今日打分</div>
                </div>
                <div class="card" style="flex: 1; text-align: center;">
                    <div style="font-size: 32px; color: #17a2b8; font-weight: bold;"><?= $weekRecords ?></div>
                    <div style="color: #666; margin-top: 5px;">本周打分</div>
                </div>
            </div>
            
            <div class="card">
                <h3 style="margin-bottom: 15px;">最近打分记录</h3>
                <?php if (empty($recentRecords)): ?>
                    <p style="color: #999; text-align: center; padding: 20px 0;">暂无记录</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>宿舍号</th>
                                <th>检查人</th>
                                <th>类型</th>
                                <th>分数</th>
                                <th>图片</th>
                                <th>时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentRecords as $record): ?>
                                <?php $images = getScoreRecordImages($record); ?>
                                <tr>
                                    <td><?= h($record['dormitory_no']) ?></td>
                                    <td><?= h($record['inspector_name']) ?></td>
                                    <td>
                                        <?php if ($record['score_type'] === 'add'): ?>
                                            <span class="badge badge-success">加分</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">减分</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($record['score_type'] === 'add'): ?>
                                            <span style="color: #28a745;">+<?= $record['score'] ?></span>
                                        <?php else: ?>
                                            <span style="color: #dc3545;">-<?= $record['score'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= empty($images) ? '无图片' : (count($images) . ' 张') ?></td>
                                    <td><?= formatDate($record['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
