<?php
/**
 * 后台首页 v2.0
 * 增强统计功能和快捷操作
 */
define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
requireAdmin();

$pageTitle = '后台管理';
$currentPage = 'dashboard';

// 统计数据
try {
    $stats = [
        'articles' => db()->fetchColumn("SELECT COUNT(*) FROM lm_article") ?: 0,
        'published' => db()->fetchColumn("SELECT COUNT(*) FROM lm_article WHERE status = 'published'") ?: 0,
        'drafts' => db()->fetchColumn("SELECT COUNT(*) FROM lm_article WHERE status = 'draft'") ?: 0,
        'comments' => db()->fetchColumn("SELECT COUNT(*) FROM lm_comment") ?: 0,
        'pending_comments' => db()->fetchColumn("SELECT COUNT(*) FROM lm_comment WHERE status = 0") ?: 0,
        'users' => db()->fetchColumn("SELECT COUNT(*) FROM lm_admin") ?: 0,
        'links' => db()->fetchColumn("SELECT COUNT(*) FROM lm_link") ?: 0,
        'pending_links' => db()->fetchColumn("SELECT COUNT(*) FROM lm_link_apply WHERE status = 'pending'") ?: 0,
        'sponsors' => db()->fetchColumn("SELECT COUNT(*) FROM lm_sponsor") ?: 0,
        'today_visits' => db()->fetchColumn("SELECT COUNT(*) FROM lm_visit_log WHERE DATE(created_at) = CURDATE()") ?: 0,
        'total_likes' => db()->fetchColumn("SELECT COUNT(*) FROM lm_article_like") ?: 0,
    ];
    
    // 最近文章
    $recentArticles = db()->fetchAll(
        "SELECT id, title, status, views, created_at FROM lm_article ORDER BY created_at DESC LIMIT 5"
    );
    
    // 最近评论
    $recentComments = db()->fetchAll(
        "SELECT c.*, a.title as article_title FROM lm_comment c 
         LEFT JOIN lm_article a ON c.article_id = a.id 
         ORDER BY c.created_at DESC LIMIT 5"
    );
    
    // 热门文章
    $hotArticles = db()->fetchAll(
        "SELECT id, title, views, created_at FROM lm_article WHERE status = 'published' ORDER BY views DESC LIMIT 5"
    );
    
    // 访问趋势（最近7天）
    $visitTrend = db()->fetchAll(
        "SELECT DATE(created_at) as date, COUNT(*) as count 
         FROM lm_visit_log 
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
         GROUP BY DATE(created_at) 
         ORDER BY date ASC"
    );
    
} catch (Exception $e) {
    $stats = array_fill_keys(['articles', 'published', 'drafts', 'comments', 'pending_comments', 'users', 'links', 'pending_links', 'sponsors', 'today_visits', 'total_likes'], 0);
    $recentArticles = [];
    $recentComments = [];
    $hotArticles = [];
    $visitTrend = [];
}

require_once LM_ROOT . '/admin/template/header.php';
?>

<!-- 统计卡片 -->
<div class="stat-cards">
    <div class="stat-card">
        <div class="stat-card-value"><?php echo $stats['articles']; ?></div>
        <div class="stat-card-label">文章总数</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-value"><?php echo $stats['comments']; ?></div>
        <div class="stat-card-label">评论总数</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-value"><?php echo $stats['users']; ?></div>
        <div class="stat-card-label">用户总数</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-value"><?php echo $stats['links']; ?></div>
        <div class="stat-card-label">友链总数</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-value"><?php echo $stats['sponsors']; ?></div>
        <div class="stat-card-label">赞助商</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-value"><?php echo $stats['today_visits']; ?></div>
        <div class="stat-card-label">今日访问</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-value"><?php echo $stats['total_likes']; ?></div>
        <div class="stat-card-label">总点赞数</div>
    </div>
</div>

<?php if ($stats['pending_comments'] > 0 || $stats['pending_links'] > 0): ?>
<div class="alert alert-warning" style="margin-bottom: 24px;">
    <?php if ($stats['pending_comments'] > 0): ?>
    有 <?php echo $stats['pending_comments']; ?> 条评论待审核 
    <a href="comments.php">去审核</a>
    <?php endif; ?>
    <?php if ($stats['pending_comments'] > 0 && $stats['pending_links'] > 0): ?> | <?php endif; ?>
    <?php if ($stats['pending_links'] > 0): ?>
    有 <?php echo $stats['pending_links']; ?> 个友链申请待处理 
    <a href="link-apply.php">去处理</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <!-- 最近文章 -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/></svg>最近文章</div>
            <a href="articles.php" class="btn btn-sm btn-secondary">查看全部</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>标题</th>
                        <th>浏览</th>
                        <th>状态</th>
                        <th>时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentArticles as $article): ?>
                    <tr>
                        <td><?php echo e(truncate($article['title'], 30)); ?></td>
                        <td><?php echo $article['views']; ?></td>
                        <td>
                            <span class="badge <?php echo $article['status'] === 'published' ? 'badge-success' : 'badge-warning'; ?>">
                                <?php echo $article['status'] === 'published' ? '已发布' : '草稿'; ?>
                            </span>
                        </td>
                        <td><?php echo timeAgo($article['created_at']); ?></td>
                        <td>
                            <a href="article-edit.php?id=<?php echo $article['id']; ?>" class="btn btn-sm btn-secondary">编辑</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentArticles)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-light); padding: 40px;">暂无文章</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- 热门文章 -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>热门文章</div>
            <a href="articles.php" class="btn btn-sm btn-secondary">查看全部</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>标题</th>
                        <th>浏览</th>
                        <th>时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hotArticles as $article): ?>
                    <tr>
                        <td><?php echo e(truncate($article['title'], 30)); ?></td>
                        <td><?php echo $article['views']; ?></td>
                        <td><?php echo timeAgo($article['created_at']); ?></td>
                        <td>
                            <a href="article-edit.php?id=<?php echo $article['id']; ?>" class="btn btn-sm btn-secondary">编辑</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($hotArticles)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--text-light); padding: 40px;">暂无文章</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 最近评论 -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>最近评论</div>
        <a href="comments.php" class="btn btn-sm btn-secondary">查看全部</a>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>评论者</th>
                    <th>内容</th>
                    <th>文章</th>
                    <th>时间</th>
                    <th>状态</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentComments as $comment): ?>
                <tr>
                    <td><?php echo e($comment['nickname']); ?></td>
                    <td><?php echo e(truncate($comment['content'], 30)); ?></td>
                    <td><?php echo $comment['article_id'] > 0 ? e(truncate($comment['article_title'] ?: '未知文章', 20)) : '留言板'; ?></td>
                    <td><?php echo timeAgo($comment['created_at']); ?></td>
                    <td>
                        <span class="badge <?php echo $comment['status'] ? 'badge-success' : 'badge-warning'; ?>">
                            <?php echo $comment['status'] ? '已显示' : '待审核'; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentComments)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: var(--text-light); padding: 40px;">暂无评论</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once LM_ROOT . '/admin/template/footer.php'; ?>
