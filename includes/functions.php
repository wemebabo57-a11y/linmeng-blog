<?php
/**
 * 公共函数库 v2.0
 */

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
    return filter_var($url, FILTER_VALIDATE_URL) || strpos($url, '/') === 0;
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
        return '<a href="' . $safeUrl . '" target="_blank" rel="noopener">' . $nickname . '</a>';
    }
    return $nickname;
}

/**
 * 获取设置项
 */
function getSetting($key, $default = '') {
    try {
        $value = db()->fetchColumn(
            "SELECT setting_value FROM lm_setting WHERE setting_key = ?",
            [$key]
        );
        return $value !== false ? $value : $default;
    } catch (Exception $e) {
        return $default;
    }
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
        Security::redirect('/login.php');
    }
}

/**
 * 要求管理员权限
 */
function requireAdmin() {
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
