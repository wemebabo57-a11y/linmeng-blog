<?php
/**
 * 友链申请管理
 */
define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/includes/config.php';
require_once APP_ROOT . '/includes/Security.php';
require_once APP_ROOT . '/includes/Database.php';
require_once APP_ROOT . '/includes/functions.php';

session_start();
requireAdmin();

$pageTitle = '友链申请';
$currentPage = 'link-apply';

$error = '';
$success = '';

// 处理操作
// 注：本页所有操作（含 POST 拒绝表单）统一通过 URL 中的 GET token 进行 CSRF 校验，
// 拒绝表单内额外渲染的 Security::csrfField() 仅作冗余，不在此重复校验，保持与通过/删除流程一致。
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        die('CSRF验证失败');
    }

    $id = (int)$_POST['id'];
    $action = $_POST['action'];

    try {
        $apply = db()->fetchOne("SELECT * FROM app_link_apply WHERE id = ?", [$id]);

        if (!$apply) {
            $error = '申请不存在';
        } elseif (($apply['status'] ?? 'pending') !== 'pending') {
            // 服务端必须校验状态：仅靠模板隐藏按钮无法阻止重放 POST，
            // 重复 approve 会向 app_link 插入重复友链
            $error = '该申请已处理，请勿重复操作';
        } else {
            switch ($action) {
                case 'approve':
                    // 入库前强制校验协议，与前台 link-apply.php 保持一致：
                    // 仅允许 http/https，防止存量数据中的 javascript: 等伪协议被写入友链表
                    $siteUrl = trim((string)$apply['site_url']);
                    if (!filter_var($siteUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($siteUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
                        $error = '网站地址协议不合法（仅支持 http/https），无法通过该申请';
                        break;
                    }
                    // 头像直链同样做协议白名单校验，不合法则降级为空（显示占位首字）
                    $siteAvatar = trim((string)($apply['site_avatar'] ?? ''));
                    $logo = ($siteAvatar !== ''
                        && filter_var($siteAvatar, FILTER_VALIDATE_URL)
                        && in_array(parse_url($siteAvatar, PHP_URL_SCHEME), ['http', 'https'], true))
                        ? $siteAvatar : '';
                    // 添加到友链表（补齐 logo/sort_order，避免列无默认值时插入失败）
                    db()->insert('app_link', [
                        'name' => $apply['site_name'],
                        'url' => $siteUrl,
                        'description' => $apply['site_description'],
                        'logo' => $logo,
                        'sort_order' => 0,
                        'status' => 1
                    ]);
                    
                    db()->update('app_link_apply', ['status' => 'approved'], 'id = ?', [$id]);
                    $success = '已通过并添加到友链列表';
                    break;
                    
                case 'reject':
                    $reply = isset($_POST['reply']) ? trim($_POST['reply']) : '';
                    db()->update('app_link_apply', [
                        'status' => 'rejected',
                        'reply' => Security::xssClean($reply)
                    ], 'id = ?', [$id]);
                    $success = '已拒绝';
                    break;
                    
                case 'delete':
                    db()->delete('app_link_apply', 'id = ?', [$id]);
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
        "SELECT * FROM app_link_apply WHERE {$where} ORDER BY created_at DESC",
        $params
    );
} catch (Exception $e) {
    $applies = [];
}

require_once APP_ROOT . '/admin/template/header.php';
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
                    <th>头像</th>
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
                    <td>
                        <?php
                        // 协议白名单：仅 http/https 才输出为可点击链接，防止 javascript: 等伪协议
                        $applySiteUrl = trim((string)$apply['site_url']);
                        $applySiteUrlSafe = filter_var($applySiteUrl, FILTER_VALIDATE_URL)
                            && in_array(parse_url($applySiteUrl, PHP_URL_SCHEME), ['http', 'https'], true);
                        ?>
                        <?php if ($applySiteUrlSafe): ?>
                        <a href="<?php echo e($applySiteUrl); ?>" target="_blank" rel="noopener"><?php echo e(truncate($applySiteUrl, 25)); ?></a>
                        <?php else: ?>
                        <span style="color: var(--text-light);"><?php echo e(truncate($applySiteUrl, 25)); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        // 头像直链：仅 http/https 协议才输出 img，防止 javascript: 等伪协议
                        $applyAvatar = trim((string)($apply['site_avatar'] ?? ''));
                        $applyAvatarSafe = $applyAvatar !== ''
                            && filter_var($applyAvatar, FILTER_VALIDATE_URL)
                            && in_array(parse_url($applyAvatar, PHP_URL_SCHEME), ['http', 'https'], true);
                        ?>
                        <?php if ($applyAvatarSafe): ?>
                        <img src="<?php echo e($applyAvatar); ?>" alt="<?php echo e($apply['site_name']); ?>头像" width="32" height="32" loading="lazy" style="border-radius: 50%; vertical-align: middle; object-fit: cover;">
                        <?php else: ?>
                        <span style="color: var(--text-light);">-</span>
                        <?php endif; ?>
                    </td>
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
                        <form method="POST" action="" style="display: inline;">
                            <?php echo Security::csrfField(); ?>
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="id" value="<?php echo (int)$apply['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-primary" data-confirm="确定要通过该申请吗？">通过</button>
                        </form>
                        <button type="button" class="btn btn-sm btn-secondary"
                                data-toggle-target="reject-<?php echo $apply['id']; ?>"
                                data-toggle-display="table-row">拒绝</button>
                        <?php endif; ?>
                        <form method="POST" action="" class="form-delete-apply" style="display: inline;">
                            <?php echo Security::csrfField(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int)$apply['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger" data-confirm="确定要删除该申请吗？">删除</button>
                        </form>
                    </td>
                </tr>
                <?php if ($apply['status'] === 'pending'): ?>
                <tr id="reject-<?php echo $apply['id']; ?>" style="display: none;">
                    <td colspan="9" style="background: var(--bg-color);">
                        <form method="POST" action="" style="display: flex; gap: 8px;">
                            <?php echo Security::csrfField(); ?>
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="id" value="<?php echo (int)$apply['id']; ?>">
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
                    <td colspan="9" style="background: #fafafa; color: var(--text-light); font-size: 0.85rem;">
                        管理员回复: <?php echo e($apply['reply']); ?>
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

<?php
// 内容变更成功后清空静态页面缓存：首页/归档/标签/关于/友链都依赖这些数据，
// 否则访客最长会看到 TTL（默认 300 秒）的旧内容。
if ($success !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    app_purge_page_cache();
}
?>
<?php require_once APP_ROOT . '/admin/template/footer.php'; ?>
