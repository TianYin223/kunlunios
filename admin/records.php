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
    
    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $scoreType = $_POST['score_type'] ?? '';
        $score = floatval($_POST['score'] ?? 0);
        
        $validator = new Validator($_POST);
        $validator->in('score_type', ['add', 'subtract'], '类型无效');
        
        if ($validator->fails()) {
            $error = $validator->firstError();
        } else {
            if ($scoreType === 'add') {
                $score = 0;
            } elseif ($score <= 0) {
                $error = '分数无效';
            }

            if (!$error) {
                $record = db()->fetch("SELECT * FROM score_records WHERE id = ?", [$id]);
                if (!$record) {
                    $error = '记录不存在';
                } else {
                    $oldScore = $record['score_type'] === 'add' ? $record['score'] : -$record['score'];
                    $newScore = $scoreType === 'add' ? $score : -$score;
                    
                    $dormitory = db()->fetch(
                        "SELECT * FROM dormitories WHERE id = ?",
                        [$record['dormitory_id']]
                    );
                    
                    if ($dormitory) {
                        $currentWeeklyScore = getWeeklyScore($dormitory);
                        $currentMonthlyScore = getMonthlyScore($dormitory);
                        $baseWeeklyScore = $currentWeeklyScore - $oldScore;
                        $baseMonthlyScore = $currentMonthlyScore - $oldScore;
                        $updatedWeeklyScore = $baseWeeklyScore + $newScore;
                        $updatedMonthlyScore = $baseMonthlyScore + $newScore;

                        if ($updatedWeeklyScore < 0 || $updatedMonthlyScore < 0) {
                            $error = '修改后宿舍总分将低于0分，请调整分数';
                        } elseif ($updatedWeeklyScore > $weeklyMaxScore || $updatedMonthlyScore > $weeklyMaxScore) {
                            $error = "修改后宿舍总分将超过上限（{$weeklyMaxScore}分），请调整分数";
                        } else {
                            db()->beginTransaction();
                            db()->update('score_records', [
                                'score_type' => $scoreType,
                                'score' => $score
                            ], 'id = ?', [$id]);
                            setDormitoryScores($dormitory['id'], $updatedWeeklyScore, $updatedMonthlyScore);
                            db()->commit();
                            $message = '修改成功';
                        }
                    }
                }
            } else {
                $error = '分数无效';
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $record = db()->fetch("SELECT * FROM score_records WHERE id = ?", [$id]);
        
        if ($record) {
            $oldScore = $record['score_type'] === 'add' ? $record['score'] : -$record['score'];
            $dormitory = db()->fetch(
                "SELECT * FROM dormitories WHERE id = ?",
                [$record['dormitory_id']]
            );
            
            if ($dormitory) {
                $updatedWeeklyScore = normalizeWeeklyScore(getWeeklyScore($dormitory) - $oldScore);
                $updatedMonthlyScore = normalizeMonthlyScore(getMonthlyScore($dormitory) - $oldScore);
                
                db()->beginTransaction();
                db()->delete('score_records', 'id = ?', [$id]);
                setDormitoryScores($dormitory['id'], $updatedWeeklyScore, $updatedMonthlyScore);
                db()->commit();
                $message = '删除成功';
            }
        }
    }

    if ($message && !$error) {
        setFlashMessage('message', $message);
        redirect($_SERVER['REQUEST_URI']);
    }
}

$page = max(1, intval($_GET['page'] ?? 1));
$pageSize = 20;
$offset = ($page - 1) * $pageSize;

$where = "WHERE 1=1";
$params = [];

$search = trim($_GET['search'] ?? '');
$dormitoryNo = trim($_GET['dormitory'] ?? '');
$period = trim($_GET['period'] ?? ($_GET['month'] ?? ''));
$scoreType = trim($_GET['type'] ?? '');

if ($search) {
    $where .= " AND (dormitory_no LIKE ? OR inspector_name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($dormitoryNo) {
    $where .= " AND dormitory_no = ?";
    $params[] = $dormitoryNo;
}

