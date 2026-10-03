<?php
/**
 * 文章详情页 v2.0
 * 支持多图展示、点赞、图片灯箱
 */
define('APP_ROOT', __DIR__);

require_once APP_ROOT . '/includes/config.php';
require_once APP_ROOT . '/includes/Security.php';
require_once APP_ROOT . '/includes/Database.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/Mailer.php';

app_session_start();
Security::setSecurityHeaders();
// 文章页包含实时互动数据（评论列表、按 IP 判断的点赞状态、点赞数），
// 永不进 CDN 共享缓存，保证评论/点赞即时可见。
app_no_cache_headers();

// 读取 Turnstile 配置（与留言板共用同一套开关/密钥）
$turnstileGuestbookEnabled = (getSetting('turnstile_guestbook_enabled', '0') === '1');
$turnstileSiteKey = getSetting('turnstile_guestbook_site_key', '') ?: getSetting('turnstile_site_key', '');
$turnstileSecretKey = getSetting('turnstile_guestbook_secret_key', '') ?: getSetting('turnstile_secret_key', '');
$turnstileActive = $turnstileGuestbookEnabled && $turnstileSiteKey !== '' && $turnstileSecretKey !== '';

$slug = (isset($_GET['slug']) && is_string($_GET['slug'])) ? trim($_GET['slug']) : '';

if (empty($slug)) {
    Security::redirect('/');
}

