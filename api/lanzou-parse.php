<?php
/**
 * 蓝奏云直链解析代理 API
 * 前端通过此接口请求解析，真实接口地址与 Key 不在前端暴露
 *
 * 安全策略：
 * - 仅允许 POST + CSRF Token
 * - 滑动窗口限流（每 IP 每分钟 6 次）
 * - 输入校验：url 必填且必须是蓝奏云域名白名单；pwd/type 长度限制
 * - 上游请求启用 SSL 证书校验
 * - 上游响应仅透传白名单字段，避免 SSRF 反射与信息泄露
 */
define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// 仅允许 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Security::jsonResponse(['success' => false, 'message' => '请求方式错误']);
}

// CSRF 校验
$token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!Security::validateToken($token)) {
    Security::jsonResponse(['success' => false, 'message' => '安全验证失败，请刷新页面重试']);
}

// 全局开关
if (getSetting('tools_enabled', '1') !== '1') {
    Security::jsonResponse(['success' => false, 'message' => '工具页已关闭']);
}
if (getSetting('lanzou_parse_enabled', '1') !== '1') {
    Security::jsonResponse(['success' => false, 'message' => '蓝奏云解析功能已关闭']);
}

// 限流：每 IP 每分钟最多 6 次，防止滥用
if (!Security::checkRateLimit(Security::getClientIp(), 'lanzou_parse', 6, 60)) {
    Security::jsonResponse(['success' => false, 'message' => '请求过于频繁，请稍后再试']);
}

// ===== 输入校验 =====
$url = trim($_POST['url'] ?? '');
$pwd = trim($_POST['pwd'] ?? '');
$type = trim($_POST['type'] ?? '');

if ($url === '') {
    Security::jsonResponse(['success' => false, 'message' => '请填写文件链接']);
}
if (strlen($url) > 500) {
    Security::jsonResponse(['success' => false, 'message' => '链接过长']);
}
if (strlen($pwd) > 50) {
    Security::jsonResponse(['success' => false, 'message' => '密码过长']);
}
// type 仅允许空或 down
if ($type !== '' && $type !== 'down') {
    $type = '';
}

// SSRF 防护：仅允许蓝奏云官方域名白名单
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    Security::jsonResponse(['success' => false, 'message' => '链接格式不正确']);
}
$host = strtolower(parse_url($url, PHP_URL_HOST));
$allowedHostSuffixes = [
    'lanzou.com',
    'lanzoui.com',
    'lanzoux.com',
    'lanzouy.com',
    'lanzoub.com',
    'lanzoue.com',
    'lanzoup.com',
    'lanzouf.com',
    'lanzoug.com',
    'lanzouh.com',
    'lanzouj.com',
    'lanzouk.com',
    'lanzoul.com',
    'lanzoum.com',
    'lanzoun.com',
    'lanzouo.com',
    'lanzous.com',
    'lanzout.com',
    'lanzouv.com',
    'lanzouw.com',
    'lanzouz.com',
    'wwe.lanzou.com',
    'wwa.lanzou.com',
    'wwb.lanzou.com',
    'wwc.lanzou.com',
    'wwd.lanzou.com',
    'wwe.lanzoui.com',
    'wwe.lanzoux.com',
    'wwe.lanzouy.com',
];
$hostAllowed = false;
foreach ($allowedHostSuffixes as $suffix) {
    if ($host === $suffix || substr($host, -strlen($suffix) - 1) === '.' . $suffix) {
        $hostAllowed = true;
        break;
    }
}
// 兜底：所有 lanzou*.com 域名
if (!$hostAllowed && preg_match('/^(wwe|wwa|wwb|wwc|wwd|m|www)?\.?lanzou[a-z]?\.com$/i', $host)) {
    $hostAllowed = true;
}
if (!$hostAllowed) {
    Security::jsonResponse(['success' => false, 'message' => '仅支持蓝奏云官方域名链接']);
}

