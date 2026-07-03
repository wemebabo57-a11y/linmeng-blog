<?php
/**
 * 留言板
 */
define('LM_ROOT', __DIR__);

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
Security::setSecurityHeaders();

$pageTitle = '留言板';
$currentPage = 'guestbook';

// 读取 Turnstile 配置（留言板专用密钥，留空回退到通用密钥）
$turnstileGuestbookEnabled = (getSetting('turnstile_guestbook_enabled', '0') === '1');
$turnstileSiteKey = getSetting('turnstile_guestbook_site_key', '') ?: getSetting('turnstile_site_key', '');
$turnstileSecretKey = getSetting('turnstile_guestbook_secret_key', '') ?: getSetting('turnstile_secret_key', '');
// 仅当三项配置齐全时才真正启用
$turnstileActive = $turnstileGuestbookEnabled && $turnstileSiteKey !== '' && $turnstileSecretKey !== '';

// 获取留言（article_id = 0 表示留言板）
$comments = [];
try {
    $comments = db()->fetchAll(
        "SELECT c.*, u.nickname as user_nickname, u.avatar as user_avatar 
         FROM lm_comment c 
         LEFT JOIN lm_admin u ON c.user_id = u.id 
         WHERE c.article_id = 0 AND c.status = 1 AND c.parent_id = 0 
         ORDER BY c.created_at DESC"
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
    $comments = [];
}

// 处理留言提交
$commentError = '';
$commentSuccess = '';
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
                    'ip' => Security::getClientIp(),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'status' => getSetting('comment_need_approve', '0') === '1' ? 0 : 1,
                    'is_admin' => $isAdmin
                ]);
                
                $commentSuccess = getSetting('comment_need_approve', '0') === '1' 
                    ? '留言已提交，等待审核' 
                    : '留言发表成功';
                
                $_POST = [];
                
                // 重新加载留言
                $comments = db()->fetchAll(
                    "SELECT c.*, u.nickname as user_nickname, u.avatar as user_avatar 
                     FROM lm_comment c 
                     LEFT JOIN lm_admin u ON c.user_id = u.id 
                     WHERE c.article_id = 0 AND c.status = 1 AND c.parent_id = 0 
                     ORDER BY c.created_at DESC"
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
        <h3 class="guestbook-count">全部留言 (<?php echo count($comments); ?>)</h3>
        
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
    </div>
</div>

<?php require_once LM_ROOT . '/template/sidebar.php'; ?>
