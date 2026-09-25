<?php
/**
 * 留言板
 */
define('LM_ROOT', __DIR__);

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

lm_session_start();
Security::setSecurityHeaders();
// 留言板内容是实时互动数据，永不进 CDN 共享缓存，留言后立即可见。
lm_no_cache_headers();

$pageTitle = '留言板';
$currentPage = 'guestbook';

// 读取 Turnstile 配置（留言板专用密钥，留空回退到通用密钥）
$turnstileGuestbookEnabled = (getSetting('turnstile_guestbook_enabled', '0') === '1');
$turnstileSiteKey = getSetting('turnstile_guestbook_site_key', '') ?: getSetting('turnstile_site_key', '');
$turnstileSecretKey = getSetting('turnstile_guestbook_secret_key', '') ?: getSetting('turnstile_secret_key', '');
// 仅当三项配置齐全时才真正启用
$turnstileActive = $turnstileGuestbookEnabled && $turnstileSiteKey !== '' && $turnstileSecretKey !== '';

// 获取留言（article_id = 0 表示留言板）。顶级留言分页，回复一次批量读取。
$comments = [];
$totalComments = 0;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$totalPages = 0;

try {
    $totalComments = (int)db()->fetchColumn(
        "SELECT COUNT(*) FROM lm_comment WHERE article_id = 0 AND status = 1 AND parent_id = 0"
    );
    $totalPages = max(1, (int)ceil($totalComments / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $comments = db()->fetchAll(
        "SELECT c.*, u.nickname as user_nickname, u.avatar as user_avatar
         FROM lm_comment c
         LEFT JOIN lm_admin u ON c.user_id = u.id
         WHERE c.article_id = 0 AND c.status = 1 AND c.parent_id = 0
         ORDER BY c.created_at DESC
         LIMIT ? OFFSET ?",
        [$perPage, $offset]
    );

    $commentIds = array_map('intval', array_column($comments, 'id'));
    $repliesByParent = [];
    if (!empty($commentIds)) {
        $placeholders = implode(',', array_fill(0, count($commentIds), '?'));
        $replies = db()->fetchAll(
            "SELECT c.*, u.nickname as user_nickname, u.avatar as user_avatar
             FROM lm_comment c
             LEFT JOIN lm_admin u ON c.user_id = u.id
             WHERE c.parent_id IN ({$placeholders}) AND c.status = 1
             ORDER BY c.created_at ASC",
            $commentIds
        );
        foreach ($replies as $reply) {
            $repliesByParent[(int)$reply['parent_id']][] = $reply;
        }
    }

    foreach ($comments as &$comment) {
        $comment['replies'] = $repliesByParent[(int)$comment['id']] ?? [];
    }
    unset($comment);
} catch (Exception $e) {
    $comments = [];
    $totalComments = 0;
    $totalPages = 0;
}

// 处理留言提交
$commentError = '';
$commentSuccess = '';
// PRG 模式：POST 成功后 302 重定向到 GET，刷新/后退不会重复提交。
// 通过 ?msg= 传递成功提示（ok=直接发布，pending=待审核）。
if (isset($_GET['msg']) && $_GET['msg'] === 'ok') {
    $commentSuccess = '留言发表成功';
} elseif (isset($_GET['msg']) && $_GET['msg'] === 'pending') {
    $commentSuccess = '留言已提交，等待审核';
}
$formUser = isLoggedIn() ? currentUser() : null;
$formNickname = $formUser ? ($formUser['nickname'] ?: $formUser['username']) : '';
$formEmail = $formUser ? ($formUser['email'] ?? '') : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guestbook') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        $commentError = '安全验证失败，请刷新页面重试';
    } elseif (!Security::checkRateLimit(Security::getClientIp(), 'guestbook_post', 10, 600)) {
        // 限流：每 IP 每 10 分钟最多 10 条留言
        $commentError = '留言过于频繁，请稍后再试';
    } elseif ($turnstileActive) {
        // 验证 Cloudflare Turnstile 人机验证
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

        // 校验网站 URL（仅 http/https，防 javascript: 等危险协议）
        if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
            $website = '';
        }

        if (empty($nickname) || empty($email) || empty($content)) {
            $commentError = '请填写必填项';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $commentError = '邮箱格式不正确';
        } elseif (strlen($content) < 2) {
            $commentError = '留言内容太短';
        } elseif (strlen($content) > 5000) {
            $commentError = '留言内容太长';
        } else {
            // 防重复提交：60 秒内同一 IP + 相同内容哈希视为重复（双击/网络重试/F5 都会被挡掉）。
            // 不依赖前端按钮 disabled——那是 UX，后端这道才是兜底。
            $ip = Security::getClientIp();
            // 哈希基于入库前的标准化值（与 insert 写入的 xssClean 结果一致），
            // 否则 PHP 端哈希与 DB 存储内容不匹配，去重会失效。
            $normalizedEmail = Security::xssClean($email);
            $normalizedContent = Security::xssClean($content);
            $dupeHash = hash('sha256', $ip . '|' . $normalizedEmail . '|' . $normalizedContent);
            try {
                $recentDupe = db()->fetchOne(
                    "SELECT id FROM lm_comment
                     WHERE ip = ? AND SHA2(CONCAT(ip, '|', email, '|', content), 256) = ?
                       AND created_at > (NOW() - INTERVAL 60 SECOND)
                     LIMIT 1",
                    [$ip, $dupeHash]
                );
            } catch (Exception $e) {
                $recentDupe = false;
            }
            if ($recentDupe) {
                // 静默视为成功（用户感知不到差异），避免暴露去重逻辑
                $msgParam = getSetting('comment_need_approve', '0') === '1' ? 'pending' : 'ok';
                // 用相对路径而非 SITE_URL，避免镜像域名/本地测试被跳到主域名
                header('Location: /guestbook.php?msg=' . $msgParam, true, 303);
                exit;
            }

            try {
                $userId = isLoggedIn() ? $_SESSION['user_id'] : 0;
                $isAdmin = isAdmin() ? 1 : 0;
                
                db()->insert('lm_comment', [
                    'article_id' => 0,
                    'parent_id' => 0,
                    'user_id' => $userId,
                    'nickname' => Security::xssClean($nickname),
                    'email' => Security::xssClean($email),
                    'website' => $website ? Security::xssClean($website) : null,
                    'content' => Security::xssClean($content),
                    'ip' => $ip,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'status' => getSetting('comment_need_approve', '0') === '1' ? 0 : 1,
                    'is_admin' => $isAdmin
                ]);
                
                // PRG：提交成功后 303 重定向到 GET，刷新不会重复发留言
                $msgParam = getSetting('comment_need_approve', '0') === '1' ? 'pending' : 'ok';
                header('Location: /guestbook.php?msg=' . $msgParam, true, 303);
                exit;
            } catch (Exception $e) {
                error_log('Guestbook post failed: ' . $e->getMessage());
                $commentError = '留言发表失败，请稍后重试';
            }
        }
    }
}

