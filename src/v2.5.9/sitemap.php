<?php
/**
 * Public XML Sitemap (sitemap protocol 0.9).
 *
 * 收录范围：首页、静态页、全部已发布文章页、分类页、标签页。
 * 生成结果写入 cache/sitemap.xml 做简单文件缓存
 * （TTL 默认 3600 秒，可用 define('APP_SITEMAP_CACHE_TTL', 秒) 覆盖），
 * 与 rss.php 相同，另带 ETag / Last-Modified 支持条件请求。
 */
define('APP_ROOT', __DIR__);

require_once APP_ROOT . '/includes/config.php';
require_once APP_ROOT . '/includes/Database.php';
require_once APP_ROOT . '/includes/functions.php';

/** Sitemap 缓存文件路径并存 TTL（秒）。 */
function sitemapCacheFile() {
    return APP_ROOT . '/cache/sitemap.xml';
}

function sitemapCacheTtl() {
    return defined('APP_SITEMAP_CACHE_TTL') ? max(1, (int)APP_SITEMAP_CACHE_TTL) : 3600;
}

/** Escape plain text for XML nodes and attributes. */
function sitemapEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * 渲染单个 <url> 节点；$lastmod 传 Unix 时间戳，0 表示省略 lastmod。
 */
function sitemapUrlNode($loc, $lastmod = 0) {
    $node = "    <url>\n        <loc>" . sitemapEscape($loc) . "</loc>\n";
    if ($lastmod > 0) {
        $node .= "        <lastmod>" . gmdate('Y-m-d\\TH:i:s+00:00', $lastmod) . "</lastmod>\n";
    }
    return $node . "    </url>\n";
}

/** 汇总整份 sitemap 内容，命中有效缓存时直接返回磁盘内容。 */
function sitemapBuild() {
    $cacheFile = sitemapCacheFile();
    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < sitemapCacheTtl()) {
        $cached = @file_get_contents($cacheFile);
        if ($cached !== false && $cached !== '') {
            return $cached;
        }
    }

    $siteUrl = rtrim(SITE_URL, '/');

    // 静态页（以实际存在的前台页面为准；login/register/后台等不计入）
    $staticPages = [
        '/archive.php', '/tags.php', '/about.php', '/guestbook.php',
        '/shuoshuo.php', '/links.php', '/gallery.php', '/git.php',
        '/donate.php', '/tools.php', '/bilibili.php', '/mirror.php',
        '/status.php',
    ];

    // 全部已发布文章（slug + 更新时间）
    $articles = [];
    // 各分类下已发布文章的最后更新时间
    $categoryLastmod = [];
    // 已发布文章的标签集合
    $tags = [];
    $latestTimestamp = 0;
    try {
        $articles = db()->fetchAll(
            "SELECT a.slug, a.tags, a.created_at, a.updated_at
             FROM app_article a
             WHERE a.status = 'published'
             ORDER BY a.created_at DESC"
        );
        $categoryRows = db()->fetchAll(
            "SELECT a.category_id, MAX(COALESCE(NULLIF(a.updated_at, '0000-00-00 00:00:00'), a.created_at)) AS last_mod
             FROM app_article a
             WHERE a.status = 'published' AND a.category_id IS NOT NULL
             GROUP BY a.category_id"
        );
        foreach ($categoryRows as $row) {
            $categoryLastmod[(int)$row['category_id']] = strtotime((string)$row['last_mod']) ?: 0;
        }
    } catch (Exception $e) {
        error_log('Sitemap query failed: ' . $e->getMessage());
    }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    // 首页
    $xml .= sitemapUrlNode($siteUrl . '/');

    // 静态页
    foreach ($staticPages as $page) {
        $xml .= sitemapUrlNode($siteUrl . $page);
    }

    // 分类页（前台通过 index.php?category=ID 展示该分类下的文章）
    foreach ($categoryLastmod as $categoryId => $lastmod) {
        $xml .= sitemapUrlNode($siteUrl . '/?category=' . $categoryId, $lastmod);
        $latestTimestamp = max($latestTimestamp, $lastmod);
    }

    // 文章页与标签
    foreach ($articles as $article) {
        $updatedRaw = trim((string)($article['updated_at'] ?? ''));
        $lastmod = ($updatedRaw !== '' && strtotime($updatedRaw) > 0)
            ? strtotime($updatedRaw)
            : (strtotime((string)$article['created_at']) ?: 0);
        $xml .= sitemapUrlNode(
            $siteUrl . '/article.php?slug=' . rawurlencode((string)$article['slug']),
            $lastmod
        );
        $latestTimestamp = max($latestTimestamp, $lastmod);

        foreach (array_filter(array_map('trim', explode(',', (string)($article['tags'] ?? '')))) as $tag) {
            $tags[$tag] = true;
        }
    }

    // 标签落地页（独立可收录地址）
    $xml .= sitemapUrlNode($siteUrl . '/tags.php');
    foreach (array_keys($tags) as $tag) {
        $xml .= sitemapUrlNode($siteUrl . '/tag.php?tag=' . rawurlencode($tag));
    }

    $xml .= '</urlset>' . "\n";

    // 落盘（临时文件 + rename，防并发读到半成品；同 PageCache::store 的做法）
    $dir = dirname($cacheFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $tmp = $cacheFile . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $xml, LOCK_EX) !== false) {
        @rename($tmp, $cacheFile);
    } else {
        @unlink($tmp);
    }

    return $xml;
}

header('Content-Type: application/xml; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=3600, stale-while-revalidate=86400');

$xml = sitemapBuild();

// 以缓存文件修改时间支撑条件请求，命中 304 直接返回
$cacheFile = sitemapCacheFile();
$lastModifiedTimestamp = is_file($cacheFile) ? (int)filemtime($cacheFile) : time();
$etag = 'W/"sitemap-' . $lastModifiedTimestamp . '-' . strlen($xml) . '"';
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModifiedTimestamp) . ' GMT');
header('ETag: ' . $etag);

$ifNoneMatch = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
$ifModifiedSince = strtotime((string)($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));
if ($ifNoneMatch === $etag || ($ifNoneMatch === '' && $ifModifiedSince && $ifModifiedSince >= $lastModifiedTimestamp)) {
    http_response_code(304);
    exit;
}

echo $xml;
