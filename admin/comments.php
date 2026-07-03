<?php
/**
 * 评论管理 v2.0
 * 增强审核、批量操作、管理员回复功能
 */
define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
requireAdmin();

$pageTitle = '评论管理';
$currentPage = 'comments';

// 处理操作
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        $message = 'CSRF验证失败';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
        
        try {
            switch ($action) {
                case 'approve':
                    if (empty($ids)) {
                        $message = '请选择要操作的评论';
                        $messageType = 'error';
                    } else {
                        $idList = implode(',', $ids);
                        db()->query("UPDATE lm_comment SET status = 1 WHERE id IN ($idList)");
                        $message = '已批准 ' . count($ids) . ' 条评论';
                        $messageType = 'success';
                    }
                    break;

                case 'reject':
                    if (empty($ids)) {
                        $message = '请选择要操作的评论';
                        $messageType = 'error';
                    } else {
                        $idList = implode(',', $ids);
                        db()->query("UPDATE lm_comment SET status = 0 WHERE id IN ($idList)");
                        $message = '已拒绝 ' . count($ids) . ' 条评论';
                        $messageType = 'success';
                    }
                    break;

                case 'delete':
                    if (empty($ids)) {
                        $message = '请选择要操作的评论';
                        $messageType = 'error';
                    } else {
                        $idList = implode(',', $ids);
                        db()->query("DELETE FROM lm_comment WHERE id IN ($idList)");
                        $message = '已删除 ' . count($ids) . ' 条评论';
                        $messageType = 'success';
                    }
                    break;

                case 'delete_all_pending':
                    db()->query("DELETE FROM lm_comment WHERE status = 0");
                    $message = '已清空所有待审核评论';
                    $messageType = 'success';
                    break;
            }
        } catch (Exception $e) {
            $message = '操作失败: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// 分页
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// 筛选
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$articleFilter = isset($_GET['article_id']) ? (int)$_GET['article_id'] : 0;

$where = '1=1';
$params = [];

if ($status === 'pending') {
    $where .= ' AND c.status = 0';
} elseif ($status === 'approved') {
    $where .= ' AND c.status = 1';
}

if ($articleFilter > 0) {
    $where .= ' AND c.article_id = ?';
    $params[] = $articleFilter;
}

// 获取评论列表
$comments = [];
$totalPages = 0;
try {
    $totalComments = db()->fetchColumn("SELECT COUNT(*) FROM lm_comment c WHERE {$where}", $params);
    $totalPages = ceil($totalComments / $perPage);
    
    $comments = db()->fetchAll(
        "SELECT c.*, a.title as article_title, a.slug as article_slug
         FROM lm_comment c
         LEFT JOIN lm_article a ON c.article_id = a.id
         WHERE {$where}
         ORDER BY c.created_at DESC
         LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, $offset])
    );
    
    // 获取每条评论的回复
    foreach ($comments as &$comment) {
        $comment['replies'] = db()->fetchAll(
            "SELECT c.*, u.nickname as user_nickname 
             FROM lm_comment c 
             LEFT JOIN lm_admin u ON c.user_id = u.id 
             WHERE c.parent_id = ? 
             ORDER BY c.created_at ASC",
            [$comment['id']]
        );
    }
    
    // 统计
    $stats = [
        'total' => db()->fetchColumn("SELECT COUNT(*) FROM lm_comment") ?: 0,
        'pending' => db()->fetchColumn("SELECT COUNT(*) FROM lm_comment WHERE status = 0") ?: 0,
        'approved' => db()->fetchColumn("SELECT COUNT(*) FROM lm_comment WHERE status = 1") ?: 0,
    ];
    
} catch (Exception $e) {
    $comments = [];
    $totalPages = 0;
    $stats = ['total' => 0, 'pending' => 0, 'approved' => 0];
}

require_once LM_ROOT . '/admin/template/header.php';
?>

