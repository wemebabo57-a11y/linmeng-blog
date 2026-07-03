<?php
/**
 * 用户账号申请管理 v2.0
 * 管理员审核前台用户注册申请
 */
define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
requireAdmin();

$pageTitle = '账号申请管理';
$currentPage = 'user-apply';

$error = '';
$success = '';

// 处理操作
// 注：本页所有操作（含 POST 拒绝表单）统一通过 URL 中的 GET token 进行 CSRF 校验，
// 拒绝表单内额外渲染的 Security::csrfField() 仅作冗余，不在此重复校验，保持与通过/删除流程一致。
if (isset($_GET['action']) && isset($_GET['id'])) {
    $token = $_GET['token'] ?? '';
    if (!Security::validateToken($token)) {
        $error = 'CSRF验证失败';
    } else {
        $id = (int)$_GET['id'];
        $action = $_GET['action'];
        
        try {
            $apply = db()->fetchOne("SELECT * FROM lm_user_apply WHERE id = ?", [$id]);
            
            if (!$apply) {
                $error = '申请不存在';
            } else {
                switch ($action) {
                    case 'approve':
                        // 添加到用户表
                        db()->insert('lm_admin', [
                            'username' => $apply['username'],
                            'password' => $apply['password'],
                            'email' => $apply['email'],
                            'nickname' => $apply['nickname'] ?: $apply['username'],
                            'role' => 'user',
                            'status' => 1,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        
                        db()->update('lm_user_apply', [
                            'status' => 'approved',
                            'handled_at' => date('Y-m-d H:i:s'),
                            'handled_by' => $_SESSION['user_id']
                        ], 'id = ?', [$id]);
                        $success = '已通过申请，用户已创建';
                        break;
                        
                    case 'reject':
                        $reply = isset($_POST['reply']) ? trim($_POST['reply']) : '';
                        db()->update('lm_user_apply', [
                            'status' => 'rejected',
                            'reply' => Security::xssClean($reply),
                            'handled_at' => date('Y-m-d H:i:s'),
                            'handled_by' => $_SESSION['user_id']
                        ], 'id = ?', [$id]);
                        $success = '已拒绝申请';
                        break;
                        
                    case 'delete':
                        db()->delete('lm_user_apply', 'id = ?', [$id]);
                        $success = '已删除申请记录';
                        break;
                }
            }
        } catch (Exception $e) {
            $error = '操作失败: ' . $e->getMessage();
        }
    }
}

// 获取申请列表
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$where = '1=1';
$params = [];

if ($filter === 'pending') {
    $where .= " AND status = 'pending'";
} elseif ($filter === 'approved') {
    $where .= " AND status = 'approved'";
} elseif ($filter === 'rejected') {
    $where .= " AND status = 'rejected'";
}

try {
    $applies = db()->fetchAll(
        "SELECT a.*, ad.nickname as handler_name 
         FROM lm_user_apply a 
         LEFT JOIN lm_admin ad ON a.handled_by = ad.id 
         WHERE {$where} 
         ORDER BY a.created_at DESC",
        $params
    );
    
    // 统计
    $stats = [
        'total' => db()->fetchColumn("SELECT COUNT(*) FROM lm_user_apply") ?: 0,
        'pending' => db()->fetchColumn("SELECT COUNT(*) FROM lm_user_apply WHERE status = 'pending'") ?: 0,
        'approved' => db()->fetchColumn("SELECT COUNT(*) FROM lm_user_apply WHERE status = 'approved'") ?: 0,
        'rejected' => db()->fetchColumn("SELECT COUNT(*) FROM lm_user_apply WHERE status = 'rejected'") ?: 0,
    ];
} catch (Exception $e) {
    $applies = [];
    $stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
}

require_once LM_ROOT . '/admin/template/header.php';
?>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo e($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error"><?php echo e($error); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/></svg>账号申请管理</div>
        <div style="display: flex; gap: 8px;">
            <a href="?filter=all" class="btn btn-sm <?php echo $filter === 'all' ? 'btn-primary' : 'btn-secondary'; ?>">全部 (<?php echo $stats['total']; ?>)</a>
            <a href="?filter=pending" class="btn btn-sm <?php echo $filter === 'pending' ? 'btn-primary' : 'btn-secondary'; ?>">待处理 (<?php echo $stats['pending']; ?>)</a>
            <a href="?filter=approved" class="btn btn-sm <?php echo $filter === 'approved' ? 'btn-primary' : 'btn-secondary'; ?>">已通过 (<?php echo $stats['approved']; ?>)</a>
            <a href="?filter=rejected" class="btn btn-sm <?php echo $filter === 'rejected' ? 'btn-primary' : 'btn-secondary'; ?>">已拒绝 (<?php echo $stats['rejected']; ?>)</a>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>用户名</th>
                    <th>昵称</th>
                    <th>邮箱</th>
                    <th>网站</th>
                    <th>申请理由</th>
                    <th>状态</th>
                    <th>申请时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applies as $apply): ?>
                <tr>
                    <td><?php echo $apply['id']; ?></td>
                    <td><?php echo e($apply['username']); ?></td>
                    <td><?php echo e($apply['nickname'] ?: '-'); ?></td>
                    <td><?php echo e($apply['email']); ?></td>
                    <td>
                        <?php if ($apply['website']): ?>
                        <a href="<?php echo e($apply['website']); ?>" target="_blank" rel="noopener"><?php echo e(truncate($apply['website'], 20)); ?></a>
                        <?php else: ?>
                        <span style="color: var(--text-light);">-</span>
                        <?php endif; ?>
                    </td>
                    <td style="max-width: 200px;">
                        <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo e($apply['reason']); ?>">
                            <?php echo e($apply['reason']); ?>
                        </div>
                    </td>
                    <td>
                        <?php 
                        $statusLabels = [
                            'pending' => ['label' => '待处理', 'class' => 'badge-warning'],
                            'approved' => ['label' => '已通过', 'class' => 'badge-success'],
                            'rejected' => ['label' => '已拒绝', 'class' => 'badge-danger']
                        ];
                        $statusInfo = $statusLabels[$apply['status']] ?? $statusLabels['pending'];
                        ?>
                        <span class="badge <?php echo $statusInfo['class']; ?>"><?php echo $statusInfo['label']; ?></span>
                    </td>
                    <td><?php echo timeAgo($apply['created_at']); ?></td>
                    <td>
                        <?php if ($apply['status'] === 'pending'): ?>
                        <a href="?action=approve&id=<?php echo $apply['id']; ?>&token=<?php echo Security::generateToken(); ?>"
                           class="btn btn-sm btn-success"
                           data-confirm="确定要通过该申请吗？通过后将创建用户账号。">通过</a>
                        <button type="button" class="btn btn-sm btn-secondary"
                                data-toggle-target="reject-<?php echo $apply['id']; ?>"
                                data-toggle-display="table-row">拒绝</button>
                        <?php endif; ?>
                        <a href="?action=delete&id=<?php echo $apply['id']; ?>&token=<?php echo Security::generateToken(); ?>"
                           class="btn btn-sm btn-danger"
                           data-confirm="确定要删除该申请吗？">删除</a>
                    </td>
                </tr>
                <?php if ($apply['status'] === 'pending'): ?>
                <tr id="reject-<?php echo $apply['id']; ?>" style="display: none;">
                    <td colspan="9" style="background: var(--bg-color); padding: 16px;">
                        <form method="POST" action="?action=reject&id=<?php echo $apply['id']; ?>&token=<?php echo Security::generateToken(); ?>" style="display: flex; gap: 8px; align-items: center;">
                            <?php echo Security::csrfField(); ?>
                            <input type="text" name="reply" class="form-input" placeholder="拒绝原因（可选）" style="flex: 1;">
                            <button type="submit" class="btn btn-sm btn-danger">确认拒绝</button>
                            <button type="button" class="btn btn-sm btn-secondary"
                                    data-toggle-target="reject-<?php echo $apply['id']; ?>"
                                    data-toggle-display="none">取消</button>
                        </form>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ($apply['reply']): ?>
                <tr>
                    <td colspan="9" style="background: var(--bg-color); color: var(--text-light); font-size: 0.85rem; padding: 8px 16px;">
                        <strong>管理员回复:</strong> <?php echo e($apply['reply']); ?>
                        <?php if ($apply['handler_name']): ?>
                        <span style="margin-left: 12px;">(处理人: <?php echo e($apply['handler_name']); ?> | <?php echo timeAgo($apply['handled_at']); ?>)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
                <?php if (empty($applies)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; color: var(--text-light); padding: 40px;">暂无申请</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once LM_ROOT . '/admin/template/footer.php'; ?>
