<?php
/**
 * 文章管理 v2.0
 * 增强搜索、批量操作、筛选功能
 */
define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/includes/config.php';
require_once APP_ROOT . '/includes/Security.php';
require_once APP_ROOT . '/includes/Database.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/AiSummaryCache.php';
require_once APP_ROOT . '/includes/PageCache.php';

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
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                
                switch ($action) {
                    case 'publish':
                        db()->query("UPDATE app_article SET status = 'published' WHERE id IN ($placeholders)", $ids);
                        $message = '已发布 ' . count($ids) . ' 篇文章';
                        $messageType = 'success';
                        break;

                    case 'draft':
                        db()->query("UPDATE app_article SET status = 'draft' WHERE id IN ($placeholders)", $ids);
                        $message = '已设为草稿 ' . count($ids) . ' 篇文章';
                        $messageType = 'success';
                        break;

                    case 'pin':
                        db()->query("UPDATE app_article SET is_top = 1 WHERE id IN ($placeholders)", $ids);
                        $message = '已置顶 ' . count($ids) . ' 篇文章';
                        $messageType = 'success';
                        break;

                    case 'unpin':
                        db()->query("UPDATE app_article SET is_top = 0 WHERE id IN ($placeholders)", $ids);
                        $message = '已取消置顶 ' . count($ids) . ' 篇文章';
                        $messageType = 'success';
                        break;

                    case 'delete':
                        // 先记录正文引用，数据库删除全部成功后再清理 Markdown 文件。
                        $articleBodies = db()->fetchAll("SELECT content FROM app_article WHERE id IN ($placeholders)", $ids);
                        $images = db()->fetchAll("SELECT image_url FROM app_article_image WHERE article_id IN ($placeholders)", $ids);
                        db()->beginTransaction();
                        try {
                            db()->query("DELETE FROM app_article_image WHERE article_id IN ($placeholders)", $ids);
                            db()->query("DELETE FROM app_comment WHERE article_id IN ($placeholders)", $ids);
                            db()->query("DELETE FROM app_article_like WHERE article_id IN ($placeholders)", $ids);
                            db()->query("DELETE FROM app_article WHERE id IN ($placeholders)", $ids);
                            db()->commit();
                        } catch (Exception $deleteError) {
                            if (db()->getPdo()->inTransaction()) {
                                db()->rollback();
                            }
                            throw $deleteError;
                        }
                        // 事务提交后再清理磁盘文件，数据库失败时不会丢失正文或图片。
                        $uploadDir = realpath(APP_ROOT . '/assets/uploads');
                        foreach ($images as $img) {
                            // realpath 解析后确认仍位于 assets/uploads 内才删除，防路径穿越；
                            // 文件不存在时 realpath 返回 false，安全跳过
                            $filePath = $uploadDir === false ? false : realpath(APP_ROOT . $img['image_url']);
                            if ($filePath !== false && strpos($filePath, $uploadDir . DIRECTORY_SEPARATOR) === 0) {
                                if (!@unlink($filePath)) {
                                    error_log('Article image cleanup failed: ' . $filePath);
                                }
                            }
                        }
                        foreach ($articleBodies as $body) {
                            ArticleContent::deleteForContent($body['content'] ?? '');
                        }
                        foreach ($ids as $deletedArticleId) {
                            AiSummaryCache::deleteArticle($deletedArticleId);
                        }
                        $message = '已删除 ' . count($ids) . ' 篇文章及其相关数据';
                        $messageType = 'success';
                        break;
                }
            } catch (Exception $e) {
                $message = '操作失败: ' . $e->getMessage();
                $messageType = 'error';
            }

            // 文章置顶/发布/删除等变更会改变前台列表，清空页面缓存以立即生效
            if ($messageType === 'success') {
                PageCache::purgeAll();
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
    $totalArticles = db()->fetchColumn("SELECT COUNT(*) FROM app_article a WHERE {$where}", $params);
    $totalPages = ceil($totalArticles / $perPage);
    
    $articles = db()->fetchAll(
        "SELECT a.*, c.name as category_name, u.nickname as author_name,
                (SELECT COUNT(*) FROM app_comment WHERE article_id = a.id) as comment_count,
                (SELECT COUNT(*) FROM app_article_like WHERE article_id = a.id) as like_count
         FROM app_article a 
         LEFT JOIN app_category c ON a.category_id = c.id 
         LEFT JOIN app_admin u ON a.author_id = u.id 
         WHERE {$where}
         ORDER BY a.is_top DESC, a.created_at DESC 
         LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, $offset])
    );
    
    // 分类列表
    $categories = getCategories();
    
    // 统计
    $stats = [
        'total' => db()->fetchColumn("SELECT COUNT(*) FROM app_article") ?: 0,
        'published' => db()->fetchColumn("SELECT COUNT(*) FROM app_article WHERE status = 'published'") ?: 0,
        'draft' => db()->fetchColumn("SELECT COUNT(*) FROM app_article WHERE status = 'draft'") ?: 0,
    ];
    
} catch (Exception $e) {
    $articles = [];
    $totalPages = 0;
    $categories = [];
    $stats = ['total' => 0, 'published' => 0, 'draft' => 0];
}

