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

    switch ($action) {
        case 'update_site_settings':
            $siteName = trim($_POST['site_name'] ?? '');
            if ($siteName === '') {
                $error = '网站名称不能为空';
                break;
            }
            if (strlen($siteName) > 60) {
                $error = '网站名称不能超过 60 个字符';
                break;
            }

            updateSetting('site_name', $siteName);
            $message = '网站名称已更新';
            Logger::info("更新网站名称: {$siteName}");
            auditLog('update', 'settings', null, ['site_name' => $siteName]);
            break;

        case 'backup':
            $result = backupDatabase();
            if ($result['success']) {
                $message = "数据库备份成功：{$result['filename']}";
                auditLog('backup', 'database', null, ['filename' => $result['filename']]);
            } else {
                $error = $result['message'];
            }
            break;

        case 'clean_logs':
            $days = max(1, intval($_POST['days'] ?? 30));
            $logDir = __DIR__ . '/../logs';
            $count = 0;

            if (is_dir($logDir)) {
                $files = glob($logDir . '/*.log');
                $cutoff = time() - ($days * 86400);

                foreach ($files as $file) {
                    if (is_file($file) && filemtime($file) < $cutoff) {
                        unlink($file);
                        $count++;
                    }
                }
            }

            $message = "已清理 {$count} 个过期日志文件";
            Logger::info("清理日志文件: {$count} 个");
            auditLog('clean', 'logs', null, ['count' => $count, 'days' => $days]);
            break;

        case 'optimize_db':
            try {
                $tables = ['users', 'dormitories', 'score_records', 'reason_options', 'settings', 'history_rankings', 'audit_logs'];
                foreach ($tables as $table) {
                    db()->query("OPTIMIZE TABLE `{$table}`");
                }
                $message = '数据库优化完成';
                Logger::info('数据库优化完成');
                auditLog('optimize', 'database', null, ['tables' => $tables]);
            } catch (Exception $e) {
                $error = '数据库优化失败：' . $e->getMessage();
                Logger::error('数据库优化失败: ' . $e->getMessage());
            }
            break;
    }

    if ($message && !$error) {
        setFlashMessage('message', $message);
        redirect($_SERVER['REQUEST_URI']);
    }
}

$siteName = getSiteName();

$systemInfo = [
    'php_version' => PHP_VERSION,
    'mysql_version' => db()->fetch("SELECT VERSION() AS version")['version'] ?? 'Unknown',
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'max_upload_size' => ini_get('upload_max_filesize'),
    'max_post_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'timezone' => date_default_timezone_get(),
];

try {
    $auditLogCount = db()->fetch("SELECT COUNT(*) AS count FROM audit_logs")['count'];
} catch (Exception $e) {
    $auditLogCount = 0;
}

$dbStats = [
    'users' => db()->fetch("SELECT COUNT(*) AS count FROM users")['count'],
    'dormitories' => db()->fetch("SELECT COUNT(*) AS count FROM dormitories")['count'],
    'score_records' => db()->fetch("SELECT COUNT(*) AS count FROM score_records")['count'],
    'audit_logs' => $auditLogCount,
];

$logDir = __DIR__ . '/../logs';
$logSize = 0;
if (is_dir($logDir)) {
    foreach (glob($logDir . '/*') as $file) {
        if (is_file($file)) {
            $logSize += filesize($file);
        }
    }
}

$backupDir = __DIR__ . '/../backups';
$backupSize = 0;
$backupCount = 0;
if (is_dir($backupDir)) {
    $files = glob($backupDir . '/*.sql');
    $backupCount = count($files);
    foreach ($files as $file) {
        if (is_file($file)) {
            $backupSize += filesize($file);
        }
    }
}

function formatBytes($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}

