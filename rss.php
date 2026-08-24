<?php
/**
 * Public RSS 2.0 feed.
 */
define('LM_ROOT', __DIR__);

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

header('Content-Type: application/rss+xml; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=900, stale-while-revalidate=3600');

/** Escape plain text for XML nodes and attributes. */
function rssEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
}

/** Wrap rich article content safely in a CDATA section. */
function rssCdata($value) {
    return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', (string)$value) . ']]>';
}

/** Normalize rich text into the concise plain-text summary used by feed readers. */
function rssDescription($article) {
    $text = trim((string)($article['excerpt'] ?? ''));
    if ($text === '') {
        $text = getArticlePlainText($article);
    }

    $text = preg_replace('/\s+/u', ' ', $text);
    return mb_strimwidth(trim($text), 0, 300, '…', 'UTF-8');
}

/** Resolve uploaded relative paths into absolute URLs, leaving external links untouched. */
function rssAbsoluteUrl($url) {
    $url = trim((string)$url);
    if ($url === '' || preg_match('#^(https?:)?//#i', $url)) {
        return $url;
    }

    return rtrim(SITE_URL, '/') . '/' . ltrim($url, '/');
}

/** Build the summary markup shown in readers: cover image, excerpt and a link back to the site. */
function rssSummaryHtml($article, $articleUrl) {
    $html = '';

    $cover = rssAbsoluteUrl($article['cover_image'] ?? '');
    if ($cover !== '') {
        $html .= '<p><img src="' . rssEscape($cover) . '" alt="' . rssEscape($article['title'] ?? '') . '" /></p>';
    }

    $html .= '<p>' . rssEscape(rssDescription($article)) . '</p>';
    $html .= '<p><a href="' . rssEscape($articleUrl) . '">阅读全文 →</a></p>';

    return $html;
}

$siteUrl = rtrim(SITE_URL, '/');
$feedUrl = $siteUrl . '/rss.php';
$siteName = getSetting('site_name', 'LinMeng Blog');
$siteDescription = getSetting('site_description', '记录生活，分享技术');
$siteLogo = rssAbsoluteUrl(getSetting('site_logo', ''));
$siteKeywords = array_filter(array_map('trim', explode(',', (string)getSetting('site_keywords', ''))));

$copyright = trim(preg_replace('/\s+/u', ' ', strip_tags((string)getSetting('site_footer', ''))));

try {
    $articles = db()->fetchAll(
        "SELECT a.title, a.slug, a.excerpt, a.content, a.tags, a.cover_image, a.created_at, a.updated_at,
                c.name AS category_name, u.nickname AS author_name
         FROM lm_article a
         LEFT JOIN lm_category c ON a.category_id = c.id
         LEFT JOIN lm_admin u ON a.author_id = u.id
         WHERE a.status = 'published'
         ORDER BY a.created_at DESC
         LIMIT 30"
    );
} catch (Exception $e) {
    error_log('RSS feed query failed: ' . $e->getMessage());
    $articles = [];
}

// Derive freshness from the articles themselves so conditional requests can hit 304.
// HTTP headers must carry real GMT, so they ignore the site_time_offset calibration
// that only applies to the human-facing dates inside the feed.
$lastBuildTimestamp = 0;
$lastModifiedTimestamp = 0;
foreach ($articles as $article) {
    $raw = (string)($article['updated_at'] ?: $article['created_at']);
    $rawTimestamp = (int)strtotime($raw);
    if ($rawTimestamp <= 0) {
        continue;
    }

    $lastModifiedTimestamp = max($lastModifiedTimestamp, $rawTimestamp);
    $lastBuildTimestamp = max($lastBuildTimestamp, applyTimeOffset($raw));
}
if ($lastBuildTimestamp <= 0) {
    $lastBuildTimestamp = siteTime();
}
if ($lastModifiedTimestamp <= 0) {
    $lastModifiedTimestamp = time();
}

