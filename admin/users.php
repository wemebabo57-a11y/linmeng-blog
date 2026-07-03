<?php
/**
 * 用户管理
 */
define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
requireAdmin();

$pageTitle = '用户管理';
$currentPage = 'users';

$error = '';
$success = '';

// 处理删除
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $token = $_GET['token'] ?? '';
    if (!Security::validateToken($token)) {
        die('CSRF验证失败');
    }
    
    $id = (int)$_GET['id'];

    // 不能删除自己
    if ($id === $_SESSION['user_id']) {
        $error = '不能删除当前登录的账号';
    } else {
        try {
            // 最后一个管理员保护：若被删用户是管理员且当前仅剩一个，拒绝
            $target = db()->fetchOne("SELECT role FROM lm_admin WHERE id = ?", [$id]);
            if ($target && $target['role'] === 'admin') {
                $adminCount = (int)db()->fetchColumn("SELECT COUNT(*) FROM lm_admin WHERE role = 'admin'");
                if ($adminCount <= 1) {
                    $error = '不能删除最后一个管理员，请先提升其他用户为管理员';
                }
            }
            if (empty($error)) {
                db()->delete('lm_admin', 'id = ?', [$id]);
                $success = '用户已删除';
            }
        } catch (Exception $e) {
            $error = '删除失败';
        }
    }
}

// 处理添加/编辑用户
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_user') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        $error = 'CSRF验证失败';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $nickname = trim($_POST['nickname'] ?? '');
        $avatar = trim($_POST['avatar'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['admin', 'user']) ? $_POST['role'] : 'user';
        $status = isset($_POST['status']) ? 1 : 0;
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        
        if (empty($username) || empty($email)) {
            $error = '请填写用户名和邮箱';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '邮箱格式不正确';
        } elseif (strlen($username) < 3) {
            $error = '用户名至少3个字符';
        } else {
            try {
                // 检查用户名是否已存在
                $exists = db()->fetchColumn(
                    "SELECT COUNT(*) FROM lm_admin WHERE username = ? AND id != ?",
                    [$username, $userId]
                );
                
                if ($exists) {
                    $error = '用户名已存在';
                } else {
                    $data = [
                        'username' => $username,
                        'email' => $email,
                        'nickname' => $nickname,
                        'avatar' => $avatar ? Security::xssClean($avatar) : null,
                        'role' => $role,
                        'status' => $status
                    ];

                    // 检查密码强度
                    if (!empty($password)) {
                        $strength = Security::checkPasswordStrength($password);
                        if (!$strength['strong']) {
                            $error = '密码强度不足: ' . implode(', ', $strength['errors']);
                        } else {
                            $data['password'] = Security::hashPassword($password);
                        }
                    } elseif ($userId === 0) {
                        $error = '新用户必须设置密码';
                    }

                    // 最后一个管理员保护：把管理员降级为普通用户且当前仅剩一个管理员时拒绝
                    if (empty($error) && $userId > 0 && $role === 'user') {
                        $current = db()->fetchOne("SELECT role FROM lm_admin WHERE id = ?", [$userId]);
                        if ($current && $current['role'] === 'admin') {
                            $adminCount = (int)db()->fetchColumn("SELECT COUNT(*) FROM lm_admin WHERE role = 'admin'");
                            if ($adminCount <= 1) {
                                $error = '不能降级最后一个管理员，请先提升其他用户为管理员';
                            }
                        }
                    }

                    if (empty($error)) {
                        if ($userId > 0) {
                            // 更新
                            db()->update('lm_admin', $data, 'id = ?', [$userId]);
                            $success = '用户已更新';
                        } else {
                            // 新建
                            db()->insert('lm_admin', $data);
                            $success = '用户已创建';
                        }
                    }
                }
            } catch (Exception $e) {
                $error = '保存失败: ' . $e->getMessage();
            }
        }
    }
}

// 获取用户列表
try {
    $users = db()->fetchAll(
        "SELECT id, username, email, nickname, avatar, role, status, last_login, created_at
         FROM lm_admin
         ORDER BY created_at DESC"
    );
} catch (Exception $e) {
    $users = [];
}

