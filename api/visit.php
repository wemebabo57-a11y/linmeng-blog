<?php
/**
 * 站点访问人数统计 API
 * 通过 Cookie 去重，避免刷新重复计数
 * 同时承接文章浏览量的前端异步上报（页面交给 CDN 缓存后不再在渲染时同步累加）
 */
define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

header('Content-Type: application/json');

// 注：不再 session_start()。
// 匿名令牌由 Security::validateToken 的无状态分支校验；
// 已登录用户携带会话令牌时由 validateToken 惰性恢复会话校验。

// 只允许 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方式错误']);
    exit;
}

// CSRF 验证（优先读取请求头，兼容表单字段）
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST[CSRF_TOKEN_NAME] ?? '');
if (!Security::validateToken($token)) {
    echo json_encode(['success' => false, 'message' => '安全验证失败，请刷新页面重试']);
    exit;
}

$clientIp = Security::getClientIp();

// ===== 文章浏览量上报（main.js 在文章页自动触发） =====
$articleId = isset($_POST['article_id']) ? (int)$_POST['article_id'] : 0;
if ($articleId > 0) {
    // 速率限制：每 IP 每小时最多 240 次，防止刷浏览量
    if (!Security::checkRateLimit($clientIp, 'article_view', 240, 3600)) {
        echo json_encode(['success' => false, 'message' => '请求过于频繁']);
        exit;
    }
    try {
        db()->query(
            "UPDATE lm_article SET views = views + 1 WHERE id = ? AND status = 'published'",
            [$articleId]
        );
    } catch (Exception $e) {
        // 计数失败静默处理，不影响前端
    }
    echo json_encode(['success' => true]);
    exit;
}

// ===== 访客统计（原有逻辑） =====
// 速率限制：每 IP 60 次/小时，防止刷数
if (!Security::checkRateLimit($clientIp, 'visit_count', 60, 3600)) {
    echo json_encode(['success' => false, 'message' => '请求过于频繁']);
    exit;
}

$cookieName = 'lm_site_visitor';
$count = getVisitorCount();

// 没有访问标记时计数 +1 并设置 30 天 Cookie
if (!isset($_COOKIE[$cookieName])) {
    // 原子自增：单条 UPSERT 替代读-改-写，避免并发访客丢失增量
    // 依赖 lm_setting.setting_key 的唯一性
    db()->query(
        "INSERT INTO lm_setting (setting_key, setting_value) VALUES ('site_visitor_count', 1)
         ON DUPLICATE KEY UPDATE setting_value = setting_value + 1",
        []
    );
    // 自增后重新读取，确保返回最新值
    $count = getVisitorCount();

    $expire = time() + 86400 * 30;
    // 与 config.php 的 $isHttps 逻辑保持一致，正确识别反代后的 HTTPS
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    setcookie($cookieName, '1', [
        'expires'  => $expire,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

echo json_encode(['success' => true, 'count' => $count]);
