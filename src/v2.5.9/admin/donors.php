<?php
/**
 * 捐赠者管理（独立于赞助商：名字 + 跳转链接 + 金额，后台可见）
 */
define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/includes/config.php';
require_once APP_ROOT . '/includes/Security.php';
require_once APP_ROOT . '/includes/Database.php';
require_once APP_ROOT . '/includes/functions.php';

session_start();
requireAdmin();

$pageTitle = '捐赠者管理';
$currentPage = 'donors';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_donor' && isset($_POST['id'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        die('CSRF验证失败');
    }
    try {
        db()->delete('app_donor', 'id = ?', [(int)$_POST['id']]);
        $success = '捐赠者已删除';
    } catch (Exception $e) {
        $error = '删除失败';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_donor') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        $error = 'CSRF验证失败';
    } else {
        $name = trim($_POST['name'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;
        $donorId = isset($_POST['donor_id']) ? (int)$_POST['donor_id'] : 0;
        $amountRaw = trim((string)($_POST['amount'] ?? ''));
        $amount = null;
        if ($amountRaw !== '') {
            if (!is_numeric($amountRaw) || (float)$amountRaw < 0) {
                $error = '金额格式不正确';
            } else {
                $amount = round((float)$amountRaw, 2);
            }
        }
        if (empty($error) && $name === '') {
            $error = '请填写捐赠者名字';
        } elseif (empty($error) && $url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            $error = '跳转链接格式不正确';
        } elseif (empty($error)) {
            try {
                $data = [
                    'name' => Security::xssClean($name),
                    'url' => Security::xssClean($url),
                    'amount' => $amount,
                    'sort_order' => $sortOrder,
                    'status' => $status
                ];
                if ($donorId > 0) {
                    db()->update('app_donor', $data, 'id = ?', [$donorId]);
                    $success = '捐赠者已更新';
                } else {
                    db()->insert('app_donor', $data);
                    $success = '捐赠者已添加';
                }
            } catch (Exception $e) {
                $error = '保存失败: ' . $e->getMessage();
            }
        }
    }
}

$allDonors = getAllDonors();

require_once APP_ROOT . '/admin/template/header.php';
?>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo e($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error"><?php echo e($error); ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <div class="card-title">添加/编辑捐赠者</div>
    </div>
    <div class="card-body">
        <form method="POST" action="" data-validate>
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="save_donor">
            <input type="hidden" name="donor_id" id="donor_id" value="0">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">名字 *</label>
                    <input type="text" name="name" class="form-input" placeholder="例如：热心网友" required id="donor_name" maxlength="100">
                </div>
                <div class="form-group">
                    <label class="form-label">点击跳转链接</label>
                    <input type="url" name="url" class="form-input" placeholder="https://example.com" id="donor_url" maxlength="500">
                    <div class="form-hint">可空；前台点击名字跳转</div>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">金额（元，可空，仅后台可见）</label>
                    <input type="number" name="amount" class="form-input" placeholder="例如：66.66" step="0.01" min="0" id="donor_amount">
                    <div class="form-hint">前台捐赠墙只显示名字，不展示金额</div>
                </div>
                <div class="form-group">
                    <label class="form-label">排序</label>
                    <input type="number" name="sort_order" class="form-input" value="0" id="donor_sort">
                </div>
            </div>
            <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="status" id="donor_status" checked style="width: auto;">
                <label for="donor_status" style="margin-bottom: 0;">显示</label>
            </div>
            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary" id="donor_submit_btn">添加捐赠者</button>
                <button type="button" class="btn btn-secondary" id="reset-donor-form">重置</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">捐赠者列表</div>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>名字</th>
                    <th>跳转链接</th>
                    <th>金额</th>
                    <th>排序</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allDonors as $donor): ?>
                <tr>
                    <td><?php echo (int)$donor['id']; ?></td>
                    <td><?php echo e($donor['name']); ?></td>
                    <td>
                        <?php if (!empty($donor['url'])): ?>
                        <a href="<?php echo e($donor['url']); ?>" target="_blank" rel="noopener"><?php echo e(truncate($donor['url'], 30)); ?></a>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                    <td><?php echo isset($donor['amount']) && $donor['amount'] !== null && $donor['amount'] !== '' ? e(number_format((float)$donor['amount'], 2)) : '-'; ?></td>
                    <td><?php echo (int)$donor['sort_order']; ?></td>
                    <td>
                        <span class="badge <?php echo $donor['status'] ? 'badge-success' : 'badge-danger'; ?>">
                            <?php echo $donor['status'] ? '显示' : '隐藏'; ?>
                        </span>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary"
                                data-edit-donor-id="<?php echo (int)$donor['id']; ?>"
                                data-edit-donor-name="<?php echo e($donor['name']); ?>"
                                data-edit-donor-url="<?php echo e($donor['url']); ?>"
                                data-edit-donor-amount="<?php echo isset($donor['amount']) && $donor['amount'] !== null ? e($donor['amount']) : ''; ?>"
                                data-edit-donor-sort="<?php echo (int)$donor['sort_order']; ?>"
                                data-edit-donor-status="<?php echo (int)$donor['status']; ?>">编辑</button>
                        <form method="POST" action="" style="display: inline;">
                            <?php echo Security::csrfField(); ?>
                            <input type="hidden" name="action" value="delete_donor">
                            <input type="hidden" name="id" value="<?php echo (int)$donor['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger" data-confirm="确定要删除该捐赠者吗？">删除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($allDonors)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-light); padding: 40px;">暂无捐赠者</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="/assets/js/admin/admin-donors.js?v=<?php echo APP_VERSION; ?>"></script>

<?php
// 内容变更成功后清空静态页面缓存：首页/归档/标签/关于/友链都依赖这些数据，
// 否则访客最长会看到 TTL（默认 300 秒）的旧内容。
if ($success !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    app_purge_page_cache();
}
?>
<?php require_once APP_ROOT . '/admin/template/footer.php'; ?>
