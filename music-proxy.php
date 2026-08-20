<?php
/**
 * 音乐代理接口 - 解决跨域和 Referer 限制问题
 * 使用方式: /music-proxy.php?url=编码后的音频URL
 *
 * 安全策略：
 * - 域名白名单（防 SSRF，仅允许已知音乐源）
 * - IP 黑名单（防 SSRF，拒绝私有/保留地址段）
 * - 启用 SSL 证书校验（防中间人）
 * - CORS 仅放行本站 Origin（防滥用）
 * - 仅放行音频/视频内容类型；application/octet-stream 需经魔数字节嗅探确认
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

/**
 * 判断 IP 是否属于私有/保留地址段（防 SSRF）
 * - 合法公网 IP → 返回 false（允许）
 * - 合法但私有/保留 → 返回 true（拒绝）
 * - 非法 IP → 返回 true（保守拒绝）
 * 另显式拒绝 IPv6 回环 ::1 与 ULA 地址段 fc00::/7
 */
function isPrivateIp($ip)
{
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return true; // 非法 IP 视为私有，拒绝
    }
    // 显式拒绝 IPv6 回环 ::1
    if ($ip === '::1') {
        return true;
    }
    // IPv6 唯一本地地址 fc00::/7（首字节以 fc/fd 开头）
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
        $packed = @inet_pton($ip);
        if ($packed !== false && isset($packed[0])) {
            $firstByte = ord($packed[0]);
            if (($firstByte & 0xFE) === 0xFC) {
                return true;
            }
        }
    }
    // 公网校验：通过即公网，未通过即私有/保留
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return true;
    }
    return false;
}

/**
 * 通过魔数字节嗅探音频格式
 * 用于 application/octet-stream 响应体的二次确认
 * @param string $data 响应体
 * @return string|null 检测到的 MIME 类型，未匹配返回 null
 */
function sniffAudioMagic($data)
{
    $len = strlen($data);
    // ID3 标签（MP3 带 ID3v2 头）
    if ($len >= 3 && strncmp($data, 'ID3', 3) === 0) {
        return 'audio/mpeg';
    }
    // MPEG 帧同步字节 0xFF 0xFB / 0xF3 / 0xF2
    if ($len >= 2) {
        $first = ord($data[0]);
        $second = ord($data[1]);
        if ($first === 0xFF && in_array($second, [0xFB, 0xF3, 0xF2], true)) {
            return 'audio/mpeg';
        }
    }
    // M4A：ftyp box，offset 4 处为 "ftypM4A"
    if ($len >= 12 && substr($data, 4, 7) === 'ftypM4A') {
        return 'audio/mp4';
    }
    // FLAC
    if ($len >= 4 && strncmp($data, 'fLaC', 4) === 0) {
        return 'audio/flac';
    }
    // RIFF + WAVE
    if ($len >= 12 && strncmp($data, 'RIFF', 4) === 0 && substr($data, 8, 4) === 'WAVE') {
        return 'audio/wav';
    }
    // OggS
    if ($len >= 4 && strncmp($data, 'OggS', 4) === 0) {
        return 'audio/ogg';
    }
    // WebM / Matroska
    if ($len >= 4
        && ord($data[0]) === 0x1A && ord($data[1]) === 0x45
        && ord($data[2]) === 0xDF && ord($data[3]) === 0xA3) {
        return 'audio/webm';
    }
    return null;
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
    'y.qq.com',
    'dl.y.qq.com',
    // 酷狗
    'm.kugou.com',
    'trackercdn.kugou.com',
    'fsandroid.kugou.com',
    'mobi.kugou.com',
    'webfs.hw.kugou.com',
    'fs.kugou.com',
    'mobiles.kugou.com',
    // 虾米
    'xiami.com',
    'www.xiami.com',
    // 酷我
    'kuwo.cn',
    'www.kuwo.cn',
    'antiserver.kuwo.cn',
    // Bilibili 音频
    'audio-qn.jdcdn.com',
];

// 允许已知音频 CDN 的子域名后缀兜底（循环匹配，避免硬编码）
$allowedSuffixes = [
    '.music.126.net',  // 网易云
    '.qqmusic.qq.com', // QQ 音乐
    '.tc.qq.com',      // 腾讯 CDN
    '.kugou.com',      // 酷狗
    '.kuwo.cn',        // 酷我
    '.xiami.com',      // 虾米
];
$hostAllowed = in_array($host, $allowedHosts, true);
if (!$hostAllowed) {
    foreach ($allowedSuffixes as $suffix) {
        if (substr($host, -strlen($suffix)) === $suffix) {
            $hostAllowed = true;
            break;
        }
    }
}

if (!$hostAllowed) {
    http_response_code(403);
    die('Domain not allowed');
}

// SSRF 防护：解析目标域名 IP，拒绝私有/保留地址段
// 注意：gethostbynamel 仅解析 IPv4 A 记录（足以阻断常见内网回流）
// DNS 解析失败时不硬拒，仍依赖上方域名白名单保护，避免误伤合法域名
$resolvedIps = @gethostbynamel($host);
if (is_array($resolvedIps)) {
    foreach ($resolvedIps as $resolvedIp) {
        if (isPrivateIp($resolvedIp)) {
            http_response_code(403);
            die('Internal IP not allowed');
        }
    }
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
// 显式拒绝的非媒体类型（不匹配 audio/ video/ application/ogg，且非 octet-stream）：
//   text/html, text/plain, application/json, application/javascript,
//   application/xml, application/xhtml+xml, image/*, font/*, text/css 等
//   —— 以上均会直接返回 415
$allowedCtPrefixes = ['audio/', 'video/', 'application/ogg'];
$ctLower = strtolower($contentType);
$ctOk = false;
foreach ($allowedCtPrefixes as $prefix) {
    if (strpos($ctLower, $prefix) === 0) {
        $ctOk = true;
        break;
    }
}
// application/octet-stream 过于宽泛，需经魔数字节嗅探确认是否为音频
// QQ 音乐等 CDN 常以 octet-stream 返回 MP3/M4A/FLAC，嗅探成功后覆写 Content-Type
if (!$ctOk && $ctLower === 'application/octet-stream') {
    $detected = sniffAudioMagic($response);
    if ($detected !== null) {
        $contentType = $detected;
        $ctOk = true;
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
