<?php
/**
 * 评论区城市与浏览器解析（留言板 / 文章评论共用）
 *
 * 设计说明：
 * - 城市 / 浏览器存入 app_comment.city / app_comment.browser（自动建列，老库兼容），
 *   发表时解析一次，列表展示零网络开销；老评论按 UA 免费补浏览器，城市按缓存懒补。
 * - 城市只精确到市（直辖市显示市名），失败 / 内网 IP 则隐藏，不阻塞评论发布与页面渲染。
 * - 在线解析走太平洋免费接口（无 key），结果带 90 天文件缓存（cache/ip_geo.json，
 *   该目录 git 忽略）；失败结果负缓存 1 小时，避免频繁打外部接口。
 */

if (!defined('APP_COMMENT_META_CACHE_TTL')) {
    define('APP_COMMENT_META_CACHE_TTL', 90 * 86400);
}
if (!defined('APP_COMMENT_META_NEG_CACHE_TTL')) {
    // 解析失败的负缓存（短 TTL，避免每次访问都打一次外部接口）
    define('APP_COMMENT_META_NEG_CACHE_TTL', 3600);
}
if (!defined('APP_COMMENT_META_API_CAP')) {
    // 单次请求内最多触发的在线解析次数：兜底页面渲染延迟（发表走 fresh 通道不受限）
    define('APP_COMMENT_META_API_CAP', 6);
}

if (!function_exists('e') && !function_exists('commentMetaEscape')) {
    // 独立 CLI 自测时 functions.php 可能未加载，这里给个同等转义兜底；
    // 正常请求下 e() 已由 functions.php 定义，本函数不会被用到。
    function commentMetaEscape($string) {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}

function commentMetaEscapeProxy($string) {
    return function_exists('e') ? e($string) : commentMetaEscape($string);
}

/**
 * 从 User-Agent 解析浏览器大类（微信 / QQ 等国产浏览器保留中文名）
 * 未知则返回 ''（调用方直接隐藏，不展示“未知”二字）。
 */
function parseCommentBrowser($userAgent) {
    $ua = trim((string)$userAgent);
    if ($ua === '') {
        return '';
    }

    // 爬虫先行：这类 UA 里常夹带 Chrome/Safari 字样，必须先拦截
    if (preg_match('/(googlebot|bingbot|baiduspider|sogou\s*orion\s*spider|360spider|bytespider|yisouspider|mj12bot|ahrefsbot|semrushbot|dotbot|petalbot|spider|crawl|slurp|mediapartners-google)/i', $ua)) {
        return '爬虫';
    }

    // 国内 App 内置 / 主流国产浏览器
    if (stripos($ua, 'micromessenger') !== false) {
        return '微信';
    }
    if (stripos($ua, 'qqbrowserlite') !== false || stripos($ua, 'mqqbrowser') !== false
        || stripos($ua, 'qqbrowser') !== false || stripos($ua, ' qq/') !== false) {
        return 'QQ浏览器';
    }
    if (stripos($ua, 'alipayclient') !== false) {
        return '支付宝';
    }
    if (stripos($ua, 'dingtalk') !== false) {
        return '钉钉';
    }
    if (stripos($ua, 'weibo') !== false) {
        return '微博';
    }
    if (stripos($ua, 'quark') !== false) {
        return '夸克';
    }
    if (stripos($ua, '360se') !== false || stripos($ua, '360ee') !== false || stripos($ua, 'qihu') !== false) {
        return '360浏览器';
    }
    if (stripos($ua, 'metasr') !== false || stripos($ua, 'sogou') !== false) {
        return '搜狗浏览器';
    }
    if (stripos($ua, 'maxthon') !== false) {
        return '傲游';
    }
    if (stripos($ua, 'ucbrowser') !== false || stripos($ua, 'ucweb') !== false) {
        return 'UC浏览器';
    }
    if (stripos($ua, 'huaweibrowser') !== false) {
        return '华为浏览器';
    }
    if (stripos($ua, 'miuibrowser') !== false) {
        return '小米浏览器';
    }
    if (stripos($ua, 'vivobrowser') !== false) {
        return 'vivo浏览器';
    }
    if (stripos($ua, 'heytapbrowser') !== false || stripos($ua, 'oppobrowser') !== false) {
        return 'OPPO浏览器';
    }

    // 国际浏览器（按 UA 特征重叠度排序：Edge/Opera 的 UA 都含 Chrome，必须先判）
    if (stripos($ua, 'edg/') !== false || stripos($ua, 'edga/') !== false
        || stripos($ua, 'edgios/') !== false || stripos($ua, 'edge/') !== false) {
        return 'Edge';
    }
    if (stripos($ua, 'opr/') !== false || stripos($ua, 'opt/') !== false || stripos($ua, 'opera') !== false) {
        return 'Opera';
    }
    if (stripos($ua, 'crios/') !== false || stripos($ua, 'chrome/') !== false) {
        return 'Chrome';
    }
    if (stripos($ua, 'fxios/') !== false || stripos($ua, 'firefox/') !== false) {
        return 'Firefox';
    }
    if (stripos($ua, 'safari/') !== false) {
        return 'Safari';
    }
    if (stripos($ua, 'msie') !== false || stripos($ua, 'trident/') !== false) {
        return 'IE';
    }

    return '';
}

/**
 * 地名归一化：去掉“市/省/自治区/特别行政区”等后缀，展示更短
 */
function normalizeGeoName($name) {
    $name = trim((string)$name);
    if ($name === '') {
        return '';
    }
    foreach (['特别行政区', '自治区', '自治州', '地区'] as $suffix) {
        $len = mb_strlen($suffix, 'UTF-8');
        if (mb_substr($name, -$len, null, 'UTF-8') === $suffix) {
            $name = mb_substr($name, 0, mb_strlen($name, 'UTF-8') - $len, 'UTF-8');
            break;
        }
    }
    if (preg_match('/^(.*)[市省区县盟旗]$/u', $name, $m) && mb_strlen($m[1], 'UTF-8') >= 1) {
        $name = $m[1];
    }
    return $name;
}

function commentGeoCacheFile() {
    $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
    return rtrim($root, '/\\') . '/cache/ip_geo.json';
}

function loadCommentGeoCache() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    $file = commentGeoCacheFile();
    if (is_readable($file)) {
        $decoded = json_decode((string)@file_get_contents($file), true);
        if (is_array($decoded)) {
            $cache = $decoded;
        }
    }
    return $cache;
}

