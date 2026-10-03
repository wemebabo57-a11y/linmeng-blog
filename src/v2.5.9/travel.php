<?php
/**
 * 开往 - 随机前往一个友链
 * 从可见友链中随机抽取一条 302 跳转；无友链时回到友链页
 */
define('APP_ROOT', __DIR__);

require_once APP_ROOT . '/includes/config.php';
require_once APP_ROOT . '/includes/Security.php';
require_once APP_ROOT . '/includes/Database.php';
require_once APP_ROOT . '/includes/functions.php';

// 跳转页不缓存，保证每次都能随机
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$links = getVisibleLinks();
$target = null;

if (!empty($links)) {
    $target = $links[array_rand($links)];
}

if ($target && !empty($target['url'])) {
    // 仅允许 http/https 跳转，防协议注入
    $scheme = strtolower((string)parse_url($target['url'], PHP_URL_SCHEME));
    if ($scheme === 'http' || $scheme === 'https') {
        header('Location: ' . $target['url'], true, 302);
        exit;
    }
}

// 没有可用的友链，回到友链页
header('Location: /links.php', true, 302);
exit;
