<?php
/**
 * 文章归档页面 v2.0
 * 按年月归档展示文章
 */
define('LM_ROOT', __DIR__);

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
Security::setSecurityHeaders();

$pageTitle = '文章归档';
$currentPage = 'archive';

// 获取归档数据
$archives = [];
try {
    $articles = db()->fetchAll(
        "SELECT id, title, slug, created_at, views 
         FROM lm_article 
         WHERE status = 'published' 
         ORDER BY created_at DESC"
    );
    
    foreach ($articles as $article) {
        $yearMonth = date('Y-m', strtotime($article['created_at']));
        $year = date('Y', strtotime($article['created_at']));
        $month = date('m', strtotime($article['created_at']));
        
        if (!isset($archives[$year])) {
            $archives[$year] = [];
        }
        if (!isset($archives[$year][$month])) {
            $archives[$year][$month] = [
                'label' => date('Y年m月', strtotime($article['created_at'])),
                'articles' => []
            ];
        }
        $archives[$year][$month]['articles'][] = $article;
    }
} catch (Exception $e) {
    $archives = [];
}

require_once LM_ROOT . '/template/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg> 文章归档</div>
    </div>
    <div class="card-body">
        <?php if (!empty($archives)): ?>
            <?php foreach ($archives as $year => $months): ?>
            <div class="archive-year">
                <h2 class="archive-year-title"><?php echo $year; ?>年</h2>
                <?php foreach ($months as $month => $data): ?>
                <div class="archive-month">
                    <h3 class="archive-month-title"><?php echo (int)$month; ?>月 <span class="archive-count">(<?php echo count($data['articles']); ?> 篇)</span></h3>
                    <div class="archive-list">
                        <?php foreach ($data['articles'] as $article): ?>
                        <a href="/article.php?slug=<?php echo e($article['slug']); ?>" class="archive-item">
                            <span class="archive-item-date"><?php echo date('m-d', strtotime($article['created_at'])); ?></span>
                            <span class="archive-item-title"><?php echo e($article['title']); ?></span>
                            <span class="archive-item-views"><?php echo $article['views']; ?> 阅读</span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
        <div class="empty-state" style="padding: 60px 20px;">
            <div class="empty-state-icon"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg></div>
            <h3>暂无文章</h3>
            <p>文章发布后将在此归档展示</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once LM_ROOT . '/template/sidebar.php'; ?>
