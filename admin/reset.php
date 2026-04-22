<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';

requireAdmin();

$message = pullFlashMessage('message');
$error = pullFlashMessage('error');
$currentWeek = getCurrentWeekPeriod();
$currentMonth = getCurrentMonthPeriod();
$maxScore = getWeeklyMaxScore();
$currentWeekday = getCurrentWeekdayCn();
$currentDisplayTime = getCurrentDisplayDatetime();

function buildRankingMap(array $dormitories, string $scoreField): array {
    $rankingMap = [];
    $position = 0;
    $currentRank = 0;
    $lastScore = null;

    foreach ($dormitories as $dormitory) {
        $position++;
        $score = $scoreField === 'monthly_score'
            ? getMonthlyScore($dormitory)
            : floatval($dormitory[$scoreField] ?? 0);

        if ($lastScore === null || $score !== $lastScore) {
            $currentRank = $position;
            $lastScore = $score;
        }

        $rankingMap[$dormitory['id']] = $currentRank;
    }

    return $rankingMap;
}

function filterDormitoriesByIds(array $allDormitories, array $selectedIds): array {
    $selectedMap = [];
    foreach ($selectedIds as $selectedId) {
        $id = intval($selectedId);
        if ($id > 0) {
            $selectedMap[$id] = true;
        }
    }

    if (empty($selectedMap)) {
        return [];
    }

    $result = [];
    foreach ($allDormitories as $dormitory) {
        if (isset($selectedMap[$dormitory['id']])) {
            $result[] = $dormitory;
        }
    }

    return $result;
}

function saveHistoryRanking($period, $dormitoryId, $dormitoryNo, $finalScore, $ranking): void {
    db()->query(
        "INSERT INTO history_rankings (month_year, dormitory_id, dormitory_no, final_score, ranking)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            dormitory_no = VALUES(dormitory_no),
            final_score = VALUES(final_score),
            ranking = VALUES(ranking),
            created_at = CURRENT_TIMESTAMP",
        [$period, $dormitoryId, $dormitoryNo, $finalScore, $ranking]
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['week_reset_all', 'week_reset_selected'], true)) {
        $newWeek = trim($_POST['new_week'] ?? '');
        if (!isValidWeekKey($newWeek)) {
            $error = '请输入正确的新周次';
        } else {
            $allDormitories = db()->fetchAll(
                "SELECT *
                 FROM dormitories
                 WHERE status = 1
                 ORDER BY score DESC, dormitory_no ASC"
            );

            if (empty($allDormitories)) {
                $error = '没有可重置的宿舍';
            } else {
                $targetDormitories = $action === 'week_reset_all'
                    ? $allDormitories
                    : filterDormitoriesByIds($allDormitories, $_POST['week_dormitory_ids'] ?? []);

                if ($action === 'week_reset_selected' && empty($targetDormitories)) {
                    $error = '请先勾选要重置周榜的宿舍';
                }

                if (!$error) {
                    try {
                        db()->beginTransaction();
                        $rankingMap = buildRankingMap($allDormitories, 'score');

                        foreach ($targetDormitories as $dormitory) {
                            saveHistoryRanking(
                                $currentWeek,
                                intval($dormitory['id']),
                                $dormitory['dormitory_no'],
                                floatval($dormitory['score']),
                                intval($rankingMap[$dormitory['id']] ?? 0)
                            );
                            db()->update('dormitories', ['score' => $maxScore], 'id = ?', [$dormitory['id']]);
                        }

                        setCurrentWeekPeriod($newWeek);
                        db()->commit();

                        $currentWeek = $newWeek;
                        $count = count($targetDormitories);
                        $message = $action === 'week_reset_all'
                            ? "周榜已重置全部宿舍（{$count} 个），分数恢复为 {$maxScore} 分"
                            : "周榜已重置选中宿舍（{$count} 个），分数恢复为 {$maxScore} 分";
                    } catch (Exception $e) {
                        db()->rollBack();
                        $error = '周榜重置失败：' . $e->getMessage();
                    }
                }
            }
        }
    } elseif (in_array($action, ['month_reset_all', 'month_reset_selected'], true)) {
        if (!hasDormitoryMonthlyScoreColumn()) {
            $error = '请先执行升级 SQL 脚本（upgrade_dual_rank_reset.sql）后再使用月榜重置';
        }

        $newMonth = trim($_POST['new_month'] ?? '');
        if (!$error && !isValidMonthKey($newMonth)) {
            $error = '请输入正确的新月份';
        } elseif (!$error) {
            $monthOrderField = hasDormitoryMonthlyScoreColumn() ? 'monthly_score' : 'score';
            $allDormitories = db()->fetchAll(
                "SELECT *
                 FROM dormitories
                 WHERE status = 1
                 ORDER BY {$monthOrderField} DESC, dormitory_no ASC"
            );

            if (empty($allDormitories)) {
                $error = '没有可重置的宿舍';
            } else {
                $targetDormitories = $action === 'month_reset_all'
                    ? $allDormitories
                    : filterDormitoriesByIds($allDormitories, $_POST['month_dormitory_ids'] ?? []);

                if ($action === 'month_reset_selected' && empty($targetDormitories)) {
                    $error = '请先勾选要重置月榜的宿舍';
                }

                if (!$error) {
                    try {
                        db()->beginTransaction();
                        $rankingMap = buildRankingMap($allDormitories, 'monthly_score');

                        foreach ($targetDormitories as $dormitory) {
                            $monthlyScore = getMonthlyScore($dormitory);
                            saveHistoryRanking(
                                $currentMonth,
                                intval($dormitory['id']),
                                $dormitory['dormitory_no'],
                                $monthlyScore,
                                intval($rankingMap[$dormitory['id']] ?? 0)
                            );
                            setDormitoryScores($dormitory['id'], getWeeklyScore($dormitory), $maxScore);
                        }

                        setCurrentMonthPeriod($newMonth);
                        db()->commit();

                        $currentMonth = $newMonth;
                        $count = count($targetDormitories);
                        $message = $action === 'month_reset_all'
                            ? "月榜已重置全部宿舍（{$count} 个），分数恢复为 {$maxScore} 分"
                            : "月榜已重置选中宿舍（{$count} 个），分数恢复为 {$maxScore} 分";
                    } catch (Exception $e) {
                        db()->rollBack();
                        $error = '月榜重置失败：' . $e->getMessage();
                    }
                }
            }
        }
    } else {
        $error = '无效的操作类型';
    }

    if ($message && !$error) {
        setFlashMessage('message', $message);
        redirect($_SERVER['REQUEST_URI']);
    }
}

