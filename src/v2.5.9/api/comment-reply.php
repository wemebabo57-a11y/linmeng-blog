<?php
/**
 * 管理员评论回复 API
 * 用于管理员在后台回复评论
 */
define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/includes/config.php';
require_once APP_ROOT . '/includes/Security.php';
require_once APP_ROOT . '/includes/Database.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/Mailer.php';

session_start();

// 只允许管理员访问
if (!isAdmin()) {
    Security::jsonResponse(['success' => false, 'message' => '无权访问']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Security::jsonResponse(['success' => false, 'message' => '请求方式错误']);
}

$token = $_POST[CSRF_TOKEN_NAME] ?? '';
if (!Security::validateToken($token)) {
    Security::jsonResponse(['success' => false, 'message' => 'CSRF验证失败']);
}

$commentId = (int)($_POST['comment_id'] ?? 0);
$content = trim($_POST['content'] ?? '');

if ($commentId <= 0 || empty($content)) {
    Security::jsonResponse(['success' => false, 'message' => '参数错误']);
}

if (mb_strlen($content, 'UTF-8') < 2) {
    Security::jsonResponse(['success' => false, 'message' => '回复内容太短']);
}

if (mb_strlen($content, 'UTF-8') > 5000) {
    Security::jsonResponse(['success' => false, 'message' => '回复内容太长']);
}

try {
    // 获取原评论信息
    $parentComment = db()->fetchOne("SELECT * FROM app_comment WHERE id = ?", [$commentId]);
    if (!$parentComment) {
        Security::jsonResponse(['success' => false, 'message' => '评论不存在']);
    }
    
    $user = currentUser();
    // 用户可能不存在（账号被删除/禁用但会话仍存活），currentUser() 返回 null/false
    // 此时访问 $user['nickname'] 会触发 PHP 8 TypeError，需提前拦截
    if (!$user) {
        Security::jsonResponse(['success' => false, 'message' => '用户不存在或已被禁用']);
    }

    $replyIp = Security::getClientIp();
    $replyUa = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $replyMeta = buildCommentMetaFields($replyIp, $replyUa);

    insertCommentRow([
        'article_id' => $parentComment['article_id'],
        'parent_id' => $commentId,
        'user_id' => $_SESSION['user_id'],
        'nickname' => $user['nickname'] ?: $user['username'],
        'email' => $user['email'],
        'website' => null,
        'content' => Security::xssClean($content),
        'ip' => $replyIp,
        'user_agent' => $replyUa,
        'city' => $replyMeta['city'],
        'browser' => $replyMeta['browser'],
        'status' => 1,
        'is_admin' => 1
    ]);

    $contextTitle = '留言板';
    $contextUrl = rtrim(SITE_URL, '/') . '/guestbook.php';
    if ((int)$parentComment['article_id'] > 0) {
        $article = db()->fetchOne("SELECT title, slug FROM app_article WHERE id = ?", [(int)$parentComment['article_id']]);
        if ($article) {
            $contextTitle = $article['title'];
            $contextUrl = rtrim(SITE_URL, '/') . '/article.php?slug=' . rawurlencode($article['slug']);
        }
    }
    app_notify_comment_reply($parentComment, $content, $user['nickname'] ?: $user['username'], $contextTitle, $contextUrl);
    
    Security::jsonResponse(['success' => true, 'message' => '回复成功']);
} catch (Exception $e) {
    error_log('Comment reply failed: ' . $e->getMessage());
    Security::jsonResponse(['success' => false, 'message' => '回复失败，请稍后重试']);
}