require_once APP_ROOT . '/admin/template/header.php';
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
        <?php
        /*
         * 批量表单独立于表格存在：每行的「删除」按钮本身就是一个 form，
         * 若把表格包进批量表单会形成表单嵌套，HTML 解析器会丢弃内层 form 起始标签，
         * 于是行内的 action=delete 与 ids[] 并入外层表单 —— 批量提交时 action 被
         * 覆盖成 delete、ids 只剩第一行，点「批量发布」会删掉当前页第一篇。
         * 因此这里只保留一个空表单承载 csrf/action，勾选框改用 form 属性关联。
         */
        ?>
        <form method="POST" action="" id="batch-form">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" id="batch-action" value="">
        </form>

        <div style="padding: 16px; border-bottom: 1px solid var(--border-color); display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                <input type="checkbox" id="select-all" style="width: auto;">
                <span>全选</span>
            </label>
            <button type="button" class="btn btn-sm btn-success" data-batch-action="publish">批量发布</button>
            <button type="button" class="btn btn-sm btn-secondary" data-batch-action="draft">批量草稿</button>
            <button type="button" class="btn btn-sm btn-primary" data-batch-action="pin">批量置顶</button>
            <button type="button" class="btn btn-sm btn-secondary" data-batch-action="unpin">取消置顶</button>
            <button type="button" class="btn btn-sm btn-danger" data-batch-action="delete">批量删除</button>
            <span style="margin-left: auto; color: var(--text-light); font-size: 0.85rem;">
                共 <?php echo $stats['total']; ?> 篇 | 已发布 <?php echo $stats['published']; ?> | 草稿 <?php echo $stats['draft']; ?>
            </span>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 40px;"><input type="checkbox" id="select-all-header" style="width: auto;" aria-label="全选本页文章"></th>
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
                    <td><input type="checkbox" name="ids[]" value="<?php echo $article['id']; ?>" form="batch-form" class="article-checkbox" style="width: auto;" aria-label="选择文章 <?php echo e($article['title']); ?>"></td>
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
        
        <?php 
        $urlPattern = '/admin/articles.php?page=%d';
        if ($search) $urlPattern .= '&search=' . urlencode($search);
        if ($statusFilter !== 'all') $urlPattern .= '&status=' . urlencode($statusFilter);
        if ($categoryFilter > 0) $urlPattern .= '&category=' . $categoryFilter;
        echo pagination($page, $totalPages, $urlPattern); 
        ?>
    </div>
</div>

<script src="/assets/js/admin/admin-articles.js?v=<?php echo APP_VERSION; ?>"></script>

<?php require_once APP_ROOT . '/admin/template/footer.php'; ?>