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

    <!-- 主题初始化（外联，避免闪烁） -->
    <script src="/assets/js/theme-init.js?v=<?php echo LM_VERSION; ?>"></script>

    <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo LM_VERSION; ?>">
    <link rel="stylesheet" href="/assets/css/design-system.css?v=<?php echo LM_VERSION; ?>">

    <!-- Google Fonts Playfair Display -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- LXGW WenKai (霞鹜文楷) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lxgw-wenkai-webfont@1.7.0/style.css" />

    <!-- Lucide Icons (defer: 不阻塞首屏，main.js 中 lucide.createIcons() 兜底) -->
    <script defer src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.min.js"></script>

    <?php if (!empty($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
        <link rel="stylesheet" href="<?php echo e($css); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="<?php echo !empty($bodyClass) ? e($bodyClass) : ''; ?>">
    <a href="#main-content" class="skip-link">跳至主要内容</a>
    <!-- 图片灯箱 -->
    <div class="lightbox" role="dialog" aria-modal="true" aria-label="图片预览">
        <button class="lightbox-close">&times;</button>
        <img src="" alt="预览图片">
    </div>

    <!-- 全局搜索浮层 -->
    <div class="search-overlay" id="search-overlay" aria-hidden="true">
        <div class="search-overlay-backdrop"></div>
        <div class="search-overlay-panel">
            <div class="search-overlay-header">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" id="global-search-input" placeholder="搜索文章标题、标签或内容..." autocomplete="off" aria-label="搜索">
                <button class="search-overlay-close" id="search-overlay-close" aria-label="关闭搜索">
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
                <img src="<?php echo e($avatar); ?>" alt="头像">
                <?php endif; ?>
                <?php echo e($siteName); ?>
            </a>
            
            <nav class="nav" id="main-nav">
                <a href="/" class="<?php echo $currentPage === 'home' ? 'active' : ''; ?>">首页</a>
                <a href="/archive.php" class="<?php echo $currentPage === 'archive' ? 'active' : ''; ?>">归档</a>
                <a href="/tags.php" class="<?php echo $currentPage === 'tags' ? 'active' : ''; ?>">标签</a>
                <a href="/guestbook.php" class="<?php echo $currentPage === 'guestbook' ? 'active' : ''; ?>">留言板</a>
                <a href="/donate.php" class="<?php echo $currentPage === 'donate' ? 'active' : ''; ?>">捐赠页</a>
                <a href="/links.php" class="<?php echo $currentPage === 'links' ? 'active' : ''; ?>">友链</a>
                <a href="/gallery.php" class="<?php echo $currentPage === 'gallery' ? 'active' : ''; ?>">免费图床</a>
                <a href="/tools.php" class="<?php echo $currentPage === 'tools' ? 'active' : ''; ?>">工具</a>
                <a href="/status.php" class="<?php echo $currentPage === 'status' ? 'active' : ''; ?>">服务状态</a>
                <a href="/about.php" class="<?php echo $currentPage === 'about' ? 'active' : ''; ?>">关于</a>
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
                <button class="theme-toggle" title="切换主题" aria-label="切换主题" aria-pressed="false">
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
                <img src="<?php echo e($avatar); ?>" alt="头像">
                <?php endif; ?>
                <?php echo e($siteName); ?>
            </a>
            <button class="mobile-drawer-close" id="mobile-drawer-close" aria-label="关闭菜单">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
        <nav class="mobile-drawer-nav">
            <a href="/" class="<?php echo $currentPage === 'home' ? 'active' : ''; ?>"><span>首页</span></a>
            <a href="/archive.php" class="<?php echo $currentPage === 'archive' ? 'active' : ''; ?>"><span>归档</span></a>
            <a href="/tags.php" class="<?php echo $currentPage === 'tags' ? 'active' : ''; ?>"><span>标签</span></a>
            <a href="/guestbook.php" class="<?php echo $currentPage === 'guestbook' ? 'active' : ''; ?>"><span>留言板</span></a>
            <a href="/donate.php" class="<?php echo $currentPage === 'donate' ? 'active' : ''; ?>"><span>捐赠页</span></a>
            <a href="/links.php" class="<?php echo $currentPage === 'links' ? 'active' : ''; ?>"><span>友链</span></a>
            <a href="/gallery.php" class="<?php echo $currentPage === 'gallery' ? 'active' : ''; ?>"><span>免费图床</span></a>
            <a href="/tools.php" class="<?php echo $currentPage === 'tools' ? 'active' : ''; ?>"><span>工具</span></a>
            <a href="/status.php" class="<?php echo $currentPage === 'status' ? 'active' : ''; ?>"><span>服务状态</span></a>
            <a href="/about.php" class="<?php echo $currentPage === 'about' ? 'active' : ''; ?>"><span>关于</span></a>
        </nav>
        <div class="mobile-drawer-footer">
            <?php if (isLoggedIn()): ?>
                <?php $drawerUser = currentUser(); ?>
                <?php if ($drawerUser): ?>
                <a href="/user.php?id=<?php echo (int)$drawerUser['id']; ?>" class="btn btn-secondary" style="flex:1;">个人主页</a>
                <?php endif; ?>
                <?php if (isAdmin()): ?>
                <a href="/admin/" class="btn btn-primary" style="flex:1;">后台管理</a>
                <?php endif; ?>
                <a href="/logout.php" class="btn btn-secondary" style="flex:1;">退出</a>
            <?php else: ?>
                <a href="/login.php" class="btn btn-primary" style="flex:1;">登录</a>
                <a href="/register.php" class="btn btn-secondary" style="flex:1;">注册</a>
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
