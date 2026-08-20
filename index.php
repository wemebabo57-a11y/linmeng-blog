<?php
/**
 * 首页 v2.1
 * 默认展示最新4篇或置顶文章，支持展开查看全部
 */
define('LM_ROOT', __DIR__);

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

lm_session_start();
Security::setSecurityHeaders();

// 首次访问跳转到指引页
if (!isset($_COOKIE['lm_guide_seen'])) {
    $isHttpsGuide = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    setcookie('lm_guide_seen', '1', [
        'expires' => time() + 365 * 86400,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttpsGuide,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    header('Location: /guide.php', true, 302);
    exit;
}

// 匿名访客输出 CDN 可缓存的 Cache-Control（须在上述重定向之后，避免 302 被共享缓存）
lm_public_cache_headers();

$pageTitle = '首页';
$currentPage = 'home';
$siteName = getSetting('site_name', '林梦的博客');
$siteDesc = getSetting('site_description', '记录生活，分享技术');
$runningDays = getRunningDays();
$siteBackground = getSetting('site_background', '');
$siteBackgroundPosition = getSetting('site_background_position', 'center center');
$siteBackgroundSize = getSetting('site_background_size', 'cover');
$siteBackgroundOverlay = getSetting('site_background_overlay', '0.45');
// 校验遮罩强度范围
$siteBackgroundOverlay = max(0, min(0.85, (float)$siteBackgroundOverlay));

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$showAll = isset($_GET['all']) && $_GET['all'] == '1';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'latest';
if (!in_array($sort, ['latest', 'hot', 'top'], true)) {
    $sort = 'latest';
}

// 分页（仅在展开全部时使用）
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$params = [];
$where = "a.status = 'published'";

if ($search) {
    $where .= ' AND (a.title LIKE ? OR a.content LIKE ? OR a.tags LIKE ?)';
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($categoryId > 0) {
    $where .= ' AND a.category_id = ?';
    $params[] = $categoryId;
}

// 获取文章列表
try {
    $totalArticles = db()->fetchColumn("SELECT COUNT(*) FROM lm_article a WHERE {$where}", $params);
    $totalPages = ceil($totalArticles / $perPage);
    $orderBy = $sort === 'hot' ? 'a.is_top DESC, a.views DESC, a.created_at DESC' : ($sort === 'top' ? 'a.is_top DESC, a.created_at DESC' : 'a.is_top DESC, a.created_at DESC');

    // 搜索/筛选时直接展示全部（带分页），否则默认只展示4篇
    if ($search || $categoryId > 0 || $showAll) {
        $displayLimit = $perPage;
        $displayOffset = $offset;
    } else {
        $displayLimit = 4;
        $displayOffset = 0;
    }

    $articles = db()->fetchAll(
        "SELECT a.*, c.name as category_name, u.nickname as author_name, u.avatar as author_avatar
         FROM lm_article a
         LEFT JOIN lm_category c ON a.category_id = c.id
         LEFT JOIN lm_admin u ON a.author_id = u.id
         WHERE {$where}
         ORDER BY {$orderBy}
         LIMIT ? OFFSET ?",
        array_merge($params, [$displayLimit, $displayOffset])
    );

    // 去重：防止 JOIN 或数据异常导致同一文章出现多次
    $seenIds = [];
    $uniqueArticles = [];
    foreach ($articles as $article) {
        if (!isset($seenIds[$article['id']])) {
            $seenIds[$article['id']] = true;
            $uniqueArticles[] = $article;
        }
    }
    $articles = $uniqueArticles;

    // 获取文章相关图片和点赞数（批量查询，避免列表页 N+1 请求）
    $articleIds = array_map('intval', array_column($articles, 'id'));
    $imagesByArticle = [];
    $likesByArticle = [];
    if (!empty($articleIds)) {
        $placeholders = implode(',', array_fill(0, count($articleIds), '?'));
        $imageRows = db()->fetchAll(
            "SELECT article_id, image_url FROM lm_article_image
             WHERE article_id IN ({$placeholders})
             ORDER BY sort_order ASC, id ASC",
            $articleIds
        );
        foreach ($imageRows as $imageRow) {
            $imageArticleId = (int)$imageRow['article_id'];
            if (!isset($imagesByArticle[$imageArticleId])) {
                $imagesByArticle[$imageArticleId] = [];
            }
            if (count($imagesByArticle[$imageArticleId]) < 3) {
                $imagesByArticle[$imageArticleId][] = $imageRow;
            }
        }

        $likeRows = db()->fetchAll(
            "SELECT article_id, COUNT(*) AS like_count
             FROM lm_article_like
             WHERE article_id IN ({$placeholders})
             GROUP BY article_id",
            $articleIds
        );
        foreach ($likeRows as $likeRow) {
            $likesByArticle[(int)$likeRow['article_id']] = (int)$likeRow['like_count'];
        }
    }

    foreach ($articles as &$article) {
        $articleId = (int)$article['id'];
        $article['images'] = $imagesByArticle[$articleId] ?? [];
        $article['like_count'] = $likesByArticle[$articleId] ?? 0;
    }
    unset($article);

} catch (Exception $e) {
    $articles = [];
    $totalPages = 0;
    $totalArticles = 0;
}

// 获取分类列表
$categories = getCategories();

// 获取热门文章
$hotArticles = getHotArticles(5);

$baseQuery = [];
if ($search !== '') {
    $baseQuery['search'] = $search;
}
if ($sort !== 'latest') {
    $baseQuery['sort'] = $sort;
}
if ($categoryId > 0) {
    $baseQuery['category'] = $categoryId;
}
if ($showAll) {
    $baseQuery['all'] = 1;
}
$makeHomeUrl = function (array $overrides = []) use ($baseQuery) {
    $query = array_merge($baseQuery, $overrides);
    foreach ($query as $key => $value) {
        if ($value === '' || $value === null || $value === false) {
            unset($query[$key]);
        }
    }
    return '/?' . http_build_query($query);
};

// 是否显示"查看全部"按钮
$showViewAllBtn = !$search && $categoryId == 0 && !$showAll && $totalArticles > 4;

require_once LM_ROOT . '/template/header.php';
?>

<section class="home-hero reveal-item<?php echo $siteBackground ? ' has-bg' : ''; ?>"<?php echo $siteBackground ? ' style="background-image: url(\'' . e($siteBackground) . '\'); background-position: ' . e($siteBackgroundPosition) . '; background-size: ' . e($siteBackgroundSize) . ';"' : ''; ?>>
    <div class="home-hero-overlay"<?php echo $siteBackground ? ' style="background: linear-gradient(135deg, rgba(0,0,0,' . $siteBackgroundOverlay . '), rgba(0,0,0,' . max(0, $siteBackgroundOverlay - 0.2) . ');"' : ''; ?>></div>
    <div class="home-hero-content">
        <div class="home-eyebrow">最新记录 · <?php echo date('Y.m.d'); ?></div>
        <h1><?php echo e($siteName); ?></h1>
        <p><?php echo e($siteDesc); ?></p>
        <div class="home-hero-actions">
            <a href="/?all=1" class="btn btn-hero">查看文章</a>
            <button type="button" class="btn btn-hero" data-open-search>搜索内容</button>
        </div>
    </div>
    <div class="home-hero-panel home-hero-summary" aria-label="站点概览">
        <div class="hero-stat-card">
            <span><?php echo (int)$totalArticles; ?></span>
            <small><?php echo ($search || $categoryId > 0) ? '匹配文章' : '文章'; ?></small>
        </div>
        <div class="hero-stat-card">
            <span><?php echo (int)getCommentCount(); ?></span>
            <small>评论</small>
        </div>
        <div class="hero-stat-card">
            <span><?php echo (int)$runningDays; ?></span>
            <small>运行天数</small>
        </div>
    </div>
</section>

<!-- 搜索和筛选 -->
<div class="card home-filter-card">
    <div class="card-body">
        <form method="GET" action="" class="search-box home-search-bar">
            <label for="home-search-input" class="visually-hidden">搜索文章</label>
            <input type="text" id="home-search-input" name="search" class="form-input" placeholder="搜索文章..." value="<?php echo e($search); ?>" autocomplete="off">
            <input type="hidden" name="sort" value="<?php echo e($sort); ?>">
            <?php if ($categoryId > 0): ?>
            <input type="hidden" name="category" value="<?php echo (int)$categoryId; ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">搜索</button>
            <?php if ($search || $categoryId > 0): ?>
            <a href="/" class="btn btn-secondary">清除</a>
            <?php endif; ?>
        </form>

        <div class="home-filter-row">
            <?php if (!empty($categories)): ?>
            <div class="home-filter-group">
                <span class="home-filter-label">分类</span>
                <div class="category-filter" aria-label="按分类筛选">
                    <a href="<?php echo e($makeHomeUrl(['category' => null, 'page' => null])); ?>" class="<?php echo $categoryId === 0 ? 'active' : ''; ?>"<?php echo $categoryId === 0 ? ' aria-current="page"' : ''; ?>>全部</a>
                    <?php foreach ($categories as $cat): ?>
                    <a href="<?php echo e($makeHomeUrl(['category' => (int)$cat['id'], 'page' => null])); ?>" class="<?php echo $categoryId === (int)$cat['id'] ? 'active' : ''; ?>"<?php echo $categoryId === (int)$cat['id'] ? ' aria-current="page"' : ''; ?>>
                        <?php echo e($cat['name']); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="home-filter-group home-sort-group">
                <span class="home-filter-label">排序</span>
                <div class="category-filter home-sort-filter" aria-label="排序方式">
                    <a href="<?php echo e($makeHomeUrl(['sort' => 'latest', 'page' => null])); ?>" class="<?php echo $sort === 'latest' ? 'active' : ''; ?>"<?php echo $sort === 'latest' ? ' aria-current="page"' : ''; ?>>最新</a>
                    <a href="<?php echo e($makeHomeUrl(['sort' => 'hot', 'page' => null])); ?>" class="<?php echo $sort === 'hot' ? 'active' : ''; ?>"<?php echo $sort === 'hot' ? ' aria-current="page"' : ''; ?>>热门</a>
                    <a href="<?php echo e($makeHomeUrl(['sort' => 'top', 'page' => null])); ?>" class="<?php echo $sort === 'top' ? 'active' : ''; ?>"<?php echo $sort === 'top' ? ' aria-current="page"' : ''; ?>>置顶</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 文章列表 -->
<div class="card article-list-card">
    <div class="card-header article-list-toolbar">
        <div class="card-title">文章列表</div>
        <div class="article-list-summary"><?php echo $showAll || $search || $categoryId > 0 ? '共 ' . (int)$totalArticles . ' 篇' : '显示前 ' . count($articles) . ' 篇 / 共 ' . (int)$totalArticles . ' 篇'; ?></div>
        <?php if (!$search && $categoryId == 0 && !$showAll && $totalArticles > 4): ?>
        <a href="?all=1&sort=<?php echo e($sort); ?>" class="article-list-more">查看全部</a>
        <?php endif; ?>
    </div>
    <?php if (!empty($articles)): ?>
        <?php foreach ($articles as $article): ?>
        <article class="article-item">
            <?php if (!empty($article['cover_image'])): ?>
            <a href="/article.php?slug=<?php echo e($article['slug']); ?>">
                <img src="<?php echo e($article['cover_image']); ?>" alt="<?php echo e($article['title']); ?>" class="article-cover" loading="lazy" decoding="async" width="800" height="450">
            </a>
            <?php elseif (!empty($article['images'][0]['image_url'])): ?>
            <a href="/article.php?slug=<?php echo e($article['slug']); ?>">
                <img src="<?php echo e($article['images'][0]['image_url']); ?>" alt="<?php echo e($article['title']); ?>" class="article-cover" loading="lazy" decoding="async" width="800" height="450">
            </a>
            <?php endif; ?>

            <h2 class="article-title">
                <a href="/article.php?slug=<?php echo e($article['slug']); ?>">
                    <?php if ($article['is_top']): ?>
                    <span class="pin-badge"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V7a1 1 0 0 1 1-1 2 2 0 0 0 0-4H8a2 2 0 0 0 0 4 1 1 0 0 1 1 1z"/></svg> 置顶</span>
                    <?php endif; ?>
                    <?php echo e($article['title']); ?>
                </a>
            </h2>

            <div class="article-meta">
                <span class="meta-item"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg> <?php echo timeAgo($article['created_at']); ?></span>
                <?php if ($article['category_name']): ?>
                <span class="meta-item"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg> <?php echo e($article['category_name']); ?></span>
                <?php endif; ?>
                <span class="meta-item"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg> <?php echo $article['views']; ?> 阅读</span>
            </div>

            <div class="article-excerpt">
                <?php echo e($article['excerpt'] ?: getExcerpt($article['content'], 80)); ?>
            </div>

            <div class="article-actions">
                <a href="/article.php?slug=<?php echo e($article['slug']); ?>" class="read-more-link">阅读全文</a>
            </div>

            <?php if ($article['tags']): ?>
            <div class="article-tags">
                <?php foreach (explode(',', $article['tags']) as $tagIndex => $tag): ?>
                    <?php if ($tagIndex >= 2) break; ?>
                    <?php $tag = trim($tag); ?>
                    <?php if ($tag !== ''): ?>
                    <a href="/?search=<?php echo urlencode($tag); ?>" class="tag"><?php echo e($tag); ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>

        <!-- 展开查看全部按钮 -->
        <?php if ($showViewAllBtn): ?>
        <div class="article-list-footer-actions">
            <a href="?all=1&sort=<?php echo e($sort); ?>" class="btn btn-primary" id="view-all-btn">展开查看全部 <?php echo (int)$totalArticles; ?> 篇文章</a>
        </div>
        <?php endif; ?>

        <!-- 收起按钮 -->
        <?php if ($showAll && !$search && $categoryId == 0): ?>
        <div class="article-list-footer-actions">
            <a href="/?sort=<?php echo e($sort); ?>" class="btn btn-secondary">收起</a>
        </div>
        <?php endif; ?>

        <?php
        // 分页仅在展开全部或搜索/筛选时显示
        if ($showAll || $search || $categoryId > 0) {
            $urlPattern = '/?page=%d';
            if ($showAll) $urlPattern .= '&all=1';
            if ($sort !== 'latest') $urlPattern .= '&sort=' . urlencode($sort);
            if ($search) $urlPattern .= '&search=' . urlencode($search);
            if ($categoryId > 0) $urlPattern .= '&category=' . $categoryId;
            echo pagination($page, $totalPages, $urlPattern);
        }
        ?>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/></svg></div>
            <h3>暂无文章</h3>
            <p><?php echo $search ? '没有找到匹配的文章' : '博主还没有发布任何文章'; ?></p>
        </div>
    <?php endif; ?>
</div>

<?php require_once LM_ROOT . '/template/bottom-widgets.php'; ?>

<?php require_once LM_ROOT . '/template/sidebar.php'; ?>