renderHeader('系统设置');
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
                <li><a href="history.php">历史记录</a></li>
                <li><a href="export.php">数据导出</a></li>
                <li><a href="system.php" class="active">系统设置</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h2>系统设置</h2>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= h($message) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= h($error) ?></div>
            <?php endif; ?>

            <div class="card">
                <h3 style="margin-bottom: 15px;">网站信息</h3>
                <form method="POST" style="max-width: 520px;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_site_settings">
                    <div class="form-group">
                        <label for="site_name">网站名称（网站昵称）</label>
                        <input type="text" id="site_name" name="site_name" maxlength="60" required value="<?= h($siteName) ?>">
                        <small style="display: block; margin-top: 6px;">将用于登录页、后台顶部、页面标题显示。</small>
                    </div>
                    <button type="submit" class="btn">保存网站名称</button>
                </form>
            </div>

            <div class="card">
                <h3 style="margin-bottom: 15px;">系统信息</h3>
                <table class="table">
                    <tr><td style="width: 220px; font-weight: 600;">PHP 版本</td><td><?= h($systemInfo['php_version']) ?></td></tr>
                    <tr><td style="font-weight: 600;">MySQL 版本</td><td><?= h($systemInfo['mysql_version']) ?></td></tr>
                    <tr><td style="font-weight: 600;">服务器软件</td><td><?= h($systemInfo['server_software']) ?></td></tr>
                    <tr><td style="font-weight: 600;">上传限制</td><td><?= h($systemInfo['max_upload_size']) ?></td></tr>
                    <tr><td style="font-weight: 600;">POST 限制</td><td><?= h($systemInfo['max_post_size']) ?></td></tr>
                    <tr><td style="font-weight: 600;">内存限制</td><td><?= h($systemInfo['memory_limit']) ?></td></tr>
                    <tr><td style="font-weight: 600;">时区</td><td><?= h($systemInfo['timezone']) ?></td></tr>
                </table>
            </div>

            <div class="card">
                <h3 style="margin-bottom: 15px;">数据库统计</h3>
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-value" style="color:#1d4ed8;"><?= $dbStats['users'] ?></div><div class="stat-label">用户数</div></div>
                    <div class="stat-card"><div class="stat-value" style="color:#0f9f6b;"><?= $dbStats['dormitories'] ?></div><div class="stat-label">宿舍数</div></div>
                    <div class="stat-card"><div class="stat-value" style="color:#eab308;"><?= $dbStats['score_records'] ?></div><div class="stat-label">打分记录</div></div>
                    <div class="stat-card"><div class="stat-value" style="color:#0891b2;"><?= $dbStats['audit_logs'] ?></div><div class="stat-label">审计日志</div></div>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-bottom: 15px;">存储占用</h3>
                <table class="table">
                    <tr><td style="width: 220px; font-weight: 600;">日志文件大小</td><td><?= formatBytes($logSize) ?></td></tr>
                    <tr><td style="font-weight: 600;">备份文件</td><td><?= $backupCount ?> 个，合计 <?= formatBytes($backupSize) ?></td></tr>
                </table>
            </div>

            <div class="card">
                <h3 style="margin-bottom: 15px;">维护操作</h3>

                <div style="margin-bottom: 20px;">
                    <h4 style="margin-bottom: 8px;">数据库备份</h4>
                    <p style="color: #64748b; margin-bottom: 10px;">将当前数据库备份到 `backups` 目录。</p>
                    <form method="POST" style="display: inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="backup">
                        <button type="submit" class="btn btn-success">立即备份</button>
                    </form>
                </div>

                <div style="margin-bottom: 20px;">
                    <h4 style="margin-bottom: 8px;">清理日志</h4>
                    <p style="color: #64748b; margin-bottom: 10px;">删除指定天数之前的日志文件。</p>
                    <form method="POST" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="clean_logs">
                        <input type="number" name="days" value="30" min="1" style="width: 120px;">
                        <span>天前</span>
                        <button type="submit" class="btn btn-secondary">清理日志</button>
                    </form>
                </div>

                <div>
                    <h4 style="margin-bottom: 8px;">优化数据库</h4>
                    <p style="color: #64748b; margin-bottom: 10px;">优化主要业务表，提升查询性能。</p>
                    <form method="POST" style="display: inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="optimize_db">
                        <button type="submit" class="btn">优化数据库</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
