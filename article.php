<?php
/**
 * 文章详情页 v2.0
 * 支持多图展示、点赞、图片灯箱
 */
define('LM_ROOT', __DIR__);

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
Security::setSecurityHeaders();

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (empty($slug)) {
    Security::redirect('/');
}

// 获取文章
try {
    $article = db()->fetchOne(
        "SELECT a.*, c.name as category_name, u.nickname as author_name, u.avatar as author_avatar 
         FROM lm_article a 
         LEFT JOIN lm_category c ON a.category_id = c.id 
         LEFT JOIN lm_admin u ON a.author_id = u.id 
         WHERE a.slug = ? AND a.status = 'published'",
        [$slug]
    );
    
    if (!$article) {
        http_response_code(404);
        $pageTitle = '文章不存在';
        $currentPage = '';
        require_once LM_ROOT . '/template/header.php';
        echo '<div class="card"><div class="empty-state"><div class="empty-state-icon"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M16 16s-1.5-2-4-2-4 2-4 2"/><line x1="9" x2="9.01" y1="9" y2="9.03"/><line x1="15" x2="15.01" y1="9" y2="9.03"/></svg></div><h3>文章不存在或已被删除</h3><p><a href="/">返回首页</a></p></div></div>';
        require_once LM_ROOT . '/template/sidebar.php';
        exit;
    }
    
    // 增加浏览量（使用原子操作避免竞态条件）
    db()->query("UPDATE lm_article SET views = views + 1 WHERE id = ?", [$article['id']]);
    $article['views']++;
    
    // 获取文章图片
    $articleImages = db()->fetchAll(
        "SELECT * FROM lm_article_image WHERE article_id = ? ORDER BY sort_order ASC, id ASC",
        [$article['id']]
    );
    
    // 获取点赞数
    $likeCount = db()->fetchColumn(
        "SELECT COUNT(*) FROM lm_article_like WHERE article_id = ?",
        [$article['id']]
    );

    $prevArticle = db()->fetchOne(
        "SELECT title, slug FROM lm_article WHERE status = 'published' AND created_at > ? ORDER BY created_at ASC LIMIT 1",
        [$article['created_at']]
    );
    $nextArticle = db()->fetchOne(
        "SELECT title, slug FROM lm_article WHERE status = 'published' AND created_at < ? ORDER BY created_at DESC LIMIT 1",
        [$article['created_at']]
    );
    
    // 检查当前用户是否已点赞
    $hasLiked = false;
    $clientIp = Security::getClientIp();
    if ($clientIp) {
        $liked = db()->fetchColumn(
            "SELECT COUNT(*) FROM lm_article_like WHERE article_id = ? AND ip = ?",
            [$article['id'], $clientIp]
        );
        $hasLiked = $liked > 0;
    }

    // 获取启用的 AI Provider（用于前台 AI 总结面板）
    $aiProviders = [];
    $defaultProviderId = 0;
    if (getSetting('ai_summary_enabled', '0') === '1') {
        try {
            $aiProviders = db()->fetchAll(
                "SELECT id, name, model FROM lm_ai_provider WHERE enabled = 1 ORDER BY sort_order DESC, id ASC"
            );
            $defaultProviderId = (int)getSetting('ai_default_provider_id', 0);
        } catch (Exception $ex) {
            $aiProviders = [];
        }
    }
    
} catch (Exception $e) {
    die('加载文章失败');
}

$pageTitle = $article['title'];
$currentPage = '';

// 获取评论
$comments = [];
try {
    $comments = db()->fetchAll(
        "SELECT c.*, u.nickname as user_nickname, u.avatar as user_avatar 
         FROM lm_comment c 
         LEFT JOIN lm_admin u ON c.user_id = u.id 
         WHERE c.article_id = ? AND c.status = 1 AND c.parent_id = 0 
         ORDER BY c.created_at DESC",
        [$article['id']]
    );
    
    // 获取回复
    foreach ($comments as &$comment) {
        $replies = db()->fetchAll(
            "SELECT c.*, u.nickname as user_nickname, u.avatar as user_avatar 
             FROM lm_comment c 
             LEFT JOIN lm_admin u ON c.user_id = u.id 
             WHERE c.parent_id = ? AND c.status = 1 
             ORDER BY c.created_at ASC",
            [$comment['id']]
        );
        $comment['replies'] = $replies;
    }
    unset($comment);
} catch (Exception $e) {
    $comments = [];
}

