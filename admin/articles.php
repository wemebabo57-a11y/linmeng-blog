<?php
/**
 * 文章管理 v2.0
 * 增强搜索、批量操作、筛选功能
 */
define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
requireAdmin();

$pageTitle = '文章管理';
$currentPage = 'articles';

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
        
        if (!empty($ids)) {
            try {
                $idList = implode(',', $ids);
                
                switch ($action) {
                    case 'publish':
                        db()->query("UPDATE lm_article SET status = 'published' WHERE id IN ($idList)");
                        $message = '已发布 ' . count($ids) . ' 篇文章';
                        $messageType = 'success';
                        break;

                    case 'draft':
                        db()->query("UPDATE lm_article SET status = 'draft' WHERE id IN ($idList)");
                        $message = '已设为草稿 ' . count($ids) . ' 篇文章';
                        $messageType = 'success';
                        break;

                    case 'top':
                        db()->query("UPDATE lm_article SET is_top = 1 WHERE id IN ($idList)");
                        $message = '已置顶 ' . count($ids) . ' 篇文章';
                        $messageType = 'success';
                        break;

                    case 'untop':
                        db()->query("UPDATE lm_article SET is_top = 0 WHERE id IN ($idList)");
                        $message = '已取消置顶 ' . count($ids) . ' 篇文章';
                        $messageType = 'success';
                        break;

                    case 'delete':
                        // 删除文章图片
                        $images = db()->fetchAll("SELECT image_url FROM lm_article_image WHERE article_id IN ($idList)");
                        foreach ($images as $img) {
                            $filePath = LM_ROOT . $img['image_url'];
                            if (file_exists($filePath) && strpos($filePath, LM_ROOT . '/assets/uploads/') === 0) {
                                @unlink($filePath);
                            }
                        }
                        db()->query("DELETE FROM lm_article_image WHERE article_id IN ($idList)");
                        db()->query("DELETE FROM lm_comment WHERE article_id IN ($idList)");
                        db()->query("DELETE FROM lm_article_like WHERE article_id IN ($idList)");
                        db()->query("DELETE FROM lm_article WHERE id IN ($idList)");
                        $message = '已删除 ' . count($ids) . ' 篇文章及其相关数据';
                        $messageType = 'success';
                        break;
                }
            } catch (Exception $e) {
                $message = '操作失败: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// 分页
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// 搜索和筛选
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;

$params = [];
$where = '1=1';

if ($search) {
    $where .= ' AND (a.title LIKE ? OR a.content LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($statusFilter === 'published') {
    $where .= " AND a.status = 'published'";
} elseif ($statusFilter === 'draft') {
    $where .= " AND a.status = 'draft'";
}

if ($categoryFilter > 0) {
    $where .= ' AND a.category_id = ?';
    $params[] = $categoryFilter;
}

// 获取文章列表
try {
    $totalArticles = db()->fetchColumn("SELECT COUNT(*) FROM lm_article a WHERE {$where}", $params);
    $totalPages = ceil($totalArticles / $perPage);
    
    $articles = db()->fetchAll(
        "SELECT a.*, c.name as category_name, u.nickname as author_name,
                (SELECT COUNT(*) FROM lm_comment WHERE article_id = a.id) as comment_count,
                (SELECT COUNT(*) FROM lm_article_like WHERE article_id = a.id) as like_count
         FROM lm_article a 
         LEFT JOIN lm_category c ON a.category_id = c.id 
         LEFT JOIN lm_admin u ON a.author_id = u.id 
         WHERE {$where}
         ORDER BY a.is_top DESC, a.created_at DESC 
         LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, $offset])
    );
    
    // 分类列表
    $categories = getCategories();
    
    // 统计
    $stats = [
        'total' => db()->fetchColumn("SELECT COUNT(*) FROM lm_article") ?: 0,
        'published' => db()->fetchColumn("SELECT COUNT(*) FROM lm_article WHERE status = 'published'") ?: 0,
        'draft' => db()->fetchColumn("SELECT COUNT(*) FROM lm_article WHERE status = 'draft'") ?: 0,
    ];
    
} catch (Exception $e) {
    $articles = [];
    $totalPages = 0;
    $categories = [];
    $stats = ['total' => 0, 'published' => 0, 'draft' => 0];
}

require_once LM_ROOT . '/admin/template/header.php';
?>