require_once LM_ROOT . '/admin/template/header.php';
?>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo e($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error"><?php echo e($error); ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 6px;"><path d="M5 12h14"/><path d="M12 5v14"/></svg> 添加/编辑用户</div>
    </div>
    <div class="card-body">
        <form method="POST" action="" data-validate>
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="save_user">
            <input type="hidden" name="user_id" id="user_id" value="0">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">用户名 *</label>
                    <input type="text" name="username" class="form-input" placeholder="用户名" required id="username">
                </div>
                
                <div class="form-group">
                    <label class="form-label">密码 <?php echo isset($_GET['edit']) ? '(留空不修改)' : '*'; ?></label>
                    <input type="password" name="password" class="form-input" placeholder="至少8位，包含大小写字母和数字" <?php echo !isset($_GET['edit']) ? 'required' : ''; ?>>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">邮箱 *</label>
                    <input type="email" name="email" class="form-input" placeholder="邮箱" required id="email">
                </div>

                <div class="form-group">
                    <label class="form-label">昵称</label>
                    <input type="text" name="nickname" class="form-input" placeholder="昵称" id="nickname">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">头像</label>
                <input type="text" name="avatar" class="form-input" placeholder="头像图片链接" id="avatar">
                <div class="form-hint">支持外部图片链接</div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">角色</label>
                    <select name="role" class="form-select" id="role">
                        <option value="user">普通用户</option>
                        <option value="admin">管理员</option>
                    </select>
                </div>
                
                <div class="form-group" style="display: flex; align-items: center; gap: 8px; padding-top: 28px;">
                    <input type="checkbox" name="status" id="status" checked style="width: auto;">
                    <label for="status" style="margin-bottom: 0;">启用账号</label>
                </div>
            </div>
            
            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary" id="submit-btn">添加用户</button>
                <button type="button" class="btn btn-secondary" id="reset-user-form">重置</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 6px;"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> 用户列表</div>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>用户名</th>
                    <th>昵称</th>
                    <th>邮箱</th>
                    <th>角色</th>
                    <th>状态</th>
                    <th>最后登录</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo e($user['username']); ?></td>
                    <td><?php echo e($user['nickname'] ?: '-'); ?></td>
                    <td><?php echo e($user['email']); ?></td>
                    <td>
                        <span class="badge <?php echo $user['role'] === 'admin' ? 'badge-success' : 'badge-secondary'; ?>">
                            <?php echo $user['role'] === 'admin' ? '管理员' : '用户'; ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?php echo $user['status'] ? 'badge-success' : 'badge-danger'; ?>">
                            <?php echo $user['status'] ? '正常' : '禁用'; ?>
                        </span>
                    </td>
                    <td><?php echo $user['last_login'] ? timeAgo($user['last_login']) : '从未登录'; ?></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary"
                                data-edit-user-id="<?php echo (int)$user['id']; ?>"
                                data-edit-user-username="<?php echo e($user['username']); ?>"
                                data-edit-user-email="<?php echo e($user['email']); ?>"
                                data-edit-user-nickname="<?php echo e($user['nickname']); ?>"
                                data-edit-user-avatar="<?php echo e($user['avatar'] ?? ''); ?>"
                                data-edit-user-role="<?php echo e($user['role']); ?>"
                                data-edit-user-status="<?php echo (int)$user['status']; ?>">编辑</button>
                        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                        <a href="?action=delete&id=<?php echo $user['id']; ?>&token=<?php echo Security::generateToken(); ?>"
                           class="btn btn-sm btn-danger"
                           data-confirm="确定要删除该用户吗？">删除</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--text-light); padding: 40px;">暂无用户</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="/assets/js/admin/admin-users.js?v=<?php echo LM_VERSION; ?>"></script>
<script>
// 补充填充头像字段（admin-users.js 未处理该字段，避免编辑时清空已有头像）
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        var avatarInput = document.getElementById('avatar');
        document.querySelectorAll('[data-edit-user-id]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (avatarInput) {
                    avatarInput.value = btn.getAttribute('data-edit-user-avatar') || '';
                }
            });
        });
        var resetBtn = document.getElementById('reset-user-form');
        if (resetBtn && avatarInput) {
            resetBtn.addEventListener('click', function() {
                avatarInput.value = '';
            });
        }
    });
})();
</script>

<?php require_once LM_ROOT . '/admin/template/footer.php'; ?>