// ===== 组装上游请求 =====
$apiUrl = getSetting('lanzou_parse_api_url', 'https://api.zxki.cn/api/lzy');
$apiKey = getSetting('lanzou_parse_api_key', '');

// 拼接查询参数
$query = http_build_query([
    'url'  => $url,
    'pwd'  => $pwd,
    'type' => $type,
]);
$sep = strpos($apiUrl, '?') === false ? '?' : '&';
$fullUrl = $apiUrl . $sep . $query;

// 构建请求头
$reqHeaders = [
    'Accept: application/json',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
];
if ($apiKey !== '') {
    $reqHeaders[] = 'X-API-Key: ' . $apiKey;
}

// 优先使用 cURL（可获取 HTTP 状态码），不可用时回退到 file_get_contents
$response = false;
$httpCode = 0;

if (function_exists('curl_init')) {
    $ch = curl_init($fullUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER     => $reqHeaders,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('lanzou parse curl error: ' . $curlError);
        Security::jsonResponse(['success' => false, 'message' => '解析服务请求失败（cURL: ' . $curlError . '），请稍后重试']);
    }
} else {
    // 回退方案：file_get_contents + stream_context（与 hitokoto.php / music-proxy.php 一致）
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'header' => implode("\r\n", $reqHeaders),
            'follow_location' => false,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        ],
    ]);
    $response = @file_get_contents($fullUrl, false, $context);

    if ($response === false) {
        error_log('lanzou parse file_get_contents failed: ' . $fullUrl);
        Security::jsonResponse(['success' => false, 'message' => '解析服务请求失败，请稍后重试']);
    }

    // 从 $http_response_header 解析 HTTP 状态码
    if (isset($http_response_header) && is_array($http_response_header)) {
        if (preg_match('/^HTTP\/[\d.]+\s+(\d+)/', $http_response_header[0], $m)) {
            $httpCode = (int)$m[1];
        }
    }
}

if ($type === 'down') {
    // type=down 模式：上游直接 302 跳转，此处不跟随跳转
    // 前端 JS 会用 JSON 模式取 downurl 后自动跳转
    Security::jsonResponse(['success' => false, 'message' => '请使用 JSON 解析模式获取直链']);
}

if ($httpCode !== 200) {
    error_log('lanzou parse upstream http ' . $httpCode . ' response: ' . substr((string)$response, 0, 300));
    Security::jsonResponse(['success' => false, 'message' => '解析服务异常（HTTP ' . $httpCode . '），请稍后重试']);
}

// ===== 响应白名单透传 =====
$data = json_decode($response, true);
if (!is_array($data)) {
    Security::jsonResponse(['success' => false, 'message' => '解析服务返回格式异常']);
}

// 仅透传白名单字段，过滤 sign 等可能包含敏感信息的字段
$code = isset($data['code']) ? (int)$data['code'] : 0;
$msg = isset($data['msg']) ? (string)$data['msg'] : '';
$name = isset($data['name']) ? (string)$data['name'] : '';
$filesize = isset($data['filesize']) ? (string)$data['filesize'] : '';
$downurl = isset($data['downurl']) ? (string)$data['downurl'] : '';

// 校验 downurl 是合法的 http(s) 链接，防止注入 javascript: 等
if ($downurl !== '' && !filter_var($downurl, FILTER_VALIDATE_URL)) {
    $downurl = '';
}
// 仅允许 http/https 协议
if ($downurl !== '') {
    $dlScheme = strtolower(parse_url($downurl, PHP_URL_SCHEME));
    if (!in_array($dlScheme, ['http', 'https'], true)) {
        $downurl = '';
    }
}

Security::jsonResponse([
    'success'  => $code === 200,
    'code'     => $code,
    'message'  => $msg !== '' ? $msg : ($code === 200 ? '解析成功' : '解析失败'),
    'name'     => $name,
    'filesize' => $filesize,
    'downurl'  => $downurl,
    'time'     => date('Y-m-d H:i:s'),
]);
