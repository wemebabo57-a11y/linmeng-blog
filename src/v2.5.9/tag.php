<?php
/**
 * 标签落地页（SEO）：/tag.php?tag=xxx
 * 独立可收录地址，替代原来分散权重的 /?search=xxx。
 */
define('APP_ROOT', __DIR__);

require_once APP_ROOT . '/includes/config.php';
require_once APP_ROOT . '/includes/Security.php';
require_once APP_ROOT . '/includes/Database.php';
require_once APP_ROOT . '/includes/functions.php';

app_session_start();
Security::setSecurityHeaders();
app_public_cache_headers();

require_once APP_ROOT . '/includes/PageCache.php';
PageCache::start(['tag', 'page']);

$tag = (isset($_GET['tag']) && is_string($_GET['tag'])) ? trim($_GET['tag']) : '';
if ($tag === '') {
    Security::redirect('/tags.php');
}

$page = (isset($_GET['page']) && is_string($_GET['page'])) ? max(1, (int)$_GET['page']) : 1;
$page = min($page, 100000);
$perPage = 10;
$offset = ($page - 1) * $perPage;

try {
    $totalArticles = (int)db()->fetchColumn(
        "SELECT COUNT(*) FROM app_article WHERE status = 'published' AND tags LIKE ?",
        ['%' . $tag . '%']
    );
    $totalPages = max(1, (int)ceil($totalArticles / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }
    $articles = db()->fetchAll(
        "SELECT a.*, c.name as category_name " .
        "FROM app_article a LEFT JOIN app_category c ON a.category_id = c.id " .
        "WHERE a.status = 'published' AND a.tags LIKE ? " .
        "ORDER BY a.is_top DESC, a.created_at DESC LIMIT ? OFFSET ?",
        ['%' . $tag . '%', $perPage, $offset]
    );
} catch (Exception $e) {
    $articles = [];
    $totalArticles = 0;
    $totalPages = 1;
}

$pageTitle = '标签：' . $tag;
$currentPage = 'tags';
$seoTitle = '标签：' . $tag;
$seoDescription = '「' . $tag . '」相关文章共 ' . (int)$totalArticles . ' 篇。';
$seoKeywords = $tag;
$seoCanonical = '/tag.php?tag=' . $tag;

require_once APP_ROOT . '/template/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title">标签：<?php echo e($tag); ?></h1>
        <div class="article-list-summary">共 <?php echo (int)$totalArticles; ?> 篇 · <a href="/tags.php">全部标签</a></div>
    </div>
    <div class="card-body">
        <?php if (!empty($articles)): ?>
            <?php foreach ($articles as $article): ?>
            <article class="article-item">
                <div class="article-item-body">
                    <h2 class="article-title">
                        <a href="/article.php?slug=<?php echo e($article['slug']); ?>"><?php echo e($article['title']); ?></a>
                    </h2>
                    <div class="article-meta">
                        <span class="meta-item"><?php echo timeAgo($article['created_at']); ?></span>
                        <?php if (!empty($article['category_name'])): ?>
                        <span class="meta-item"><?php echo e($article['category_name']); ?></span>
                        <?php endif; ?>
                        <span class="meta-item"><?php echo (int)$article['views']; ?> 阅读</span>
                    </div>
                    <div class="article-excerpt"><?php echo e($article['excerpt'] ?: getExcerpt($article['content'], 80)); ?></div>
                </div>
            </article>
            <?php endforeach; ?>
            <?php echo pagination($page, $totalPages, '/tag.php?tag=' . urlencode($tag) . '&page=%d'); ?>
        <?php else: ?>
        <div class="empty-state" style="padding: 60px 20px;">
            <h3>这个标签下还没有文章</h3>
            <p><a href="/tags.php">看看其他标签</a> · <a href="/">回首页</a></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once APP_ROOT . '/template/sidebar.php'; ?>
