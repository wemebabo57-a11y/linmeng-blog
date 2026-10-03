<?php
/**
 * 心知天气代理接口
 * 服务端持有密钥（公钥+私钥签名鉴权），根据访客 IP 定位城市，
 * 返回实时天气数据，避免前端直连泄露私钥与跨域问题。
 * 设置项：weather_popup_enabled / seniverse_public_key / seniverse_private_key / weather_city
 */
define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/includes/config.php';
require_once APP_ROOT . '/includes/Security.php';
require_once APP_ROOT . '/includes/Database.php';
require_once APP_ROOT . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
// 结果因访客 IP 而异，只允许浏览器私有缓存，禁止 CDN 共享缓存；
// Vary 提示遵守规范的 CDN：响应随这些头变化，不要跨用户复用
header('Cache-Control: private, max-age=600');
header('Vary: CF-Connecting-IP, X-Real-IP, X-Forwarded-For');

// 此接口无会话依赖，不启动 session，避免携带 Set-Cookie

// 速率限制：每个 IP 每分钟最多 30 次
if (!Security::checkRateLimit(Security::getClientIp(), 'weather_proxy', 30, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'rate_limited'], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 请求心知天气"实况天气"接口
 * 鉴权优先使用"公钥+私钥签名"方式（请求中不出现明文私钥），
 * 未配置公钥时回退为私钥直接请求方式。
 * HTTP 客户端：curl 优先（更通用），allow_url_fopen 兜底。
 *
 * @param string $location  地点（IP / 城市名 / 拼音等）
 * @param string $publicKey 公钥（uid），可为空
 * @param string $privateKey 私钥
 * @param string|null $lastError 输出参数：失败原因（调试用）
 * @return array|null 成功返回 results[0]，失败返回 null（诊断信息写入 error_log）
 */
function seniverseWeatherNow($location, $publicKey, $privateKey, &$lastError = null)
{
    $params = [
        'location' => $location,
        'language' => 'zh-Hans',
        'unit' => 'c',
    ];

    if ($publicKey !== '') {
        // 参与签名的参数只有 ts / ttl / uid，按参数名字典升序排列
        $sigParams = [
            'ts' => (string)time(),
            'ttl' => '300',
            'uid' => $publicKey,
        ];
        ksort($sigParams);
        $pairs = [];
        foreach ($sigParams as $k => $v) {
            $pairs[] = $k . '=' . $v;
        }
        $stringToSign = implode('&', $pairs);
        $sig = rawurlencode(base64_encode(hash_hmac('sha1', $stringToSign, $privateKey, true)));
        $query = http_build_query(array_merge($sigParams, $params)) . '&sig=' . $sig;
    } else {
        $query = http_build_query(array_merge(['key' => $privateKey], $params));
    }

    $url = 'https://api.seniverse.com/v3/weather/now.json?' . $query;

    // curl 优先
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            error_log('weather.php curl failed: ' . $curlError);
            $lastError = 'curl: ' . $curlError;
            return null;
        }
        if ($httpCode !== 200) {
            // 记录心知返回的原始错误（如密钥无效/余额不足/频率超限），便于排查
            error_log('weather.php seniverse HTTP ' . $httpCode . ' body: ' . substr((string)$response, 0, 300));
            $lastError = 'HTTP ' . $httpCode . ': ' . substr((string)$response, 0, 200);
            return null;
        }
    } else {
        // 无 curl 时回退 file_get_contents（需要 allow_url_fopen + openssl）
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 6,
                'method' => 'GET',
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $ctx);
        if ($response === false) {
            error_log('weather.php file_get_contents failed (check allow_url_fopen/openssl)');
            $lastError = 'file_get_contents failed (check allow_url_fopen/openssl)';
            return null;
        }
    }

    $data = json_decode((string)$response, true);
    if (!is_array($data) || empty($data['results'][0]['now']) || empty($data['results'][0]['location'])) {
        $reason = 'unexpected_payload: ' . substr(json_encode($data), 0, 200);
        error_log('weather.php ' . $reason);
        $lastError = $reason;
        return null;
    }

    return $data['results'][0];
}

// 只要私钥已配置即提供数据：弹窗开关只控制弹窗行为，侧栏天气组件也依赖此接口
$publicKey = trim((string)getSetting('seniverse_public_key', ''));
$privateKey = trim((string)getSetting('seniverse_private_key', ''));

if ($privateKey === '') {
    echo json_encode(['success' => false, 'error' => 'not_configured'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 定位策略：优先用访客公网 IP 定位，失败或不可用时回退到后台设置的默认城市。
// 使用展示型 IP（站点在 Cloudflare 后且未开 APP_TRUST_PROXY 时也能拿到真实访客 IP）
$clientIp = Security::getDisplayClientIp();
$defaultCity = trim((string)getSetting('weather_city', ''));
if ($defaultCity === '') {
    $defaultCity = '北京';
}

$useIp = filter_var(
    $clientIp,
    FILTER_VALIDATE_IP,
    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
);
// 心知天气的 IP 库对 IPv6 支持有限，IPv6 直接走城市兜底
if ($useIp && filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
    $useIp = false;
}

$lastError = '';
$result = null;
$source = 'city';

if ($useIp) {
    $result = seniverseWeatherNow($clientIp, $publicKey, $privateKey, $lastError);
    if ($result !== null) {
        $source = 'ip';
    } else {
        error_log('weather.php ip locate failed (' . $clientIp . '), fallback to city: ' . $defaultCity . ' | ' . $lastError);
    }
}
if ($result === null) {
    $result = seniverseWeatherNow($defaultCity, $publicKey, $privateKey, $lastError);
}

if ($result === null) {
    echo json_encode(['success' => false, 'error' => 'fetch_failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$city = isset($result['location']['name']) ? (string)$result['location']['name'] : '';
$now = $result['now'];

$payload = [
    'success' => true,
    'source' => $source,
    'city' => $city,
    'text' => isset($now['text']) ? (string)$now['text'] : '',
    'code' => isset($now['code']) ? (string)$now['code'] : '99',
    'temperature' => isset($now['temperature']) ? (string)$now['temperature'] : '',
];

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