<?php if ($message): ?>
<div class="alert alert-<?php echo $messageType; ?>"><?php echo e($message); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>评论管理</div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <a href="?status=all" class="btn btn-sm <?php echo $status === 'all' ? 'btn-primary' : 'btn-secondary'; ?>">全部 (<?php echo $stats['total']; ?>)</a>
            <a href="?status=pending" class="btn btn-sm <?php echo $status === 'pending' ? 'btn-primary' : 'btn-secondary'; ?>">待审核 (<?php echo $stats['pending']; ?>)</a>
            <a href="?status=approved" class="btn btn-sm <?php echo $status === 'approved' ? 'btn-primary' : 'btn-secondary'; ?>">已通过 (<?php echo $stats['approved']; ?>)</a>
            <?php if ($stats['pending'] > 0): ?>
            <form method="POST" action="" class="form-clear-pending" style="display: inline;">
                <?php echo Security::csrfField(); ?>
                <input type="hidden" name="action" value="delete_all_pending">
                <button type="submit" class="btn btn-sm btn-danger" data-confirm="确定清空所有待审核评论？">清空待审核</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <form method="POST" action="" id="batch-form">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" id="batch-action" value="">
            
            <div style="padding: 16px; border-bottom: 1px solid var(--border-color); display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                    <input type="checkbox" id="select-all" style="width: auto;">
                    <span>全选</span>
                </label>
                <button type="button" class="btn btn-sm btn-success" data-batch-action="approve">批量通过</button>
                <button type="button" class="btn btn-sm btn-secondary" data-batch-action="reject">批量拒绝</button>
                <button type="button" class="btn btn-sm btn-danger" data-batch-action="delete">批量删除</button>
            </div>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 40px;"><input type="checkbox" id="select-all-header" style="width: auto;"></th>
                        <th>评论者</th>
                        <th>内容</th>
                        <th>文章</th>
                        <th>时间</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comments as $comment): ?>
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="<?php echo $comment['id']; ?>" class="comment-checkbox" style="width: auto;"></td>
                        <td>
                            <div style="font-weight: 500;"><?php echo e($comment['nickname']); ?></div>
                            <div style="font-size: 0.8rem; color: var(--text-light);"><?php echo e($comment['email']); ?></div>
                        </td>
                        <td style="max-width: 300px;">
                            <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo e($comment['content']); ?>">
                                <?php echo e($comment['content']); ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($comment['article_id'] > 0 && $comment['article_title']): ?>
                            <a href="/article.php?slug=<?php echo e($comment['article_slug']); ?>" target="_blank">
                                <?php echo e(truncate($comment['article_title'], 20)); ?>
                            </a>
                            <?php else: ?>
                            <span style="color: var(--text-light);">留言板</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo timeAgo($comment['created_at']); ?></td>
                        <td>
                            <span class="badge <?php echo $comment['status'] ? 'badge-success' : 'badge-warning'; ?>">
                                <?php echo $comment['status'] ? '已显示' : '待审核'; ?>
                            </span>
                        </td>
                        <td>
                        <div style="display: flex; gap: 4px;">
                            <?php if (!$comment['status']): ?>
                            <form method="POST" action="" style="display: inline;">
                                <?php echo Security::csrfField(); ?>
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="ids[]" value="<?php echo $comment['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-success">通过</button>
                            </form>
                            <?php else: ?>
                            <form method="POST" action="" style="display: inline;">
                                <?php echo Security::csrfField(); ?>
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="ids[]" value="<?php echo $comment['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-secondary">拒绝</button>
                            </form>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-primary" data-reply-id="<?php echo $comment['id']; ?>">回复</button>
                            <form method="POST" action="" class="form-delete-comment" style="display: inline;">
                                <?php echo Security::csrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="ids[]" value="<?php echo $comment['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger" data-confirm="确定删除这条评论？">删除</button>
                            </form>
                        </div>
                    </td>
                    </tr>
                    <!-- 回复表单 -->
                    <tr id="reply-form-<?php echo $comment['id']; ?>" style="display: none;">
                        <td colspan="7" style="background: var(--bg-color); padding: 16px;">
                            <div class="reply-form" style="margin: 0;">
                                <textarea id="reply-content-<?php echo $comment['id']; ?>" placeholder="输入回复内容..." rows="3"></textarea>
                                <div style="display: flex; gap: 8px;">
                                    <button type="button" class="btn btn-sm btn-primary" data-submit-reply="<?php echo $comment['id']; ?>">发送回复</button>
                                    <button type="button" class="btn btn-sm btn-secondary" data-cancel-reply="<?php echo $comment['id']; ?>">取消</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <!-- 显示已有回复 -->
                    <?php if (!empty($comment['replies'])): ?>
                    <?php foreach ($comment['replies'] as $reply): ?>
                    <tr>
                        <td colspan="7" style="background: var(--bg-color); padding: 12px 16px;">
                            <div style="display: flex; gap: 8px; align-items: flex-start; margin-left: 40px;">
                                <span style="color: var(--primary-color); font-weight: 600; font-size: 0.85rem;">管理员回复:</span>
                                <div style="flex: 1;">
                                    <div style="color: var(--text-color); font-size: 0.9rem;"><?php echo e($reply['content']); ?></div>
                                    <div style="color: var(--text-light); font-size: 0.75rem; margin-top: 4px;"><?php echo timeAgo($reply['created_at']); ?></div>
                                </div>
                                <form method="POST" action="" style="display: inline;">
                                    <?php echo Security::csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="ids[]" value="<?php echo (int)$reply['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" data-confirm="确定删除这条回复？">删除</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (empty($comments)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-light); padding: 40px;">暂无评论</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </form>
        
        <?php 
        $urlPattern = '/admin/comments.php?page=%d&status=' . $status;
        echo pagination($page, $totalPages, $urlPattern); 
        ?>
    </div>
</div>

<script src="/assets/js/admin/admin-comments.js?v=<?php echo LM_VERSION; ?>"></script>

<?php require_once LM_ROOT . '/admin/template/footer.php'; ?>