function saveCommentGeoCache(array $cache) {
    // 控制体积：只保留最近 4000 条
    if (count($cache) > 4000) {
        $cache = array_slice($cache, -4000, null, true);
    }
    $file = commentGeoCacheFile();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($file, json_encode($cache, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/**
 * 太平洋免费接口查 IP 归属（无 key，GBK 编码），超时 2 秒，任何异常返回 ''
 */
function fetchIpCityFromApi($ip) {
    $url = 'https://whois.pconline.com.cn/ipJson.jsp?ip=' . urlencode($ip) . '&json=true';
    $raw = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        $raw = curl_exec($ch);
        curl_close($ch);
    } elseif (function_exists('file_get_contents')) {
        $ctx = stream_context_create(['http' => ['timeout' => 2, 'header' => "User-Agent: Mozilla/5.0\r\n"]]);
        $raw = @file_get_contents($url, false, $ctx);
    }

    if (!is_string($raw) || $raw === '') {
        return '';
    }
    if (function_exists('mb_convert_encoding')) {
        $raw = mb_convert_encoding($raw, 'UTF-8', 'GBK');
    } elseif (function_exists('iconv')) {
        $converted = @iconv('GBK', 'UTF-8//IGNORE', $raw);
        if (is_string($converted) && $converted !== '') {
            $raw = $converted;
        }
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return '';
    }

    $pro = normalizeGeoName($data['pro'] ?? '');
    $city = normalizeGeoName($data['city'] ?? '');
    if ($city !== '') {
        return $city;
    }
    if ($pro !== '') {
        return $pro;
    }
    // 兜底：从 addr（如“广东省深圳市 电信”）里取最后一个“X市”
    $addr = (string)($data['addr'] ?? '');
    if ($addr !== '' && preg_match('/.*([\x{4e00}-\x{9fa5}]{1,7}市)/u', $addr, $m)) {
        return normalizeGeoName($m[1]);
    }
    return '';
}

/**
 * IP 解析到城市（只到市一级）。内网 / 非法 / 解析失败返回 ''。
 * $fresh=true 为发表时的单次解析（不受单请求次数上限约束，走缓存优先）。
 */
function resolveIpCity($ip, $fresh = false) {
    static $mem = [];
    static $apiCalls = 0;
    $ip = trim((string)$ip);
    if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return '';
    }
    // 内网、回环、保留地址不对外查询
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return '';
    }
    if (array_key_exists($ip, $mem)) {
        return $mem[$ip];
    }

    $cache = loadCommentGeoCache();
    if (isset($cache[$ip]) && is_array($cache[$ip])) {
        $age = time() - (int)($cache[$ip]['t'] ?? 0);
        $cachedCity = (string)($cache[$ip]['city'] ?? '');
        if (($cachedCity !== '' && $age < APP_COMMENT_META_CACHE_TTL)
            || ($cachedCity === '' && $age < APP_COMMENT_META_NEG_CACHE_TTL)) {
            $mem[$ip] = $cachedCity;
            return $cachedCity;
        }
    }

    // 列表页懒补老数据时限流，避免一次渲染触发太多外部请求拖慢页面
    if (!$fresh && $apiCalls >= APP_COMMENT_META_API_CAP) {
        $mem[$ip] = '';
        return '';
    }
    $apiCalls++;

    $city = fetchIpCityFromApi($ip);
    $mem[$ip] = $city;
    $cache[$ip] = ['city' => $city, 't' => time()];
    saveCommentGeoCache($cache);
    return $city;
}

