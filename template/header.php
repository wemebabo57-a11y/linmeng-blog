<?php
/**
 * 公共头部模板 v2.0
 * 支持深色/浅色主题切换
 */
if (!defined('LM_ROOT')) {
    die('Access Denied');
}

$siteName = getSetting('site_name', '林梦的博客');
$siteDesc = getSetting('site_description', '记录生活，分享技术');
$siteKeywords = getSetting('site_keywords', '林梦,博客,技术,生活');
$favicon = getSetting('site_favicon', '');

// 全站背景图设置（在 body 上应用，所有页面生效）
$siteBackground = getSetting('site_background', '');
$siteBackgroundPosition = getSetting('site_background_position', 'center center');
$siteBackgroundSize = getSetting('site_background_size', 'cover');
$siteBackgroundOverlay = getSetting('site_background_overlay', '0.45');
$siteBackgroundOverlay = max(0, min(0.85, (float)$siteBackgroundOverlay));
if ($siteBackground !== '' && !isValidImageUrl($siteBackground)) {
    $siteBackground = '';
}
if (!preg_match('/^(?:left|center|right|top|bottom|\d{1,3}(?:\.\d+)?%)(?:\s+(?:left|center|right|top|bottom|\d{1,3}(?:\.\d+)?%))?$/i', $siteBackgroundPosition)) {
    $siteBackgroundPosition = 'center center';
}
if (!preg_match('/^(?:cover|contain|auto|\d{1,4}(?:\.\d+)?(?:px|%|vw|vh))(?:\s+(?:auto|\d{1,4}(?:\.\d+)?(?:px|%|vw|vh)))?$/i', $siteBackgroundSize)) {
    $siteBackgroundSize = 'cover';
}
$bodyClasses = trim((string)($bodyClass ?? ''));
if ($siteBackground !== '') {
    $bodyClasses = trim($bodyClasses . ' has-site-background');
}
$bodyStyle = '';
if ($siteBackground !== '') {
    $bodyStyle = '--site-background-image:url(' . json_encode($siteBackground, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ');'
        . '--site-background-position:' . $siteBackgroundPosition . ';'
        . '--site-background-size:' . $siteBackgroundSize . ';'
        . '--site-background-overlay:' . $siteBackgroundOverlay . ';';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo e($siteDesc); ?>">
    <meta name="keywords" content="<?php echo e($siteKeywords); ?>">
    <meta name="author" content="林梦">
    <meta name="csrf-token" content="<?php echo Security::generateToken(); ?>">
    <meta name="csrf-token-name" content="<?php echo e(CSRF_TOKEN_NAME); ?>">

    <title><?php echo isset($pageTitle) ? e($pageTitle) . ' - ' : ''; ?><?php echo e($siteName); ?></title>

    <?php if ($favicon): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo e($favicon); ?>">
    <?php endif; ?>

    <!-- 主题初始化（内联，避免闪烁且省一次请求） -->
    <script>
    (function() {
        var theme = 'auto';
        try {
            theme = localStorage.getItem('theme') || 'auto';
        } catch (e) {
            theme = 'auto';
        }
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else if (theme === 'light') {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    })();
    </script>

    <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo LM_VERSION; ?>">
    <link rel="stylesheet" href="/assets/css/design-system.css?v=<?php echo LM_VERSION; ?>">
    <link rel="stylesheet" href="/assets/css/theme-refresh.css?v=<?php echo LM_VERSION; ?>">
    <link rel="alternate" type="application/rss+xml" title="<?php echo e($siteName); ?> RSS" href="/rss.php">

    <!-- 正文优先使用系统中文字体，避免远程字体阻塞首屏。 -->

    <?php if (!empty($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
        <link rel="stylesheet" href="<?php echo e($css); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="<?php echo e($bodyClasses); ?>"<?php echo $bodyStyle !== '' ? ' style="' . e($bodyStyle) . '"' : ''; ?><?php echo !empty($articleViewId) ? ' data-article-id="' . (int)$articleViewId . '"' : ''; ?>>
    <?php if ($currentPage === 'home'): ?>
    <!-- 首页保留少量装饰，详情和工具页不再承担持续动画开销。 -->
    <div class="sakura-container" aria-hidden="true">
        <?php for ($i = 0; $i < 6; $i++): ?>
        <span class="sakura" style="left:<?php echo 8 + ($i * 17); ?>%; animation-duration:<?php echo 11 + ($i % 3) * 2; ?>s; animation-delay:<?php echo $i * 2; ?>s;"></span>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <a href="#main-content" class="skip-link">跳至主要内容</a>
    <!-- 图片灯箱 -->
    <div class="lightbox" role="dialog" aria-modal="true" aria-label="图片预览" aria-hidden="true">
        <button type="button" class="lightbox-close" aria-label="关闭图片预览">&times;</button>
        <img src="" alt="预览图片">
    </div>

    <!-- 全局搜索浮层 -->
    <div class="search-overlay" id="search-overlay" role="dialog" aria-modal="true" aria-labelledby="global-search-title" aria-hidden="true">
        <div class="search-overlay-backdrop"></div>
        <div class="search-overlay-panel">
            <div class="search-overlay-header">
                <span id="global-search-title" class="visually-hidden">全局搜索</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" id="global-search-input" placeholder="搜索文章标题、标签或内容..." autocomplete="off" aria-label="搜索">
                <button type="button" class="search-overlay-close" id="search-overlay-close" aria-label="关闭搜索">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
            <div class="search-overlay-body">
                <div class="search-hint">输入关键词后按 Enter 跳转搜索页</div>
                <div class="search-results" id="search-results"></div>
            </div>
            <div class="search-overlay-footer">
                <span><kbd>/</kbd> 打开搜索</span>
                <span><kbd>Esc</kbd> 关闭</span>
                <span><kbd>↑</kbd><kbd>↓</kbd> 选择</span>
                <span><kbd>Enter</kbd> 跳转</span>
            </div>
        </div>
    </div>

    <!-- 阅读进度条 -->
    <div class="reading-progress" id="reading-progress" aria-hidden="true"></div>

    <!-- 头部导航 -->
    <header class="header" id="main-header">
        <div class="container header-inner">
            <a href="/" class="logo">
                <?php 
                $avatar = getSetting('site_logo', '');
                if ($avatar): 
                ?>
                <img src="<?php echo e($avatar); ?>" alt="头像" width="40" height="40">
                <?php endif; ?>
                <?php echo e($siteName); ?>
            </a>
            
            <?php
            $primaryNavItems = [
                ['href' => '/', 'page' => 'home', 'label' => '首页'],
                ['href' => '/archive.php', 'page' => 'archive', 'label' => '归档'],
                ['href' => '/tags.php', 'page' => 'tags', 'label' => '标签'],
                ['href' => '/about.php', 'page' => 'about', 'label' => '关于']
            ];
            $secondaryNavItems = [
                ['href' => '/guestbook.php', 'page' => 'guestbook', 'label' => '留言板'],
                ['href' => '/donate.php', 'page' => 'donate', 'label' => '捐赠页'],
                ['href' => '/links.php', 'page' => 'links', 'label' => '友链'],
                ['href' => '/gallery.php', 'page' => 'gallery', 'label' => '免费图床'],
                ['href' => '/status.php', 'page' => 'status', 'label' => '服务状态']
            ];
            $moreNavActive = in_array($currentPage, array_column($secondaryNavItems, 'page'), true);
            ?>
            <nav class="nav" id="main-nav" aria-label="主导航">
                <?php foreach ($primaryNavItems as $item): ?>
                <a href="<?php echo e($item['href']); ?>" class="<?php echo $currentPage === $item['page'] ? 'active' : ''; ?>"<?php echo $currentPage === $item['page'] ? ' aria-current="page"' : ''; ?>><?php echo e($item['label']); ?></a>
                <?php endforeach; ?>
                <div class="nav-more" data-nav-more>
                    <button type="button" class="nav-more-trigger<?php echo $moreNavActive ? ' active' : ''; ?>" aria-haspopup="true" aria-expanded="false" aria-controls="nav-more-menu">
                        更多
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div class="nav-more-menu" id="nav-more-menu" role="menu" aria-hidden="true">
                        <?php foreach ($secondaryNavItems as $item): ?>
                        <a href="<?php echo e($item['href']); ?>" role="menuitem" class="<?php echo $currentPage === $item['page'] ? 'active' : ''; ?>"<?php echo $currentPage === $item['page'] ? ' aria-current="page"' : ''; ?>><?php echo e($item['label']); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </nav>
            
            <div class="header-actions">
                <!-- 搜索触发按钮 -->
                <button class="search-trigger" id="search-trigger" title="搜索 (/)" aria-label="打开搜索">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                </button>

                <!-- 社交图标链接 -->
                <?php if (getSetting('github_url')): ?>
                <a href="<?php echo e(getSetting('github_url')); ?>" target="_blank" rel="noopener" class="social-icon-link" title="GitHub">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/></svg>
                </a>
                <?php endif; ?>
                <?php if (getSetting('bilibili_url')): ?>
                <a href="<?php echo e(getSetting('bilibili_url')); ?>" target="_blank" rel="noopener" class="social-icon-link" title="Bilibili">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.813 4.653h.854c1.51.054 2.769.578 3.773 1.574 1.004.995 1.524 2.249 1.56 3.76v7.36c-.036 1.51-.556 2.769-1.56 3.773s-2.262 1.524-3.773 1.56H5.333c-1.51-.036-2.769-.556-3.773-1.56S.036 18.858 0 17.347v-7.36c.036-1.511.556-2.765 1.56-3.76 1.004-.996 2.262-1.52 3.773-1.574h.774l-1.174-1.12a1.234 1.234 0 0 1-.373-.906c0-.356.124-.658.373-.907l.027-.027c.267-.249.573-.373.92-.373.347 0 .653.124.92.373L9.653 4.44c.071.071.134.142.187.213h4.267a.836.836 0 0 1 .16-.213l2.853-2.747c.267-.249.573-.373.92-.373.347 0 .662.124.929.373.25.249.383.551.4.907 0 .355-.124.657-.373.906zM5.333 7.24c-.746.018-1.373.276-1.88.773-.506.498-.769 1.13-.786 1.894v7.52c.017.764.28 1.395.786 1.893.507.498 1.134.756 1.88.773h13.334c.746-.017 1.373-.275 1.88-.773.506-.498.769-1.129.786-1.893v-7.52c-.017-.765-.28-1.396-.786-1.894-.507-.497-1.134-.755-1.88-.773zM8 11.107c.373 0 .684.124.933.373.25.249.383.569.4.96v1.173c-.017.391-.15.711-.4.96-.249.25-.56.374-.933.374s-.684-.125-.933-.374c-.25-.249-.383-.569-.4-.96V12.44c.017-.391.15-.711.4-.96.249-.249.56-.373.933-.373zm8 0c.373 0 .684.124.933.373.25.249.383.569.4.96v1.173c-.017.391-.15.711-.4.96-.249.25-.56.374-.933.374s-.684-.125-.933-.374c-.25-.249-.383-.569-.4-.96V12.44c.017-.391.15-.711.4-.96.249-.249.56-.373.933-.373z"/></svg>
                </a>
                <?php endif; ?>

                <!-- 主题切换按钮 -->
                <button class="theme-toggle" type="button" title="切换主题" aria-label="切换主题" aria-pressed="false">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                </button>

                <?php if (isLoggedIn()): ?>
                    <?php $currentUser = currentUser(); ?>
                    <?php if ($currentUser): ?>
                    <a href="/user.php?id=<?php echo (int)$currentUser['id']; ?>" class="btn btn-sm btn-secondary hidden-mobile">个人主页</a>
                    <?php endif; ?>
                    <?php if (isAdmin()): ?>
                    <a href="/admin/" class="btn btn-sm btn-primary hidden-mobile">后台管理</a>
                    <?php endif; ?>
                    <a href="/logout.php" class="btn btn-sm btn-secondary hidden-mobile">退出</a>
                <?php else: ?>
                    <a href="/login.php" class="btn btn-sm btn-primary hidden-mobile">登录</a>
                    <a href="/register.php" class="btn btn-sm btn-secondary hidden-mobile">注册</a>
                <?php endif; ?>
                <button class="mobile-menu-btn" id="mobile-menu-btn" aria-label="打开菜单" aria-expanded="false" aria-controls="mobile-drawer">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                </button>
            </div>
        </div>
    </header>

    <!-- 移动端侧滑菜单 -->
    <div class="mobile-drawer-overlay" id="mobile-drawer-overlay"></div>
    <aside class="mobile-drawer" id="mobile-drawer" aria-hidden="true" role="navigation" aria-label="移动端导航">
        <div class="mobile-drawer-header">
            <a href="/" class="logo">
                <?php if ($avatar): ?>
                <img src="<?php echo e($avatar); ?>" alt="头像" width="40" height="40">
                <?php endif; ?>
                <?php echo e($siteName); ?>
            </a>
            <button type="button" class="mobile-drawer-close" id="mobile-drawer-close" aria-label="关闭菜单">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
        <nav class="mobile-drawer-nav" aria-label="移动端主导航">
            <?php foreach (array_merge($primaryNavItems, $secondaryNavItems) as $item): ?>
            <a href="<?php echo e($item['href']); ?>" class="<?php echo $currentPage === $item['page'] ? 'active' : ''; ?>"<?php echo $currentPage === $item['page'] ? ' aria-current="page"' : ''; ?>><span><?php echo e($item['label']); ?></span></a>
            <?php endforeach; ?>
        </nav>
        <div class="mobile-drawer-footer">
            <?php if (isLoggedIn()): ?>
                <?php $drawerUser = currentUser(); ?>
                <?php if ($drawerUser): ?>
                <a href="/user.php?id=<?php echo (int)$drawerUser['id']; ?>" class="btn btn-secondary">个人主页</a>
                <?php endif; ?>
                <?php if (isAdmin()): ?>
                <a href="/admin/" class="btn btn-primary">后台管理</a>
                <?php endif; ?>
                <a href="/logout.php" class="btn btn-secondary">退出</a>
            <?php else: ?>
                <a href="/login.php" class="btn btn-primary">登录</a>
                <a href="/register.php" class="btn btn-secondary">注册</a>
            <?php endif; ?>
        </div>
    </aside>

    <!-- 返回顶部按钮 -->
    <button class="back-to-top" id="back-to-top" title="返回顶部" aria-label="返回顶部">
        <svg class="back-to-top-arrow" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 15-6-6-6 6"/></svg>
        <svg class="back-to-top-ring" viewBox="0 0 44 44" aria-hidden="true">
            <circle cx="22" cy="22" r="20" fill="none" stroke="currentColor" stroke-width="2" stroke-dasharray="125.6" stroke-dashoffset="125.6" stroke-linecap="round"/>
        </svg>
    </button>
    
    <!-- 主体内容 -->
    <div class="container main-wrapper">
        <main class="main-content" id="main-content">
