<?php
/**
 * 说说页
 * 时间线形式展示站长发布的说说（文字 + 图片 + 心情）
 */
define('APP_ROOT', __DIR__);

require_once APP_ROOT . '/includes/config.php';
require_once APP_ROOT . '/includes/Security.php';
require_once APP_ROOT . '/includes/Database.php';
require_once APP_ROOT . '/includes/functions.php';

app_session_start();
Security::setSecurityHeaders();
app_public_cache_headers();

// 静态页面缓存（仅匿名 GET/HEAD，白名单外的 query 不缓存）
require_once APP_ROOT . '/includes/PageCache.php';
PageCache::start(['page']);

$pageTitle = '说说';
$currentPage = 'shuoshuo';
$bodyClass = 'page-shuoshuo';
$extraCss = ['/assets/css/shuoshuo.css?v=' . APP_VERSION];

$perPage = 10;
$totalShuoshuo = getShuoshuoCount();
$totalPages = max(1, (int)ceil($totalShuoshuo / $perPage));
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$items = getVisibleShuoshuo($perPage, $offset);
$avatar = getSetting('site_logo', '');

require_once APP_ROOT . '/template/header.php';
?>

<!-- Hero 区域 -->
<div class="card shuoshuo-hero">
    <h1 class="shuoshuo-hero-title">说说</h1>
    <p class="shuoshuo-hero-subtitle">记录碎片化的心情与日常</p>
    <div class="shuoshuo-hero-stats">
        <div class="shuoshuo-hero-stat">
            <div class="shuoshuo-hero-stat-value"><?php echo $totalShuoshuo; ?></div>
            <div class="shuoshuo-hero-stat-label">条说说</div>
        </div>
    </div>
</div>

<?php if (!empty($items)): ?>
<!-- 说说时间线 -->
<div class="shuoshuo-timeline">
    <?php foreach ($items as $item): ?>
    <?php
        $images = parseShuoshuoImages($item['images']);
        $mood = trim((string)($item['mood'] ?? ''));
    ?>
    <article class="card shuoshuo-item">
        <div class="shuoshuo-item-header">
            <img src="<?php echo e($avatar ?: '/assets/images/default-avatar.png'); ?>" alt="<?php echo e(getSetting('site_author', '') ?: getSetting('site_name', '我的博客')); ?>" class="shuoshuo-avatar" width="48" height="48" loading="lazy">
            <div class="shuoshuo-item-meta">
                <div class="shuoshuo-item-author"><?php echo e(getSetting('site_author', '') ?: getSetting('site_name', '我的博客')); ?></div>
                <time class="shuoshuo-item-time" datetime="<?php echo e(date('c', applyTimeOffset($item['created_at']))); ?>"><?php echo timeAgo($item['created_at']); ?></time>
            </div>
            <?php if ($mood !== ''): ?>
            <span class="shuoshuo-mood"><?php echo e($mood); ?></span>
            <?php endif; ?>
        </div>
        <?php if (trim((string)$item['content']) !== ''): ?>
        <div class="shuoshuo-item-content"><?php echo nl2br(e($item['content'])); ?></div>
        <?php endif; ?>
        <?php if (!empty($images)): ?>
        <div class="shuoshuo-item-images<?php echo count($images) === 1 ? ' shuoshuo-item-images--single' : ''; ?>">
            <?php foreach ($images as $img): ?>
            <img src="<?php echo e($img); ?>" alt="说说配图" class="shuoshuo-item-image" loading="lazy" decoding="async">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="shuoshuo-item-footer">
            <?php echo formatDate($item['created_at']); ?>
        </div>
    </article>
    <?php endforeach; ?>
</div>

<?php echo pagination($page, $totalPages, '/shuoshuo.php?page=%d'); ?>

<?php else: ?>
<!-- 空状态 -->
<div class="empty-state card">
    <div class="empty-state-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/><path d="M8 12h.01"/><path d="M12 12h.01"/><path d="M16 12h.01"/></svg>
    </div>
    <h3>还没有说说</h3>
    <p>博主还什么都没说～</p>
    <a href="/" class="btn btn-primary" style="margin-top:16px;">回首页看看</a>
</div>
<?php endif; ?>

<?php require_once APP_ROOT . '/template/sidebar.php'; ?>
