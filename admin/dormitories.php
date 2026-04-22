<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';

requireAdmin();

$message = pullFlashMessage('message');
$error = pullFlashMessage('error');
$weeklyMaxScore = getWeeklyMaxScore();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_max_score') {
        $newMaxScore = max(1, floatval($_POST['weekly_max_score'] ?? DEFAULT_SCORE));
        updateSetting('weekly_max_score', $newMaxScore);
        updateSetting('monthly_max_score', $newMaxScore);
        $weeklyMaxScore = $newMaxScore;
        $message = '每周总分上限更新成功';
        Logger::info("更新每周总分上限: {$newMaxScore}");
        auditLog('update', 'settings', null, ['weekly_max_score' => $newMaxScore]);
    } elseif ($action === 'add') {
        $dormitoryNo = trim($_POST['dormitory_no'] ?? '');
        
        $validator = new Validator($_POST);
        $validator->required('dormitory_no', '请输入宿舍号')
                  ->unique('dormitory_no', 'dormitories', 'dormitory_no', null, '宿舍号已存在');
        
        if ($validator->fails()) {
            $error = $validator->firstError();
        } else {
            $insertData = [
                'dormitory_no' => $dormitoryNo,
                'score' => $weeklyMaxScore
            ];
            if (hasDormitoryMonthlyScoreColumn()) {
                $insertData['monthly_score'] = $weeklyMaxScore;
            }
            db()->insert('dormitories', $insertData);
            $message = '添加成功';
            Logger::info("添加宿舍: {$dormitoryNo}");
            auditLog('create', 'dormitory', db()->lastInsertId(), ['dormitory_no' => $dormitoryNo]);
        }
    } elseif ($action === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        $status = intval($_POST['status'] ?? 0);
        db()->update('dormitories', ['status' => $status], 'id = ?', [$id]);
        $message = '状态更新成功';
        Logger::info("切换宿舍状态: ID {$id} -> " . ($status ? '启用' : '禁用'));
        auditLog('toggle', 'dormitory', $id, ['status' => $status]);
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $dorm = db()->fetch("SELECT dormitory_no FROM dormitories WHERE id = ?", [$id]);
        db()->delete('dormitories', 'id = ?', [$id]);
        $message = '删除成功';
        Logger::info("删除宿舍: {$dorm['dormitory_no']}");
        auditLog('delete', 'dormitory', $id, ['dormitory_no' => $dorm['dormitory_no']]);
    } elseif ($action === 'batch_add') {
        $dormitories = trim($_POST['dormitories'] ?? '');
        $lines = array_filter(array_map('trim', explode("\n", $dormitories)));
        
        $count = 0;
        foreach ($lines as $line) {
            if (!empty($line)) {
                $exists = db()->fetch(
                    "SELECT id FROM dormitories WHERE dormitory_no = ?",
                    [$line]
                );
                if (!$exists) {
                    $insertData = [
                        'dormitory_no' => $line,
                        'score' => $weeklyMaxScore
                    ];
                    if (hasDormitoryMonthlyScoreColumn()) {
                        $insertData['monthly_score'] = $weeklyMaxScore;
                    }
                    db()->insert('dormitories', $insertData);
                    $count++;
                }
            }
        }
        $message = "批量添加成功，共添加 {$count} 个宿舍";
    }

    if ($message && !$error) {
        setFlashMessage('message', $message);
        redirect($_SERVER['REQUEST_URI']);
    }
}

$page = max(1, intval($_GET['page'] ?? 1));
$pageSize = 20;
$offset = ($page - 1) * $pageSize;

$search = trim($_GET['search'] ?? '');
$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND dormitory_no LIKE ?";
    $params[] = "%{$search}%";
}

$total = db()->fetch(
    "SELECT COUNT(*) as count FROM dormitories {$where}",
    $params
)['count'];

$dormitories = db()->fetchAll(
    "SELECT * FROM dormitories {$where} ORDER BY dormitory_no ASC LIMIT {$offset}, {$pageSize}",
    $params
);

$totalPages = ceil($total / $pageSize);

