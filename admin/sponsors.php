<?php
/**
 * 赞助商管理
 */
define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
requireAdmin();

$pageTitle = '赞助商管理';
$currentPage = 'sponsors';

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
        db()->delete('lm_sponsor', 'id = ?', [$id]);
        $success = '赞助商已删除';
    } catch (Exception $e) {
        $error = '删除失败';
    }
}

// 处理添加/编辑
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_sponsor') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        $error = 'CSRF验证失败';
    } else {
        $name = trim($_POST['name'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $detail = trim($_POST['detail'] ?? '');
        $iconUrl = trim($_POST['icon_url'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;
        $sponsorId = isset($_POST['sponsor_id']) ? (int)$_POST['sponsor_id'] : 0;

        if (empty($name)) {
            $error = '请填写赞助商名称';
        } elseif ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            $error = '跳转链接格式不正确';
        } else {
            $icon = $iconUrl;

            // 处理图标上传
            if (!empty($_FILES['icon_file']['tmp_name'])) {
                $upload = saveUploadedImage($_FILES['icon_file'], 'sponsor_');
                if (!$upload['success']) {
                    $error = '图标上传失败：' . $upload['message'];
                } else {
                    $icon = $upload['url'];
                }
            }

            if (empty($error)) {
                try {
                    $data = [
                        'name' => Security::xssClean($name),
                        'url' => Security::xssClean($url),
                        'detail' => Security::xssClean($detail),
                        'icon' => Security::xssClean($icon),
                        'sort_order' => $sortOrder,
                        'status' => $status
                    ];

                    if ($sponsorId > 0) {
                        db()->update('lm_sponsor', $data, 'id = ?', [$sponsorId]);
                        $success = '赞助商已更新';
                    } else {
                        db()->insert('lm_sponsor', $data);
                        $success = '赞助商已添加';
                    }
                } catch (Exception $e) {
                    $error = '保存失败: ' . $e->getMessage();
                }
            }
        }
    }
}

// 获取全部赞助商（包含隐藏）
$allSponsors = getSponsors(false);

require_once LM_ROOT . '/admin/template/header.php';
?>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo e($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error"><?php echo e($error); ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M5 12h14"/><path d="M12 5v14"/></svg>添加/编辑赞助商</div>
    </div>
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data" data-validate>
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="save_sponsor">
            <input type="hidden" name="sponsor_id" id="sponsor_id" value="0">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">赞助商名称 *</label>
                    <input type="text" name="name" class="form-input" placeholder="例如：阿里云" required id="sponsor_name">
                </div>

                <div class="form-group">
                    <label class="form-label">跳转链接</label>
                    <input type="url" name="url" class="form-input" placeholder="https://example.com" id="sponsor_url">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">详情介绍</label>
                <input type="text" name="detail" class="form-input" placeholder="一句话介绍赞助商" id="sponsor_detail">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">图标 URL</label>
                    <input type="text" name="icon_url" class="form-input" placeholder="https://example.com/logo.png 或留空上传本地图标" id="sponsor_icon_url">
                    <div class="form-hint">可直接填写图标链接，或点击下方上传本地图标</div>
                </div>

                <div class="form-group">
                    <label class="form-label">排序</label>
                    <input type="number" name="sort_order" class="form-input" value="0" id="sponsor_sort">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">上传本地图标</label>
                <input type="file" name="icon_file" class="form-input" accept="image/jpeg,image/png,image/gif,image/webp" id="sponsor_icon_file">
                <div class="form-hint">支持 jpg、png、gif、webp，不超过 5MB。上传文件会覆盖上方图标 URL</div>
            </div>

            <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="status" id="sponsor_status" checked style="width: auto;">
                <label for="sponsor_status" style="margin-bottom: 0;">显示</label>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary" id="sponsor_submit_btn">添加赞助商</button>
                <button type="button" class="btn btn-secondary" id="reset-sponsor-form">重置</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>赞助商列表</div>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>图标</th>
                    <th>名称</th>
                    <th>链接</th>
                    <th>详情</th>
                    <th>排序</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allSponsors as $sponsor): ?>
                <tr>
                    <td><?php echo $sponsor['id']; ?></td>
                    <td>
                        <?php if ($sponsor['icon']): ?>
                        <img src="<?php echo e($sponsor['icon']); ?>" alt="" class="sponsor-admin-thumb">
                        <?php else: ?>
                        <div class="sponsor-admin-thumb sponsor-admin-thumb-placeholder"><?php echo mb_substr($sponsor['name'], 0, 1); ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo e($sponsor['name']); ?></td>
                    <td>
                        <?php if ($sponsor['url']): ?>
                        <a href="<?php echo e($sponsor['url']); ?>" target="_blank" rel="noopener"><?php echo e(truncate($sponsor['url'], 30)); ?></a>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                    <td><?php echo e(truncate($sponsor['detail'] ?: '-', 20)); ?></td>
                    <td><?php echo $sponsor['sort_order']; ?></td>
                    <td>
                        <span class="badge <?php echo $sponsor['status'] ? 'badge-success' : 'badge-danger'; ?>">
                            <?php echo $sponsor['status'] ? '显示' : '隐藏'; ?>
                        </span>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary"
                                data-edit-sponsor-id="<?php echo (int)$sponsor['id']; ?>"
                                data-edit-sponsor-name="<?php echo e($sponsor['name']); ?>"
                                data-edit-sponsor-url="<?php echo e($sponsor['url']); ?>"
                                data-edit-sponsor-detail="<?php echo e($sponsor['detail']); ?>"
                                data-edit-sponsor-icon="<?php echo e($sponsor['icon']); ?>"
                                data-edit-sponsor-sort="<?php echo (int)$sponsor['sort_order']; ?>"
                                data-edit-sponsor-status="<?php echo (int)$sponsor['status']; ?>">编辑</button>
                        <a href="?action=delete&id=<?php echo $sponsor['id']; ?>&token=<?php echo Security::generateToken(); ?>"
                           class="btn btn-sm btn-danger"
                           data-confirm="确定要删除该赞助商吗？">删除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($allSponsors)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--text-light); padding: 40px;">暂无赞助商</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="/assets/js/admin/admin-sponsors.js?v=<?php echo LM_VERSION; ?>"></script>

<?php require_once LM_ROOT . '/admin/template/footer.php'; ?>