$etag = 'W/"rss-' . $lastModifiedTimestamp . '-' . count($articles) . '"';
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModifiedTimestamp) . ' GMT');
header('ETag: ' . $etag);

$ifNoneMatch = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
$ifModifiedSince = strtotime((string)($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));
if ($ifNoneMatch === $etag || ($ifNoneMatch === '' && $ifModifiedSince && $ifModifiedSince >= $lastModifiedTimestamp)) {
    http_response_code(304);
    exit;
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0"
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:media="http://search.yahoo.com/mrss/"
     xmlns:dc="http://purl.org/dc/elements/1.1/">
    <channel>
        <title><?php echo rssEscape($siteName); ?></title>
        <link><?php echo rssEscape($siteUrl . '/'); ?></link>
        <description><?php echo rssEscape($siteDescription); ?></description>
        <language>zh-CN</language>
        <lastBuildDate><?php echo gmdate(DATE_RSS, $lastBuildTimestamp); ?></lastBuildDate>
        <generator><?php echo rssEscape($siteName . ' RSS'); ?></generator>
        <docs>https://www.rssboard.org/rss-specification</docs>
        <ttl>60</ttl>
<?php foreach ($siteKeywords as $keyword): ?>
        <category><?php echo rssCdata($keyword); ?></category>
<?php endforeach; ?>
<?php if ($copyright !== ''): ?>
        <copyright><?php echo rssCdata($copyright); ?></copyright>
<?php endif; ?>
<?php if ($siteLogo !== ''): ?>
        <image>
            <url><?php echo rssEscape($siteLogo); ?></url>
            <title><?php echo rssEscape($siteName); ?></title>
            <link><?php echo rssEscape($siteUrl . '/'); ?></link>
        </image>
<?php endif; ?>
        <atom:link href="<?php echo rssEscape($feedUrl); ?>" rel="self" type="application/rss+xml" />
<?php foreach ($articles as $article): ?>
<?php
    $articleUrl = $siteUrl . '/article.php?slug=' . rawurlencode((string)$article['slug']);
    $publishedAt = applyTimeOffset($article['created_at']);
    $authorName = trim((string)($article['author_name'] ?? '')) ?: $siteName;
    $coverUrl = rssAbsoluteUrl($article['cover_image'] ?? '');
    $updatedAt = trim((string)($article['updated_at'] ?? ''));
    // Guard against NULL and zero dates, which would otherwise emit a 1970 timestamp.
    $hasUpdate = $updatedAt !== ''
        && $updatedAt !== (string)$article['created_at']
        && strtotime($updatedAt) > 0;
?>
        <item>
            <title><?php echo rssEscape($article['title']); ?></title>
            <link><?php echo rssEscape($articleUrl); ?></link>
            <guid isPermaLink="true"><?php echo rssEscape($articleUrl); ?></guid>
            <pubDate><?php echo gmdate(DATE_RSS, $publishedAt); ?></pubDate>
            <dc:creator><?php echo rssCdata($authorName); ?></dc:creator>
<?php if (!empty($article['category_name'])): ?>
            <category><?php echo rssCdata($article['category_name']); ?></category>
<?php endif; ?>
<?php foreach (array_filter(array_map('trim', explode(',', (string)($article['tags'] ?? '')))) as $tag): ?>
            <category><?php echo rssCdata($tag); ?></category>
<?php endforeach; ?>
<?php if ($coverUrl !== ''): ?>
            <media:thumbnail url="<?php echo rssEscape($coverUrl); ?>" />
<?php endif; ?>
<?php if ($hasUpdate): ?>
            <atom:updated><?php echo gmdate(DATE_ATOM, applyTimeOffset($updatedAt)); ?></atom:updated>
<?php endif; ?>
            <description><?php echo rssCdata(rssSummaryHtml($article, $articleUrl)); ?></description>
        </item>
<?php endforeach; ?>
    </channel>
</rss>
