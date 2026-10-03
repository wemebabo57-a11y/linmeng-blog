<?php
/**
 * 开源仓库管理（我的仓库）
 */
define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/includes/config.php';
require_once APP_ROOT . '/includes/Security.php';
require_once APP_ROOT . '/includes/Database.php';
require_once APP_ROOT . '/includes/functions.php';

session_start();
requireAdmin();

$pageTitle = '仓库管理';
$currentPage = 'repos';

$error = '';
$success = '';

// 处理删除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_repo' && isset($_POST['id'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        die('CSRF验证失败');
    }

    $id = (int)$_POST['id'];
    try {
        db()->delete('app_repo', 'id = ?', [$id]);
        $success = '仓库已删除';
    } catch (Exception $e) {
        $error = '删除失败';
    }
}

// 处理添加/编辑
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_repo') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        $error = 'CSRF验证失败';
    } else {
        $name = trim($_POST['name'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;
        $repoId = isset($_POST['repo_id']) ? (int)$_POST['repo_id'] : 0;

        if (empty($name) || empty($url)) {
            $error = '请填写仓库名称和地址';
        } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
            $error = '仓库地址格式不正确';
        } else {
            try {
                $data = [
                    'name' => Security::xssClean($name),
                    'url' => Security::xssClean($url),
                    'description' => Security::xssClean($description),
                    'sort_order' => $sortOrder,
                    'status' => $status
                ];

                if ($repoId > 0) {
                    db()->update('app_repo', $data, 'id = ?', [$repoId]);
                    $success = '仓库已更新';
                } else {
                    db()->insert('app_repo', $data);
                    $success = '仓库已添加';
                }
            } catch (Exception $e) {
                $error = '保存失败: ' . $e->getMessage();
            }
        }
    }
}

// 获取全部仓库（包含隐藏）
$allRepos = getAllRepos();

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
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M5 12h14"/><path d="M12 5v14"/></svg>添加/编辑仓库</div>
    </div>
    <div class="card-body">
        <form method="POST" action="" data-validate>
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="save_repo">
            <input type="hidden" name="repo_id" id="repo_id" value="0">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">仓库名称 *</label>
                    <input type="text" name="name" class="form-input" placeholder="例如：my-awesome-project" required id="repo_name">
                </div>

                <div class="form-group">
                    <label class="form-label">排序</label>
                    <input type="number" name="sort_order" class="form-input" value="0" id="repo_sort">
                    <div class="form-hint">数字越大越靠前</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">仓库地址 *</label>
                <input type="url" name="url" class="form-input" placeholder="https://github.com/username/repo" required id="repo_url">
            </div>

            <div class="form-group">
                <label class="form-label">仓库详情</label>
                <textarea name="description" class="form-input" rows="3" placeholder="简短介绍该仓库，如功能、技术栈等" id="repo_desc"></textarea>
            </div>

            <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="status" id="repo_status" checked style="width: auto;">
                <label for="repo_status" style="margin-bottom: 0;">显示该仓库</label>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary" id="repo_submit_btn">添加仓库</button>
                <button type="button" class="btn btn-secondary" id="reset-repo-form">重置</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/></svg>仓库列表</div>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>名称</th>
                    <th>地址</th>
                    <th>详情</th>
                    <th>排序</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allRepos as $r): ?>
                <tr>
                    <td><?php echo $r['id']; ?></td>
                    <td><?php echo e($r['name']); ?></td>
                    <td><a href="<?php echo e($r['url']); ?>" target="_blank" rel="noopener"><?php echo e(truncate($r['url'], 40)); ?></a></td>
                    <td><?php echo e(truncate($r['description'] ?: '-', 30)); ?></td>
                    <td><?php echo $r['sort_order']; ?></td>
                    <td>
                        <span class="badge <?php echo $r['status'] ? 'badge-success' : 'badge-danger'; ?>">
                            <?php echo $r['status'] ? '显示' : '隐藏'; ?>
                        </span>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary"
                                data-edit-repo-id="<?php echo (int)$r['id']; ?>"
                                data-edit-repo-name="<?php echo e($r['name']); ?>"
                                data-edit-repo-url="<?php echo e($r['url']); ?>"
                                data-edit-repo-desc="<?php echo e($r['description']); ?>"
                                data-edit-repo-sort="<?php echo (int)$r['sort_order']; ?>"
                                data-edit-repo-status="<?php echo (int)$r['status']; ?>">编辑</button>
                        <form method="POST" action="" class="form-delete-repo" style="display: inline;">
                            <?php echo Security::csrfField(); ?>
                            <input type="hidden" name="action" value="delete_repo">
                            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger" data-confirm="确定要删除该仓库吗？">删除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($allRepos)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-light); padding: 40px;">暂无仓库，请添加</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="/assets/js/admin/admin-repos.js?v=<?php echo APP_VERSION; ?>"></script>

<?php require_once APP_ROOT . '/admin/template/footer.php'; ?>