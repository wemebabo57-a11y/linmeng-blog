<?php
/**
 * AI 总结代理 API
 * 前端通过此接口请求文章总结，真实 API Key 不在前端暴露
 *
 * 支持两种模式：
 *  - 普通模式（默认）：返回完整 JSON
 *  - 流式模式（stream=1）：返回 text/event-stream，逐段推送总结
 *
 * 安全增强：
 *  - 仅允许同源 AJAX 请求（Origin/Referer 校验）
 *  - 必须带 X-Requested-With: XMLHttpRequest
 *  - 全局开关 + IP 限流（10/小时）+ 文章维度限流（同 IP 同文章 5/小时）
 *  - 已登录用户额外按用户 ID 限流（20/小时），防止单 IP 多账号滥用
 *  - 缓存命中不消耗限流额度
 */

define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';
require_once LM_ROOT . '/includes/AiProvider.php';

session_start();

// 关闭错误输出，避免污染 SSE 流；错误写入日志
ini_set('display_errors', '0');
error_reporting(E_ALL);

$isStream = isset($_POST['stream']) && $_POST['stream'] === '1';

/* ------------------------- 安全校验 ------------------------- */

// 1) 必须是 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonError('请求方式错误');
}

// 2) 必须带 AJAX 标识，阻止浏览器直接访问或跨站简单表单提交
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    sendJsonError('非法请求');
}

// 3) Origin/Referer 同源校验，防止跨站调用
if (!isSameOriginRequest()) {
    sendJsonError('非法来源');
}

// 4) CSRF 校验
$token = $_POST[CSRF_TOKEN_NAME] ?? '';
if (!Security::validateToken($token)) {
    sendJsonError('安全验证失败，请刷新页面重试');
}

// 5) 全局开关
if (getSetting('ai_summary_enabled', '0') !== '1') {
    sendJsonError('AI 总结功能未启用');
}

$articleId = isset($_POST['article_id']) ? (int)$_POST['article_id'] : 0;
$providerId = isset($_POST['provider_id']) ? (int)$_POST['provider_id'] : 0;
$clientIp = Security::getClientIp();

if ($articleId <= 0) {
    sendJsonError('参数错误');
}

/* ------------------------- 限流策略 ------------------------- */
// IP 维度：每小时 10 次（含流式请求）
if (!Security::checkRateLimit($clientIp, 'ai_summary', 10, 3600)) {
    sendJsonError('请求过于频繁，请稍后再试');
}
// 文章维度：同 IP 同文章每小时 5 次，防止针对性消耗
if (!Security::checkRateLimit($clientIp . '_art' . $articleId, 'ai_summary_article', 5, 3600)) {
    sendJsonError('该文章请求过于频繁，请稍后再试');
}
// 登录用户额外限流：每用户每小时 20 次
if (isLoggedIn()) {
    $userId = (int)$_SESSION['user_id'];
    if (!Security::checkRateLimit('u' . $userId, 'ai_summary_user', 20, 3600)) {
        sendJsonError('请求过于频繁，请稍后再试');
    }
}

