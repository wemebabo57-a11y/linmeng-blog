<?php
/**
 * 服务状态页
 * 公开页面，展示所有启用服务的实时探测结果（60 秒缓存）
 */
define('LM_ROOT', __DIR__);

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';
require_once LM_ROOT . '/api/service-probe.php'; // 引入 probeService / logServiceProbe

session_start();
Security::setSecurityHeaders();

$pageTitle = '服务状态';
$currentPage = 'status';
$bodyClass = 'page-status';

// 读取所有启用的服务
$services = [];
$fetchError = '';
try {
    $services = db()->fetchAll("SELECT * FROM lm_service WHERE enabled = 1 ORDER BY sort_order ASC, id ASC");
} catch (Exception $e) {
    $fetchError = '服务状态功能尚未初始化，请联系管理员执行建表脚本。';
}

// 探测：根据后台设置的间隔判断是否需要重新探测（懒触发兜底）
// 若已配置 cron 定时调用 service-probe-all.php，此处通常不会触发，直接用缓存日志
$interval = (int)getSetting('service_probe_interval', 5); // 分钟
if ($interval < 1) {
    $interval = 1;
}
$now = time();
$lastProbeAt = getSetting('service_last_probe_at', '');
$needProbeAll = (!$lastProbeAt) || (strtotime($lastProbeAt) < $now - $interval * 60);

if ($needProbeAll && !$fetchError && count($services) > 0) {
    try {
        probeAllServices();
    } catch (Exception $e) {
        // 懒触发失败不影响后续展示缓存
    }
}

$onlineCount = 0;
$totalCount = count($services);

foreach ($services as &$svc) {
    $last = null;
    try {
        $last = db()->fetchOne(
            "SELECT * FROM lm_service_log WHERE service_id = ? ORDER BY created_at DESC LIMIT 1",
            [$svc['id']]
        );
    } catch (Exception $e) {
        $last = null;
    }

    if ($last) {
        $svc['status']     = (int)$last['status'];
        $svc['latency']    = (int)$last['latency_ms'];
        $svc['message']    = $last['message'];
        $svc['checked_at'] = $last['created_at'];
    } else {
        $svc['status']     = 0;
        $svc['latency']    = 0;
        $svc['message']    = '尚未探测';
        $svc['checked_at'] = '-';
    }

    if ($svc['status']) {
        $onlineCount++;
    }
}
unset($svc);

require_once LM_ROOT . '/template/header.php';
?>

<style>
.status-hero {
    text-align: center;
    padding: 48px 24px 32px;
}
.status-hero-title {
    font-size: 2rem;
    font-weight: 700;
    margin: 0 0 8px;
    font-family: 'Playfair Display', 'LXGW WenKai', serif;
}
.status-hero-subtitle {
    color: var(--text-secondary);
    margin: 0 0 24px;
}
.status-overall {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    border-radius: 999px;
    font-weight: 600;
    font-size: 0.95rem;
}
.status-overall.all-ok {
    background: rgba(61, 139, 107, 0.12);
    color: var(--success-color);
}
.status-overall.some-down {
    background: rgba(184, 134, 42, 0.12);
    color: var(--warning-color);
}
.status-overall.all-down {
    background: rgba(196, 59, 59, 0.12);
    color: var(--danger-color);
}
.status-overall-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: currentColor;
    animation: status-pulse 2s ease-in-out infinite;
}
@keyframes status-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}
.status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}
.status-card {
    background: var(--bg-elevated);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius);
    padding: 20px;
    transition: box-shadow 0.2s ease;
}
.status-card:hover {
    box-shadow: var(--shadow-md);
}
.status-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}
.status-card-name {
    font-weight: 600;
    font-size: 1.05rem;
    display: flex;
    align-items: center;
    gap: 8px;
}
.status-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
.status-dot.online {
    background: var(--success-color);
    box-shadow: 0 0 8px rgba(61, 139, 107, 0.5);
}
.status-dot.offline {
    background: var(--danger-color);
    box-shadow: 0 0 8px rgba(196, 59, 59, 0.4);
}
.status-badge {
    font-size: 0.78rem;
    padding: 3px 10px;
    border-radius: 999px;
    font-weight: 500;
}
.status-badge.online {
    background: rgba(61, 139, 107, 0.12);
    color: var(--success-color);
}
.status-badge.offline {
    background: rgba(196, 59, 59, 0.12);
    color: var(--danger-color);
}
.status-card-meta {
    color: var(--text-secondary);
    font-size: 0.85rem;
    line-height: 1.6;
}
.status-card-meta span {
    display: inline-block;
    margin-right: 12px;
}
.status-card-time {
    color: var(--text-tertiary);
    font-size: 0.8rem;
    margin-top: 8px;
}
.status-refresh-bar {
    display: flex;
    justify-content: center;
    margin: 24px 0;
}
</style>

