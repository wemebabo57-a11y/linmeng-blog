<?php
/**
 * 站点访问人数统计 API
 * 通过 Cookie 去重，避免刷新重复计数
 * 同时承接文章浏览量的前端异步上报（页面交给 CDN 缓存后不再在渲染时同步累加）
 */
define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/includes/config.php';
require_once APP_ROOT . '/includes/Security.php';
require_once APP_ROOT . '/includes/Database.php';
require_once APP_ROOT . '/includes/functions.php';

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

// ===== PV 上报（main.js 每次页面加载触发） =====
if (isset($_POST['pv']) && $_POST['pv'] === '1') {
    // 速率限制：每 IP 每小时最多 600 次，防刷
    if (!Security::checkRateLimit($clientIp, 'pv_count', 600, 3600)) {
        echo json_encode(['success' => false, 'message' => '请求过于频繁']);
        exit;
    }

    // 跳过常见爬虫/机器人，避免虚增 PV
    $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $botKeywords = ['bot', 'spider', 'crawl', 'slurp', 'curl', 'wget', 'headless', 'lighthouse', 'monitor', 'uptime'];
    foreach ($botKeywords as $kw) {
        if (strpos($ua, $kw) !== false) {
            echo json_encode(['success' => true, 'skipped' => true]);
            exit;
        }
    }

    // 页面路径仅取站内相对路径，截断防超长
    $page = parse_url((string)($_POST['page'] ?? '/'), PHP_URL_PATH);
    if (!is_string($page) || $page === '' || strpos($page, '/') !== 0) {
        $page = '/';
    }
    $page = mb_substr($page, 0, 200);

    try {
        // 写访问日志（今日 PV / 24h PV / 24h 访客均基于此表统计）
        db()->insert('app_visit_log', [
            'page' => $page,
            'referer' => mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 500) ?: null,
            'ip' => $clientIp,
            'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            'created_at' => date('Y-m-d H:i:s', siteTime())
        ]);
        // 概率清理 30 天前的明细，避免 app_visit_log 无限膨胀拖慢侧栏/后台统计
        if (mt_rand(1, 200) === 1) {
            db()->query(
                "DELETE FROM app_visit_log WHERE created_at < ?",
                [date('Y-m-d H:i:s', siteTime() - 30 * 86400)]
            );
        }
        // 总 PV 原子自增
        db()->query(
            "INSERT INTO app_setting (setting_key, setting_value) VALUES ('site_pv_total', 1)
             ON DUPLICATE KEY UPDATE setting_value = setting_value + 1",
            []
        );
    } catch (Exception $e) {
        // 统计失败静默处理，不影响前端
    }
    echo json_encode(['success' => true]);
    exit;
}

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
            "UPDATE app_article SET views = views + 1 WHERE id = ? AND status = 'published'",
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

$cookieName = 'app_site_visitor';
$count = getVisitorCount();

// 没有访问标记时计数 +1 并设置 30 天 Cookie
if (!isset($_COOKIE[$cookieName])) {
    // 原子自增：单条 UPSERT 替代读-改-写，避免并发访客丢失增量
    // 依赖 app_setting.setting_key 的唯一性
    db()->query(
        "INSERT INTO app_setting (setting_key, setting_value) VALUES ('site_visitor_count', 1)
         ON DUPLICATE KEY UPDATE setting_value = setting_value + 1",
        []
    );
    // 自增后重新读取，确保返回最新值
    $count = getVisitorCount();

    $expire = time() + 86400 * 30;
    setcookie($cookieName, '1', [
        'expires'  => $expire,
        'path'     => '/',
        'secure'   => app_is_https(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

echo json_encode(['success' => true, 'count' => $count]);
