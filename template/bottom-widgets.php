<?php
/**
 * 主内容区底部小工具：站点统计 + 一言
 * 用于填充文章列表/详情页主内容区下方的空白
 */
if (!defined('LM_ROOT')) {
    die('Access Denied');
}

// 复用首页已准备好的 $hotArticles；若不存在则自行获取
if (!isset($hotArticles)) {
    $hotArticles = getHotArticles(5);
}
?>

<!-- 主内容区底部小工具 -->
<div class="bottom-widgets">
    <!-- 站点统计 -->
    <div class="widget">
        <div class="widget-header">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M19 9l-5 5-4-4-3 3"/></svg>
            <span>站点统计</span>
        </div>
        <div class="widget-body">
            <div class="stat-grid">
                <div class="stat-cell">
                    <div class="stat-value stat-value--primary"><?php echo getArticleCount(); ?></div>
                    <div class="stat-label">篇文章</div>
                </div>
                <div class="stat-cell">
                    <div class="stat-value stat-value--success"><?php echo getCommentCount(); ?></div>
                    <div class="stat-label">条评论</div>
                </div>
                <div class="stat-cell">
                    <div class="stat-value stat-value--warning"><?php
                        if (!isset($tagArticles)) {
                            $tagArticles = db()->fetchAll("SELECT tags FROM lm_article WHERE status = 'published' AND tags != ''");
                        }
                        $uniqueTags = [];
                        foreach ($tagArticles as $row) {
                            foreach (explode(',', $row['tags']) as $tag) {
                                $tag = trim($tag);
                                if ($tag !== '') { $uniqueTags[$tag] = true; }
                            }
                        }
                        echo count($uniqueTags);
                    ?></div>
                    <div class="stat-label">个标签</div>
                </div>
                <div class="stat-cell">
                    <div class="stat-value stat-value--accent"><?php echo getRunningDays(); ?></div>
                    <div class="stat-label">天运行</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 一言/随机语录 -->
    <div class="widget">
        <div class="widget-header">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V21z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 .75 1 2 1z"/></svg>
            <span>一言</span>
        </div>
        <div class="widget-body">
            <div class="hitokoto-widget" id="hitokoto-widget">
                <div class="hitokoto-text" id="hitokoto-text">加载中...</div>
                <div class="hitokoto-from" id="hitokoto-from"></div>
            </div>
        </div>
    </div>

    <!-- 热门文章 -->
    <div class="widget">
        <div class="widget-header">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            <span>热门文章</span>
        </div>
        <div class="widget-body">
            <div class="link-list">
                <?php if (!empty($hotArticles)): ?>
                    <?php foreach ($hotArticles as $article): ?>
                    <a href="/article.php?slug=<?php echo e($article['slug']); ?>" class="link-item link-item--compact">
                        <div class="link-info">
                            <div class="link-name"><?php echo e($article['title']); ?></div>
                            <div class="link-desc"><?php echo (int)$article['views']; ?> 次阅读</div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-text">暂无文章</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
