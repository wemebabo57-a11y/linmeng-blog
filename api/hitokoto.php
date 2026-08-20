<?php
/**
 * 一言API代理 - 避免前端CORS问题
 * 代理请求 hitokoto.cn，返回随机一言
 */
define('LM_ROOT', dirname(__DIR__));
require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

// 注：此接口无会话依赖，去掉 session_start() 后响应不再携带 Set-Cookie，
// 上面的公共缓存头才能真正被 CDN/浏览器缓存命中。
if (!Security::checkRateLimit(Security::getClientIp(), 'hitokoto_proxy', 30, 60)) {
    http_response_code(429);
    echo json_encode(['hitokoto' => '请求过于频繁，请稍后再试', 'from' => '林梦博客'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 创建HTTP上下文，设置5秒超时
$ctx = stream_context_create([
    'http' => [
        'timeout' => 5,
        'method' => 'GET'
    ]
]);

// 请求一言API，分类：a(动画), b(漫画), d(网络), i(原创), k(哲学)
$url = 'https://v1.hitokoto.cn/?c=a&c=b&c=d&c=i&c=k';
$response = @file_get_contents($url, false, $ctx);

// 校验响应为合法 JSON 数组后才输出，否则返回安全兜底，避免透传无效内容
$data = ($response !== false) ? json_decode($response, true) : null;

if (is_array($data)) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} else {
    echo json_encode([
        'hitokoto' => '生活不止眼前的代码，还有远方的Bug。',
        'from' => '林梦博客'
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
