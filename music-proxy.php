<?php
/**
 * 音乐代理接口 - 解决跨域和 Referer 限制问题
 * 使用方式: /music-proxy.php?url=编码后的音频URL
 *
 * 安全策略：
 * - 域名白名单（防 SSRF，仅允许已知音乐源）
 * - 启用 SSL 证书校验（防中间人）
 * - CORS 仅放行本站 Origin（防滥用）
 * - 仅放行音频/视频内容类型
 */

// 防止直接访问
if (!defined('LM_ROOT')) {
    define('LM_ROOT', __DIR__);
}

// 载入站点配置（SITE_URL 等）
$configFile = LM_ROOT . '/includes/config.php';
if (is_file($configFile)) {
    require_once $configFile;
}

// 获取音频 URL
// 注意：$_GET 已自动对参数值做一次 URL 解码，无需再 urldecode（否则会双重解码破坏 URL 中的 + 与 % 字符）
$audioUrl = isset($_GET['url']) ? $_GET['url'] : '';

if (empty($audioUrl)) {
    http_response_code(400);
    die('Missing url parameter');
}

// 验证 URL 格式
if (!filter_var($audioUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($audioUrl, PHP_URL_SCHEME), ['http', 'https'])) {
    http_response_code(400);
    die('Invalid URL');
}

// SSRF 防护：仅允许已知音乐源域名白名单
$host = strtolower(parse_url($audioUrl, PHP_URL_HOST));
$allowedHosts = [
    // 网易云
    'music.163.com',
    'm10.music.126.net',
    'm7.music.126.net',
    'm8.music.126.net',
    'music.126.net',
    // 网易云第三方 API 聚合
    'api.uomg.com',
    'api.injahow.cn',
    // QQ 音乐（腾讯）常见音频 CDN
    'isure.wsweb.tc.qq.com',
    'dl.stream.qqmusic.qq.com',
    'dl.stream.qqmusic.tc.qq.com',
    'streamoc.music.tc.qq.com',
    'streamoc.music.qq.com',
    // 酷狗
    'm.kugou.com',
    'trackercdn.kugou.com',
    'fsandroid.kugou.com',
];

// 允许 126.net / qqmusic.qq.com 子域名兜底
$hostAllowed = in_array($host, $allowedHosts, true);
if (!$hostAllowed && (substr($host, -13) === '.music.126.net' || substr($host, -14) === '.qqmusic.qq.com')) {
    $hostAllowed = true;
}

if (!$hostAllowed) {
    http_response_code(403);
    die('Domain not allowed');
}

// 设置请求头
// 安全策略：禁用自动跟随重定向。白名单内的第三方 API（如 api.uomg.com / api.injahow.cn）
// 可能返回 302 跳转到任意 URL（含内网/metadata 端点），跟随会形成 SSRF。
// 改由客户端按需处理 Location 头。
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 30,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'header' => [
            "Accept: audio/webm,audio/ogg,audio/wav,audio/*;q=0.9,application/ogg;q=0.7,video/*;q=0.6,*/*;q=0.5",
            "Accept-Language: zh-CN,zh;q=0.9,en;q=0.8",
            "Referer: https://music.163.com/",
            "Origin: https://music.163.com"
        ],
        'follow_location' => false,
    ],
    // 启用 SSL 证书验证，防止中间人攻击
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'allow_self_signed' => false,
    ]
]);

// 获取音频数据
$response = @file_get_contents($audioUrl, false, $context);

if ($response === false) {
    http_response_code(502);
    die('Failed to fetch audio');
}

// 获取内容类型
$contentType = 'audio/mpeg';
if (isset($http_response_header) && is_array($http_response_header)) {
    foreach ($http_response_header as $header) {
        if (stripos($header, 'Content-Type:') === 0) {
            $contentType = trim(substr($header, 13));
            break;
        }
    }
}

// 仅放行音频/视频内容类型，防止代理被用于返回 HTML/脚本等内容
// 移除 application/octet-stream：过于宽泛，任意二进制均可由此代理外泄
$allowedCtPrefixes = ['audio/', 'video/', 'application/ogg'];
$ctLower = strtolower($contentType);
$ctOk = false;
foreach ($allowedCtPrefixes as $prefix) {
    if (strpos($ctLower, $prefix) === 0) {
        $ctOk = true;
        break;
    }
}
if (!$ctOk) {
    http_response_code(415);
    die('Unsupported media type');
}

// CORS：仅放行本站 Origin（同源或配置的 SITE_URL）
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$siteHost = defined('SITE_URL') ? parse_url(SITE_URL, PHP_URL_HOST) : '';
$allowOrigin = '';

if (!empty($origin) && !empty($siteHost)) {
    $originHost = parse_url($origin, PHP_URL_HOST);
    if ($originHost === $siteHost) {
        $allowOrigin = $origin;
    }
}

// 设置响应头
header('Content-Type: ' . $contentType);
header('Content-Length: ' . strlen($response));
if ($allowOrigin !== '') {
    header('Access-Control-Allow-Origin: ' . $allowOrigin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Range');
}
header('Cache-Control: public, max-age=3600');
header('X-Proxy-By: LinMeng-Blog-MusicProxy/1.0');
header('X-Content-Type-Options: nosniff');

// 输出音频
echo $response;
