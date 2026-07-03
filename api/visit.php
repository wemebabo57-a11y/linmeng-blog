<?php
/**
 * 站点访问人数统计 API
 * 通过 Cookie 去重，避免刷新重复计数
 */
define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

header('Content-Type: application/json');

session_start();

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

// 速率限制：每 IP 60 次/小时，防止刷数
$clientIp = Security::getClientIp();
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
