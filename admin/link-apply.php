<?php
/**
 * 友链申请管理
 */
define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
requireAdmin();

$pageTitle = '友链申请';
$currentPage = 'link-apply';

$error = '';
$success = '';

// 处理操作
// 注：本页所有操作（含 POST 拒绝表单）统一通过 URL 中的 GET token 进行 CSRF 校验，
// 拒绝表单内额外渲染的 Security::csrfField() 仅作冗余，不在此重复校验，保持与通过/删除流程一致。
if (isset($_GET['action']) && isset($_GET['id'])) {
    $token = $_GET['token'] ?? '';
    if (!Security::validateToken($token)) {
        die('CSRF验证失败');
    }

    $id = (int)$_GET['id'];
    $action = $_GET['action'];

    try {
        $apply = db()->fetchOne("SELECT * FROM lm_link_apply WHERE id = ?", [$id]);

        if (!$apply) {
            $error = '申请不存在';
        } else {
            switch ($action) {
                case 'approve':
                    // 添加到友链表（补齐 logo/sort_order，避免列无默认值时插入失败）
                    db()->insert('lm_link', [
                        'name' => $apply['site_name'],
                        'url' => $apply['site_url'],
                        'description' => $apply['site_description'],
                        'logo' => '',
                        'sort_order' => 0,
                        'status' => 1
                    ]);
                    
                    db()->update('lm_link_apply', ['status' => 'approved'], 'id = ?', [$id]);
                    $success = '已通过并添加到友链列表';
                    break;
                    
                case 'reject':
                    $reply = isset($_POST['reply']) ? trim($_POST['reply']) : '';
                    db()->update('lm_link_apply', [
                        'status' => 'rejected',
                        'reply' => Security::xssClean($reply)
                    ], 'id = ?', [$id]);
                    $success = '已拒绝';
                    break;
                    
                case 'delete':
                    db()->delete('lm_link_apply', 'id = ?', [$id]);
                    $success = '已删除';
                    break;
            }
        }
    } catch (Exception $e) {
        $error = '操作失败: ' . $e->getMessage();
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
        "SELECT * FROM lm_link_apply WHERE {$where} ORDER BY created_at DESC",
        $params
    );
} catch (Exception $e) {
    $applies = [];
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
        <div class="card-title">📋 友链申请</div>
        <div style="display: flex; gap: 8px;">
            <a href="?filter=all" class="btn btn-sm <?php echo $filter === 'all' ? 'btn-primary' : 'btn-secondary'; ?>">全部</a>
            <a href="?filter=pending" class="btn btn-sm <?php echo $filter === 'pending' ? 'btn-primary' : 'btn-secondary'; ?>">待处理</a>
            <a href="?filter=approved" class="btn btn-sm <?php echo $filter === 'approved' ? 'btn-primary' : 'btn-secondary'; ?>">已通过</a>
            <a href="?filter=rejected" class="btn btn-sm <?php echo $filter === 'rejected' ? 'btn-primary' : 'btn-secondary'; ?>">已拒绝</a>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>网站名称</th>
                    <th>网站地址</th>
                    <th>描述</th>
                    <th>联系邮箱</th>
                    <th>状态</th>
                    <th>申请时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applies as $apply): ?>
                <tr>
                    <td><?php echo $apply['id']; ?></td>
                    <td><?php echo e($apply['site_name']); ?></td>
                    <td><a href="<?php echo e($apply['site_url']); ?>" target="_blank" rel="noopener"><?php echo e(truncate($apply['site_url'], 25)); ?></a></td>
                    <td><?php echo e(truncate($apply['site_description'] ?: '-', 20)); ?></td>
                    <td><?php echo e($apply['email']); ?></td>
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
                           class="btn btn-sm btn-primary"
                           data-confirm="确定要通过该申请吗？">通过</a>
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
                    <td colspan="8" style="background: var(--bg-color);">
                        <form method="POST" action="?action=reject&id=<?php echo $apply['id']; ?>&token=<?php echo Security::generateToken(); ?>" style="display: flex; gap: 8px;">
                            <?php echo Security::csrfField(); ?>
                            <input type="text" name="reply" class="form-input" placeholder="拒绝原因（可选）">
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
                    <td colspan="8" style="background: #fafafa; color: var(--text-light); font-size: 0.85rem;">
                        管理员回复: <?php echo e($apply['reply']); ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
                <?php if (empty($applies)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--text-light); padding: 40px;">暂无申请</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once LM_ROOT . '/admin/template/footer.php'; ?>