/**
 * 发表时调用：一次算出要入库的城市与浏览器（解析失败给空串，不阻断发表）
 */
function buildCommentMetaFields($ip, $userAgent) {
    return [
        'city' => resolveIpCity($ip, true),
        'browser' => parseCommentBrowser($userAgent),
    ];
}

/**
 * 确保 app_comment 存在 city / browser 列（首次发表时自动补列，老库兼容）
 */
function ensureCommentMetaColumns() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo = db()->getPdo();
        $cols = [];
        $stmt = $pdo->query('SHOW COLUMNS FROM `app_comment`');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cols[strtolower((string)($row['Field'] ?? ''))] = true;
        }
        if (!isset($cols['city'])) {
            $pdo->exec("ALTER TABLE `app_comment` ADD COLUMN `city` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '评论者所在城市'");
        }
        if (!isset($cols['browser'])) {
            $pdo->exec("ALTER TABLE `app_comment` ADD COLUMN `browser` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '评论者浏览器'");
        }
    } catch (Exception $e) {
        error_log('ensureCommentMetaColumns failed: ' . $e->getMessage());
    }
}

/**
 * 评论入库统一入口：自动补列；极端情况下（如列缺失且无 ALTER 权限）去新字段重试，
 * 保证评论照常发布，城市/浏览器只是不显示。
 */
function insertCommentRow(array $row) {
    ensureCommentMetaColumns();
    try {
        return db()->insert('app_comment', $row);
    } catch (Exception $e) {
        unset($row['city'], $row['browser']);
        return db()->insert('app_comment', $row);
    }
}

/**
 * 评论归属文案：如“深圳 · Chrome”，��失项自动省略，都缺失返回 ''
 * （优先用入库值；老评论按 UA 免费补浏览器、按缓存懒补城市）
 */
function formatCommentMeta($comment) {
    $parts = [];
    $city = trim((string)($comment['city'] ?? ''));
    if ($city === '') {
        $city = resolveIpCity($comment['ip'] ?? '');
    }
    if ($city !== '') {
        $parts[] = $city;
    }
    $browser = trim((string)($comment['browser'] ?? ''));
    if ($browser === '') {
        $browser = parseCommentBrowser($comment['user_agent'] ?? '');
    }
    if ($browser !== '') {
        $parts[] = $browser;
    }
    return implode(' · ', $parts);
}

/**
 * 评论归属 HTML（前台留言板 / 文章评论区共用），无信息时返回空字符串
 */
function commentMetaHtml($comment) {
    $text = formatCommentMeta($comment);
    if ($text === '') {
        return '';
    }
    return '<span class="comment-meta">' . commentMetaEscapeProxy($text) . '</span>';
}