try {
    // 读取已发布文章
    $article = db()->fetchOne(
        "SELECT id, title, content FROM lm_article WHERE id = ? AND status = 'published'",
        [$articleId]
    );
    if (!$article) {
        sendJsonError('文章不存在');
    }

    // 文章正文过短则不生成总结（防止无意义消耗）
    $plainCheck = trim(strip_tags($article['content']));
    if (mb_strlen($plainCheck, 'UTF-8') < 50) {
        sendJsonError('文章内容过短，无需生成总结');
    }

    // 确定 Provider
    if ($providerId <= 0) {
        $providerId = (int)getSetting('ai_default_provider_id', 0);
    }

    $provider = db()->fetchOne(
        "SELECT * FROM lm_ai_provider WHERE id = ? AND enabled = 1",
        [$providerId]
    );
    if (!$provider) {
        sendJsonError('所选 AI 模型不可用');
    }

    // 缓存命中检查（命中直接返回，不计入限流）
    $contentHash = md5($article['title'] . $article['content']);
    $cached = db()->fetchOne(
        "SELECT summary FROM lm_ai_summary_cache WHERE article_id = ? AND provider_id = ? AND content_hash = ?",
        [$articleId, $providerId, $contentHash]
    );
    if ($cached) {
        if ($isStream) {
            // 流式模式：缓存一次性回放
            startSseStream();
            sseEmit(['success' => true, 'streaming' => true]);
            sseEmit(['delta' => $cached['summary']]);
            sseEmit(['done' => true, 'cached' => true]);
            exit;
        }
        echo json_encode([
            'success' => true,
            'summary' => $cached['summary'],
            'cached' => true
        ]);
        exit;
    }

    // 内容清洗与截断
    $plainContent = strip_tags($article['content']);
    $plainContent = preg_replace('/\s+/', ' ', $plainContent);
    $maxChars = 8000;
    if (mb_strlen($plainContent, 'UTF-8') > $maxChars) {
        $plainContent = mb_substr($plainContent, 0, $maxChars, 'UTF-8') . '...';
    }

    $content = "标题：" . $article['title'] . "\n正文：\n" . $plainContent;
    $systemPrompt = getSetting('ai_summary_prompt', '请用中文总结下面这篇文章。');

    $ai = new AiProvider($provider);

    if (!$isStream) {
        // 普通模式：阻塞请求，整体返回
        $summary = $ai->request($content, $systemPrompt);
        saveCache($articleId, $providerId, $contentHash, $summary);
        echo json_encode([
            'success' => true,
            'summary' => $summary,
            'cached' => false
        ]);
        exit;
    }

    /* ------------------------- 流式模式 ------------------------- */
    startSseStream();
    sseEmit(['success' => true, 'streaming' => true]);

    // 客户端断开时停止向上游读取
    ignore_user_abort(true);
    ob_implicit_flush(true);

    $fullSummary = '';
    try {
        $fullSummary = $ai->requestStream($content, $systemPrompt, function ($delta) {
            if (connection_aborted()) {
                throw new Exception('client_aborted');
            }
            sseEmit(['delta' => $delta]);
        });
    } catch (Exception $e) {
        // 上游错误或客户端中断
        if ($e->getMessage() === 'client_aborted') {
            exit;
        }
        error_log('AI summary stream error: ' . $e->getMessage());
        sseEmit(['success' => false, 'message' => '总结生成失败：' . $e->getMessage()]);
        exit;
    }

    // 完整保存缓存
    if (trim($fullSummary) !== '') {
        saveCache($articleId, $providerId, $contentHash, $fullSummary);
    }
    sseEmit(['done' => true]);

} catch (Exception $e) {
    error_log('AI summary error: ' . $e->getMessage());
    sendJsonError('总结生成失败，请稍后重试');
}

/* ======================== 辅助函数 ======================== */

/**
 * 同源校验：检查 Origin/Referer 是否为本站
 */
function isSameOriginRequest() {
    $siteUrl = rtrim(getSetting('site_url', ''), '/');
    if ($siteUrl === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $siteUrl = $scheme . '://' . $host;
    }
    $parsed = parse_url($siteUrl);
    $expectedHost = $parsed['host'] ?? '';

    // 优先校验 Origin
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        $originHost = parse_url($origin, PHP_URL_HOST);
        return $originHost !== null && $originHost === $expectedHost;
    }

    // 回退到 Referer
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer !== '') {
        $refererHost = parse_url($referer, PHP_URL_HOST);
        return $refererHost !== null && $refererHost === $expectedHost;
    }

    // 两者都没有：拒绝
    return false;
}

/**
 * 统一 JSON 错误输出
 */
function sendJsonError($message) {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

/**
 * 开启 SSE 流式输出
 */
function startSseStream() {
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-transform');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no'); // Nginx：禁用缓冲，立即下发
    header('X-Content-Type-Options: nosniff');
    // 关闭 PHP 输出缓冲
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', '0');
    flush();
}

/**
 * 发送一条 SSE 数据
 */
function sseEmit(array $data) {
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    // 兼容多层 buffer
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

/**
 * 保存缓存
 */
function saveCache($articleId, $providerId, $contentHash, $summary) {
    try {
        db()->query(
            "INSERT INTO lm_ai_summary_cache (article_id, provider_id, content_hash, summary, created_at)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                content_hash = VALUES(content_hash),
                summary = VALUES(summary),
                created_at = VALUES(created_at)",
            [$articleId, $providerId, $contentHash, $summary]
        );
    } catch (Exception $e) {
        error_log('AI summary cache save failed: ' . $e->getMessage());
    }
}
