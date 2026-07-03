<?php
/**
 * 服务探测接口
 * - 直接访问（?id=N）：返回单次探测结果 JSON，供后台测试按钮调用
 * - 作为库被 require：仅提供 probeService() 函数，不输出
 */
define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

/**
 * 探测单个服务
 * @param array $service 服务行（含 name/host/type/port/path）
 * @return array ['online' => bool, 'latency' => int(ms), 'message' => string]
 */
function probeService(array $service) {
    $host = trim($service['host']);
    $port = (int)$service['port'];
    $type = $service['type'];
    $path = !empty($service['path']) ? $service['path'] : '/';

    // 校验 host 合法性，防止拼接到 URL/curl 导致异常
    if (!filter_var($host, FILTER_VALIDATE_DOMAIN) && !filter_var($host, FILTER_VALIDATE_IP)) {
        return ['online' => false, 'latency' => 0, 'message' => '主机地址不合法'];
    }
    if ($port < 1 || $port > 65535) {
        return ['online' => false, 'latency' => 0, 'message' => '端口不合法'];
    }

    // SSRF 防护：拒绝内网/回环/链路本地地址，防止探测内部服务（含云元数据 169.254.169.254）
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ipLong = ip2long($host);
        if ($ipLong !== false) {
            $isPrivate = (filter_var($host, FILTER_FLAG_NO_PRIV_RANGE) === false)
                      || (filter_var($host, FILTER_FLAG_NO_RES_RANGE) === false);
            if ($isPrivate) {
                return ['online' => false, 'latency' => 0, 'message' => '禁止探测内网或保留地址'];
            }
        }
    } else {
        // 域名解析后再校验
        $resolved = @gethostbynamel($host);
        if ($resolved) {
            foreach ($resolved as $resolvedIp) {
                $isPrivate = (filter_var($resolvedIp, FILTER_FLAG_NO_PRIV_RANGE) === false)
                          || (filter_var($resolvedIp, FILTER_FLAG_NO_RES_RANGE) === false);
                if ($isPrivate) {
                    return ['online' => false, 'latency' => 0, 'message' => '主机解析到内网地址，已拒绝'];
                }
            }
        }
    }

    $start = microtime(true);

    if ($type === 'http' && function_exists('curl_init')) {
        $scheme = ($port === 443) ? 'https://' : 'http://';
        $url = $scheme . $host . ':' . $port . $path;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_NOBODY         => true,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT      => 'LM-ServiceMonitor/1.0',
        ]);
        $result   = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        $latency = (int)round((microtime(true) - $start) * 1000);

        if ($result !== false && $httpCode > 0) {
            return ['online' => true, 'latency' => $latency, 'message' => 'HTTP ' . $httpCode];
        }
        return ['online' => false, 'latency' => $latency, 'message' => $error ?: '连接失败'];
    }

    // TCP 探测（type=tcp，或 http 但无 curl 时降级为端口连通性检测）
    $fp = @fsockopen($host, $port, $errno, $errstr, 4);
    $latency = (int)round((microtime(true) - $start) * 1000);

    if ($fp) {
        fclose($fp);
        return ['online' => true, 'latency' => $latency, 'message' => $type === 'tcp' ? '端口连通' : '连接成功'];
    }
    return ['online' => false, 'latency' => $latency, 'message' => $errstr ?: '端口不通'];
}

/**
 * 写入一条探测日志
 */
function logServiceProbe($serviceId, array $probe) {
    try {
        db()->insert('lm_service_log', [
            'service_id' => (int)$serviceId,
            'status'     => $probe['online'] ? 1 : 0,
            'latency_ms' => $probe['latency'],
            'message'    => mb_substr($probe['message'], 0, 250),
        ]);
    } catch (Exception $e) {
        // 日志写入失败不影响主流程
    }
}

/**
 * 批量探测所有启用的服务，并更新最后探测时间
 * 供 cron 脚本 / 状态页懒触发 / 后台手动触发共用
 * @return array 每项 ['name','online','latency','message']
 */
function probeAllServices() {
    $services = db()->fetchAll("SELECT * FROM lm_service WHERE enabled = 1 ORDER BY sort_order ASC, id ASC");
    $results = [];
    foreach ($services as $svc) {
        $probe = probeService($svc);
        logServiceProbe($svc['id'], $probe);
        $results[] = [
            'name'    => $svc['name'],
            'online'  => $probe['online'],
            'latency' => $probe['latency'],
            'message' => $probe['message'],
        ];
    }
    setSetting('service_last_probe_at', date('Y-m-d H:i:s'));
    return $results;
}

// ===== 直接访问时作为 AJAX 接口 =====
if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    header('Content-Type: application/json; charset=utf-8');

    // 仅管理员可调用测试接口
    session_start();
    if (!isAdmin()) {
        Security::jsonResponse(['success' => false, 'message' => '无权访问'], 403);
    }

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        Security::jsonResponse(['success' => false, 'message' => '参数错误']);
    }

    try {
        $service = db()->fetchOne("SELECT * FROM lm_service WHERE id = ?", [$id]);
        if (!$service) {
            Security::jsonResponse(['success' => false, 'message' => '服务不存在']);
        }

        $probe = probeService($service);
        logServiceProbe($service['id'], $probe);

        Security::jsonResponse([
            'success' => true,
            'online'  => $probe['online'],
            'latency' => $probe['latency'],
            'message' => $probe['message'],
            'name'    => $service['name'],
        ]);
    } catch (Exception $e) {
        Security::jsonResponse(['success' => false, 'message' => '探测失败']);
    }
}
