<?php
/**
 * 镜像软件展示页
 * 按分区展示镜像软件，提供下载/官方入口
 */
define('LM_ROOT', __DIR__);

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

lm_session_start();
Security::setSecurityHeaders();

$pageTitle = '镜像软件';
$currentPage = 'mirror';
$bodyClass = 'page-mirror';

$categories = getMirrorCategories(true);

// 预加载每个分区下的可见软件
$grouped = [];
$totalSoftwares = 0;
foreach ($categories as $cat) {
    $list = getMirrorSoftwares($cat['id'], true);
    if (!empty($list)) {
        $grouped[] = ['category' => $cat, 'softwares' => $list];
        $totalSoftwares += count($list);
    }
}

// 如果没有可见分区但仍有未分类软件，单独展示
$uncategorized = getMirrorSoftwares(0, true);
if (!empty($uncategorized)) {
    $grouped[] = [
        'category' => ['id' => 0, 'name' => '未分类', 'icon' => '📦', 'description' => '未归类软件'],
        'softwares' => $uncategorized
    ];
    $totalSoftwares += count($uncategorized);
}

require_once LM_ROOT . '/template/header.php';
?>

<!-- Hero -->
<div class="card mirror-hero">
    <div class="mirror-hero-inner">
        <div class="mirror-hero-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        </div>
        <div class="mirror-hero-text">
            <h1 class="mirror-hero-title">镜像软件</h1>
            <p class="mirror-hero-subtitle">精选软件镜像下载，高速、稳定、可信</p>
        </div>
        <div class="mirror-hero-stats">
            <div class="mirror-hero-stat">
                <div class="mirror-hero-stat-value"><?php echo count($grouped); ?></div>
                <div class="mirror-hero-stat-label">分区</div>
            </div>
            <div class="mirror-hero-stat">
                <div class="mirror-hero-stat-value"><?php echo $totalSoftwares; ?></div>
                <div class="mirror-hero-stat-label">软件</div>
            </div>
        </div>
    </div>
</div>

<!-- 搜索 & 分区过滤 -->
<div class="mirror-toolbar">
    <div class="mirror-search">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="text" id="mirror-search-input" placeholder="搜索软件名称或描述..." autocomplete="off">
    </div>
    <div class="mirror-filter" id="mirror-filter">
        <button type="button" class="mirror-filter-item active" data-cat="all">全部</button>
        <?php foreach ($grouped as $g): ?>
        <button type="button" class="mirror-filter-item" data-cat="<?php echo (int)$g['category']['id']; ?>">
            <?php if (!empty($g['category']['icon']) && !preg_match('#^https?://#i', $g['category']['icon'])): ?>
                <span class="mirror-filter-emoji"><?php echo e($g['category']['icon']); ?></span>
            <?php endif; ?>
            <?php echo e($g['category']['name']); ?>
        </button>
        <?php endforeach; ?>
    </div>
</div>

<?php if (empty($grouped)): ?>
<!-- 空状态 -->
<div class="empty-state card">
    <div class="empty-state-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    </div>
    <h3>暂无镜像软件</h3>
    <p>管理员还没有发布任何镜像软件，稍后再来看看吧～</p>
</div>
<?php else: ?>