<?php if ($message): ?>
<div class="alert alert-<?php echo $messageType; ?>"><?php echo e($message); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/></svg>文章列表</div>
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <form method="GET" action="" style="display: flex; gap: 8px; flex-wrap: wrap;">
                <input type="text" name="search" class="form-input" placeholder="搜索文章..." value="<?php echo e($search); ?>" style="width: 200px;">
                <select name="status" class="form-select" style="width: auto;">
                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>全部状态</option>
                    <option value="published" <?php echo $statusFilter === 'published' ? 'selected' : ''; ?>>已发布</option>
                    <option value="draft" <?php echo $statusFilter === 'draft' ? 'selected' : ''; ?>>草稿</option>
                </select>
                <select name="category" class="form-select" style="width: auto;">
                    <option value="0">全部分类</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter === (int)$cat['id'] ? 'selected' : ''; ?>>
                        <?php echo e($cat['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sm btn-secondary">筛选</button>
                <?php if ($search || $statusFilter !== 'all' || $categoryFilter > 0): ?>
                <a href="articles.php" class="btn btn-sm btn-secondary">清除</a>
                <?php endif; ?>
            </form>
            <a href="article-edit.php" class="btn btn-sm btn-primary">+ 写文章</a>
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
                <button type="button" class="btn btn-sm btn-success" data-batch-action="publish">批量发布</button>
                <button type="button" class="btn btn-sm btn-secondary" data-batch-action="draft">批量草稿</button>
                <button type="button" class="btn btn-sm btn-primary" data-batch-action="top">批量置顶</button>
                <button type="button" class="btn btn-sm btn-secondary" data-batch-action="untop">取消置顶</button>
                <button type="button" class="btn btn-sm btn-danger" data-batch-action="delete">批量删除</button>
                <span style="margin-left: auto; color: var(--text-light); font-size: 0.85rem;">
                    共 <?php echo $stats['total']; ?> 篇 | 已发布 <?php echo $stats['published']; ?> | 草稿 <?php echo $stats['draft']; ?>
                </span>
            </div>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 40px;"><input type="checkbox" id="select-all-header" style="width: auto;"></th>
                        <th>ID</th>
                        <th>标题</th>
                        <th>分类</th>
                        <th>浏览</th>
                        <th>评论</th>
                        <th>点赞</th>
                        <th>状态</th>
                        <th>时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $article): ?>
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="<?php echo $article['id']; ?>" class="article-checkbox" style="width: auto;"></td>
                        <td><?php echo $article['id']; ?></td>
                        <td>
                            <?php if ($article['is_top']): ?>
                            <span style="color: var(--warning-color);">[置顶]</span>
                            <?php endif; ?>
                            <?php echo e(truncate($article['title'], 30)); ?>
                        </td>
                        <td><?php echo e($article['category_name'] ?: '未分类'); ?></td>
                        <td><?php echo $article['views']; ?></td>
                        <td><?php echo $article['comment_count']; ?></td>
                        <td><?php echo $article['like_count']; ?></td>
                        <td>
                            <span class="badge <?php echo $article['status'] === 'published' ? 'badge-success' : 'badge-warning'; ?>">
                                <?php echo $article['status'] === 'published' ? '已发布' : '草稿'; ?>
                            </span>
                        </td>
                        <td><?php echo timeAgo($article['created_at']); ?></td>
                        <td>
                            <div style="display: flex; gap: 4px;">
                                <a href="/article.php?slug=<?php echo e($article['slug']); ?>" target="_blank" class="btn btn-sm btn-secondary">查看</a>
                                <a href="article-edit.php?id=<?php echo $article['id']; ?>" class="btn btn-sm btn-primary">编辑</a>
                                <form method="POST" action="" class="form-delete-article" style="display: inline;">
                                    <?php echo Security::csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="ids[]" value="<?php echo $article['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" data-confirm="确定删除这篇文章吗？相关评论、图片、点赞也会被删除。">删除</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($articles)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; color: var(--text-light); padding: 40px;">
                            <?php echo $search ? '没有找到匹配的文章' : '暂无文章'; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </form>
        
        <?php 
        $urlPattern = '/admin/articles.php?page=%d';
        if ($search) $urlPattern .= '&search=' . urlencode($search);
        if ($statusFilter !== 'all') $urlPattern .= '&status=' . $statusFilter;
        if ($categoryFilter > 0) $urlPattern .= '&category=' . $categoryFilter;
        echo pagination($page, $totalPages, $urlPattern); 
        ?>
    </div>
</div>

<script src="/assets/js/admin/admin-articles.js?v=<?php echo LM_VERSION; ?>"></script>

<?php require_once LM_ROOT . '/admin/template/footer.php'; ?>
