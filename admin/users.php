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
    
    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $realName = trim($_POST['real_name'] ?? '');
        $role = $_POST['role'] ?? 'inspector';
        
        // 使用验证器
        $validator = new Validator($_POST);
        $validator->required('username', '请输入用户名')
                  ->minLength('username', 3, '用户名至少3个字符')
                  ->maxLength('username', 50, '用户名不能超过50个字符')
                  ->unique('username', 'users', 'username', null, '用户名已存在')
                  ->required('password', '请输入密码')
                  ->required('real_name', '请输入真实姓名')
                  ->minLength('real_name', 2, '真实姓名至少2个字符')
                  ->in('role', ['admin', 'inspector'], '角色无效');
        
        if ($validator->fails()) {
            $error = $validator->firstError();
        } else {
            // 验证密码强度
            $pwdCheck = validatePasswordStrength($password);
            if (!$pwdCheck['valid']) {
                $error = $pwdCheck['message'];
            } else {
                db()->insert('users', [
                    'username' => $username,
                    'password' => generatePassword($password),
                    'real_name' => $realName,
                    'role' => $role
                ]);
                $message = '添加成功';
                Logger::info("添加用户: {$username}");
                auditLog('create', 'user', db()->lastInsertId(), ['username' => $username, 'role' => $role]);
            }
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $realName = trim($_POST['real_name'] ?? '');
        $role = $_POST['role'] ?? 'inspector';
        $password = $_POST['new_password'] ?? '';
        
        $validator = new Validator($_POST);
        $validator->required('real_name', '请输入真实姓名')
                  ->minLength('real_name', 2, '真实姓名至少2个字符')
                  ->in('role', ['admin', 'inspector'], '角色无效');
        
        if ($validator->fails()) {
            $error = $validator->firstError();
        } else {
            $updateData = [
                'real_name' => $realName,
                'role' => $role
            ];
            
            if (!empty($password)) {
                $pwdCheck = validatePasswordStrength($password);
                if (!$pwdCheck['valid']) {
                    $error = $pwdCheck['message'];
                } else {
                    $updateData['password'] = generatePassword($password);
                }
            }
            
            if (!$error) {
                db()->update('users', $updateData, 'id = ?', [$id]);
                $message = '更新成功';
                Logger::info("更新用户: ID {$id}");
                auditLog('update', 'user', $id, ['real_name' => $realName, 'role' => $role]);
            }
        }
    } elseif ($action === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        $status = intval($_POST['status'] ?? 0);
        db()->update('users', ['status' => $status], 'id = ?', [$id]);
        $message = '状态更新成功';
        Logger::info("切换用户状态: ID {$id} -> " . ($status ? '启用' : '禁用'));
        auditLog('toggle', 'user', $id, ['status' => $status]);
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $currentUser = getCurrentUser();
        if ($id === $currentUser['id']) {
            $error = '不能删除自己的账号';
        } else {
            $user = db()->fetch("SELECT username FROM users WHERE id = ?", [$id]);
            db()->delete('users', 'id = ?', [$id]);
            $message = '删除成功';
            Logger::info("删除用户: {$user['username']}");
            auditLog('delete', 'user', $id, ['username' => $user['username']]);
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

$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (username LIKE ? OR real_name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($roleFilter) {
    $where .= " AND role = ?";
    $params[] = $roleFilter;
}

$total = db()->fetch(
    "SELECT COUNT(*) as count FROM users {$where}",
    $params
)['count'];

$users = db()->fetchAll(
    "SELECT * FROM users {$where} ORDER BY id ASC LIMIT {$offset}, {$pageSize}",
    $params
);

$totalPages = ceil($total / $pageSize);

renderHeader('账号管理');
?>

<div class="container" style="padding-top: 20px;">
    <div style="display: flex;">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="index.php">仪表盘</a></li>
                <li><a href="dormitories.php">宿舍管理</a></li>
                <li><a href="users.php" class="active">账号管理</a></li>
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
                <h2>账号管理</h2>
                <button class="btn" onclick="showAddModal()">添加账号</button>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?= h($message) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= h($error) ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="flex flex-between mb-20">
                    <form method="GET" style="display: flex; gap: 10px;">
                        <input type="text" name="search" placeholder="搜索用户名/姓名" 
                               value="<?= h($search) ?>" style="padding: 8px; width: 200px;">
                        <select name="role" style="padding: 8px;">
                            <option value="">全部角色</option>
                            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>管理员</option>
                            <option value="inspector" <?= $roleFilter === 'inspector' ? 'selected' : '' ?>>检查人</option>
                        </select>
                        <button type="submit" class="btn btn-sm">搜索</button>
                        <?php if ($search || $roleFilter): ?>
                            <a href="?" class="btn btn-sm btn-secondary">清除</a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>用户名</th>
                            <th>真实姓名</th>
                            <th>角色</th>
                            <th>状态</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= h($user['username']) ?></td>
                                <td><?= h($user['real_name']) ?></td>
                                <td>
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <span class="badge badge-info">管理员</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">检查人</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['status']): ?>
                                        <span class="badge badge-success">启用</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">禁用</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDate($user['created_at']) ?></td>
                                <td>
                                    <button class="btn btn-sm" onclick="showEditModal(
                                        <?= $user['id'] ?>,
                                        '<?= h($user['username']) ?>',
                                        '<?= h($user['real_name']) ?>',
                                        '<?= $user['role'] ?>'
                                    )">编辑</button>
                                    <form method="POST" style="display: inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $user['status'] ? 0 : 1 ?>">
                                        <button type="submit" class="btn btn-sm <?= $user['status'] ? 'btn-secondary' : 'btn-success' ?>">
                                            <?= $user['status'] ? '禁用' : '启用' ?>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('确定删除？')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
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
                            <a href="?page=<?= $i ?>&search=<?= h($search) ?>&role=<?= h($roleFilter) ?>" 
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
        <h3 style="margin-bottom: 20px;">添加账号</h3>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" name="username" required placeholder="请输入用户名">
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" required placeholder="至少6位字符" minlength="6">
                <small style="color: #666;">密码要求：至少6位字符</small>
            </div>
            <div class="form-group">
                <label>真实姓名</label>
                <input type="text" name="real_name" required placeholder="请输入真实姓名">
            </div>
            <div class="form-group">
                <label>角色</label>
                <select name="role">
                    <option value="inspector">检查人</option>
                    <option value="admin">管理员</option>
                </select>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="hideAddModal()">取消</button>
                <button type="submit" class="btn">确定</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; 
     background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 8px; width: 400px;">
        <h3 style="margin-bottom: 20px;">编辑账号</h3>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" id="edit_username" disabled style="background: #f5f5f5;">
            </div>
            <div class="form-group">
                <label>新密码（留空不修改）</label>
                <input type="password" name="new_password" placeholder="留空则不修改密码，填写时至少6位" minlength="6">
            </div>
            <div class="form-group">
                <label>真实姓名</label>
                <input type="text" name="real_name" id="edit_real_name" required>
            </div>
            <div class="form-group">
                <label>角色</label>
                <select name="role" id="edit_role">
                    <option value="inspector">检查人</option>
                    <option value="admin">管理员</option>
                </select>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="hideEditModal()">取消</button>
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

function showEditModal(id, username, realName, role) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_real_name').value = realName;
    document.getElementById('edit_role').value = role;
    document.getElementById('editModal').style.display = 'flex';
}

function hideEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
</script>

<?php renderFooter(); ?>