// 处理评论提交
$commentError = '';
$commentSuccess = '';
$formUser = isLoggedIn() ? currentUser() : null;
$formNickname = $formUser ? ($formUser['nickname'] ?: $formUser['username']) : '';
$formEmail = $formUser ? ($formUser['email'] ?? '') : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'comment') {
    // 验证CSRF
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        $commentError = '安全验证失败，请刷新页面重试';
    } elseif (!Security::checkRateLimit(Security::getClientIp(), 'comment_post', 10, 600)) {
        // 限流：每 IP 每 10 分钟最多 10 条评论
        $commentError = '评论过于频繁，请稍后再试';
    } else {
        $nickname = trim($_POST['nickname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;

        // 校验网站 URL（仅 http/https，防 javascript: 等危险协议）
        if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
            $website = '';
        }

        if (empty($nickname) || empty($email) || empty($content)) {
            $commentError = '请填写必填项';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $commentError = '邮箱格式不正确';
        } elseif (strlen($content) < 2) {
            $commentError = '评论内容太短';
        } elseif (strlen($content) > 5000) {
            $commentError = '评论内容太长';
        } else {
            try {
                $userId = isLoggedIn() ? $_SESSION['user_id'] : 0;
                $isAdmin = isAdmin() ? 1 : 0;

                // 校验 parent_id：必须存在、属于当前文章、且已审核通过；否则视为顶级评论
                if ($parentId > 0) {
                    $parent = db()->fetchOne(
                        "SELECT id FROM lm_comment WHERE id = ? AND article_id = ? AND status = 1",
                        [$parentId, $article['id']]
                    );
                    if (!$parent) {
                        $parentId = 0;
                    }
                }

                db()->insert('lm_comment', [
                    'article_id' => $article['id'],
                    'parent_id' => $parentId,
                    'user_id' => $userId,
                    'nickname' => Security::xssClean($nickname),
                    'email' => Security::xssClean($email),
                    'website' => $website ? Security::xssClean($website) : null,
                    'content' => Security::xssClean($content),
                    'ip' => Security::getClientIp(),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'status' => getSetting('comment_need_approve', '0') === '1' ? 0 : 1,
                    'is_admin' => $isAdmin
                ]);
                
                $commentSuccess = getSetting('comment_need_approve', '0') === '1' 
                    ? '评论已提交，等待审核' 
                    : '评论发表成功';
                    
                // 清空POST数据，防止重复提交
                $_POST = [];
                
                // 重新加载评论
                $comments = db()->fetchAll(
                    "SELECT c.*, u.nickname as user_nickname, u.avatar as user_avatar 
                     FROM lm_comment c 
                     LEFT JOIN lm_admin u ON c.user_id = u.id 
                     WHERE c.article_id = ? AND c.status = 1 AND c.parent_id = 0 
                     ORDER BY c.created_at DESC",
                    [$article['id']]
                );
                
                foreach ($comments as &$comment) {
                    $replies = db()->fetchAll(
                        "SELECT c.*, u.nickname as user_nickname, u.avatar as user_avatar 
                         FROM lm_comment c 
                         LEFT JOIN lm_admin u ON c.user_id = u.id 
                         WHERE c.parent_id = ? AND c.status = 1 
                         ORDER BY c.created_at ASC",
                        [$comment['id']]
                    );
                    $comment['replies'] = $replies;
                }
                unset($comment);
                
            } catch (Exception $e) {
                error_log('Comment post failed: ' . $e->getMessage());
                $commentError = '评论发表失败，请稍后重试';
            }
        }
    }
}

require_once LM_ROOT . '/template/header.php';
?>

