<?php
/**
 * 服务状态管理
 * 管理员可添加/编辑/删除需监控的服务，并手动测试连通性
 */
define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
requireAdmin();

$pageTitle = '服务状态';
$currentPage = 'services';

$error = '';
$success = '';

// 处理删除
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $token = $_GET['token'] ?? '';
    if (!Security::validateToken($token)) {
        die('CSRF验证失败');
    }

    $id = (int)$_GET['id'];
    try {
        db()->delete('lm_service_log', 'service_id = ?', [$id]);
        db()->delete('lm_service', 'id = ?', [$id]);
        $success = '服务已删除';
    } catch (Exception $e) {
        $error = '删除失败';
    }
}

// 处理添加/编辑
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_service') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        $error = 'CSRF验证失败';
    } else {
        $name = trim($_POST['name'] ?? '');
        $host = trim($_POST['host'] ?? '');
        $type = ($_POST['type'] ?? 'http') === 'tcp' ? 'tcp' : 'http';
        $port = (int)($_POST['port'] ?? ($type === 'http' ? 80 : 80));
        $path = trim($_POST['path'] ?? '/') ?: '/';
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $serviceId = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;

        if (empty($name)) {
            $error = '请填写服务名称';
        } elseif (empty($host)) {
            $error = '请填写主机地址（IP/域名）';
        } elseif (!filter_var($host, FILTER_VALIDATE_DOMAIN) && !filter_var($host, FILTER_VALIDATE_IP)) {
            $error = '主机地址格式不正确';
        } elseif ($port < 1 || $port > 65535) {
            $error = '端口必须在 1-65535 之间';
        } else {
            try {
                $data = [
                    'name'       => Security::xssClean($name),
                    'host'        => Security::xssClean($host),
                    'type'        => $type,
                    'port'        => $port,
                    'path'        => Security::xssClean($path),
                    'sort_order'  => $sortOrder,
                    'enabled'     => $enabled
                ];

                if ($serviceId > 0) {
                    db()->update('lm_service', $data, 'id = ?', [$serviceId]);
                    $success = '服务已更新';
                } else {
                    db()->insert('lm_service', $data);
                    $success = '服务已添加';
                }
            } catch (Exception $e) {
                $error = '保存失败: ' . $e->getMessage();
            }
        }
    }
}

// 处理探测设置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_probe_settings') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        $error = 'CSRF验证失败';
    } else {
        $interval = (int)($_POST['probe_interval'] ?? 5);
        if ($interval < 1) {
            $interval = 1;
        }
        $probeKey = trim($_POST['probe_key'] ?? '');
        setSetting('service_probe_interval', $interval);
        setSetting('service_probe_key', $probeKey);
        $success = '探测设置已保存';
    }
}

// 处理手动立即探测
if (isset($_GET['action']) && $_GET['action'] === 'probe_now') {
    $token = $_GET['token'] ?? '';
    if (!Security::validateToken($token)) {
        die('CSRF验证失败');
    }
    require_once LM_ROOT . '/api/service-probe.php';
    try {
        $probeResults = probeAllServices();
        $okCount = 0;
        foreach ($probeResults as $r) {
            if ($r['online']) {
                $okCount++;
            }
        }
        $success = '探测完成：' . $okCount . '/' . count($probeResults) . ' 项在线';
    } catch (Exception $e) {
        $error = '探测失败: ' . $e->getMessage();
    }
}

// 获取全部服务
try {
    $allServices = db()->fetchAll("SELECT * FROM lm_service ORDER BY sort_order ASC, id ASC");
} catch (Exception $e) {
    $allServices = [];
    $error = '读取服务列表失败，请先在云服务器执行 /database/service-status.sql 建表';
}

