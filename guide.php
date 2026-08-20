<?php
/**
 * 指引页（引导页）
 * 首次访问站点时自动跳转到此页；展示博主头像与在玩的游戏
 */
define('LM_ROOT', __DIR__);

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

lm_session_start();
Security::setSecurityHeaders();
lm_public_cache_headers();

$pageTitle = '指引';
$currentPage = 'guide';
$bodyClass = 'page-guide';
$extraJs = ['/assets/js/guide.js?v=' . LM_VERSION];

$siteName = getSetting('site_name', '林梦的博客');
$siteLogo = getSetting('site_logo', '');
$avatarUrl = !empty($siteLogo) ? $siteLogo : '/assets/images/default-avatar.png';
$games = getVisibleGames();

require_once LM_ROOT . '/template/header.php';
?>

<!-- Hero 区域 -->
<div class="card guide-hero">
    <div class="guide-hero-inner">
        <div class="guide-avatar-wrap">
            <img src="<?php echo e($avatarUrl); ?>" alt="<?php echo e($siteName); ?>的头像" class="guide-avatar" width="120" height="120">
        </div>
        <div class="guide-hero-text">
            <h1 class="guide-hero-title"><?php echo e($siteName); ?></h1>
        </div>
    </div>
</div>

<!-- 游戏区域 -->
<section class="guide-games card">
    <div class="guide-section-header">
        <h2 class="guide-section-title">在玩的游戏</h2>
        <span class="guide-section-hint">点击居中 · 可拖拽 · ←→ 切换</span>
    </div>
    <?php if (!empty($games)): ?>
    <div class="guide-carousel">
        <button type="button" id="guide-prev" class="guide-nav-btn guide-nav-prev" aria-label="上一个">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <div class="guide-stage" id="guide-stage">
            <div class="guide-track-inner" id="guide-track-inner">
                <?php foreach ($games as $g): ?>
                <article class="guide-card" tabindex="0">
                    <div class="guide-card-cover">
                        <?php if (!empty($g['image_url'])): ?>
                        <img src="<?php echo e($g['image_url']); ?>" alt="<?php echo e($g['name']); ?>" loading="lazy">
                        <?php else: ?>
                        <div class="guide-card-cover-placeholder"><?php echo e(mb_substr($g['name'], 0, 1)); ?></div>
                        <?php endif; ?>
                        <div class="guide-card-overlay">
                            <div class="guide-card-name"><?php echo e($g['name']); ?></div>
                            <?php if (!empty($g['description'])): ?>
                            <p class="guide-card-desc"><?php echo e($g['description']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
        <button type="button" id="guide-next" class="guide-nav-btn guide-nav-next" aria-label="下一个">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
    </div>
    <div class="guide-dots" id="guide-dots" role="tablist" aria-label="游戏切换"></div>
    <?php else: ?>
    <div class="guide-empty">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="6" x2="10" y1="11" y2="11"/><line x1="8" x2="8" y1="9" y2="13"/><line x1="15" x2="15.01" y1="12" y2="12"/><line x1="18" x2="18.01" y1="10" y2="10"/><path d="M17.32 5H6.68a4 4 0 0 0-3.978 3.59c-.006.052-.01.101-.017.152C2.604 9.416 2 14.456 2 16a3 3 0 0 0 3 3c1 0 1.5-.5 2-1l1.414-1.414A2 2 0 0 1 9.828 16h4.344a2 2 0 0 1 1.414.586L17 18c.5.5 1 1 2 1a3 3 0 0 0 3-3c0-1.545-.604-6.584-.685-7.258-.007-.05-.011-.1-.017-.152A4 4 0 0 0 17.32 5z"/></svg>
        <h3>暂无游戏</h3>
        <p>博主还没添加在玩的游戏～</p>
    </div>
    <?php endif; ?>
</section>

<!-- CTA 区域 -->
<div class="guide-cta">
    <a href="/index.php" class="btn btn-primary guide-cta-btn">进入博客</a>
    <a href="/about.php" class="btn btn-secondary">了解更多</a>
</div>

<?php require_once LM_ROOT . '/template/sidebar.php'; ?>
