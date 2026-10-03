<?php
/**
 * 站点统计查询 API（GET）
 * 返回：今日 PV / 近24小时访客 / 近24小时 PV / 总 PV / 总访客
 * 无会话依赖，可被 CDN 短缓存（60 秒）
 */
define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/includes/config.php';
require_once APP_ROOT . '/includes/Security.php';
require_once APP_ROOT . '/includes/Database.php';
require_once APP_ROOT . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => '请求方式错误']);
    exit;
}

// 允许边缘短缓存，降低源站统计查询压力
header('Cache-Control: public, max-age=60');

echo json_encode([
    'success' => true,
    'stats' => getSiteStats()
]);