<!-- 分区列表 -->
<?php foreach ($grouped as $g): ?>
<section class="mirror-section" data-cat-id="<?php echo (int)$g['category']['id']; ?>">
    <div class="mirror-section-header">
        <div class="mirror-section-title">
            <?php if (!empty($g['category']['icon'])): ?>
                <?php if (preg_match('#^https?://#i', $g['category']['icon'])): ?>
                <img src="<?php echo e($g['category']['icon']); ?>" alt="" class="mirror-section-icon">
                <?php else: ?>
                <span class="mirror-section-emoji"><?php echo e($g['category']['icon']); ?></span>
                <?php endif; ?>
            <?php else: ?>
            <span class="mirror-section-emoji">📁</span>
            <?php endif; ?>
            <span><?php echo e($g['category']['name']); ?></span>
            <span class="mirror-section-count"><?php echo count($g['softwares']); ?></span>
        </div>
        <?php if (!empty($g['category']['description'])): ?>
        <div class="mirror-section-desc"><?php echo e($g['category']['description']); ?></div>
        <?php endif; ?>
    </div>

    <div class="mirror-grid">
        <?php foreach ($g['softwares'] as $index => $sw): ?>
        <article class="mirror-card"
                 data-cat-id="<?php echo (int)$g['category']['id']; ?>"
                 data-keywords="<?php echo e(strtolower($sw['name'] . ' ' . ($sw['description'] ?? '') . ' ' . $sw['version'])); ?>"
                 style="animation-delay: <?php echo min($index * 0.04, 0.4); ?>s;">
            <div class="mirror-card-top">
                <div class="mirror-card-icon">
                    <?php if (!empty($sw['icon_url'])): ?>
                    <img src="<?php echo e($sw['icon_url']); ?>" alt="<?php echo e($sw['name']); ?> 图标" loading="lazy">
                    <?php else: ?>
                    <div class="mirror-card-icon-placeholder"><?php echo mb_substr($sw['name'], 0, 1); ?></div>
                    <?php endif; ?>
                </div>
                <div class="mirror-card-head">
                    <div class="mirror-card-name"><?php echo e($sw['name']); ?></div>
                    <?php if (!empty($sw['version'])): ?>
                    <span class="mirror-card-version">v<?php echo e($sw['version']); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($sw['description'])): ?>
            <p class="mirror-card-desc"><?php echo e($sw['description']); ?></p>
            <?php else: ?>
            <p class="mirror-card-desc mirror-card-desc-empty">暂无介绍</p>
            <?php endif; ?>

            <div class="mirror-card-actions">
                <?php if (!empty($sw['download_url'])): ?>
                <a href="<?php echo e($sw['download_url']); ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm mirror-card-btn-download">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    下载
                </a>
                <?php endif; ?>
                <?php if (!empty($sw['official_url'])): ?>
                <a href="<?php echo e($sw['official_url']); ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm mirror-card-btn-official">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 7h2a5 5 0 0 1 0 10h-2"/><path d="M9 17H7A5 5 0 0 1 7 7h2"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    官网
                </a>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endforeach; ?>

<!-- 无结果提示 -->
<div class="mirror-empty-result" id="mirror-empty-result" style="display:none;">
    <div class="empty-state card">
        <div class="empty-state-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        </div>
        <h3>未找到匹配的软件</h3>
        <p>尝试换个关键词或切换其他分区</p>
    </div>
</div>

<?php endif; ?>

<script>
/* 镜像页前端交互：搜索 + 分区过滤 */
(function () {
    var searchInput = document.getElementById('mirror-search-input');
    var filterBtns = document.querySelectorAll('.mirror-filter-item');
    var sections = document.querySelectorAll('.mirror-section');
    var emptyResult = document.getElementById('mirror-empty-result');
    var currentCat = 'all';
    var currentKw = '';

    function applyFilter() {
        var visibleCount = 0;
        sections.forEach(function (sec) {
            var catId = sec.getAttribute('data-cat-id');
            var catMatch = currentCat === 'all' || currentCat === catId;
            var cards = sec.querySelectorAll('.mirror-card');
            var secVisible = 0;
            cards.forEach(function (card) {
                var kw = card.getAttribute('data-keywords') || '';
                var kwMatch = !currentKw || kw.indexOf(currentKw) !== -1;
                var show = catMatch && kwMatch;
                card.style.display = show ? '' : 'none';
                if (show) secVisible++;
            });
            sec.style.display = (catMatch && secVisible > 0) ? '' : 'none';
            if (catMatch && secVisible > 0) visibleCount += secVisible;
        });
        if (emptyResult) emptyResult.style.display = visibleCount === 0 ? '' : 'none';
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            currentKw = searchInput.value.trim().toLowerCase();
            applyFilter();
        });
    }

    filterBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            filterBtns.forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            currentCat = btn.getAttribute('data-cat');
            applyFilter();
        });
    });
})();
</script>

<?php require_once LM_ROOT . '/template/sidebar.php'; ?>