<?php
/**
 * 友链管理
 */
define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
requireAdmin();

$pageTitle = '友链管理';
$currentPage = 'links';

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
        db()->delete('lm_link', 'id = ?', [$id]);
        $success = '友链已删除';
    } catch (Exception $e) {
        $error = '删除失败';
    }
}

// 处理添加/编辑
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_link') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        $error = 'CSRF验证失败';
    } else {
        $name = trim($_POST['name'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $logo = trim($_POST['logo'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;
        $linkId = isset($_POST['link_id']) ? (int)$_POST['link_id'] : 0;
        
        if (empty($name) || empty($url)) {
            $error = '请填写名称和链接';
        } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
            $error = '链接格式不正确';
        } else {
            try {
                $data = [
                    'name' => Security::xssClean($name),
                    'url' => Security::xssClean($url),
                    'description' => Security::xssClean($description),
                    'logo' => Security::xssClean($logo),
                    'sort_order' => $sortOrder,
                    'status' => $status
                ];
                
                if ($linkId > 0) {
                    db()->update('lm_link', $data, 'id = ?', [$linkId]);
                    $success = '友链已更新';
                } else {
                    db()->insert('lm_link', $data);
                    $success = '友链已添加';
                }
            } catch (Exception $e) {
                $error = '保存失败: ' . $e->getMessage();
            }
        }
    }
}

// 获取友链列表（含隐藏项；排序方向与前台 getLinks/getVisibleLinks 保持一致）
$allLinks = db()->fetchAll("SELECT * FROM lm_link ORDER BY sort_order DESC, id DESC");

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
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M5 12h14"/><path d="M12 5v14"/></svg>添加/编辑友链</div>
    </div>
    <div class="card-body">
        <form method="POST" action="" data-validate>
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="save_link">
            <input type="hidden" name="link_id" id="link_id" value="0">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">网站名称 *</label>
                    <input type="text" name="name" class="form-input" placeholder="网站名称" required id="link_name">
                </div>
                
                <div class="form-group">
                    <label class="form-label">链接地址 *</label>
                    <input type="url" name="url" class="form-input" placeholder="https://example.com" required id="link_url">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">网站描述</label>
                <input type="text" name="description" class="form-input" placeholder="简短描述" id="link_description">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Logo地址</label>
                    <input type="text" name="logo" class="form-input" placeholder="https://example.com/logo.png" id="link_logo">
                </div>
                
                <div class="form-group">
                    <label class="form-label">排序</label>
                    <input type="number" name="sort_order" class="form-input" value="0" id="link_sort">
                </div>
            </div>
            
            <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="status" id="link_status" checked style="width: auto;">
                <label for="link_status" style="margin-bottom: 0;">显示</label>
            </div>
            
            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary" id="link_submit_btn">添加友链</button>
                <button type="button" class="btn btn-secondary" id="reset-link-form">重置</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>友链列表</div>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>名称</th>
                    <th>链接</th>
                    <th>描述</th>
                    <th>排序</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allLinks as $link): ?>
                <tr>
                    <td><?php echo $link['id']; ?></td>
                    <td><?php echo e($link['name']); ?></td>
                    <td><a href="<?php echo e($link['url']); ?>" target="_blank" rel="noopener"><?php echo e(truncate($link['url'], 30)); ?></a></td>
                    <td><?php echo e(truncate($link['description'] ?: '-', 20)); ?></td>
                    <td><?php echo $link['sort_order']; ?></td>
                    <td>
                        <span class="badge <?php echo $link['status'] ? 'badge-success' : 'badge-danger'; ?>">
                            <?php echo $link['status'] ? '显示' : '隐藏'; ?>
                        </span>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary"
                                data-edit-link-id="<?php echo (int)$link['id']; ?>"
                                data-edit-link-name="<?php echo e($link['name']); ?>"
                                data-edit-link-url="<?php echo e($link['url']); ?>"
                                data-edit-link-description="<?php echo e($link['description']); ?>"
                                data-edit-link-logo="<?php echo e($link['logo']); ?>"
                                data-edit-link-sort="<?php echo (int)$link['sort_order']; ?>"
                                data-edit-link-status="<?php echo (int)$link['status']; ?>">编辑</button>
                        <a href="?action=delete&id=<?php echo $link['id']; ?>&token=<?php echo Security::generateToken(); ?>"
                           class="btn btn-sm btn-danger"
                           data-confirm="确定要删除该友链吗？">删除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($allLinks)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-light); padding: 40px;">暂无友链</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="/assets/js/admin/admin-links.js?v=<?php echo LM_VERSION; ?>"></script>

<?php require_once LM_ROOT . '/admin/template/footer.php'; ?>
