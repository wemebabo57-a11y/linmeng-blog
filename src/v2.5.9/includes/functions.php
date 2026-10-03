<?php
/**
 * 公共函数库 v2.0
 */
require_once __DIR__ . '/ArticleContent.php';
require_once __DIR__ . '/CommentMeta.php';

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
 * 60 秒内同一 IP + 相同邮箱/内容视为重复评论（留言板与文章评论共用）。
 */
function app_is_recent_comment_duplicate($ip, $email, $content) {
    $dupeHash = hash('sha256', $ip . '|' . Security::xssClean($email) . '|' . Security::xssClean($content));
    try {
        return (bool)db()->fetchOne(
            "SELECT id FROM app_comment
             WHERE ip = ? AND SHA2(CONCAT(ip, '|', email, '|', content), 256) = ?
               AND created_at > (NOW() - INTERVAL 60 SECOND)
             LIMIT 1",
            [$ip, $dupeHash]
        );
    } catch (Exception $e) {
        return false;
    }
}

/**
 * 获取设置项
 */
function getSetting($key, $default = '') {
    if (!array_key_exists('app_settings_cache', $GLOBALS)) {
        $GLOBALS['app_settings_cache'] = [];
        try {
            $rows = db()->fetchAll("SELECT setting_key, setting_value FROM app_setting");
            foreach ($rows as $row) {
                $GLOBALS['app_settings_cache'][(string)$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $GLOBALS['app_settings_cache'] = [];
        }
    }

    return array_key_exists($key, $GLOBALS['app_settings_cache'])
        ? $GLOBALS['app_settings_cache'][$key]
        : $default;
}

/**
 * 设置设置项
 */
function setSetting($key, $value) {
    try {
        $exists = db()->fetchColumn(
            "SELECT COUNT(*) FROM app_setting WHERE setting_key = ?",
            [$key]
        );
        
        if ($exists) {
            db()->update('app_setting', ['setting_value' => $value], 'setting_key = ?', [$key]);
        } else {
            db()->insert('app_setting', [
                'setting_key' => $key,
                'setting_value' => $value
            ]);
        }
        if (array_key_exists('app_settings_cache', $GLOBALS)) {
            $GLOBALS['app_settings_cache'][$key] = $value;
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * 清空静态页面缓存（首页/归档/标签/关于/友链）。
 *
 * 这些页面的渲染依赖文章、评论、说说、友链、分类与站点设置，
 * 任意一处变更后都应立即失效，否则访客最长会看到 TTL（默认 300 秒）的旧内容。
 * 此前只有文章增删改会调用，改设置/审核评论/发说说的缓存脏数据要靠过期自愈。
 */
function app_purge_page_cache() {
    try {
        if (!class_exists('PageCache')) {
            require_once APP_ROOT . '/includes/PageCache.php';
        }
        PageCache::purgeAll();
    } catch (Exception $e) {
        // 缓存清理失败不应影响主流程
        error_log('Page cache purge failed: ' . $e->getMessage());
    }
    $sitemap = APP_ROOT . '/cache/sitemap.xml';
    if (is_file($sitemap)) {
        @unlink($sitemap);
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
        return db()->fetchColumn("SELECT COUNT(*) FROM app_article WHERE status = 'published'") ?: 0;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * 获取评论数量
 */
function getCommentCount() {
    try {
        return db()->fetchColumn("SELECT COUNT(*) FROM app_comment WHERE status = 1") ?: 0;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * 获取友链列表
 */
function getLinks() {
    try {
        return db()->fetchAll("SELECT * FROM app_link ORDER BY sort_order DESC, id ASC");
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
            "SELECT * FROM app_link WHERE status = 1 ORDER BY sort_order DESC, id ASC"
        );
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 确保 app_link_apply 存在 site_avatar 列（提交友链申请时自动补列，老库兼容）
 */
function ensureLinkApplyColumns() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo = db()->getPdo();
        $cols = [];
        $stmt = $pdo->query('SHOW COLUMNS FROM `app_link_apply`');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cols[strtolower((string)($row['Field'] ?? ''))] = true;
        }
        if (!isset($cols['site_avatar'])) {
            $pdo->exec("ALTER TABLE `app_link_apply` ADD COLUMN `site_avatar` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '头像直链' AFTER `site_description`");
        }
    } catch (Exception $e) {
        error_log('ensureLinkApplyColumns failed: ' . $e->getMessage());
    }
}

/**
 * 获取赞助商列表
 */
function getSponsors($onlyVisible = true) {
    try {
        $sql = "SELECT * FROM app_sponsor";
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
        return db()->fetchAll("SELECT * FROM app_category ORDER BY sort_order DESC, id ASC");
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
            "SELECT id, title, slug, created_at FROM app_article WHERE status = 'published' ORDER BY created_at DESC LIMIT ?",
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
            "SELECT id, title, slug, views, created_at FROM app_article WHERE status = 'published' ORDER BY views DESC LIMIT ?",
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
             FROM app_article
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
        while (db()->fetchColumn("SELECT COUNT(*) FROM app_article WHERE slug = ?", [$slug]) > 0) {
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
function app_is_https() {
    return Security::isHttps();
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
function app_session_start() {
    $started = false;
    if (session_status() === PHP_SESSION_ACTIVE) {
        $started = true;
    } else {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $hasRemember = !empty($_COOKIE['remember_token']);
        if (isset($_COOKIE[session_name()]) || $hasRemember || ($method !== 'GET' && $method !== 'HEAD')) {
            $started = (bool)session_start();
        }
    }
    if ($started) {
        app_attempt_remember_login();
    }
    // 维护模式统一在这里拦截：所有前台页面都会调用本函数，
    // 而后台页面用的是原生 session_start()，因此后台天然豁免。
    app_maintenance_guard();
    return $started;
}

/**
 * 维护模式守卫。
 *
 * 后台「维护模式」开关此前只是个存了不用的设置项，这里让它真正生效：
 * - 已登录的管理员放行（否则没人能进去关掉维护）；
 * - 后台、API、静态资源、登录/注册/资料页放行（管理员需要能登录）；
 * - 其余前台请求返回 503 + Retry-After，避免搜索引擎把维护页当成正式内容收录。
 */
function app_maintenance_guard() {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (PHP_SAPI === 'cli') {
        return;
    }
    if (getSetting('site_maintenance', '0') !== '1') {
        return;
    }

    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    foreach (['/admin/', '/api/', '/assets/', '/cache/'] as $prefix) {
        if (strpos($script, $prefix) !== false) {
            return;
        }
    }
    if (in_array(basename($script), ['login.php', 'logout.php', 'register.php', 'profile.php'], true)) {
        return;
    }
    if (isLoggedIn() && isAdmin()) {
        return;
    }

    app_render_maintenance_page();
}

/**
 * 输出维护页（自包含，不依赖模板）并以 503 结束请求。
 */
function app_render_maintenance_page() {
    $siteName = getSetting('site_name', '我的博客');

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(503);
    header('Retry-After: 3600');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>站点维护中 - <?php echo e($siteName); ?></title>
<style>
  :root { --primary: #e45757; --bg: #f7f6f3; --card: #fff; --text: #22262a; --muted: #79838c; --border: #dcdad3; }
  @media (prefers-color-scheme: dark) {
    :root { --primary: #ef7a70; --bg: #15181a; --card: #202428; --text: #e9ebed; --muted: #7f8991; --border: #33393f; }
  }
  * { box-sizing: border-box; }
  body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
         padding: 24px; background: var(--bg); color: var(--text);
         font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif; }
  .box { width: 100%; max-width: 460px; padding: 40px 32px; text-align: center;
         background: var(--card); border: 1px solid var(--border); border-radius: 14px;
         box-shadow: 0 14px 34px rgba(30, 34, 38, 0.08); }
  .badge { display: inline-block; margin-bottom: 18px; padding: 5px 12px; border-radius: 999px;
           background: rgba(228, 87, 87, 0.10); color: var(--primary); font-size: 0.8rem; font-weight: 700; letter-spacing: 0.04em; }
  h1 { margin: 0 0 12px; font-size: 1.35rem; }
  p { margin: 0 0 8px; color: var(--muted); line-height: 1.8; font-size: 0.94rem; }
  .hint { margin-top: 22px; font-size: 0.82rem; color: var(--muted); }
</style>
</head>
<body>
  <div class="box">
    <div class="badge">503 · MAINTENANCE</div>
    <h1><?php echo e($siteName); ?> 正在维护</h1>
    <p>站长正在对站点进行更新，稍后就会恢复访问。</p>
    <p>带来不便，敬请谅解。</p>
    <div class="hint">RSS 与站点地图仍然可用</div>
  </div>
</body>
</html>
    <?php
    exit;
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
function app_attempt_remember_login() {
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
            "SELECT * FROM app_admin WHERE remember_token = ? AND status = 1",
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
        db()->update('app_admin', ['remember_token' => hash('sha256', $newToken)], 'id = ?', [$user['id']]);
        if (!headers_sent()) {
            setcookie('remember_token', $newToken, [
                'expires'  => time() + 30 * 86400,
                'path'     => '/',
                'httponly' => true,
                'secure'   => app_is_https(),
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
function app_public_cache_headers($sMaxage = 600, $swr = 3600) {
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
function app_no_cache_headers() {
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
 *
 * 为防"管理员被降权/禁用后既有会话仍持有完全权限"（此前最长可保留 2 小时），
 * 后台身份以 5 分钟为 TTL 周期对照数据库复核一次：
 * - role 不再是 admin → 本次请求起收回管理员权限（保留登录态）；
 * - status != 1（被禁用）→ 直接踢出登录（清空会话并失效 remember_token）；
 * - 查询失败（数据库抖动）→ 维持现状态，不误伤。
 */
function isAdmin() {
    if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true || empty($_SESSION['user_id'])) {
        return false;
    }
    if (empty($_SESSION['_admin_checked_at']) || $_SESSION['_admin_checked_at'] < time() - 300) {
        $_SESSION['_admin_checked_at'] = time();
        try {
            $u = db()->fetchOne("SELECT role, status FROM app_admin WHERE id = ? LIMIT 1", [$_SESSION['user_id']]);
        } catch (Exception $e) {
            $u = null;
        }
        if (is_array($u)) {
            if (isset($u['role']) && $u['role'] !== 'admin') {
                // 已降权：立即收回管理员权限
                $_SESSION['is_admin'] = false;
                $_SESSION['role'] = $u['role'];
            }
            if (isset($u['status']) && (int)$u['status'] !== 1) {
                // 账号被禁用：踢出登录
                try {
                    db()->update('app_admin', ['remember_token' => null], 'id = ?', [$_SESSION['user_id']]);
                } catch (Exception $e) { /* 忽略 */ }
                $_SESSION = [];
            }
        } elseif ($u === false) {
            // 账号已被删除（fetchOne 无记录时返回 false，注意与"查询异常"的 null 区分）：
            // 必须立即失效会话，否则被删除的管理员在会话有效期内仍持有全部后台权限
            try {
                db()->update('app_admin', ['remember_token' => null], 'id = ?', [$_SESSION['user_id']]);
            } catch (Exception $e) { /* 忽略 */ }
            $_SESSION = [];
        }
    }
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
        return db()->fetchOne("SELECT * FROM app_admin WHERE id = ?", [$_SESSION['user_id']]);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * 要求登录
 */
function requireLogin() {
    if (!isLoggedIn()) {
        app_attempt_remember_login();
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
        app_attempt_remember_login();
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

    // 标签必须成对闭合：只有开始标签（如 <script src="//evil/x.js">）时，
    // 下面的 script 正则会一个都匹配不到，从而整体跳过域名白名单，
    // 而浏览器依然会加载并执行这个外部脚本。
    $scriptOpenCount = preg_match_all('/<script\b/iu', $code);
    $scriptCloseCount = preg_match_all('/<\/script\s*>/iu', $code);
    if ($scriptOpenCount !== $scriptCloseCount) {
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
    // OAuth state 需写入会话并在回调中校验；站点对匿名 GET 不开会话（app_session_start 会跳过），
    // 此处确保会话已启动，否则 state 不落盘、回调 hash_equals 校验必然失败
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (function_exists('app_session_start')) {
            app_session_start();
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
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
    // OAuth state 需写入会话并在回调中校验；站点对匿名 GET 不开会话（app_session_start 会跳过），
    // 此处确保会话已启动，否则 state 不落盘、回调 hash_equals 校验必然失败
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (function_exists('app_session_start')) {
            app_session_start();
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
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

/* ==================== 说说模块 ==================== */

/**
 * 确保说说表存在（首次访问自动创建）
 */
function ensureShuoshuoTables() {
    static $checked = false;
    if ($checked) return true;
    try {
        $pdo = db()->getPdo();
        $pdo->exec("CREATE TABLE IF NOT EXISTS `app_shuoshuo` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `content` TEXT NOT NULL,
            `images` TEXT NOT NULL,
            `mood` VARCHAR(30) NOT NULL DEFAULT '',
            `status` TINYINT NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_status_created` (`status`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $checked = true;
        return true;
    } catch (Exception $e) {
        error_log('ensureShuoshuoTables failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * 获取可见说说列表（前台用，分页）
 */
function getVisibleShuoshuo($limit = 10, $offset = 0) {
    ensureShuoshuoTables();
    try {
        return db()->fetchAll(
            "SELECT * FROM app_shuoshuo WHERE status = 1 ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?",
            [(int)$limit, (int)$offset]
        );
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 获取说说总数（前台可见）
 */
function getShuoshuoCount() {
    ensureShuoshuoTables();
    try {
        return db()->fetchColumn("SELECT COUNT(*) FROM app_shuoshuo WHERE status = 1") ?: 0;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * 获取全部说说（后台管理用）
 */
function getAllShuoshuo() {
    ensureShuoshuoTables();
    try {
        return db()->fetchAll("SELECT * FROM app_shuoshuo ORDER BY created_at DESC, id DESC");
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 解析说说的图片字段（JSON 或换行分隔的 URL 列表）为索引数组
 */
function parseShuoshuoImages($images) {
    $raw = trim((string)$images);
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $urls = [];
        foreach ($decoded as $url) {
            $url = trim((string)$url);
            if ($url !== '' && isValidImageUrl($url)) {
                $urls[] = $url;
            }
        }
        return $urls;
    }
    // 兼容后台「每行一个 URL」的输入格式
    $urls = [];
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
        $line = trim($line);
        if ($line !== '' && isValidImageUrl($line)) {
            $urls[] = $line;
        }
    }
    return $urls;
}

/* ==================== 站点访问统计（PV / UV） ==================== */

/**
 * 获取总 PV（自统计上线起累计的页面浏览量）
 */
function getSitePvTotal() {
    try {
        return (int) getSetting('site_pv_total', 0);
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * 今日 PV（ Asia/Shanghai 当天的页面浏览量）
 */
function getTodayPv() {
    try {
        return (int) db()->fetchColumn(
            "SELECT COUNT(*) FROM app_visit_log WHERE created_at >= ?",
            [date('Y-m-d 00:00:00', siteTime())]
        );
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * 近 24 小时 PV
 */
function getPv24h() {
    try {
        return (int) db()->fetchColumn(
            "SELECT COUNT(*) FROM app_visit_log WHERE created_at >= ?",
            [date('Y-m-d H:i:s', siteTime() - 86400)]
        );
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * 近 24 小时访客（按 IP 去重）
 */
function getVisitors24h() {
    try {
        return (int) db()->fetchColumn(
            "SELECT COUNT(DISTINCT ip) FROM app_visit_log WHERE created_at >= ?",
            [date('Y-m-d H:i:s', siteTime() - 86400)]
        );
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * 汇总统计数据（侧栏/接口共用）
 */
function getSiteStats() {
    return [
        'today_pv'    => getTodayPv(),
        'pv_24h'      => getPv24h(),
        'visitors_24h' => getVisitors24h(),
        'total_pv'    => getSitePvTotal(),
        'visitors'    => getVisitorCount(),
    ];
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
        $pdo->exec("CREATE TABLE IF NOT EXISTS `app_game` (
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
        return db()->fetchAll("SELECT * FROM app_game ORDER BY sort_order DESC, id ASC");
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
        return db()->fetchAll("SELECT * FROM app_game WHERE status = 1 ORDER BY sort_order DESC, id ASC");
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
        return db()->fetchOne("SELECT * FROM app_game WHERE id = ?", [(int)$id]);
    } catch (Exception $e) {
        return null;
    }
}

/* ==================== 开源仓库模块 ==================== */

/**
 * 确保仓库表存在（首次访问自动创建）
 */
function ensureRepoTables() {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $pdo = db()->getPdo();
        $pdo->exec("CREATE TABLE IF NOT EXISTS `app_repo` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(150) NOT NULL,
            `url` VARCHAR(500) NOT NULL,
            `description` VARCHAR(500) NOT NULL DEFAULT '',
            `sort_order` INT NOT NULL DEFAULT 0,
            `status` TINYINT NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_status_sort` (`status`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Exception $e) {
        error_log('ensureRepoTables failed: ' . $e->getMessage());
    }
}

/**
 * 获取所有仓库（后台管理用）
 */
function getAllRepos() {
    ensureRepoTables();
    try {
        return db()->fetchAll("SELECT * FROM app_repo ORDER BY sort_order DESC, id ASC");
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 获取可见仓库列表（前台用）
 */
function getVisibleRepos() {
    ensureRepoTables();
    try {
        return db()->fetchAll("SELECT * FROM app_repo WHERE status = 1 ORDER BY sort_order DESC, id ASC");
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 获取单个仓库
 */
function getRepo($id) {
    ensureRepoTables();
    try {
        return db()->fetchOne("SELECT * FROM app_repo WHERE id = ?", [(int)$id]);
    } catch (Exception $e) {
        return null;
    }
}

/* ==================== 曝光/人气：SEO 与动态流辅助 ==================== */

/**
 * 输出 canonical 绝对地址（SEO：避免 ?search/?category 等重复收录）。
 */
function appCanonicalUrl($path = '') {
    $base = rtrim(SITE_URL, '/');
    $path = trim((string)$path);
    if ($path === '' || $path === '/') {
        return $base . '/';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return $base . '/' . ltrim($path, '/');
}

/**
 * 把相对上传路径转成绝对 URL（OG/Twitter/JSON-LD 用）。
 */
function appAbsoluteAssetUrl($url) {
    $url = trim((string)$url);
    if ($url === '' || preg_match('#^(https?:)?//#i', $url)) {
        return $url;
    }
    return rtrim(SITE_URL, '/') . '/' . ltrim($url, '/');
}

/**
 * 最新评论（首页动态流用，默认只取已审核）。
 * 返回字段含文章标题/slug，便于直接跳转。
 */
function getLatestComments($limit = 8) {
    $limit = max(1, min(20, (int)$limit));
    try {
        return db()->fetchAll(
            "SELECT c.id, c.article_id, c.nickname, c.content, c.created_at, c.is_admin, " .
            "a.title AS article_title, a.slug AS article_slug " .
            "FROM app_comment c LEFT JOIN app_article a ON a.id = c.article_id " .
            "WHERE c.status = 1 AND (c.article_id = 0 OR a.status = 'published') " .
            "ORDER BY c.created_at DESC, c.id DESC LIMIT {$limit}"
        );
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 相关文章：同标签优先，其次同分类，最后按热度补齐。
 * $article 需含 id/category_id/tags 字段。
 */
function getRelatedArticles(array $article, $limit = 6) {
    $limit = max(1, min(12, (int)$limit));
    $id = (int)($article['id'] ?? 0);
    if ($id <= 0) {
        return [];
    }
    $related = [];
    $seen = [$id => true];
    $pick = function ($rows) use (&$related, &$seen, $limit) {
        foreach ((array)$rows as $row) {
            $rid = (int)($row['id'] ?? 0);
            if ($rid <= 0 || isset($seen[$rid])) {
                continue;
            }
            $seen[$rid] = true;
            $related[] = $row;
            if (count($related) >= $limit) {
                break;
            }
        }
    };
    try {
        $tags = [];
        foreach (explode(',', (string)($article['tags'] ?? '')) as $tag) {
            $tag = trim($tag);
            if ($tag !== '') {
                $tags[] = $tag;
            }
        }
        $tags = array_slice($tags, 0, 5);
        if (!empty($tags)) {
            $conds = [];
            $params = [$id];
            foreach ($tags as $tag) {
                $conds[] = 'a.tags LIKE ?';
                $params[] = '%' . $tag . '%';
            }
            $rows = db()->fetchAll(
                "SELECT a.id, a.title, a.slug, a.cover_image, a.views, a.created_at " .
                "FROM app_article a WHERE a.status = 'published' AND a.id != ? AND (" . implode(' OR ', $conds) . ") " .
                "ORDER BY a.views DESC, a.created_at DESC LIMIT {$limit}",
                $params
            );
            $pick($rows);
        }
        $categoryId = (int)($article['category_id'] ?? 0);
        if (count($related) < $limit && $categoryId > 0) {
            $need = $limit - count($related);
            $rows = db()->fetchAll(
                "SELECT a.id, a.title, a.slug, a.cover_image, a.views, a.created_at " .
                "FROM app_article a WHERE a.status = 'published' AND a.id != ? AND a.category_id = ? " .
                "ORDER BY a.views DESC, a.created_at DESC LIMIT {$need}",
                [$id, $categoryId]
            );
            $pick($rows);
        }
        if (count($related) < $limit) {
            $need = $limit - count($related);
            $ids = array_keys($seen);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $rows = db()->fetchAll(
                "SELECT a.id, a.title, a.slug, a.cover_image, a.views, a.created_at " .
                "FROM app_article a WHERE a.status = 'published' AND a.id NOT IN ({$placeholders}) " .
                "ORDER BY a.views DESC, a.created_at DESC LIMIT {$need}",
                $ids
            );
            $pick($rows);
        }
    } catch (Exception $e) {
        return $related;
    }
    return $related;
}

/* ==================== 捐赠者模块（独立于赞助商） ==================== */

/**
 * 确保捐赠者表存在（首次访问自动创建，与 app_sponsor 完全独立）。
 */
function ensureDonorTables() {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $pdo = db()->getPdo();
        $pdo->exec("CREATE TABLE IF NOT EXISTS `app_donor` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `url` VARCHAR(500) NOT NULL DEFAULT '',
            `amount` DECIMAL(10,2) NULL DEFAULT NULL COMMENT '捐赠金额',
            `sort_order` INT NOT NULL DEFAULT 0,
            `status` TINYINT NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_status_sort` (`status`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Exception $e) {
        error_log('ensureDonorTables failed: ' . $e->getMessage());
    }
}

/**
 * 获取可见捐赠者（前台用）。
 */
function getDonors($onlyVisible = true) {
    ensureDonorTables();
    try {
        $sql = "SELECT * FROM app_donor";
        if ($onlyVisible) {
            $sql .= " WHERE status = 1";
        }
        $sql .= " ORDER BY sort_order DESC, id ASC";
        return db()->fetchAll($sql);
    } catch (Exception $e) {
        return [];
    }
}

/** 获取全部捐赠者（后台管理用）。 */
function getAllDonors() {
    ensureDonorTables();
    try {
        return db()->fetchAll("SELECT * FROM app_donor ORDER BY sort_order DESC, id ASC");
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 低调捐赠墙取数：名字 + 跳转链接（前台只展示这两项，金额仅后台可见）。
 */
function getDonorWall() {
    $rows = getDonors(true);
    $wall = [];
    foreach ($rows as $row) {
        $name = trim((string)($row['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $url = trim((string)($row['url'] ?? ''));
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = '';
        }
        $wall[] = ['name' => $name, 'url' => $url];
    }
    return $wall;
}

/**
 * 确保镜像分区 / 镜像软件两张表存在（老版本升级后首次访问后台时自建）。
 */
function ensureMirrorTables() {
    static $checked = false;
    if ($checked) return true;
    try {
        $pdo = db()->getPdo();
        $pdo->exec("CREATE TABLE IF NOT EXISTS `app_mirror_category` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `description` VARCHAR(255) NOT NULL DEFAULT '',
            `icon` VARCHAR(500) NOT NULL DEFAULT '',
            `sort_order` INT NOT NULL DEFAULT 0,
            `status` TINYINT NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `app_mirror_software` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `category_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `name` VARCHAR(150) NOT NULL,
            `description` VARCHAR(500) NOT NULL DEFAULT '',
            `icon_url` VARCHAR(500) NOT NULL DEFAULT '',
            `download_url` VARCHAR(500) NOT NULL DEFAULT '',
            `official_url` VARCHAR(500) NOT NULL DEFAULT '',
            `version` VARCHAR(100) NOT NULL DEFAULT '',
            `sort_order` INT NOT NULL DEFAULT 0,
            `views` INT UNSIGNED NOT NULL DEFAULT 0,
            `status` TINYINT NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_category` (`category_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $checked = true;
        return true;
    } catch (Exception $e) {
        error_log('ensureMirrorTables failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * 获取镜像分区。
 * @param bool $onlyVisible true 仅返回已启用分区（前台用）
 */
function getMirrorCategories($onlyVisible = false) {
    ensureMirrorTables();
    try {
        $sql = "SELECT * FROM app_mirror_category";
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
 * 获取镜像软件。
 * @param int  $categoryId  0 表示未分类（category_id = 0）
 * @param bool $onlyVisible true 仅返回已发布软件（前台用）
 */
function getMirrorSoftwares($categoryId = 0, $onlyVisible = false) {
    ensureMirrorTables();
    try {
        $sql = "SELECT * FROM app_mirror_software WHERE category_id = ?";
        $params = [(int)$categoryId];
        if ($onlyVisible) {
            $sql .= " AND status = 1";
        }
        $sql .= " ORDER BY sort_order DESC, id ASC";
        return db()->fetchAll($sql, $params);
    } catch (Exception $e) {
        return [];
    }
}

/** 获取全部镜像软件（后台管理用，带分区名）。 */
function getAllMirrorSoftwares() {
    ensureMirrorTables();
    try {
        return db()->fetchAll(
            "SELECT s.*, c.name AS category_name
               FROM app_mirror_software s
               LEFT JOIN app_mirror_category c ON c.id = s.category_id
              ORDER BY s.sort_order DESC, s.id ASC"
        );
    } catch (Exception $e) {
        return [];
    }
}