if ($period) {
    $where .= " AND month_year = ?";
    $params[] = $period;
}

if ($scoreType) {
    $where .= " AND score_type = ?";
    $params[] = $scoreType;
}

$total = db()->fetch(
    "SELECT COUNT(*) as count FROM score_records {$where}",
    $params
)['count'];

$records = db()->fetchAll(
    "SELECT * FROM score_records {$where} ORDER BY created_at DESC LIMIT {$offset}, {$pageSize}",
    $params
);

$totalPages = ceil($total / $pageSize);

$dormitories = db()->fetchAll(
    "SELECT DISTINCT dormitory_no FROM dormitories ORDER BY dormitory_no"
);

$periods = db()->fetchAll(
    "SELECT DISTINCT month_year FROM score_records ORDER BY month_year DESC"
);

renderHeader('打分记录');
?>

<div class="container" style="padding-top: 20px;">
    <div style="display: flex;">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="index.php">仪表盘</a></li>
                <li><a href="dormitories.php">宿舍管理</a></li>
                <li><a href="users.php">账号管理</a></li>
                <li><a href="settings.php">打分设置</a></li>
                <li><a href="records.php" class="active">打分记录</a></li>
                <li><a href="reset.php">分数重置</a></li>
                <li><a href="history.php">历史记录</a></li>
                <li><a href="export.php">数据导出</a></li>
                <li><a href="system.php">系统设置</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="page-header flex flex-between">
                <h2>打分记录</h2>
                <span style="color: #666;">共 <?= $total ?> 条记录</span>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?= h($message) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= h($error) ?></div>
            <?php endif; ?>
            
            <div class="card">
                <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
                    <input type="text" name="search" placeholder="搜索宿舍/检查人" 
                           value="<?= h($search) ?>" style="padding: 8px; width: 180px;">
                    <select name="dormitory" style="padding: 8px;">
                        <option value="">全部宿舍</option>
                        <?php foreach ($dormitories as $d): ?>
                            <option value="<?= h($d['dormitory_no']) ?>" 
                                    <?= $dormitoryNo === $d['dormitory_no'] ? 'selected' : '' ?>>
                                <?= h($d['dormitory_no']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="period" style="padding: 8px;">
                        <option value="">全部星期</option>
                        <?php foreach ($periods as $m): ?>
                            <option value="<?= h($m['month_year']) ?>" 
                                    <?= $period === $m['month_year'] ? 'selected' : '' ?>>
                                <?= h(formatPeriodDisplay($m['month_year'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="type" style="padding: 8px;">
                        <option value="">全部类型</option>
                        <option value="add" <?= $scoreType === 'add' ? 'selected' : '' ?>>加分</option>
                        <option value="subtract" <?= $scoreType === 'subtract' ? 'selected' : '' ?>>减分</option>
                    </select>
                    <button type="submit" class="btn btn-sm">筛选</button>
                    <?php if ($search || $dormitoryNo || $period || $scoreType): ?>
                        <a href="records.php" class="btn btn-sm btn-secondary">清除</a>
                    <?php endif; ?>
                </form>
                
                <?php if (empty($records)): ?>
                    <p style="color: #999; text-align: center; padding: 40px 0;">暂无记录</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>宿舍号</th>
                                <th>检查人</th>
                                <th>类型</th>
                                <th>分数</th>
                                <th>图片</th>
                                <th>星期</th>
                                <th>时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
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
                                    <td>
                                        <?php if (empty($images)): ?>
                                            <span style="color: #999;">无图片</span>
                                        <?php else: ?>
                                            <div style="display: flex; gap: 6px; flex-wrap: wrap; max-width: 220px; align-items: center;">
                                                <?php foreach (array_slice($images, 0, 3) as $img): ?>
                                                    <a href="../<?= h($img) ?>" target="_blank">
                                                        <img src="../<?= h($img) ?>" alt="现场图"
                                                             style="width: 44px; height: 44px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;">
                                                    </a>
                                                <?php endforeach; ?>
                                                <button type="button" class="btn btn-sm btn-secondary"
                                                        onclick='showImageModal(<?= json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'>
                                                    查看(<?= count($images) ?>)
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= h(formatPeriodDisplay($record['month_year'], strtotime($record['created_at']))) ?></td>
                                    <td><?= formatDate($record['created_at']) ?></td>
                                    <td>
                                        <button class="btn btn-sm" onclick="showEditModal(
                                            <?= $record['id'] ?>,
                                            '<?= $record['score_type'] ?>',
                                            <?= $record['score'] ?>
                                        )">编辑</button>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('确定删除？删除后分数会回退')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $record['id'] ?>">
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
                                <a href="?page=<?= $i ?>&search=<?= h($search) ?>&dormitory=<?= h($dormitoryNo) ?>&period=<?= h($period) ?>&type=<?= h($scoreType) ?>" 
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

<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; 
     background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 8px; width: 400px;">
        <h3 style="margin-bottom: 20px;">编辑打分记录</h3>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label>类型</label>
                <select name="score_type" id="edit_score_type">
                    <option value="add">加分</option>
                    <option value="subtract">减分</option>
                </select>
            </div>
            <div class="form-group">
                <label>分数</label>
                <input type="number" name="score" id="edit_score" step="0.5" min="0" required>
                <small id="edit_score_hint" style="display:none; color:#64748b;">加分固定为 0 分。</small>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="hideEditModal()">取消</button>
                <button type="submit" class="btn">确定</button>
            </div>
        </form>
    </div>
</div>

<div id="imageModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
     background: rgba(0,0,0,0.7); z-index: 1001; align-items: center; justify-content: center;">
    <div style="background: white; padding: 20px; border-radius: 8px; width: 90%; max-width: 900px; max-height: 85vh; overflow-y: auto;">
        <div class="flex flex-between" style="margin-bottom: 15px;">
            <h3>现场图片</h3>
            <button type="button" class="btn btn-secondary" onclick="hideImageModal()">关闭</button>
        </div>
        <div id="imageModalGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px;"></div>
    </div>
</div>

<script>
function showEditModal(id, type, score) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_score_type').value = type;
    document.getElementById('edit_score').value = score;
    syncEditScoreInput();
    document.getElementById('editModal').style.display = 'flex';
}

function hideEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function showImageModal(images) {
    const grid = document.getElementById('imageModalGrid');
    grid.innerHTML = '';

    if (!Array.isArray(images) || images.length === 0) {
        grid.innerHTML = '<div style="color:#999;">无图片</div>';
    } else {
        images.forEach(path => {
            const wrapper = document.createElement('a');
            wrapper.href = '../' + path;
            wrapper.target = '_blank';
            wrapper.style.textDecoration = 'none';

            const img = document.createElement('img');
            img.src = '../' + path;
            img.alt = '现场图';
            img.style.width = '100%';
            img.style.height = '160px';
            img.style.objectFit = 'cover';
            img.style.borderRadius = '6px';
            img.style.border = '1px solid #ddd';

            wrapper.appendChild(img);
            grid.appendChild(wrapper);
        });
    }

    document.getElementById('imageModal').style.display = 'flex';
}

function hideImageModal() {
    document.getElementById('imageModal').style.display = 'none';
}

function syncEditScoreInput() {
    const typeEl = document.getElementById('edit_score_type');
    const scoreEl = document.getElementById('edit_score');
    const hintEl = document.getElementById('edit_score_hint');
    if (!typeEl || !scoreEl) {
        return;
    }

    const isAdd = typeEl.value === 'add';
    scoreEl.readOnly = isAdd;
    scoreEl.min = isAdd ? '0' : '0.5';
    if (isAdd) {
        scoreEl.value = '0';
    } else if (parseFloat(scoreEl.value || '0') <= 0) {
        scoreEl.value = '0.5';
    }
    if (hintEl) {
        hintEl.style.display = isAdd ? '' : 'none';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const typeEl = document.getElementById('edit_score_type');
    if (typeEl) {
        typeEl.addEventListener('change', syncEditScoreInput);
    }
});
</script>

<?php renderFooter(); ?>