// 获取文章
try {
    $article = db()->fetchOne(
        "SELECT a.*, c.name as category_name, u.nickname as author_name, u.avatar as author_avatar 
         FROM app_article a 
         LEFT JOIN app_category c ON a.category_id = c.id 
         LEFT JOIN app_admin u ON a.author_id = u.id 
         WHERE a.slug = ? AND a.status = 'published'",
        [$slug]
    );
    
    if (!$article) {
        http_response_code(404);
        $pageTitle = '文章不存在';
        $currentPage = '';
        require_once APP_ROOT . '/template/header.php';
        echo '<div class="card"><div class="empty-state"><div class="empty-state-icon"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M16 16s-1.5-2-4-2-4 2-4 2"/><line x1="9" x2="9.01" y1="9" y2="9.03"/><line x1="15" x2="15.01" y1="9" y2="9.03"/></svg></div><h3>文章不存在或已被删除</h3><p><a href="/">返回首页</a></p></div></div>';
        require_once APP_ROOT . '/template/sidebar.php';
        exit;
    }
    
    // 浏览量改为前端异步上报（main.js -> api/visit.php），
    // 页面因此可安全交给 CDN 缓存而不丢失计数。
    $articleViewId = (int)$article['id'];
    
    // 获取文章图片
    $articleImages = db()->fetchAll(
        "SELECT * FROM app_article_image WHERE article_id = ? ORDER BY sort_order ASC, id ASC",
        [$article['id']]
    );
    
    // 获取点赞数
    $likeCount = db()->fetchColumn(
        "SELECT COUNT(*) FROM app_article_like WHERE article_id = ?",
        [$article['id']]
    );

    $prevArticle = db()->fetchOne(
        "SELECT title, slug FROM app_article WHERE status = 'published' AND created_at > ? ORDER BY created_at ASC LIMIT 1",
        [$article['created_at']]
    );
    $nextArticle = db()->fetchOne(
        "SELECT title, slug FROM app_article WHERE status = 'published' AND created_at < ? ORDER BY created_at DESC LIMIT 1",
        [$article['created_at']]
    );
    
    // 检查当前用户是否已点赞
    $hasLiked = false;
    $clientIp = Security::getClientIp();
    if ($clientIp) {
        $liked = db()->fetchColumn(
            "SELECT COUNT(*) FROM app_article_like WHERE article_id = ? AND ip = ?",
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
                "SELECT id, name, model FROM app_ai_provider WHERE enabled = 1 ORDER BY sort_order DESC, id ASC"
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
$isArticlePage = true;
$articlePlainText = getArticlePlainText($article);

// SEO：标题/摘要/关键词/规范地址/封面/发布时间（header.php 统一输出 OG/Twitter/JSON-LD）
$seoTitle = $article['title'];
$seoDescription = trim((string)($article['excerpt'] ?? ''));
if ($seoDescription === '') {
    $seoDescription = mb_substr($articlePlainText, 0, 160, 'UTF-8');
}
$seoKeywords = trim((string)($article['tags'] ?? ''));
if ($article['category_name'] !== null && $article['category_name'] !== '') {
    $seoKeywords = trim($seoKeywords . ',' . $article['category_name'], ',');
}
$seoCanonical = '/article.php?slug=' . $article['slug'];
$seoImage = trim((string)($article['cover_image'] ?? ''));
$seoType = 'article';
$seoPublished = gmdate(DATE_ATOM, strtotime($article['created_at']) ?: time());
$seoModified = $seoPublished;
if (!empty($article['updated_at']) && strtotime($article['updated_at']) > 0) {
    $seoModified = gmdate(DATE_ATOM, strtotime($article['updated_at']));
}
$seoArticleJsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'headline' => $article['title'],
    'description' => $seoDescription,
    'datePublished' => $seoPublished,
    'dateModified' => $seoModified,
    'mainEntityOfPage' => appCanonicalUrl($seoCanonical),
    'author' => ['@type' => 'Person', 'name' => $article['author_name'] ?: (getSetting('site_author', '') ?: $siteName)],
];
if ($seoImage !== '') {
    $seoArticleJsonLd['image'] = appAbsoluteAssetUrl($seoImage);
}
// 相关阅读（同标签优先，其次同分类，再按热度补齐）
$relatedArticles = getRelatedArticles($article, 6);

// 获取评论
$comments = [];
try {
    $comments = db()->fetchAll(
        "SELECT c.*, u.nickname as user_nickname, u.avatar as user_avatar 
         FROM app_comment c 
         LEFT JOIN app_admin u ON c.user_id = u.id 
         WHERE c.article_id = ? AND c.status = 1 AND c.parent_id = 0 
         ORDER BY c.created_at DESC",
        [$article['id']]
    );
    
    // 批量获取回复，避免评论数量增加后产生 N+1 查询
    $commentIds = array_map('intval', array_column($comments, 'id'));
    $repliesByParent = [];
    if (!empty($commentIds)) {
        $placeholders = implode(',', array_fill(0, count($commentIds), '?'));
        $replies = db()->fetchAll(
            "SELECT c.*, u.nickname as user_nickname, u.avatar as user_avatar
             FROM app_comment c
             LEFT JOIN app_admin u ON c.user_id = u.id
             WHERE c.parent_id IN ({$placeholders}) AND c.status = 1
             ORDER BY c.created_at ASC",
            $commentIds
        );
        foreach ($replies as $reply) {
            $parentId = (int)$reply['parent_id'];
            $repliesByParent[$parentId][] = $reply;
        }
    }
    foreach ($comments as &$comment) {
        $comment['replies'] = $repliesByParent[(int)$comment['id']] ?? [];
    }
    unset($comment);
} catch (Exception $e) {
    $comments = [];
}

// 处理评论提交
$commentError = '';
$commentSuccess = '';
// PRG：成功后 303 到 GET，刷新不会重复发评论
if (isset($_GET['msg']) && $_GET['msg'] === 'ok') {
    $commentSuccess = '评论发表成功';
} elseif (isset($_GET['msg']) && $_GET['msg'] === 'pending') {
    $commentSuccess = '评论已提交，等待审核';
}
$formUser = isLoggedIn() ? currentUser() : null;
$formNickname = $formUser ? ($formUser['nickname'] ?: $formUser['username']) : '';
$formEmail = $formUser ? ($formUser['email'] ?? '') : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'comment') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        $commentError = '安全验证失败，请刷新页面重试';
    } elseif (!Security::checkRateLimit(Security::getClientIp(), 'comment_post', 10, 600)) {
        $commentError = '评论过于频繁，请稍后再试';
    } elseif ($turnstileActive) {
        $turnstileToken = $_POST['cf-turnstile-response'] ?? '';
        $turnstileResult = Security::verifyTurnstileToken(
            $turnstileToken,
            $turnstileSecretKey,
            Security::getClientIp()
        );
        if (!$turnstileResult['success']) {
            $commentError = '人机验证失败：' . $turnstileResult['error'];
        }
    }

    if ($commentError === '') {
        $nickname = trim($_POST['nickname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;

        if ($website !== '') {
            $website = Security::sanitizeUrl($website);
            if ($website === '#') {
                $website = '';
            }
        }

        if (empty($nickname) || empty($email) || empty($content)) {
            $commentError = '请填写必填项';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $commentError = '邮箱格式不正确';
        } elseif (mb_strlen($content, 'UTF-8') < 2) {
            $commentError = '评论内容太短';
        } elseif (mb_strlen($content, 'UTF-8') > 5000) {
            $commentError = '评论内容太长';
        } else {
            $commentIp = Security::getClientIp();
            $articleReturn = '/article.php?slug=' . rawurlencode($article['slug']);
            if (app_is_recent_comment_duplicate($commentIp, $email, $content)) {
                $msgParam = getSetting('comment_need_approve', '0') === '1' ? 'pending' : 'ok';
                header('Location: ' . $articleReturn . '&msg=' . $msgParam . '#comments', true, 303);
                exit;
            }

            try {
                $userId = isLoggedIn() ? $_SESSION['user_id'] : 0;
                $isAdmin = isAdmin() ? 1 : 0;

                if ($parentId > 0) {
                    $parent = db()->fetchOne(
                        "SELECT * FROM app_comment WHERE id = ? AND article_id = ? AND status = 1",
                        [$parentId, $article['id']]
                    );
                    if (!$parent) {
                        $parentId = 0;
                    }
                }

                $commentUa = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $commentMeta = buildCommentMetaFields($commentIp, $commentUa);

                $parentComment = null;
                if ($parentId > 0) {
                    $parentComment = db()->fetchOne("SELECT * FROM app_comment WHERE id = ?", [$parentId]);
                }

                insertCommentRow([
                    'article_id' => $article['id'],
                    'parent_id' => $parentId,
                    'user_id' => $userId,
                    'nickname' => Security::xssClean($nickname),
                    'email' => Security::xssClean($email),
                    'website' => $website ? Security::xssClean($website) : null,
                    'content' => Security::xssClean($content),
                    'ip' => $commentIp,
                    'user_agent' => $commentUa,
                    'city' => $commentMeta['city'],
                    'browser' => $commentMeta['browser'],
                    'status' => getSetting('comment_need_approve', '0') === '1' ? 0 : 1,
                    'is_admin' => $isAdmin
                ]);

                $needApprove = getSetting('comment_need_approve', '0') === '1';
                if (!$needApprove) {
                    app_purge_page_cache();
                }

                if ($parentComment && !$isAdmin && !$needApprove) {
                    $replyName = Security::xssClean($nickname);
                    app_notify_comment_reply(
                        $parentComment,
                        Security::xssClean($content),
                        html_entity_decode($replyName, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                        $article['title'],
                        rtrim(SITE_URL, '/') . '/article.php?slug=' . rawurlencode($article['slug']) . '#comments'
                    );
                }

                $msgParam = $needApprove ? 'pending' : 'ok';
                header('Location: ' . $articleReturn . '&msg=' . $msgParam . '#comments', true, 303);
                exit;
            } catch (Exception $e) {
                error_log('Comment post failed: ' . $e->getMessage());
                $commentError = '评论发表失败，请稍后重试';
            }
        }
    }
}

require_once APP_ROOT . '/template/header.php';
?>

<!-- 文章详情 -->
<article class="card article-detail-card">
    <?php if ($article['cover_image']): ?>
    <div class="article-detail-cover">
        <img src="<?php echo e($article['cover_image']); ?>" alt="<?php echo e($article['title']); ?>" decoding="async" width="1200" height="675">
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
            $contentLength = mb_strlen($articlePlainText, 'UTF-8');
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
                <span class="ai-summary-label">AI 总结</span>
                <select id="ai-provider-select" class="form-select ai-provider-select">
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
            <div id="ai-summary-error" class="alert alert-error ai-summary-alert"></div>
            <div id="ai-summary-content" class="ai-summary-content" style="display: none;">
            </div>
        </div>
        <?php elseif (getSetting('ai_summary_enabled', '0') === '1' && isAdmin()): ?>
        <!-- AI 总结已启用但无可用模型，向管理员提示 -->
        <div class="ai-summary-panel">
            <div class="alert alert-warning ai-summary-alert">
                <strong>AI 总结已启用</strong>，但当前没有可用的 AI Provider。
                请前往 <a href="/admin/ai-providers.php">后台 AI 管理</a> 添加并启用至少一个 Provider。
            </div>
        </div>
        <?php endif; ?>
        
        <div class="article-content" id="article-content">
            <?php echo renderArticleContent($article); ?>
        </div>
        
        <!-- 文章图片画廊 -->
        <?php if (!empty($articleImages)): ?>
        <div class="article-gallery">
            <?php foreach ($articleImages as $index => $img): ?>
            <div class="article-gallery-item<?php echo $index >= 6 ? ' article-gallery-item--extra' : ''; ?>">
                <img src="<?php echo e($img['image_url']); ?>" alt="<?php echo e($article['title']); ?> - 图片 <?php echo (int)$index + 1; ?>" loading="lazy" decoding="async" width="800" height="450">
                <?php if ($index === 5 && count($articleImages) > 6): ?>
                <button type="button" class="article-gallery-more" aria-label="查看全部 <?php echo count($articleImages); ?> 张图片">+<?php echo count($articleImages) - 6; ?></button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($article['tags']): ?>
        <div class="article-tags article-detail-tags">
            <span class="article-tags-label">标签:</span>
            <?php foreach (explode(',', $article['tags']) as $tag): ?>
            <?php $tag = trim($tag); ?>
            <?php if ($tag !== ''): ?>
            <a href="/tag.php?tag=<?php echo urlencode($tag); ?>" class="tag"><?php echo e($tag); ?></a>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($relatedArticles)): ?>
        <div class="article-related">
            <div class="article-related-title">相关阅读</div>
            <div class="article-related-list">
                <?php foreach ($relatedArticles as $related): ?>
                <a href="/article.php?slug=<?php echo e($related['slug']); ?>" class="article-related-item">
                    <?php if (!empty($related['cover_image'])): ?>
                    <img src="<?php echo e($related['cover_image']); ?>" alt="<?php echo e($related['title']); ?>" loading="lazy" decoding="async" width="400" height="225" class="article-related-cover">
                    <?php endif; ?>
                    <div class="article-related-info">
                        <div class="article-related-name"><?php echo e($related['title']); ?></div>
                        <div class="article-related-meta"><?php echo (int)$related['views']; ?> 阅读 · <?php echo timeAgo($related['created_at']); ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
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
            <button type="button" class="article-action-btn like-btn <?php echo $hasLiked ? 'active' : ''; ?>" data-article-id="<?php echo $article['id']; ?>" aria-pressed="<?php echo $hasLiked ? 'true' : 'false'; ?>">
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
<div class="share-modal" id="share-modal" role="dialog" aria-modal="true" aria-labelledby="share-modal-title" aria-hidden="true">
    <div class="share-modal-backdrop"></div>
    <div class="share-modal-panel">
        <div class="share-modal-title" id="share-modal-title">
            分享文章
            <button type="button" class="share-modal-close" aria-label="关闭分享弹窗">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
        <div class="share-grid">
            <button type="button" class="share-item" data-share="twitter">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                <span>X</span>
            </button>
            <button type="button" class="share-item" data-share="weibo">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M10.098 20.323c-3.977.391-7.414-1.406-7.672-4.02-.259-2.609 2.759-5.047 6.74-5.441 3.979-.394 7.413 1.404 7.671 4.018.259 2.6-2.759 5.049-6.739 5.443zM9.05 17.219c-.384.616-1.208.884-1.829.602-.612-.279-.793-.991-.406-1.593.379-.595 1.176-.861 1.793-.601.622.263.82.972.442 1.592zm1.27-1.627c-.141.237-.449.353-.689.253-.236-.09-.313-.361-.177-.586.138-.227.436-.346.672-.24.239.09.315.36.194.573zm.176-2.719c-1.893-.493-4.033.45-4.857 2.118-.836 1.704-.026 3.591 1.886 4.21 1.983.642 4.318-.341 5.132-2.179.8-1.793-.201-3.642-2.161-4.149zm7.563-1.224c-.346-.105-.579-.18-.401-.649.386-1.031.425-1.922.008-2.557-.781-1.19-2.924-1.126-5.354-.034 0 0-.767.334-.571-.271.378-1.19.321-2.188-.267-2.765-1.336-1.308-4.887.047-7.93 3.026C1.369 10.368 0 12.923 0 15.129c0 4.224 5.407 6.804 10.695 6.804 6.936 0 11.551-4.021 11.551-7.21 0-1.925-1.628-3.013-3.187-3.474zm.799-4.962c-.778-.825-1.924-1.156-2.984-.984-.357.058-.553.389-.421.73.132.341.478.523.841.447.633-.129 1.31.064 1.766.547.458.484.604 1.159.423 1.782-.093.317.081.653.404.748.323.095.666-.075.764-.389.305-1.026.046-2.199-.793-2.881zm2.273-2.155c-1.615-1.714-3.995-2.4-6.2-2.043-.43.069-.721.473-.58.89.142.418.565.64.998.555 1.699-.274 3.532.256 4.782 1.585 1.25 1.328 1.671 3.173 1.24 4.863-.109.404.142.818.56.924.418.106.849-.135.965-.537.574-2.144.03-4.569-1.765-6.237z"/></svg>
                <span>微博</span>
            </button>
            <button type="button" class="share-item" data-share="facebook">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                <span>Facebook</span>
            </button>
            <button type="button" class="share-item" data-share="qq">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.36 2 11.75c0 3.1 1.63 5.82 4.13 7.5-.1.74-.5 2.5-2.13 3.75 0 0 2.26-.1 4.13-1.88.63.16 1.29.25 1.97.25h.13c.68 0 1.34-.09 1.97-.25 1.87 1.78 4.13 1.88 4.13 1.88-1.63-1.25-2.03-3.01-2.13-3.75 2.5-1.68 4.13-4.4 4.13-7.5C22 6.36 17.52 2 12 2zm-1.05 13.4c-.5 0-.9.4-.9.9s.4.9.9.9.9-.4.9-.9-.4-.9-.9-.9zm2.1 0c-.5 0-.9.4-.9.9s.4.9.9.9.9-.4.9-.9-.4-.9-.9-.9zM12 16c-1.66 0-3.12-.85-3.97-2.13l1.06-1.06c.6.87 1.66 1.44 2.91 1.44s2.31-.57 2.91-1.44l1.06 1.06C15.12 15.15 13.66 16 12 16z"/></svg>
                <span>QQ</span>
            </button>
            <button type="button" class="share-item" data-share="qzone">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 4.86 5.36.78-3.88 3.78.92 5.34L12 14.27 7.2 16.76l.92-5.34L4.24 7.64l5.36-.78L12 2zm0 13.5c-1.93 0-3.5-1.57-3.5-3.5s1.57-3.5 3.5-3.5 3.5 1.57 3.5 3.5-1.57 3.5-3.5 3.5zm0-5.5c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                <span>QQ空间</span>
            </button>
            <button type="button" class="share-item" data-share="telegram">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M21.9 4.6L2.7 12.1c-.65.25-.66 1.16-.02 1.42l4.7 1.47 1.8 5.6c.2.63 1 .77 1.45.28l2.6-2.8 4.9 3.6c.5.37 1.24.1 1.42-.5l3.43-15c.18-.72-.46-1.35-1.08-1.57zM8.7 13.9l9.1-7.5c.15-.12.35.06.23.22l-7.5 8.1-.3 2.9-1.53-3.72z"/></svg>
                <span>Telegram</span>
            </button>
            <button type="button" class="share-item" data-share="wechat">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/><path d="M8 12h.01"/><path d="M12 12h.01"/><path d="M16 12h.01"/></svg>
                <span>微信扫码</span>
            </button>
            <button type="button" class="share-item" data-share="system">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" x2="15.42" y1="13.51" y2="17.49"/><line x1="15.41" x2="8.59" y1="6.51" y2="10.49"/></svg>
                <span>系统分享</span>
            </button>
            <button type="button" class="share-item" data-share="copy">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                <span>复制链接</span>
            </button>
        </div>
        <div class="share-link-box">
            <label for="share-link-input" class="visually-hidden">文章分享链接</label>
            <input type="text" id="share-link-input" class="share-link-input" value="" readonly>
            <button type="button" class="btn btn-primary share-copy-btn">复制</button>
        </div>
        <div class="share-wechat-box" id="share-wechat-box" style="display:none;">
            <div class="share-wechat-tip">微信扫一扫分享</div>
            <img id="share-wechat-qrcode" alt="微信分享二维码" width="140" height="140" loading="lazy">
        </div>
    </div>
</div>

<!-- 评论区 -->
<div class="card" id="comments">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg> 评论 (<?php echo count($comments); ?>)</div>
    </div>
    <div class="card-body">
        <?php if ($commentSuccess): ?>
        <div class="alert alert-success" role="status"><?php echo e($commentSuccess); ?></div>
        <?php endif; ?>
        
        <?php if ($commentError): ?>
        <div class="alert alert-error" role="alert"><?php echo e($commentError); ?></div>
        <?php endif; ?>
        
        <!-- 评论表单 -->
        <form method="POST" action="" data-validate class="comment-form">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="comment">
            <?php /* 楼中楼：点某条评论的「回复」后由 JS 写入父评论 ID；校验失败重渲染时保留上下文 */ ?>
            <input type="hidden" name="parent_id" id="comment-parent-id" value="<?php echo isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0; ?>">

            <div class="comment-reply-notice" id="comment-reply-notice" hidden>
                <span>正在回复 <strong id="comment-reply-target"></strong></span>
                <button type="button" class="comment-reply-cancel" id="comment-reply-cancel">取消回复</button>
            </div>
            
            <div class="form-row">
                <div class="form-group form-group-inline">
                    <label for="comment-nickname" class="visually-hidden">昵称</label>
                    <input type="text" id="comment-nickname" name="nickname" class="form-input" placeholder="昵称 *" required
                           value="<?php echo isset($_POST['nickname']) ? e($_POST['nickname']) : e($formNickname); ?>">
                </div>
                <div class="form-group form-group-inline">
                    <label for="comment-email" class="visually-hidden">邮箱</label>
                    <input type="email" id="comment-email" name="email" class="form-input" placeholder="邮箱 *" required
                           value="<?php echo isset($_POST['email']) ? e($_POST['email']) : e($formEmail); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="comment-website" class="visually-hidden">网站（选填）</label>
                <input type="url" id="comment-website" name="website" class="form-input" placeholder="网站（选填）"
                       value="<?php echo isset($_POST['website']) ? e($_POST['website']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="comment-content" class="visually-hidden">评论内容</label>
                <textarea id="comment-content" name="content" class="form-textarea" placeholder="写下你的评论..." required><?php echo isset($_POST['content']) ? e($_POST['content']) : ''; ?></textarea>
            </div>

            <?php if ($turnstileActive): ?>
            <div class="form-group">
                <div class="cf-turnstile" data-sitekey="<?php echo e($turnstileSiteKey); ?>" data-theme="light"></div>
                <div class="form-hint">请完成上方人机验证后再提交评论</div>
            </div>
            <?php endif; ?>
            
            <button type="submit" class="btn btn-primary">发表评论</button>
        </form>

        <?php if ($turnstileActive): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        <?php endif; ?>
        
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
                        <?php echo commentMetaHtml($comment); ?>
                        <button type="button" class="comment-reply-btn" data-reply-id="<?php echo (int)$comment['id']; ?>" data-reply-name="<?php echo e($comment['nickname']); ?>" aria-label="回复 <?php echo e($comment['nickname']); ?>">回复</button>
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
                                <?php echo commentMetaHtml($reply); ?>
                                <?php /* 回复「回复」时归到所在楼层（parent_id 指向顶级评论），并自动 @ 对方 */ ?>
                                <button type="button" class="comment-reply-btn" data-reply-id="<?php echo (int)$comment['id']; ?>" data-reply-name="<?php echo e($reply['nickname']); ?>" data-reply-append="1" aria-label="回复 <?php echo e($reply['nickname']); ?>">回复</button>
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
        <div class="empty-state empty-state-roomy">
            <div class="empty-state-icon"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg></div>
            <p>暂无评论，来抢沙发吧！</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once APP_ROOT . '/template/bottom-widgets.php'; ?>

<script defer src="/assets/js/ai-summary.js?v=<?php echo APP_VERSION; ?>"></script>

<?php require_once APP_ROOT . '/template/sidebar.php'; ?>
