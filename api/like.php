<?php
/**
 * 文章点赞API
 */
define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

header('Content-Type: application/json');

session_start();

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方式错误']);
    exit;
}

// CSRF验证
$token = $_POST[CSRF_TOKEN_NAME] ?? '';
if (!Security::validateToken($token)) {
    echo json_encode(['success' => false, 'message' => '安全验证失败，请刷新页面重试']);
    exit;
}

$clientIp = Security::getClientIp();

// 限流：每个 IP 每分钟最多 30 次点赞操作
if (!Security::checkRateLimit($clientIp, 'article_like', 30, 60)) {
    echo json_encode(['success' => false, 'message' => '操作过于频繁，请稍后再试']);
    exit;
}

$articleId = isset($_POST['article_id']) ? (int)$_POST['article_id'] : 0;

if ($articleId <= 0) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

try {
    // 检查文章是否存在
    $article = db()->fetchOne("SELECT id FROM lm_article WHERE id = ? AND status = 'published'", [$articleId]);
    if (!$article) {
        echo json_encode(['success' => false, 'message' => '文章不存在']);
        exit;
    }

    // 原子化点赞切换：事务 + INSERT IGNORE，消除 SELECT-then-act 的 TOCTOU 竞态
    // 依赖 lm_article_like 上的 UNIQUE KEY (article_id, ip)
    // 注：点赞数为派生值（COUNT(*) 实时计算），lm_article 无 likes 列，无需维护计数列
    db()->beginTransaction();
    try {
        $stmt = db()->query(
            "INSERT IGNORE INTO lm_article_like (article_id, ip, created_at) VALUES (?, ?, ?)",
            [$articleId, $clientIp, date('Y-m-d H:i:s')]
        );
        $inserted = $stmt->rowCount();

        if ($inserted > 0) {
            // 新插入：本次为点赞
            $liked = true;
            $message = '点赞成功';
        } else {
            // 已存在（重复点赞）：按原切换逻辑取消点赞
            db()->delete('lm_article_like', 'article_id = ? AND ip = ?', [$articleId, $clientIp]);
            $liked = false;
            $message = '已取消点赞';
        }

        // 获取最新点赞数
        $count = db()->fetchColumn("SELECT COUNT(*) FROM lm_article_like WHERE article_id = ?", [$articleId]);

        db()->commit();
    } catch (Exception $e) {
        db()->rollback();
        throw $e;
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'count' => (int)$count,
        'liked' => $liked
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '操作失败']);
}