renderHeader('宿舍管理');
?>

<div class="container" style="padding-top: 20px;">
    <div style="display: flex;">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="index.php">仪表盘</a></li>
                <li><a href="dormitories.php" class="active">宿舍管理</a></li>
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
            <div class="page-header flex flex-between">
                <h2>宿舍管理</h2>
                <button class="btn" onclick="showAddModal()">添加宿舍</button>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?= h($message) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= h($error) ?></div>
            <?php endif; ?>

            <div class="card">
                <h3 style="margin-bottom: 15px;">每周总分上限设置</h3>
                <form method="POST" style="display: flex; gap: 10px; align-items: end; flex-wrap: wrap;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_max_score">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>当前上限（分）</label>
                        <input type="number" name="weekly_max_score" step="0.5" min="1" value="<?= $weeklyMaxScore ?>" style="width: 180px;">
                    </div>
                    <button type="submit" class="btn">保存上限</button>
                </form>
                <small style="color: #666; display: block; margin-top: 8px;">
                    新增宿舍、分数重置和后续加分都会遵循该上限
                </small>
            </div>
            
            <div class="card">
                <div class="flex flex-between mb-20">
                    <form method="GET" style="display: flex; gap: 10px;">
                        <input type="text" name="search" placeholder="搜索宿舍号" 
                               value="<?= h($search) ?>" style="padding: 8px; width: 200px;">
                        <button type="submit" class="btn btn-sm">搜索</button>
                        <?php if ($search): ?>
                            <a href="?" class="btn btn-sm btn-secondary">清除</a>
                        <?php endif; ?>
                    </form>
                    <button class="btn btn-sm btn-success" onclick="showBatchModal()">批量添加</button>
                </div>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>宿舍号</th>
                            <th>周分数</th>
                            <th>月分数</th>
                            <th>状态</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dormitories as $dorm): ?>
                            <tr>
                                <td><?= h($dorm['dormitory_no']) ?></td>
                                <td><strong><?= $dorm['score'] ?></strong></td>
                                <td><strong><?= h($dorm['monthly_score'] ?? $dorm['score']) ?></strong></td>
                                <td>
                                    <?php if ($dorm['status']): ?>
                                        <span class="badge badge-success">启用</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">禁用</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDate($dorm['created_at']) ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= $dorm['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $dorm['status'] ? 0 : 1 ?>">
                                        <button type="submit" class="btn btn-sm <?= $dorm['status'] ? 'btn-secondary' : 'btn-success' ?>">
                                            <?= $dorm['status'] ? '禁用' : '启用' ?>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('确定删除？删除后无法恢复')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $dorm['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($totalPages > 1): ?>
                    <div class="text-center mt-20">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= h($search) ?>" 
                               class="btn btn-sm <?= $i === $page ? '' : 'btn-secondary' ?>"
                               style="margin: 0 2px;">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="addModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; 
     background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 8px; width: 400px;">
        <h3 style="margin-bottom: 20px;">添加宿舍</h3>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>宿舍号</label>
                <input type="text" name="dormitory_no" required placeholder="请输入宿舍号">
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="hideAddModal()">取消</button>
                <button type="submit" class="btn">确定</button>
            </div>
        </form>
    </div>
</div>

<div id="batchModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; 
     background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 8px; width: 500px;">
        <h3 style="margin-bottom: 20px;">批量添加宿舍</h3>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="batch_add">
            <div class="form-group">
                <label>宿舍号列表（每行一个）</label>
                <textarea name="dormitories" rows="10" placeholder="101&#10;102&#10;103" required></textarea>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="hideBatchModal()">取消</button>
                <button type="submit" class="btn">确定</button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddModal() {
    document.getElementById('addModal').style.display = 'flex';
}

function hideAddModal() {
    document.getElementById('addModal').style.display = 'none';
}

function showBatchModal() {
    document.getElementById('batchModal').style.display = 'flex';
}

function hideBatchModal() {
    document.getElementById('batchModal').style.display = 'none';
}
</script>

<?php renderFooter(); ?>