<!-- Hero -->
<div class="status-hero">
    <h1 class="status-hero-title">服务状态</h1>
    <p class="status-hero-subtitle">实时监控各服务在线情况，数据每 <?php echo (int)$interval; ?> 分钟自动刷新</p>
    <?php if (!$fetchError && $totalCount > 0): ?>
        <?php
        if ($onlineCount === $totalCount) {
            $overallClass = 'all-ok';
            $overallText = '所有服务运行正常';
        } elseif ($onlineCount === 0) {
            $overallClass = 'all-down';
            $overallText = '所有服务离线';
        } else {
            $overallClass = 'some-down';
            $overallText = $onlineCount . '/' . $totalCount . ' 项服务在线';
        }
        ?>
        <div class="status-overall <?php echo $overallClass; ?>">
            <span class="status-overall-dot"></span>
            <?php echo e($overallText); ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($fetchError): ?>
<div class="empty-state card">
    <h3>暂时无法显示</h3>
    <p><?php echo e($fetchError); ?></p>
</div>
<?php elseif ($totalCount === 0): ?>
<div class="empty-state card">
    <h3>暂无监控服务</h3>
    <p>管理员尚未添加任何服务。</p>
</div>
<?php else: ?>

<div class="status-grid">
    <?php foreach ($services as $svc): ?>
    <div class="status-card">
        <div class="status-card-head">
            <div class="status-card-name">
                <span class="status-dot <?php echo $svc['status'] ? 'online' : 'offline'; ?>"></span>
                <?php echo e($svc['name']); ?>
            </div>
            <span class="status-badge <?php echo $svc['status'] ? 'online' : 'offline'; ?>">
                <?php echo $svc['status'] ? '在线' : '离线'; ?>
            </span>
        </div>
        <div class="status-card-meta">
            <span>
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 7h10v10H7z"/></svg>
                <?php echo e($svc['host']); ?>:<?php echo (int)$svc['port']; ?>
            </span>
            <span>
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?php echo $svc['status'] ? (int)$svc['latency'] . ' ms' : '-'; ?>
            </span>
            <span><?php echo e($svc['message']); ?></span>
        </div>
        <div class="status-card-time">
            最后检测：<?php echo e($svc['checked_at']); ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="status-refresh-bar">
    <button type="button" class="btn btn-secondary" id="status-refresh-btn">刷新状态</button>
</div>
<?php endif; ?>

<script>
document.getElementById('status-refresh-btn')?.addEventListener('click', function () {
    // 强制刷新：加随机参数绕过缓存，服务端会重新探测（若超过间隔）
    window.location.href = window.location.pathname + '?t=' + Date.now();
});
// 按后台设置的探测间隔自动刷新
setTimeout(function () {
    window.location.reload();
}, <?php echo $interval * 60 * 1000; ?>);
</script>

<?php require_once LM_ROOT . '/template/sidebar.php'; ?>
