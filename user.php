<?php
/**
 * 用户个人主页
 */
define('LM_ROOT', __DIR__);

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
Security::setSecurityHeaders();

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($userId <= 0) {
    Security::redirect('/');
}

try {
    $user = db()->fetchOne(
        "SELECT id, username, nickname, email, bio, website, avatar, created_at, status 
         FROM lm_admin 
         WHERE id = ?",
        [$userId]
    );
} catch (Exception $e) {
    $user = false;
}

if (!$user || (int)$user['status'] !== 1) {
    http_response_code(404);
    $pageTitle = '用户不存在';
    $currentPage = '';
    require_once LM_ROOT . '/template/header.php';
    echo '<div class="card"><div class="empty-state"><div class="empty-state-icon"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M16 16s-1.5-2-4-2-4 2-4 2"/><line x1="9" x2="9.01" y1="9" y2="9.03"/><line x1="15" x2="15.01" y1="9" y2="9.03"/></svg></div><h3>用户不存在或已被禁用</h3><p><a href="/">返回首页</a></p></div></div>';
    require_once LM_ROOT . '/template/sidebar.php';
    exit;
}

$nickname = $user['nickname'] ?: $user['username'];
$pageTitle = $nickname . ' 的主页';
$currentPage = '';

$articles = [];
try {
    $articles = db()->fetchAll(
        "SELECT id, title, slug, excerpt, cover_image, views, created_at 
         FROM lm_article 
         WHERE author_id = ? AND status = 'published' 
         ORDER BY created_at DESC 
         LIMIT 20",
        [$userId]
    );
} catch (Exception $e) {
    $articles = [];
}

require_once LM_ROOT . '/template/header.php';
?>

<div class="card">
    <div class="card-body" style="text-align: center; padding: 40px 24px;">
        <img src="<?php echo e($user['avatar'] ?: '/assets/images/default-avatar.png'); ?>" alt="" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin-bottom: 16px;">
        <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 8px;"><?php echo e($nickname); ?></h1>
        <p style="color: var(--text-light); margin-bottom: 16px;">加入于 <?php echo formatDate($user['created_at']); ?></p>

        <?php if (!empty($user['bio'])): ?>
        <p style="max-width: 600px; margin: 0 auto 16px; line-height: 1.6;"><?php echo nl2br(e($user['bio'])); ?></p>
        <?php endif; ?>

        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
            <?php if (!empty($user['website'])): ?>
            <a href="<?php echo e(Security::sanitizeUrl($user['website'])); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 4px;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                个人网站
            </a>
            <?php endif; ?>
            <?php if (isLoggedIn() && $_SESSION['user_id'] === $userId): ?>
            <a href="/profile.php" class="btn btn-sm btn-primary">编辑资料</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($articles)): ?>
<div class="card" style="margin-top: 24px;">
    <div class="card-header">
        <div class="card-title">发布的文章</div>
    </div>
    <div class="card-body">
        <div class="article-list">
            <?php foreach ($articles as $article): ?>
            <article class="article-item">
                <?php if ($article['cover_image']): ?>
                <a href="/article.php?slug=<?php echo e($article['slug']); ?>" class="article-cover">
                    <img src="<?php echo e($article['cover_image']); ?>" alt="<?php echo e($article['title']); ?>" loading="lazy">
                </a>
                <?php endif; ?>
                <div class="article-info">
                    <h2 class="article-title">
                        <a href="/article.php?slug=<?php echo e($article['slug']); ?>"><?php echo e($article['title']); ?></a>
                    </h2>
                    <?php if ($article['excerpt']): ?>
                    <p class="article-excerpt"><?php echo e($article['excerpt']); ?></p>
                    <?php endif; ?>
                    <div class="article-meta">
                        <span class="meta-item"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg> <?php echo formatDate($article['created_at']); ?></span>
                        <span class="meta-item"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg> <?php echo $article['views']; ?> 阅读</span>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card" style="margin-top: 24px;">
    <div class="card-body">
        <div class="empty-state" style="padding: 40px 20px;">
            <p>该用户还没有发布文章</p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once LM_ROOT . '/template/sidebar.php'; ?>
