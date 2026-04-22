<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';

requireAdmin();

$page = max(1, intval($_GET['page'] ?? 1));
$pageSize = 20;
$offset = ($page - 1) * $pageSize;

$period = trim($_GET['period'] ?? ($_GET['month'] ?? ''));

$where = "WHERE 1=1";
$params = [];

if ($period) {
    $where .= " AND month_year = ?";
    $params[] = $period;
}

$total = db()->fetch(
    "SELECT COUNT(*) as count FROM history_rankings {$where}",
    $params
)['count'];

$history = db()->fetchAll(
    "SELECT * FROM history_rankings {$where} ORDER BY month_year DESC, ranking ASC LIMIT {$offset}, {$pageSize}",
    $params
);

$totalPages = ceil($total / $pageSize);

$periods = db()->fetchAll(
    "SELECT DISTINCT month_year FROM history_rankings ORDER BY month_year DESC"
);

renderHeader('历史记录');
?>

<div class="container" style="padding-top: 20px;">
    <div style="display: flex;">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="index.php">仪表盘</a></li>
                <li><a href="dormitories.php">宿舍管理</a></li>
                <li><a href="users.php">账号管理</a></li>
                <li><a href="settings.php">打分设置</a></li>
                <li><a href="records.php">打分记录</a></li>
                <li><a href="reset.php">分数重置</a></li>
                <li><a href="history.php" class="active">历史记录</a></li>
                <li><a href="export.php">数据导出</a></li>
                <li><a href="system.php">系统设置</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="page-header flex flex-between">
                <h2>历史排行榜</h2>
                <span style="color: #666;">共 <?= $total ?> 条记录</span>
            </div>
            
            <div class="card">
                <form method="GET" style="display: flex; gap: 10px; margin-bottom: 20px;">
                    <select name="period" style="padding: 8px;">
                        <option value="">全部周期</option>
                        <?php foreach ($periods as $m): ?>
                            <option value="<?= h($m['month_year']) ?>" 
                                    <?= $period === $m['month_year'] ? 'selected' : '' ?>>
                                <?= h(formatPeriodDisplay($m['month_year'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-sm">筛选</button>
                    <?php if ($period): ?>
                        <a href="history.php" class="btn btn-sm btn-secondary">清除</a>
                    <?php endif; ?>
                </form>
                
                <?php if (empty($history)): ?>
                    <p style="color: #999; text-align: center; padding: 40px 0;">暂无历史记录</p>
                <?php else: ?>
                    <?php
                    $currentPeriodGroup = '';
                    foreach ($history as $record): 
                        if ($currentPeriodGroup !== $record['month_year']):
                            if ($currentPeriodGroup !== ''):
                                echo '</tbody></table>';
                            endif;
                            $currentPeriodGroup = $record['month_year'];
                    ?>
                        <h4 style="margin: 20px 0 10px; color: #667eea;"><?= h(formatPeriodDisplay($currentPeriodGroup, strtotime($record['created_at'] ?? ''))) ?></h4>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>排名</th>
                                    <th>宿舍号</th>
                                    <th>最终分数</th>
                                </tr>
                            </thead>
                            <tbody>
                    <?php endif; ?>
                                <tr>
                                    <td>
                                        <?php if ($record['ranking'] == 1): ?>
                                            <span class="badge badge-success">🥇 第1名</span>
                                        <?php elseif ($record['ranking'] == 2): ?>
                                            <span class="badge badge-info">🥈 第2名</span>
                                        <?php elseif ($record['ranking'] == 3): ?>
                                            <span class="badge badge-secondary">🥉 第3名</span>
                                        <?php else: ?>
                                            第<?= $record['ranking'] ?>名
                                        <?php endif; ?>
                                    </td>
                                    <td><?= h($record['dormitory_no']) ?></td>
                                    <td><strong><?= $record['final_score'] ?></strong></td>
                                </tr>
                    <?php endforeach; ?>
                            </tbody>
                        </table>
                    
                    <?php if ($totalPages > 1): ?>
                        <div class="text-center mt-20">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?= $i ?>&period=<?= h($period) ?>" 
                                   class="btn btn-sm <?= $i === $page ? '' : 'btn-secondary' ?>"
                                   style="margin: 0 2px;">
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
