<?php
/**
 * 标签云页面 v2.0
 * 展示所有文章标签
 */
define('LM_ROOT', __DIR__);

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
Security::setSecurityHeaders();

$pageTitle = '标签云';
$currentPage = 'tags';

// 获取所有标签及其文章数量
$tags = [];
try {
    $articles = db()->fetchAll("SELECT tags FROM lm_article WHERE status = 'published' AND tags != ''");
    $tagCounts = [];
    foreach ($articles as $article) {
        $articleTags = explode(',', $article['tags']);
        foreach ($articleTags as $tag) {
            $tag = trim($tag);
            if (!empty($tag)) {
                if (!isset($tagCounts[$tag])) {
                    $tagCounts[$tag] = 0;
                }
                $tagCounts[$tag]++;
            }
        }
    }
    arsort($tagCounts);
    $tags = $tagCounts;
} catch (Exception $e) {
    $tags = [];
}

require_once LM_ROOT . '/template/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg> 标签云</div>
    </div>
    <div class="card-body">
        <?php if (!empty($tags)): ?>
        <div class="tag-cloud">
            <?php 
            $maxCount = max($tags);
            $minCount = min($tags);
            foreach ($tags as $tag => $count): 
                $size = $minCount == $maxCount ? 1 : 0.8 + ($count - $minCount) / ($maxCount - $minCount) * 1.5;
            ?>
            <a href="/?search=<?php echo urlencode($tag); ?>" class="tag-cloud-item" style="font-size: <?php echo $size; ?>rem;">
                <?php echo e($tag); ?>
                <span class="tag-cloud-count"><?php echo $count; ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state" style="padding: 60px 20px;">
            <div class="empty-state-icon"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg></div>
            <h3>暂无标签</h3>
            <p>文章添加标签后将在此显示</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once LM_ROOT . '/template/sidebar.php'; ?>