$weeklyTop10 = db()->fetchAll(
    "SELECT *
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

renderHeader('分数重置');
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
                <li><a href="reset.php" class="active">分数重置</a></li>
                <li><a href="history.php">历史记录</a></li>
                <li><a href="export.php">数据导出</a></li>
                <li><a href="system.php">系统设置</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h2>分数重置</h2>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= h($message) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= h($error) ?></div>
            <?php endif; ?>

            <div class="card">
                <h3 style="margin-bottom: 15px;">当前排行榜预览（前 10）</h3>
                <p style="color: #666; margin-bottom: 15px;">
                    左侧周榜（<span data-live-clock="weekday"><?= h($currentWeekday) ?></span>），右侧月榜（<?= h($currentMonth) ?>），重置后会回到 <?= h($maxScore) ?> 分。
                </p>
                <p style="color: #666; margin: -6px 0 15px;">时间表：<span data-live-clock="datetime"><?= h($currentDisplayTime) ?></span></p>

                <div style="display: flex; gap: 20px;">
                    <div style="flex: 1;">
                        <h4 style="color: #1d4ed8; margin-bottom: 10px;">周榜前 10 名</h4>
                        <?php if (empty($weeklyTop10)): ?>
                            <p style="color: #999;">暂无数据</p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                <tr>
                                    <th><input type="checkbox" id="selectAllWeek" onchange="toggleSelect('week')"></th>
                                    <th>排名</th>
                                    <th>宿舍号</th>
                                    <th>周分数</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($weeklyTop10 as $index => $dormitory): ?>
                                    <tr>
                                        <td><input type="checkbox" class="week-checkbox" name="week_dormitory_ids[]" form="weekResetForm" value="<?= $dormitory['id'] ?>"></td>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= h($dormitory['dormitory_no']) ?></td>
                                        <td><?= h($dormitory['score']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <div style="flex: 1;">
                        <h4 style="color: #1d4ed8; margin-bottom: 10px;">月榜前 10 名</h4>
                        <?php if (empty($monthlyTop10)): ?>
                            <p style="color: #999;">暂无数据</p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                <tr>
                                    <th><input type="checkbox" id="selectAllMonth" onchange="toggleSelect('month')"></th>
                                    <th>排名</th>
                                    <th>宿舍号</th>
                                    <th>月分数</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($monthlyTop10 as $index => $dormitory): ?>
                                    <tr>
                                        <td><input type="checkbox" class="month-checkbox" name="month_dormitory_ids[]" form="monthResetForm" value="<?= $dormitory['id'] ?>"></td>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= h($dormitory['dormitory_no']) ?></td>
                                        <td><?= h(getMonthlyScore($dormitory)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 20px;">
                <div class="card" style="flex: 1;">
                    <h3 style="margin-bottom: 15px;">周榜重置</h3>
                    <form method="POST" id="weekResetForm">
                        <?= csrfField() ?>
                        <div class="form-group">
                            <label for="new_week">新周次</label>
                            <input type="week" id="new_week" name="new_week" required value="<?= h(getCurrentWeekKey()) ?>">
                            <small>重置周榜时会保存当前周榜历史并切换到新周次。</small>
                        </div>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <button type="button" class="btn btn-secondary" onclick="selectAllScope('week')">全选周榜</button>
                            <button type="button" class="btn btn-secondary" onclick="deselectAllScope('week')">取消全选</button>
                            <button type="submit" class="btn btn-danger" name="action" value="week_reset_selected" onclick="return confirm('确定重置周榜已勾选宿舍吗？')">重置周榜选中</button>
                            <button type="submit" class="btn btn-danger" name="action" value="week_reset_all" onclick="return confirm('确定重置周榜全部宿舍吗？')">重置周榜全部</button>
                        </div>
                    </form>
                </div>

                <div class="card" style="flex: 1;">
                    <h3 style="margin-bottom: 15px;">月榜重置</h3>
                    <form method="POST" id="monthResetForm">
                        <?= csrfField() ?>
                        <div class="form-group">
                            <label for="new_month">新月份</label>
                            <input type="month" id="new_month" name="new_month" required value="<?= h(getCurrentMonthKey()) ?>">
                            <small>重置月榜时会保存当前月榜历史并切换到新月份。</small>
                        </div>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <button type="button" class="btn btn-secondary" onclick="selectAllScope('month')">全选月榜</button>
                            <button type="button" class="btn btn-secondary" onclick="deselectAllScope('month')">取消全选</button>
                            <button type="submit" class="btn btn-danger" name="action" value="month_reset_selected" onclick="return confirm('确定重置月榜已勾选宿舍吗？')">重置月榜选中</button>
                            <button type="submit" class="btn btn-danger" name="action" value="month_reset_all" onclick="return confirm('确定重置月榜全部宿舍吗？')">重置月榜全部</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleSelect(type) {
    const masterId = type === 'week' ? 'selectAllWeek' : 'selectAllMonth';
    const className = type === 'week' ? '.week-checkbox' : '.month-checkbox';
    const master = document.getElementById(masterId);
    document.querySelectorAll(className).forEach(function (checkbox) {
        checkbox.checked = master.checked;
    });
}

function selectAllScope(type) {
    const className = type === 'week' ? '.week-checkbox' : '.month-checkbox';
    document.querySelectorAll(className).forEach(function (checkbox) {
        checkbox.checked = true;
    });
    const master = document.getElementById(type === 'week' ? 'selectAllWeek' : 'selectAllMonth');
    if (master) {
        master.checked = true;
    }
}

function deselectAllScope(type) {
    const className = type === 'week' ? '.week-checkbox' : '.month-checkbox';
    document.querySelectorAll(className).forEach(function (checkbox) {
        checkbox.checked = false;
    });
    const master = document.getElementById(type === 'week' ? 'selectAllWeek' : 'selectAllMonth');
    if (master) {
        master.checked = false;
    }
}
</script>

<?php renderFooter(); ?>
