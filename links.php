<?php
/**
 * 友链展示页
 * 独立页面展示所有可见友链，支持搜索与复制站点信息
 */
define('LM_ROOT', __DIR__);

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
Security::setSecurityHeaders();

$pageTitle = '友链';
$currentPage = 'links';
$bodyClass = 'page-links';
$extraJs = ['/assets/js/starfield.js?v=' . LM_VERSION, '/assets/js/links.js?v=' . LM_VERSION];

$links = getVisibleLinks();
$siteName = getSetting('site_name', '林梦的博客');

require_once LM_ROOT . '/template/header.php';
?>

<!-- Hero 区域 -->
<div class="card links-hero">
    <h1 class="links-hero-title">Friends</h1>
    <p class="links-hero-subtitle">这里汇聚了一群有趣的灵魂，按 Ctrl/⌘ + K 可快速搜索</p>
    <div class="links-stats">
        <div class="links-stat">
            <div class="links-stat-value" id="links-count"><?php echo count($links); ?></div>
            <div class="links-stat-label">位小伙伴</div>
        </div>
    </div>
</div>

<!-- 工具栏 -->
<div class="links-toolbar">
    <div class="search-box">
        <input type="text" id="links-search" class="form-input" placeholder="搜索友链名称、描述或网址..." autocomplete="off">
        <button type="button" class="btn btn-primary" id="links-search-btn">搜索</button>
    </div>
    <div class="links-actions">
        <button type="button" class="btn btn-secondary" id="copy-site-info"
                data-info="<?php echo e($siteName . ' (' . SITE_URL . ')'); ?>">
            复制本站信息
        </button>
        <a href="/link-apply.php" class="btn btn-primary">申请友链</a>
    </div>
</div>

<?php if (!empty($links)): ?>
<!-- 友链网格 -->
<div class="links-section">
    <div class="links-grid" id="links-grid">
        <?php foreach ($links as $index => $link): ?>
        <a href="<?php echo e($link['url']); ?>" target="_blank" rel="noopener" class="link-card"
           data-keywords="<?php echo e(strtolower($link['name'] . ' ' . ($link['description'] ?? '') . ' ' . $link['url'])); ?>"
           style="animation-delay: <?php echo min($index * 0.03, 0.5); ?>s;">
            <?php if (!empty($link['logo'])): ?>
            <img src="<?php echo e($link['logo']); ?>" alt="" class="link-card-avatar" loading="lazy">
            <?php else: ?>
            <div class="link-card-avatar link-card-avatar-placeholder">
                <?php echo mb_substr($link['name'], 0, 1); ?>
            </div>
            <?php endif; ?>
            <div class="link-card-body">
                <div class="link-card-name"><?php echo e($link['name']); ?></div>
                <div class="link-card-url">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    <span><?php echo e(truncate($link['url'], 40)); ?></span>
                </div>
                <div class="link-card-desc">
                    <?php echo e($link['description'] ?: '暂无描述'); ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<!-- 空状态 -->
<div class="empty-state card">
    <div class="empty-state-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
    </div>
    <h3>还没有友链</h3>
    <p>成为第一位小伙伴吧！</p>
    <a href="/link-apply.php" class="btn btn-primary" style="margin-top:16px;">申请友链</a>
</div>
<?php endif; ?>

<?php require_once LM_ROOT . '/template/sidebar.php'; ?>