require_once LM_ROOT . '/admin/template/header.php';
?>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo e($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error"><?php echo e($error); ?></div>
<?php endif; ?>

<?php
$currentInterval = (int)getSetting('service_probe_interval', 5);
$currentKey = getSetting('service_probe_key', '');
$lastProbeAt = getSetting('service_last_probe_at', '');
$siteRoot = rtrim(str_replace('\\', '/', LM_ROOT), '/');
?>

<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>探测设置</div>
        <a href="?action=probe_now&token=<?php echo Security::generateToken(); ?>" class="btn btn-sm btn-primary">立即探测</a>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="save_probe_settings">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">探测间隔（分钟）*</label>
                    <input type="number" name="probe_interval" class="form-input" value="<?php echo $currentInterval; ?>" min="1" max="1440">
                    <div class="form-hint">状态页和定时任务按此间隔探测。建议 1-10 分钟。</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Web 触发密钥（可选）</label>
                    <input type="text" name="probe_key" class="form-input" value="<?php echo e($currentKey); ?>" placeholder="留空则禁止 Web 触发">
                    <div class="form-hint">用于 Web/Curl 触发探测时的鉴权，CLI（crontab）无需此密钥。</div>
                </div>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">保存设置</button>
            </div>
        </form>

        <div style="margin-top: 16px; padding: 12px 16px; background: var(--bg-elevated); border-radius: var(--radius); border-left: 3px solid var(--primary-color);">
            <div style="font-weight: 600; margin-bottom: 6px;">定时任务配置（推荐）</div>
            <div style="color: var(--text-secondary); font-size: 0.88rem; line-height: 1.7;">
                在云服务器 crontab 中添加以下命令，即可实现真正的定时探测（无需用户访问触发）：<br>
                <code style="display:inline-block; margin-top:6px; padding:6px 10px; background:var(--bg-secondary); border-radius:4px; word-break:break-all;"><?php echo '*/' . max(1, $currentInterval); ?> * * * * php <?php echo $siteRoot; ?>/api/service-probe-all.php</code><br>
                <span style="color: var(--text-tertiary);">未配置 cron 时，状态页访问会自动懒触发探测作为兜底。</span><br>
                <?php if ($lastProbeAt): ?>
                <span style="color: var(--text-tertiary);">上次探测时间：<?php echo e($lastProbeAt); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M5 12h14"/><path d="M12 5v14"/></svg>添加/编辑服务</div>
    </div>
    <div class="card-body">
        <form method="POST" action="" data-validate>
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="save_service">
            <input type="hidden" name="service_id" id="service_id" value="0">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">服务名称 *</label>
                    <input type="text" name="name" class="form-input" placeholder="例如：博客主站" required id="service_name">
                </div>

                <div class="form-group">
                    <label class="form-label">主机地址（IP/域名）*</label>
                    <input type="text" name="host" class="form-input" placeholder="example.com 或 1.2.3.4" required id="service_host">
                    <div class="form-hint">不含协议和端口，如 kslinmeng.cn</div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">探测类型</label>
                    <select name="type" class="form-input" id="service_type">
                        <option value="http">HTTP（网页）</option>
                        <option value="tcp">TCP（端口）</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">端口</label>
                    <input type="number" name="port" class="form-input" value="80" min="1" max="65535" id="service_port">
                </div>

                <div class="form-group">
                    <label class="form-label">排序</label>
                    <input type="number" name="sort_order" class="form-input" value="0" id="service_sort">
                </div>
            </div>

            <div class="form-group" id="path-group">
                <label class="form-label">HTTP 探测路径</label>
                <input type="text" name="path" class="form-input" value="/" placeholder="/" id="service_path">
                <div class="form-hint">仅 HTTP 类型使用，通常填 / 即可</div>
            </div>

            <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="enabled" id="service_enabled" checked style="width: auto;">
                <label for="service_enabled" style="margin-bottom: 0;">启用监控</label>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary" id="service_submit_btn">添加服务</button>
                <button type="button" class="btn btn-secondary" id="reset-service-form">重置</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>服务列表</div>
        <a href="/status.php" target="_blank" class="btn btn-sm btn-secondary">查看状态页</a>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>名称</th>
                    <th>主机</th>
                    <th>类型</th>
                    <th>端口</th>
                    <th>排序</th>
                    <th>状态</th>
                    <th>探测</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allServices as $svc): ?>
                <tr data-service-id="<?php echo (int)$svc['id']; ?>">
                    <td><?php echo (int)$svc['id']; ?></td>
                    <td><?php echo e($svc['name']); ?></td>
                    <td><?php echo e($svc['host']); ?></td>
                    <td><?php echo $svc['type'] === 'tcp' ? 'TCP' : 'HTTP'; ?></td>
                    <td><?php echo (int)$svc['port']; ?></td>
                    <td><?php echo (int)$svc['sort_order']; ?></td>
                    <td>
                        <span class="badge <?php echo $svc['enabled'] ? 'badge-success' : 'badge-danger'; ?>">
                            <?php echo $svc['enabled'] ? '启用' : '禁用'; ?>
                        </span>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-secondary service-test-btn"
                                data-id="<?php echo (int)$svc['id']; ?>">测试</button>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary"
                                data-edit-id="<?php echo (int)$svc['id']; ?>"
                                data-edit-name="<?php echo e($svc['name']); ?>"
                                data-edit-host="<?php echo e($svc['host']); ?>"
                                data-edit-type="<?php echo e($svc['type']); ?>"
                                data-edit-port="<?php echo (int)$svc['port']; ?>"
                                data-edit-path="<?php echo e($svc['path']); ?>"
                                data-edit-sort="<?php echo (int)$svc['sort_order']; ?>"
                                data-edit-enabled="<?php echo (int)$svc['enabled']; ?>">编辑</button>
                        <a href="?action=delete&id=<?php echo (int)$svc['id']; ?>&token=<?php echo Security::generateToken(); ?>"
                           class="btn btn-sm btn-danger"
                           data-confirm="确定要删除该服务吗？相关探测记录也会一并删除。">删除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($allServices)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; color: var(--text-light); padding: 40px;">暂无服务，请在上方添加</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="/assets/js/admin/admin-services.js?v=<?php echo LM_VERSION; ?>"></script>

<?php require_once LM_ROOT . '/admin/template/footer.php'; ?>
