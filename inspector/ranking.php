<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';

requireLogin();

$currentMonth = getCurrentMonthPeriod();
$maxScore = getWeeklyMaxScore();
$currentWeekday = getCurrentWeekdayCn();
$currentDisplayTime = getCurrentDisplayDatetime();

$weeklyTop10 = db()->fetchAll(
    "SELECT dormitory_no, score
     FROM dormitories
     WHERE status = 1
     ORDER BY score DESC, dormitory_no ASC
     LIMIT 10"
);

$monthOrderField = hasDormitoryMonthlyScoreColumn() ? 'monthly_score' : 'score';
$monthlyTop10 = db()->fetchAll(
    "SELECT *
     FROM dormitories
     WHERE status = 1
     ORDER BY {$monthOrderField} DESC, dormitory_no ASC
     LIMIT 10"
);

renderHeader('排行榜');
?>

<div class="container" style="padding-top: 20px;">
    <div style="display: flex;">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="index.php">打分上报</a></li>
                <li><a href="records.php">打分记录</a></li>
                <li><a href="ranking.php" class="active">排行榜</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header flex flex-between">
                <h2>排行榜</h2>
                <span style="color: #666;">总分上限：<?= h($maxScore) ?> 分</span>
            </div>
            <p style="color: #666; margin: 0 0 16px;">时间表：<span data-live-clock="datetime"><?= h($currentDisplayTime) ?></span></p>

            <div style="display: flex; gap: 20px;">
                <div class="card" style="flex: 1;">
                    <h3 style="margin-bottom: 14px; color: #1d4ed8;">周榜（<span data-live-clock="weekday"><?= h($currentWeekday) ?></span>）前 10 名</h3>
                    <?php if (empty($weeklyTop10)): ?>
                        <p style="color: #999; text-align: center; padding: 20px 0;">暂无数据</p>
                    <?php else: ?>
                        <?php foreach ($weeklyTop10 as $index => $dorm): ?>
                            <div class="ranking-item">
                                <div class="ranking-num <?= $index < 3 ? 'top-' . ($index + 1) : 'normal' ?>">
                                    <?= $index + 1 ?>
                                </div>
                                <div class="ranking-info">
                                    <strong><?= h($dorm['dormitory_no']) ?></strong>
                                </div>
                                <div class="ranking-score"><?= h($dorm['score']) ?> 分</div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="card" style="flex: 1;">
                    <h3 style="margin-bottom: 14px; color: #1d4ed8;">月榜（<?= h($currentMonth) ?>）前 10 名</h3>
                    <?php if (empty($monthlyTop10)): ?>
                        <p style="color: #999; text-align: center; padding: 20px 0;">暂无数据</p>
                    <?php else: ?>
                        <?php foreach ($monthlyTop10 as $index => $dorm): ?>
                            <div class="ranking-item">
                                <div class="ranking-num <?= $index < 3 ? 'top-' . ($index + 1) : 'normal' ?>">
                                    <?= $index + 1 ?>
                                </div>
                                <div class="ranking-info">
                                    <strong><?= h($dorm['dormitory_no']) ?></strong>
                                </div>
                                <div class="ranking-score"><?= h(getMonthlyScore($dorm)) ?> 分</div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
