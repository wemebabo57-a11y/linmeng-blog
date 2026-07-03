<?php
define('LM_ROOT', __DIR__);

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
Security::setSecurityHeaders();

$pageTitle = '关于';
$currentPage = 'about';

$siteName = getSetting('site_name', '林梦的博客');
$siteDesc = getSetting('site_description', '记录生活，分享技术');
$githubUrl = getSetting('github_url', '');
$bilibiliUrl = getSetting('bilibili_url', '');
$startDate = getSetting('site_start_date', '');
$latestArticles = getLatestArticles(4);

require_once LM_ROOT . '/template/header.php';
?>

<div class="card">
    <div class="about-hero">
        <h1>关于 <?php echo e($siteName); ?></h1>
        <p class="about-hero-subtitle">这里是站点介绍、运行信息和常用入口。</p>
    </div>
    <div class="card-body">
        <div class="about-grid">
            <div class="about-tile">
                <strong>站点简介</strong>
                <span><?php echo e($siteDesc); ?></span>
            </div>
            <div class="about-tile">
                <strong>内容统计</strong>
                <span><?php echo (int)getArticleCount(); ?> 篇文章，<?php echo (int)getCommentCount(); ?> 条评论</span>
            </div>
            <div class="about-tile">
                <strong>运行时间</strong>
                <span><?php echo (int)getRunningDays(); ?> 天<?php echo $startDate ? '，起始于 ' . e(date('Y-m-d H:i:s', strtotime($startDate))) : ''; ?></span>
            </div>
            <div class="about-tile">
                <strong>访问人数</strong>
                <span>已有 <span class="visitor-count" data-count="<?php echo getVisitorCount(); ?>"><?php echo getVisitorCount(); ?></span> 个人访问此站</span>
            </div>
        </div>

        <div class="about-actions">
            <a href="/archive.php" class="btn btn-primary">查看归档</a>
            <a href="/tags.php" class="btn btn-secondary">浏览标签</a>
            <a href="/guestbook.php" class="btn btn-secondary">去留言</a>
            <?php if ($githubUrl): ?>
            <a href="<?php echo e($githubUrl); ?>" target="_blank" rel="noopener" class="btn btn-secondary">GitHub</a>
            <?php endif; ?>
            <?php if ($bilibiliUrl): ?>
            <a href="<?php echo e($bilibiliUrl); ?>" target="_blank" rel="noopener" class="btn btn-secondary">Bilibili</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card card-spaced">
    <div class="card-header">
        <div class="card-title">最近更新</div>
    </div>
    <div class="card-body">
        <?php if (!empty($latestArticles)): ?>
        <div class="link-list">
            <?php foreach ($latestArticles as $article): ?>
            <a href="/article.php?slug=<?php echo e($article['slug']); ?>" class="link-item">
                <div class="link-info">
                    <div class="link-name"><?php echo e($article['title']); ?></div>
                    <div class="link-desc"><?php echo timeAgo($article['created_at']); ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state" style="padding: 40px 20px;">
            <h3>暂无文章</h3>
            <p>发布文章后会在这里展示最近更新。</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once LM_ROOT . '/template/sidebar.php'; ?>
