<?php
/**
 * 一言API代理 - 避免前端CORS问题
 * 代理请求 hitokoto.cn，返回随机一言
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

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
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'hitokoto' => '生活不止眼前的代码，还有远方的Bug。',
        'from' => '林梦博客'
    ], JSON_UNESCAPED_UNICODE);
}