<!-- 文章详情 -->
<article class="card article-detail-card">
    <?php if ($article['cover_image']): ?>
    <div class="article-detail-cover">
        <img src="<?php echo e($article['cover_image']); ?>" alt="<?php echo e($article['title']); ?>">
    </div>
    <?php endif; ?>

    <div class="card-body article-detail-body">
        <h1 class="article-detail-title"><?php echo e($article['title']); ?></h1>

        <div class="article-meta article-detail-meta">
            <span class="meta-item"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg> <?php echo formatDate($article['created_at']); ?></span>
            <?php if ($article['category_name']): ?>
            <span class="meta-item"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg> <?php echo e($article['category_name']); ?></span>
            <?php endif; ?>
            <span class="meta-item"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg> <?php echo $article['views']; ?> 阅读</span>
            <?php if ($article['author_name']): ?>
            <span class="meta-item"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> <?php echo e($article['author_name']); ?></span>
            <?php endif; ?>
            <?php
            $contentLength = mb_strlen(strip_tags($article['content']), 'UTF-8');
            $readingMinutes = max(1, ceil($contentLength / 500));
            ?>
            <span class="meta-item reading-time" id="reading-time"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> <?php echo $readingMinutes; ?> 分钟阅读</span>
        </div>
        
        <!-- 文章目录(TOC) -->
        <div id="article-toc" class="toc-container" style="display: none;">
            <div class="toc-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/></svg>
                文章目录
            </div>
            <ul id="toc-list" class="toc-list"></ul>
        </div>

        <?php if (!empty($aiProviders)): ?>
        <!-- AI 总结面板 -->
        <div class="ai-summary-panel">
            <div class="ai-summary-header">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><path d="M8.5 8.5v.01"/><path d="M16 15.5v.01"/><path d="M12 12v.01"/><path d="M11 17v.01"/><path d="M7 14v.01"/></svg>
                <span style="font-weight: 600;">AI 总结</span>
                <select id="ai-provider-select" class="form-select" style="width: auto; min-width: 180px;">
                    <?php foreach ($aiProviders as $p): ?>
                    <option value="<?php echo (int)$p['id']; ?>" <?php echo ((int)$p['id'] === $defaultProviderId) ? 'selected' : ''; ?>>
                        <?php echo e($p['name']); ?>（<?php echo e($p['model']); ?>）
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="ai-generate-btn" class="btn btn-primary btn-sm" data-article-id="<?php echo (int)$article['id']; ?>">
                    生成总结
                </button>
            </div>
            <div id="ai-summary-loading" class="ai-summary-loading" style="display: none;">
                正在生成，请稍候...
            </div>
            <div id="ai-summary-error" class="alert alert-error" style="display: none; margin: 0; padding: 10px 14px; font-size: 0.85rem;"></div>
            <div id="ai-summary-content" class="ai-summary-content" style="display: none;">
            </div>
        </div>
        <?php elseif (getSetting('ai_summary_enabled', '0') === '1' && isAdmin()): ?>
        <!-- AI 总结已启用但无可用模型，向管理员提示 -->
        <div class="ai-summary-panel">
            <div class="alert alert-warning" style="margin: 0; font-size: 0.85rem;">
                <strong>AI 总结已启用</strong>，但当前没有可用的 AI Provider。
                请前往 <a href="/admin/ai-providers.php">后台 AI 管理</a> 添加并启用至少一个 Provider。
            </div>
        </div>
        <?php endif; ?>
        
        <div class="article-content" id="article-content">
            <?php echo $article['content']; ?>
        </div>
        
        <!-- 文章图片画廊 -->
        <?php if (!empty($articleImages)): ?>
        <div class="article-gallery">
            <?php foreach (array_slice($articleImages, 0, 6) as $index => $img): ?>
            <div class="article-gallery-item">
                <img src="<?php echo e($img['image_url']); ?>" alt="文章图片" loading="lazy">
                <?php if ($index === 5 && count($articleImages) > 6): ?>
                <div class="article-gallery-more">+<?php echo count($articleImages) - 6; ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($article['tags']): ?>
        <div class="article-tags article-detail-tags">
            <span class="article-tags-label">标签:</span>
            <?php foreach (explode(',', $article['tags']) as $tag): ?>
            <span class="tag"><?php echo e(trim($tag)); ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="article-neighbor-nav">
            <?php if ($prevArticle): ?>
            <a href="/article.php?slug=<?php echo e($prevArticle['slug']); ?>" class="article-neighbor prev">
                <span>上一篇</span>
                <strong><?php echo e($prevArticle['title']); ?></strong>
            </a>
            <?php else: ?>
            <span class="article-neighbor disabled"><span>上一篇</span><strong>没有更新文章</strong></span>
            <?php endif; ?>
            <?php if ($nextArticle): ?>
            <a href="/article.php?slug=<?php echo e($nextArticle['slug']); ?>" class="article-neighbor next">
                <span>下一篇</span>
                <strong><?php echo e($nextArticle['title']); ?></strong>
            </a>
            <?php else: ?>
            <span class="article-neighbor disabled"><span>下一篇</span><strong>没有更早文章</strong></span>
            <?php endif; ?>
        </div>

        <!-- 文章互动按钮 -->
        <div class="article-actions">
            <button class="article-action-btn like-btn <?php echo $hasLiked ? 'active' : ''; ?>" data-article-id="<?php echo $article['id']; ?>">
                <span><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg></span>
                <span>点赞</span>
                <span class="like-count"><?php echo $likeCount; ?></span>
            </button>
            <button class="article-action-btn copy-link-btn">
                <span><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg></span>
                <span>复制链接</span>
            </button>
        </div>

        <!-- 文章工具栏 -->
        <div class="article-toolbar">
            <button class="article-toolbar-btn" id="article-bookmark-btn" type="button">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg>
                收藏文章
            </button>
            <button class="article-toolbar-btn" id="article-share-btn" type="button">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" x2="15.42" y1="13.51" y2="17.49"/><line x1="15.41" x2="8.59" y1="6.51" y2="10.49"/></svg>
                分享
            </button>
        </div>
    </div>
