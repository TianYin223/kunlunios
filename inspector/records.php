<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';

requireLogin();

$currentUser = getCurrentUser();

$page = max(1, intval($_GET['page'] ?? 1));
$pageSize = 20;
$offset = ($page - 1) * $pageSize;

$where = "WHERE inspector_id = ?";
$params = [$currentUser['id']];

$total = db()->fetch(
    "SELECT COUNT(*) as count FROM score_records {$where}",
    $params
)['count'];

$records = db()->fetchAll(
    "SELECT * FROM score_records {$where} ORDER BY created_at DESC LIMIT {$offset}, {$pageSize}",
    $params
);

$totalPages = ceil($total / $pageSize);

renderHeader('我的上报记录');
?>

<div class="container" style="padding-top: 20px;">
    <div style="display: flex;">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="index.php">打分上报</a></li>
                <li><a href="records.php" class="active">打分记录</a></li>
                <li><a href="ranking.php">排行榜</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header flex flex-between">
                <h2>我的上报记录</h2>
                <span style="color: #666;">共 <?= $total ?> 条记录</span>
            </div>

            <div class="card">
                <?php if (empty($records)): ?>
                    <p class="text-center" style="color: #999; padding: 40px 0;">暂无记录</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                        <tr>
                            <th>宿舍号</th>
                            <th>类型</th>
                            <th>分数</th>
                            <th>图片</th>
                            <th>周次</th>
                            <th>时间</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($records as $record): ?>
                            <?php $images = getScoreRecordImages($record); ?>
                            <tr>
                                <td><?= h($record['dormitory_no']) ?></td>
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
                                <td>
                                    <?php if (empty($images)): ?>
                                        <span style="color: #999;">无图片</span>
                                    <?php else: ?>
                                        <div style="display: flex; gap: 6px; flex-wrap: wrap; max-width: 220px;">
                                            <?php foreach (array_slice($images, 0, 3) as $img): ?>
                                                <a href="../<?= h($img) ?>" target="_blank">
                                                    <img src="../<?= h($img) ?>" alt="现场图"
                                                         style="width: 44px; height: 44px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;">
                                                </a>
                                            <?php endforeach; ?>
                                            <?php if (count($images) > 3): ?>
                                                <span style="color: #666; align-self: center;">+<?= count($images) - 3 ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= h($record['month_year']) ?></td>
                                <td><?= formatDate($record['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ($totalPages > 1): ?>
                        <div class="text-center mt-20">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?= $i ?>"
                                   class="btn btn-sm <?= $i === $page ? '' : 'btn-secondary' ?>"
                                   style="margin: 0 2px; <?= $i === $page ? '' : 'background: #6c757d;' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
