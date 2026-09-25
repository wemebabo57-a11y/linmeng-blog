<?php
/**
 * 公共函数库 v2.0
 */
require_once __DIR__ . '/ArticleContent.php';

/**
 * 获取数据库实例
 */
function db() {
    return Database::getInstance();
}

// 暴露全局 PDO 实例，供安全类等使用
$GLOBALS['db'] = db()->getPdo();


function ensureUploadPath() {
    if (!is_dir(UPLOAD_PATH)) {
        @mkdir(UPLOAD_PATH, 0755, true);
    }
    return is_dir(UPLOAD_PATH) && is_writable(UPLOAD_PATH);
}

function saveUploadedImage($file, $prefix = '') {
    $prefix = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$prefix);
    if (!ensureUploadPath()) {
        return ['success' => false, 'message' => '上传目录不存在或不可写'];
    }

    $validate = Security::validateUpload($file);
    if (!$validate['valid']) {
        return ['success' => false, 'message' => implode('，', $validate['errors'])];
    }

    $fileName = $prefix . Security::generateFileName($validate['ext']);
    $uploadPath = UPLOAD_PATH . $fileName;
    $tmpPath = $file['tmp_name'];

    if (!Security::reprocessImage($tmpPath, $uploadPath, $validate['mime'])) {
        return ['success' => false, 'message' => '图片重新处理失败，上传被拒绝'];
    }

    return ['success' => true, 'url' => '/assets/uploads/' . $fileName];
}