</article>

<!-- 分享弹窗 -->
<div class="share-modal" id="share-modal" role="dialog" aria-modal="true" aria-label="分享" aria-hidden="true">
    <div class="share-modal-backdrop"></div>
    <div class="share-modal-panel">
        <div class="share-modal-title">
            分享文章
            <button class="share-modal-close" aria-label="关闭">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
        <div class="share-grid">
            <div class="share-item" data-share="twitter">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                <span>X</span>
            </div>
            <div class="share-item" data-share="weibo">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M10.098 20.323c-3.977.391-7.414-1.406-7.672-4.02-.259-2.609 2.759-5.047 6.74-5.441 3.979-.394 7.413 1.404 7.671 4.018.259 2.6-2.759 5.049-6.739 5.443zM9.05 17.219c-.384.616-1.208.884-1.829.602-.612-.279-.793-.991-.406-1.593.379-.595 1.176-.861 1.793-.601.622.263.82.972.442 1.592zm1.27-1.627c-.141.237-.449.353-.689.253-.236-.09-.313-.361-.177-.586.138-.227.436-.346.672-.24.239.09.315.36.194.573zm.176-2.719c-1.893-.493-4.033.45-4.857 2.118-.836 1.704-.026 3.591 1.886 4.21 1.983.642 4.318-.341 5.132-2.179.8-1.793-.201-3.642-2.161-4.149zm7.563-1.224c-.346-.105-.579-.18-.401-.649.386-1.031.425-1.922.008-2.557-.781-1.19-2.924-1.126-5.354-.034 0 0-.767.334-.571-.271.378-1.19.321-2.188-.267-2.765-1.336-1.308-4.887.047-7.93 3.026C1.369 10.368 0 12.923 0 15.129c0 4.224 5.407 6.804 10.695 6.804 6.936 0 11.551-4.021 11.551-7.21 0-1.925-1.628-3.013-3.187-3.474zm.799-4.962c-.778-.825-1.924-1.156-2.984-.984-.357.058-.553.389-.421.73.132.341.478.523.841.447.633-.129 1.31.064 1.766.547.458.484.604 1.159.423 1.782-.093.317.081.653.404.748.323.095.666-.075.764-.389.305-1.026.046-2.199-.793-2.881zm2.273-2.155c-1.615-1.714-3.995-2.4-6.2-2.043-.43.069-.721.473-.58.89.142.418.565.64.998.555 1.699-.274 3.532.256 4.782 1.585 1.25 1.328 1.671 3.173 1.24 4.863-.109.404.142.818.56.924.418.106.849-.135.965-.537.574-2.144.03-4.569-1.765-6.237z"/></svg>
                <span>微博</span>
            </div>
            <div class="share-item" data-share="facebook">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                <span>Facebook</span>
            </div>
            <div class="share-item" data-share="copy">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                <span>复制链接</span>
            </div>
        </div>
        <div class="share-link-box">
            <input type="text" class="share-link-input" value="" readonly>
            <button type="button" class="btn btn-primary share-copy-btn">复制</button>
        </div>
    </div>