require_once LM_ROOT . '/template/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg> 留言板</div>
    </div>
    <div class="card-body">
        <p class="guestbook-intro">欢迎留下你的足迹，有任何问题或建议都可以在这里告诉我~</p>
        
        <?php if ($commentSuccess): ?>
        <div class="alert alert-success"><?php echo e($commentSuccess); ?></div>
        <?php endif; ?>
        
        <?php if ($commentError): ?>
        <div class="alert alert-error"><?php echo e($commentError); ?></div>
        <?php endif; ?>
        
        <!-- 留言表单 -->
        <form method="POST" action="" data-validate class="guestbook-form">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="guestbook">
            
            <div class="form-row">
                <div class="form-group">
                    <input type="text" name="nickname" class="form-input" placeholder="昵称 *" required
                           value="<?php echo isset($_POST['nickname']) ? e($_POST['nickname']) : e($formNickname); ?>">
                </div>
                <div class="form-group">
                    <input type="email" name="email" class="form-input" placeholder="邮箱 *" required
                           value="<?php echo isset($_POST['email']) ? e($_POST['email']) : e($formEmail); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <input type="url" name="website" class="form-input" placeholder="网站（选填）"
                       value="<?php echo isset($_POST['website']) ? e($_POST['website']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <textarea name="content" class="form-textarea" placeholder="写下你的留言..." required><?php echo isset($_POST['content']) ? e($_POST['content']) : ''; ?></textarea>
            </div>

            <?php if ($turnstileActive): ?>
            <div class="form-group">
                <div class="cf-turnstile" data-sitekey="<?php echo e($turnstileSiteKey); ?>" data-theme="light"></div>
                <div class="form-hint">请完成上方人机验证后再提交留言</div>
            </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary">发表留言</button>
        </form>

        <?php if ($turnstileActive): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        <?php endif; ?>
        
        <!-- 留言列表 -->
        <h3 class="guestbook-count">全部留言 (<?php echo (int)$totalComments; ?>)</h3>
        
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
            <p>还没有留言，来做第一个留言的人吧！</p>
        </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="留言分页">
            <?php if ($page > 1): ?>
            <a href="/guestbook.php?page=<?php echo $page - 1; ?>" class="page-link" rel="prev" aria-label="上一页">&lt;</a>
            <?php endif; ?>
            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++):
            ?>
                <?php if ($pageNumber === $page): ?>
                <span class="page-link active" aria-current="page"><?php echo $pageNumber; ?></span>
                <?php else: ?>
                <a href="/guestbook.php?page=<?php echo $pageNumber; ?>" class="page-link"><?php echo $pageNumber; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="/guestbook.php?page=<?php echo $page + 1; ?>" class="page-link" rel="next" aria-label="下一页">&gt;</a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
</div>

<?php require_once LM_ROOT . '/template/sidebar.php'; ?>
