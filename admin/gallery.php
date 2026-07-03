<?php
/**
 * 后台图库管理
 * 管理员可以查看所有用户上传的图片
 */
define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
requireAdmin();

$pageTitle = '图库管理';
$currentPage = 'gallery';

$error = '';
$success = '';

// 获取分页参数
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 24;
$offset = ($page - 1) * $perPage;

// 获取筛选参数
$filterUser = trim($_GET['user'] ?? '');

// 构建查询条件
$where = '';
$params = [];
if ($filterUser !== '') {
    $where = "WHERE username = ?";
    $params[] = $filterUser;
}

// 获取总数
try {
    $totalSql = "SELECT COUNT(*) FROM lm_gallery " . $where;
    $total = db()->fetchColumn($totalSql, $params) ?: 0;
} catch (Exception $e) {
    $total = 0;
}

$totalPages = max(1, ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// 获取图片列表
try {
    $sql = "SELECT * FROM lm_gallery " . $where . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $queryParams = array_merge($params, [$perPage, $offset]);
    $images = db()->fetchAll($sql, $queryParams);
} catch (Exception $e) {
    $images = [];
}

// 获取所有有上传记录的用户名（用于筛选）
try {
    $users = db()->fetchAll("SELECT DISTINCT username FROM lm_gallery ORDER BY username");
} catch (Exception $e) {
    $users = [];
}

require_once LM_ROOT . '/admin/template/header.php';
?>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo e($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error"><?php echo e($error); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
            图库管理
        </div>
        <div style="font-size: 0.85rem; color: var(--text-light);">共 <?php echo $total; ?> 张图片</div>
    </div>
    <div class="card-body">
        <!-- 筛选 -->
        <form method="GET" action="" style="margin-bottom: 20px; display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="margin-bottom: 0; min-width: 200px;">
                <label class="form-label">按用户筛选</label>
                <select name="user" class="form-select">
                    <option value="">全部用户</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?php echo e($u['username']); ?>" <?php echo $filterUser === $u['username'] ? 'selected' : ''; ?>><?php echo e($u['username']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">筛选</button>
            <?php if ($filterUser): ?>
            <a href="gallery.php" class="btn btn-secondary">清除筛选</a>
            <?php endif; ?>
        </form>

        <?php if (empty($images)): ?>
        <div class="empty-state" style="padding: 60px 20px;">
            <div class="empty-state-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
            </div>
            <h3>暂无图片</h3>
            <p>用户还没有上传过任何图片</p>
        </div>
        <?php else: ?>
        <div class="admin-gallery-grid">
            <?php foreach ($images as $img): ?>
            <div class="admin-gallery-item">
                <div class="admin-gallery-img-wrapper">
                    <img src="<?php echo e($img['cdn_url'] ?: $img['raw_url']); ?>" alt="<?php echo e($img['original_name']); ?>" loading="lazy">
                </div>
                <div class="admin-gallery-info">
                    <div class="admin-gallery-filename" title="<?php echo e($img['original_name']); ?>"><?php echo e($img['original_name']); ?></div>
                    <div class="admin-gallery-meta">
                        <span class="admin-gallery-user"><?php echo e($img['username']); ?></span>
                        <span><?php echo formatFileSize($img['file_size']); ?></span>
                    </div>
                    <div class="admin-gallery-meta" style="margin-top: 4px;">
                        <span style="color: var(--text-light);"><?php echo formatDate($img['created_at']); ?></span>
                    </div>
                    <div class="admin-gallery-actions">
                        <a href="<?php echo e($img['cdn_url'] ?: $img['raw_url']); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-secondary">查看</a>
                        <button class="btn btn-sm btn-secondary" data-copy-url="<?php echo e($img['cdn_url'] ?: $img['raw_url']); ?>">复制链接</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- 分页 -->
        <?php if ($totalPages > 1): ?>
        <div style="margin-top: 24px;">
            <?php
            $urlPattern = 'gallery.php?' . ($filterUser ? 'user=' . urlencode($filterUser) . '&' : '') . 'page=%d';
            echo pagination($page, $totalPages, $urlPattern);
            ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.admin-gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
}
.admin-gallery-item {
    border-radius: var(--radius);
    overflow: hidden;
    border: 1px solid var(--border-color);
    background: var(--card-bg);
}
.admin-gallery-img-wrapper {
    aspect-ratio: 1;
    overflow: hidden;
    background: var(--bg-subtle);
    display: flex;
    align-items: center;
    justify-content: center;
}
.admin-gallery-img-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.admin-gallery-info {
    padding: 12px;
}
.admin-gallery-filename {
    font-size: 0.85rem;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 6px;
}
.admin-gallery-meta {
    font-size: 0.75rem;
    color: var(--text-light);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.admin-gallery-user {
    background: var(--primary-color);
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
}
.admin-gallery-actions {
    margin-top: 10px;
    display: flex;
    gap: 8px;
}
.admin-gallery-actions .btn {
    flex: 1;
    font-size: 0.75rem;
    padding: 6px 8px;
}
</style>

<script src="/assets/js/admin/admin-gallery.js?v=<?php echo LM_VERSION; ?>"></script>

<?php
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

require_once LM_ROOT . '/admin/template/footer.php';
?>