</div>

<!-- 评论区 -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg> 评论 (<?php echo count($comments); ?>)</div>
    </div>
    <div class="card-body">
        <?php if ($commentSuccess): ?>
        <div class="alert alert-success"><?php echo e($commentSuccess); ?></div>
        <?php endif; ?>
        
        <?php if ($commentError): ?>
        <div class="alert alert-error"><?php echo e($commentError); ?></div>
        <?php endif; ?>
        
        <!-- 评论表单 -->
        <form method="POST" action="" data-validate class="comment-form" style="margin-bottom: 32px;">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="comment">
            
            <div class="form-row">
                <div class="form-group" style="margin-bottom: 0;">
                    <input type="text" name="nickname" class="form-input" placeholder="昵称 *" aria-label="昵称" required
                           value="<?php echo isset($_POST['nickname']) ? e($_POST['nickname']) : e($formNickname); ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <input type="email" name="email" class="form-input" placeholder="邮箱 *" aria-label="邮箱" required
                           value="<?php echo isset($_POST['email']) ? e($_POST['email']) : e($formEmail); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <input type="url" name="website" class="form-input" placeholder="网站（选填）" aria-label="网站"
                       value="<?php echo isset($_POST['website']) ? e($_POST['website']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <textarea name="content" class="form-textarea" placeholder="写下你的评论..." aria-label="评论内容" required><?php echo isset($_POST['content']) ? e($_POST['content']) : ''; ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">发表评论</button>
        </form>
        
        <!-- 评论列表 -->
        <?php if (!empty($comments)): ?>
        <div class="comment-list">
            <?php foreach ($comments as $comment): ?>
            <div class="comment-item">
                <img src="<?php echo e($comment['user_avatar'] ?: '/assets/images/default-avatar.png'); ?>" alt="" class="comment-avatar">
                <div class="comment-body">
                    <div class="comment-header">
                        <span class="comment-author"><?php echo formatCommentAuthor($comment); ?></span>
                        <?php if ($comment['is_admin']): ?>
                        <span class="comment-badge">管理员</span>
                        <?php endif; ?>
                        <span class="comment-time"><?php echo timeAgo($comment['created_at']); ?></span>
                    </div>
                    <div class="comment-content"><?php echo nl2br($comment['content']); ?></div>
                    
                    <?php if (!empty($comment['replies'])): ?>
                        <?php foreach ($comment['replies'] as $reply): ?>
                        <div class="comment-reply">
                            <div class="comment-header">
                                <span class="comment-author"><?php echo formatCommentAuthor($reply); ?></span>
                                <?php if ($reply['is_admin']): ?>
                                <span class="comment-badge">管理员</span>
                                <?php endif; ?>
                                <span class="comment-time"><?php echo timeAgo($reply['created_at']); ?></span>
                            </div>
                            <div class="comment-content"><?php echo nl2br($reply['content']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state" style="padding: 40px 20px;">
            <div class="empty-state-icon"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg></div>
            <p>暂无评论，来抢沙发吧！</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once LM_ROOT . '/template/bottom-widgets.php'; ?>

<script src="/assets/js/ai-summary.js?v=<?php echo LM_VERSION; ?>"></script>

<?php require_once LM_ROOT . '/template/sidebar.php'; ?>

