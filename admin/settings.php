<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';

requireAdmin();

$message = pullFlashMessage('message');
$error = pullFlashMessage('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();

    $action = $_POST['action'] ?? '';
    if ($action === 'update_settings') {
        $dailyLimit = max(1, intval($_POST['daily_limit'] ?? 3));
        $weeklyMaxScore = max(1, floatval($_POST['weekly_max_score'] ?? DEFAULT_SCORE));
        $scoreOptionsRaw = trim($_POST['score_option_values'] ?? '');
        $scoreOptions = parseScoreOptionValues($scoreOptionsRaw);

        if (empty($scoreOptions)) {
            $error = '请至少配置一个有效的扣分选项（例如：0.5,1,1.5,2）';
        } else {
            updateSetting('weekly_max_score', $weeklyMaxScore);
            updateSetting('monthly_max_score', $weeklyMaxScore);
            updateSetting('fixed_score', 0);
            updateSetting('score_option_values', serializeScoreOptionValues($scoreOptions));
            updateSetting('daily_limit', $dailyLimit);

            $message = '设置更新成功';
            Logger::info("更新系统设置: 周总分上限={$weeklyMaxScore}, 扣分选项=" . implode('/', $scoreOptions) . ", 每日限制={$dailyLimit}");
            auditLog('update', 'settings', null, [
                'weekly_max_score' => $weeklyMaxScore,
                'score_option_values' => $scoreOptions,
                'daily_limit' => $dailyLimit
            ]);
        }
    }

    if ($message && !$error) {
        setFlashMessage('message', $message);
        redirect($_SERVER['REQUEST_URI']);
    }
}

$weeklyMaxScore = getWeeklyMaxScore();
$scoreOptionsText = serializeScoreOptionValues(getScoreOptionValues());
$dailyLimit = getDailyLimit();

renderHeader('打分设置');
?>

<div class="container" style="padding-top: 20px;">
    <div style="display: flex;">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="index.php">仪表盘</a></li>
                <li><a href="dormitories.php">宿舍管理</a></li>
                <li><a href="users.php">账号管理</a></li>
                <li><a href="settings.php" class="active">打分设置</a></li>
                <li><a href="records.php">打分记录</a></li>
                <li><a href="reset.php">分数重置</a></li>
                <li><a href="history.php">历史记录</a></li>
                <li><a href="export.php">数据导出</a></li>
                <li><a href="system.php">系统设置</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h2>打分设置</h2>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= h($message) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= h($error) ?></div>
            <?php endif; ?>

            <div class="card">
                <h3 style="margin-bottom: 20px;">基础设置</h3>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_settings">

                    <div class="form-group">
                        <label>每周总分上限</label>
                        <input type="number" name="weekly_max_score" step="0.5" min="1" value="<?= h($weeklyMaxScore) ?>">
                        <small style="display: block; margin-top: 5px;">
                            每周每个宿舍最高总分。新建宿舍、分数重置和后续加分都遵循该上限。
                        </small>
                    </div>

                    <div class="form-group">
                        <label>扣分可选分值</label>
                        <input type="text" name="score_option_values" value="<?= h($scoreOptionsText) ?>" placeholder="例如：0.5,1,1.5,2">
                        <small style="display: block; margin-top: 5px;">
                            仅用于用户端“减分”场景，多个分值用英文逗号分隔；“加分”固定为 0 分。
                        </small>
                    </div>

                    <div class="form-group">
                        <label>每日打分次数限制</label>
                        <input type="number" name="daily_limit" min="1" value="<?= h($dailyLimit) ?>">
                        <small style="display: block; margin-top: 5px;">
                            每个宿舍每天最多可被打分的次数。
                        </small>
                    </div>

                    <button type="submit" class="btn">保存设置</button>
                </form>
            </div>

            <div class="card">
                <h3 style="margin-bottom: 14px;">现场图片说明</h3>
                <ul style="color: #475569; line-height: 1.9; padding-left: 20px;">
                    <li>管理员后台已取消“原因选项”配置。</li>
                    <li>用户端上报以现场图片作为依据，每次需上传 4 到 10 张。</li>
                    <li>管理员可在打分记录中直接查看对应现场图片。</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