function isValidImageUrl($url) {
    $url = trim((string)$url);
    if ($url === '') {
        return false;
    }
    $scheme = strtolower((string)(parse_url($url, PHP_URL_SCHEME) ?: ''));
    if ($scheme !== '') {
        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    return strpos($url, '/') === 0 && strpos($url, '//') !== 0;
}


/**
 * HTML实体编码
 */
function e($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * 渲染评论/留言作者（带网站链接时进行协议校验）
 */
function formatCommentAuthor($comment) {
    $nickname = e($comment['nickname'] ?? '');
    $website = trim($comment['website'] ?? '');
    if ($website !== '') {
        // sanitizeUrl 已做协议白名单校验（仅允许 http/https/mailto，危险协议返回 #），
        // 其本身不做 HTML 转义；评论 website 入库前已转义，此处不可再用 e() 二次转义，
        // 否则 URL 中的 & 会被编码为 &amp; 导致带查询参数的链接失效。
        $safeUrl = Security::sanitizeUrl($website);
        return '<a href="' . e($safeUrl) . '" target="_blank" rel="noopener noreferrer">' . $nickname . '</a>';
    }
    return $nickname;
}

/**
 * 获取设置项
 */
function getSetting($key, $default = '') {
    if (!array_key_exists('lm_settings_cache', $GLOBALS)) {
        $GLOBALS['lm_settings_cache'] = [];
        try {
            $rows = db()->fetchAll("SELECT setting_key, setting_value FROM lm_setting");
            foreach ($rows as $row) {
                $GLOBALS['lm_settings_cache'][(string)$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $GLOBALS['lm_settings_cache'] = [];
        }
    }

    return array_key_exists($key, $GLOBALS['lm_settings_cache'])
        ? $GLOBALS['lm_settings_cache'][$key]
        : $default;
}

/**
 * 设置设置项
 */
function setSetting($key, $value) {
    try {
        $exists = db()->fetchColumn(
            "SELECT COUNT(*) FROM lm_setting WHERE setting_key = ?",
            [$key]
        );
        
        if ($exists) {
            db()->update('lm_setting', ['setting_value' => $value], 'setting_key = ?', [$key]);
        } else {
            db()->insert('lm_setting', [
                'setting_key' => $key,
                'setting_value' => $value
            ]);
        }
        if (array_key_exists('lm_settings_cache', $GLOBALS)) {
            $GLOBALS['lm_settings_cache'][$key] = $value;
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * 获取时间偏移量（秒）
 */
function getTimeOffset() {
    return (int)getSetting('site_time_offset', 0);
}

/**
 * 获取校准后的当前时间戳
 */
function siteTime() {
    return time() + getTimeOffset();
}

/**
 * 对日期/时间戳应用时间偏移
 */
function applyTimeOffset($date) {
    $timestamp = is_numeric($date) ? (int)$date : strtotime($date);
    return $timestamp + getTimeOffset();
}

/**
 * 获取文章数量
 */
function getArticleCount() {
    try {
        return db()->fetchColumn("SELECT COUNT(*) FROM lm_article") ?: 0;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * 获取评论数量
 */
function getCommentCount() {
    try {
        return db()->fetchColumn("SELECT COUNT(*) FROM lm_comment WHERE status = 1") ?: 0;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * 获取友链列表
 */
function getLinks() {
    try {
        return db()->fetchAll("SELECT * FROM lm_link ORDER BY sort_order DESC, id ASC");
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 获取可见友链列表（前台展示用）
 */
function getVisibleLinks() {
    try {
        return db()->fetchAll(
            "SELECT * FROM lm_link WHERE status = 1 ORDER BY sort_order DESC, id ASC"
        );
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 获取赞助商列表
 */
function getSponsors($onlyVisible = true) {
    try {
        $sql = "SELECT * FROM lm_sponsor";
        if ($onlyVisible) {
            $sql .= " WHERE status = 1";
        }
        $sql .= " ORDER BY sort_order DESC, id ASC";
        return db()->fetchAll($sql);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 获取分类列表
 */
function getCategories() {
    try {
        return db()->fetchAll("SELECT * FROM lm_category ORDER BY sort_order DESC, id ASC");
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 获取最新文章
 */
function getLatestArticles($limit = 5) {
    try {
        return db()->fetchAll(
            "SELECT id, title, slug, created_at FROM lm_article WHERE status = 'published' ORDER BY created_at DESC LIMIT ?",
            [$limit]
        );
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 获取热门文章
 */
function getHotArticles($limit = 5) {
    try {
        return db()->fetchAll(
            "SELECT id, title, slug, views, created_at FROM lm_article WHERE status = 'published' ORDER BY views DESC LIMIT ?",
            [$limit]
        );
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 获取创作日历数据
 * 按天聚合已发布文章，返回 ['Y-m-d' => 篇数]
 */
function getArticleCalendarDays() {
    static $days = null;
    if ($days !== null) {
        return $days;
    }
    $days = [];
    try {
        $rows = db()->fetchAll(
            "SELECT DATE(created_at) AS d, COUNT(*) AS c
             FROM lm_article
             WHERE status = 'published'
             GROUP BY DATE(created_at)"
        );
        foreach ($rows as $row) {
            if (!empty($row['d'])) {
                $days[$row['d']] = (int)$row['c'];
            }
        }
    } catch (Exception $e) {
        $days = [];
    }
    return $days;
}

/**
 * 获取运行天数
 */
function getRunningDays() {
    $startDate = getSetting('site_start_date', date('Y-m-d'));
    $start = strtotime($startDate);
    $now = siteTime();
    return max(0, floor(($now - $start) / 86400));
}

/**
 * 获取站点访问人数
 */
function getVisitorCount() {
    try {
        return (int) getSetting('site_visitor_count', 0);
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * 格式化日期
 */
function formatDate($date) {
    return date('Y-m-d H:i', applyTimeOffset($date));
}

/**
 * 时间友好显示
 */
function timeAgo($date) {
    $time = applyTimeOffset($date);
    $now = siteTime();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return '刚刚';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . '分钟前';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . '小时前';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . '天前';
    } elseif ($diff < 2592000) {
        return floor($diff / 604800) . '周前';
    } elseif ($diff < 31536000) {
        return floor($diff / 2592000) . '个月前';
    } else {
        return floor($diff / 31536000) . '年前';
    }
}

/**
 * 生成文章摘要
 */
function getExcerpt($content, $length = 30) {
    // 去除HTML标签
    $text = strip_tags($content);
    // 去除多余空白
    $text = preg_replace('/\s+/', ' ', $text);
    // 截取
    if (mb_strlen($text) > $length) {
        return mb_substr($text, 0, $length) . '...';
    }
    return $text;
}

/**
 * 格式化文章正文用于前台显示
 *
 * 后台 textarea 是纯文本输入，换行符 \n 在 HTML 中会被折叠为空白，
 * 导致排版丢失。此函数检测内容是否已含块级 HTML 标签：
 * - 是：视为已结构化 HTML，原样返回（保留作者写的 <p>/<h2>/style 等）
 * - 否：按空行拆段，段内单换行转 <br>，保留行内标签（<strong>/<a> 等）
 *
 * 不修改数据库内容，仅在前台渲染时处理，已发布文章不受影响。
 */
function formatArticleContent($content) {
    if ($content === '' || $content === null) {
        return '';
    }
    // 检测是否包含块级标签（开或闭）——有则视为已结构化 HTML
    if (preg_match('/<\/?(p|div|section|article|h[1-6]|ul|ol|li|dl|dt|dd|blockquote|pre|table|thead|tbody|tfoot|tr|td|th|caption|hr|figure|figcaption|details|summary)\b/i', $content)) {
        return $content;
    }
    // 纯文本或仅含行内标签：按空行分段
    $content = trim($content);
    if ($content === '') {
        return '';
    }
    $paragraphs = preg_split('/\n\s*\n+/', $content);
    $html = '';
    foreach ($paragraphs as $p) {
        $p = trim($p);
        if ($p === '') {
            continue;
        }
        // 段内单换行转 <br>（nl2br 保留已有的行内 HTML 标签）
        $p = nl2br($p);
        $html .= '<p>' . $p . '</p>' . "\n";
    }
    return $html;
}

/** Render an article body from its legacy database HTML or Markdown source. */
function renderArticleContent(array $article) {
    return ArticleContent::render($article);
}

/** Return normalized article text for excerpts, reading time and AI features. */
function getArticlePlainText(array $article) {
    return ArticleContent::plainText($article);
}

/**
 * 截取字符串
 */
function truncate($string, $length = 100, $suffix = '...') {
    if (mb_strlen($string) > $length) {
        return mb_substr($string, 0, $length) . $suffix;
    }
    return $string;
}

/**
 * 生成分页HTML
 */
function pagination($currentPage, $totalPages, $urlPattern = '/?page=%d') {
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<div class="pagination">';
    
    // 上一页
    if ($currentPage > 1) {
        $html .= '<a href="' . sprintf($urlPattern, $currentPage - 1) . '" class="page-link">&lt;</a>';
    }
    
    // 页码
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    if ($start > 1) {
        $html .= '<a href="' . sprintf($urlPattern, 1) . '" class="page-link">1</a>';
        if ($start > 2) {
            $html .= '<span class="page-ellipsis">...</span>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i === $currentPage) {
            $html .= '<span class="page-link active">' . $i . '</span>';
        } else {
            $html .= '<a href="' . sprintf($urlPattern, $i) . '" class="page-link">' . $i . '</a>';
        }
    }
    
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<span class="page-ellipsis">...</span>';
        }
        $html .= '<a href="' . sprintf($urlPattern, $totalPages) . '" class="page-link">' . $totalPages . '</a>';
    }
    
    // 下一页
    if ($currentPage < $totalPages) {
        $html .= '<a href="' . sprintf($urlPattern, $currentPage + 1) . '" class="page-link">&gt;</a>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * 生成URL友好的slug
 */
function generateSlug($title) {
    $slug = mb_strtolower($title);
    $slug = preg_replace('/[^\w\s-]/u', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    
    if (empty($slug)) {
        $slug = date('Y-m-d') . '-' . uniqid();
    }
    
    // 检查是否已存在
    $originalSlug = $slug;
    $counter = 1;
    
    try {
        while (db()->fetchColumn("SELECT COUNT(*) FROM lm_article WHERE slug = ?", [$slug]) > 0) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
    } catch (Exception $e) {
        // 忽略
    }
    
    return $slug;
}

/**
 * 判断当前请求是否为 HTTPS（兼容反向代理 / CDN 转发）。
 * 与 config.php、api 等处的判断逻辑保持一致，避免各处重复且不一致的检测。
 */
function lm_is_https() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

/**
 * 公共页面条件会话：
 * - 携带会话 Cookie / 记住登录 Cookie，或非 GET/HEAD 请求（表单提交）：正常开启会话；
 * - 匿名 GET：不开启会话，响应不带 Set-Cookie，可被 CDN 缓存。
 * 返回 true 表示会话已开启。
 *
 * 携带 remember_token 的访客视为非匿名，会开启会话并尝试自动登录，
 * 因此其响应为私有、不进共享缓存。
 */
function lm_session_start() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        lm_attempt_remember_login();
        return true;
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $hasRemember = !empty($_COOKIE['remember_token']);
    if (isset($_COOKIE[session_name()]) || $hasRemember || ($method !== 'GET' && $method !== 'HEAD')) {
        $started = session_start();
        if ($started) {
            lm_attempt_remember_login();
        }
        return (bool)$started;
    }
    return false;
}

/**
 * 尝试通过 remember_token Cookie 自动登录（“记住我”）。
 *
 * - 已登录或无 Cookie 时直接返回；
 * - 命中数据库中启用状态的用户后写入会话，并轮换令牌（旧 Cookie 失效），
 *   降低令牌被盗用后的可重放窗口；
 * - 需要一个已开启的会话；若尚未开启会先行开启。
 *
 * 返回 true 表示已成功自动登录。
 */
function lm_attempt_remember_login() {
    if (isLoggedIn()) {
        return true;
    }
    $raw = isset($_COOKIE['remember_token']) ? (string)$_COOKIE['remember_token'] : '';
    // 本站令牌由 Security::randomString(32) 生成，为 32 位十六进制；做基本格式校验避免无谓查询
    if ($raw === '' || !preg_match('/^[a-f0-9]{16,128}$/i', $raw)) {
        return false;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (headers_sent() || !session_start()) {
            return false;
        }
    }

    try {
        $hashed = hash('sha256', $raw);
        $user = db()->fetchOne(
            "SELECT * FROM lm_admin WHERE remember_token = ? AND status = 1",
            [$hashed]
        );
    } catch (Exception $e) {
        return false;
    }

    if (!$user) {
        return false;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['is_admin'] = ($user['role'] === 'admin');

    // 防会话固定
    session_regenerate_id(true);

    // 轮换令牌：旧 Cookie 立即失效
    try {
        $newToken = Security::randomString(32);
        db()->update('lm_admin', ['remember_token' => hash('sha256', $newToken)], 'id = ?', [$user['id']]);
        if (!headers_sent()) {
            setcookie('remember_token', $newToken, [
                'expires'  => time() + 30 * 86400,
                'path'     => '/',
                'httponly' => true,
                'secure'   => lm_is_https(),
                'samesite' => 'Lax'
            ]);
        }
    } catch (Exception $e) {
        // 令牌轮换失败不影响本次登录
    }

    return true;
}

/**
 * 输出 CDN 友好的缓存头（仅对未开启会话的 GET/HEAD 生效）：
 * - max-age=0：浏览器每次重新校验，避免本地陈旧内容；
 * - s-maxage：CDN 边缘缓存时长（默认 10 分钟）；
 * - stale-while-revalidate：边缘过期后先返回旧内容并后台刷新，源站压力更小。
 * 会话已开启（登录用户/回头访客）时输出私有响应，禁止共享缓存。
 */
function lm_public_cache_headers($sMaxage = 600, $swr = 3600) {
    if (headers_sent()) {
        return;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        header('Cache-Control: private, no-cache');
        return;
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method !== 'GET' && $method !== 'HEAD') {
        return;
    }
    // 搜索结果与带提示参数的页面不进共享缓存，避免任意关键词污染边缘缓存
    foreach (['search', 's', 'q', 'keyword', 'msg'] as $key) {
        if (isset($_GET[$key]) && $_GET[$key] !== '') {
            header('Cache-Control: private, no-cache');
            return;
        }
    }
    header('Cache-Control: public, max-age=0, s-maxage=' . (int)$sMaxage . ', stale-while-revalidate=' . (int)$swr);
}

/**
 * 实时页面缓存头：禁止一切共享缓存（CDN/代理），浏览器每次回源校验。
 * 用于包含用户实时数据的页面：文章页（评论、点赞状态、点赞数）、留言板等。
 * 这些页面的内容对每位访客都可能不同（点赞按 IP 区分），绝不进边缘缓存。
 */
function lm_no_cache_headers() {
    if (headers_sent()) {
        return;
    }
    header('Cache-Control: private, no-cache');
}

/**
 * 检查是否已登录
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/**
 * 检查是否是管理员
 */
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

/**
 * 获取当前用户信息
 */
function currentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        return db()->fetchOne("SELECT * FROM lm_admin WHERE id = ?", [$_SESSION['user_id']]);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * 要求登录
 */
function requireLogin() {
    if (!isLoggedIn()) {
        lm_attempt_remember_login();
    }
    if (!isLoggedIn()) {
        Security::redirect('/login.php');
    }
}

/**
 * 要求管理员权限
 */
function requireAdmin() {
    if (!isLoggedIn()) {
        lm_attempt_remember_login();
    }
    if (!isAdmin()) {
        Security::redirect('/');
    }
}

/**
 * 校验统计代码是否仅包含允许的标签与域名
 */
function isValidAnalyticsCode($code) {
    if (trim($code) === '') {
        return true;
    }

    $allowedDomains = [
        'www.google-analytics.com',
        'www.googletagmanager.com',
        'ssl.google-analytics.com',
        'hm.baidu.com',
        'static.cloudflareinsights.com',
        'analytics.umami.is',
        'plausible.io',
        'scripts.simpleanalyticscdn.com',
        'queue.simpleanalyticscdn.com',
    ];

    // 只允许这些标签
    $allowedTags = '<script><noscript><img><iframe><div><span>';
    $cleaned = strip_tags($code, $allowedTags);
    if ($cleaned !== $code) {
        return false;
    }

    // 拒绝内联脚本事件处理器
    if (preg_match('/\s*on\w+\s*=/iu', $code)) {
        return false;
    }

    // 检查 script 标签
    if (preg_match_all('/<script\b[^>]*>([\s\S]*?)<\/script>/iu', $code, $matches)) {
        foreach ($matches[0] as $scriptTag) {
            if (!preg_match('/src\s*=\s*["\']?([^"\'>\s]+)["\']?/iu', $scriptTag, $srcMatch)) {
                return false; // 拒绝无 src 的内联脚本
            }
            $url = $srcMatch[1];
            $host = parse_url($url, PHP_URL_HOST);
            if (!$host || !in_array(strtolower($host), $allowedDomains, true)) {
                return false;
            }
        }
    }

    // 检查 img 标签
    if (preg_match_all('/<img\b[^>]*>/iu', $code, $matches)) {
        foreach ($matches[0] as $imgTag) {
            if (!preg_match('/src\s*=\s*["\']?([^"\'>\s]+)["\']?/iu', $imgTag, $srcMatch)) {
                return false;
            }
            $url = $srcMatch[1];
            $host = parse_url($url, PHP_URL_HOST);
            if (!$host || !in_array(strtolower($host), $allowedDomains, true)) {
                return false;
            }
        }
    }

    // 检查 iframe 标签
    if (preg_match_all('/<iframe\b[^>]*>/iu', $code, $matches)) {
        foreach ($matches[0] as $iframeTag) {
            if (!preg_match('/src\s*=\s*["\']?([^"\'>\s]+)["\']?/iu', $iframeTag, $srcMatch)) {
                return false;
            }
            $url = $srcMatch[1];
            $host = parse_url($url, PHP_URL_HOST);
            if (!$host || !in_array(strtolower($host), $allowedDomains, true)) {
                return false;
            }
        }
    }

    return true;
}

/**
 * 获取 GitHub OAuth 登录 URL
 */
function getGithubLoginUrl() {
    $clientId = getSetting('github_client_id', '');
    if (empty($clientId)) {
        return '#';
    }
    $state = Security::randomString(32);
    $_SESSION['github_oauth_state'] = $state;
    $redirectUri = rtrim(SITE_URL, '/') . '/github-callback.php';
    $params = [
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'scope' => 'read:user user:email',
        'state' => $state,
    ];
    return 'https://github.com/login/oauth/authorize?' . http_build_query($params);
}

/**
 * 获取 GitCode OAuth 登录 URL
 */
function getGitcodeLoginUrl() {
    $clientId = getSetting('gitcode_client_id', '');
    if (empty($clientId)) {
        return '#';
    }
    $state = Security::randomString(32);
    $_SESSION['gitcode_oauth_state'] = $state;
    $redirectUri = rtrim(SITE_URL, '/') . '/gitcode-callback.php';
    // scope 可后台配置，留空回退 all_user（读取用户基础资料）
    $scope = getSetting('gitcode_oauth_scope', '');
    if ($scope === '') {
        $scope = 'all_user';
    }
    $params = [
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => $scope,
        'state' => $state,
    ];
    return 'https://gitcode.com/oauth/authorize?' . http_build_query($params);
}

/**
 * 记录访问日志
 */
function logVisit() {
    try {
        $page = $_SERVER['REQUEST_URI'] ?? '/';
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        $ip = Security::getClientIp();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        db()->insert('lm_visit_log', [
            'page' => $page,
            'referer' => $referer,
            'ip' => $ip,
            'user_agent' => $ua,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        // 忽略日志错误
    }
}

/* ==================== 游戏模块 ==================== */

/**
 * 确保游戏表存在（首次访问自动创建）
 */
function ensureGameTables() {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $pdo = db()->getPdo();
        $pdo->exec("CREATE TABLE IF NOT EXISTS `lm_game` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(150) NOT NULL,
            `description` VARCHAR(500) NOT NULL DEFAULT '',
            `image_url` VARCHAR(500) NOT NULL DEFAULT '',
            `sort_order` INT NOT NULL DEFAULT 0,
            `status` TINYINT NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_status_sort` (`status`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Exception $e) {
        error_log('ensureGameTables failed: ' . $e->getMessage());
    }
}

/**
 * 获取所有游戏（后台管理用）
 */
function getAllGames() {
    ensureGameTables();
    try {
        return db()->fetchAll("SELECT * FROM lm_game ORDER BY sort_order DESC, id ASC");
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 获取可见游戏列表（前台用）
 */
function getVisibleGames() {
    ensureGameTables();
    try {
        return db()->fetchAll("SELECT * FROM lm_game WHERE status = 1 ORDER BY sort_order DESC, id ASC");
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 获取单个游戏
 */
function getGame($id) {
    ensureGameTables();
    try {
        return db()->fetchOne("SELECT * FROM lm_game WHERE id = ?", [(int)$id]);
    } catch (Exception $e) {
        return null;
    }
}
