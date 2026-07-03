<?php
/**
 * 全量探测脚本（定时任务入口）
 *
 * 用法一（推荐，服务器 crontab）：
 *   */5 * * * * php /www/wwwroot/kslinmeng.cn/api/service-probe-all.php
 *   （把路径换成你的实际站点路径；间隔与后台设置的探测间隔一致）
 *
 * 用法二（Web 触发，需带密钥）：
 *   curl "https://kslinmeng.cn/api/service-probe-all.php?key=后台设置的密钥"
 *   密钥在 后台 > 服务状态 > 探测设置 中配置；留空则禁止 Web 触发。
 *
 * 用法三（后台手动触发）：
 *   后台 > 服务状态 > 探测设置 中点"立即探测"按钮。
 *
 * 说明：无论是否配置 cron，状态页（/status.php）在距上次探测超过
 *       后台设置的间隔时，会自动懒触发一次探测作为兜底。
 */
define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';
require_once LM_ROOT . '/api/service-probe.php'; // 引入 probeAllServices()

$isCli = php_sapi_name() === 'cli';

// Web 访问需校验密钥，防止被外部滥用刷探测；CLI 不校验
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    $key = $_GET['key'] ?? '';
    $savedKey = getSetting('service_probe_key', '');
    if ($savedKey === '' || !hash_equals($savedKey, $key)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => '无权访问或未配置探测密钥'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    $results = probeAllServices();
    $time = date('Y-m-d H:i:s');

    if ($isCli) {
        echo "探测完成 " . count($results) . " 项服务 @ {$time}\n";
        foreach ($results as $r) {
            $tag = $r['online'] ? 'OK  ' : 'DOWN';
            echo "  [{$tag}] {$r['name']} - {$r['latency']}ms - {$r['message']}\n";
        }
        exit(0);
    }

    echo json_encode([
        'success' => true,
        'count'   => count($results),
        'time'    => $time,
        'results' => $results,
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    if ($isCli) {
        echo "探测失败: " . $e->getMessage() . "\n";
        exit(1);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '探测失败'], JSON_UNESCAPED_UNICODE);
}
