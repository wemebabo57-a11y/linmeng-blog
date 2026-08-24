<?php
/**
 * 镜像软件管理
 * 支持：分区管理 + 软件管理（图标URL、下载URL、官方链接、介绍、发布）
 */
define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
requireAdmin();

ensureMirrorTables();

$pageTitle = '镜像软件';
$currentPage = 'mirror';

$error = '';
$success = '';
$activeTab = isset($_GET['tab']) && $_GET['tab'] === 'category' ? 'category' : 'software';

/* ============== 处理删除 ============== */
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && isset($_GET['type'])) {
    $token = $_GET['token'] ?? '';
    if (!Security::validateToken($token)) {
        $error = 'CSRF验证失败';
    } else {
        $id = (int)$_GET['id'];
        $type = $_GET['type'];
        try {
            if ($type === 'category') {
                $cnt = db()->fetchColumn("SELECT COUNT(*) FROM lm_mirror_software WHERE category_id = ?", [$id]);
                if ($cnt > 0) {
                    $error = '该分区下还有软件，无法删除。请先移动或删除软件。';
                } else {
                    db()->delete('lm_mirror_category', 'id = ?', [$id]);
                    $success = '分区已删除';
                }
            } elseif ($type === 'software') {
                db()->delete('lm_mirror_software', 'id = ?', [$id]);
                $success = '软件已删除';
            }
        } catch (Exception $e) {
            $error = '删除失败: ' . $e->getMessage();
        }
    }
}

/* ============== 处理分区保存 ============== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_category') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        $error = 'CSRF验证失败';
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;

        if (empty($name)) {
            $error = '请填写分区名称';
        } else {
            try {
                $data = [
                    'name' => Security::xssClean($name),
                    'description' => Security::xssClean($description),
                    'icon' => Security::xssClean($icon),
                    'sort_order' => $sortOrder,
                    'status' => $status
                ];

                if ($categoryId > 0) {
                    db()->update('lm_mirror_category', $data, 'id = ?', [$categoryId]);
                    $success = '分区已更新';
                } else {
                    db()->insert('lm_mirror_category', $data);
                    $success = '分区已创建';
                }
                $activeTab = 'category';
            } catch (Exception $e) {
                $error = '保存失败: ' . $e->getMessage();
            }
        }
    }
}

/* ============== 处理软件保存 ============== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_software') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        $error = 'CSRF验证失败';
    } else {
        $name = trim($_POST['name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $iconUrl = trim($_POST['icon_url'] ?? '');
        $downloadUrl = trim($_POST['download_url'] ?? '');
        $officialUrl = trim($_POST['official_url'] ?? '');
        $version = trim($_POST['version'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;
        $softwareId = isset($_POST['software_id']) ? (int)$_POST['software_id'] : 0;

        if (empty($name)) {
            $error = '请填写软件名称';
        } elseif (!empty($iconUrl) && !filter_var($iconUrl, FILTER_VALIDATE_URL) && strpos($iconUrl, '/') !== 0) {
            $error = '图标 URL 格式不正确';
        } elseif (!empty($downloadUrl) && !filter_var($downloadUrl, FILTER_VALIDATE_URL) && strpos($downloadUrl, '/') !== 0) {
            $error = '下载 URL 格式不正确';
        } elseif (!empty($officialUrl) && !filter_var($officialUrl, FILTER_VALIDATE_URL)) {
            $error = '官方链接格式不正确';
        } else {
            // 处理图标上传
            if (!empty($_FILES['icon_file']['tmp_name'])) {
                $upload = saveUploadedImage($_FILES['icon_file'], 'mirror_');
                if (!$upload['success']) {
                    $error = '图标上传失败：' . $upload['message'];
                } else {
                    $iconUrl = $upload['url'];
                }
            }

            if (empty($error)) {
                try {
                    $data = [
                        'category_id' => $categoryId,
                        'name' => Security::xssClean($name),
                        'description' => Security::xssClean($description),
                        'icon_url' => Security::xssClean($iconUrl),
                        'download_url' => Security::xssClean($downloadUrl),
                        'official_url' => Security::xssClean($officialUrl),
                        'version' => Security::xssClean($version),
                        'sort_order' => $sortOrder,
                        'status' => $status
                    ];

                    if ($softwareId > 0) {
                        db()->update('lm_mirror_software', $data, 'id = ?', [$softwareId]);
                        $success = '软件已更新';
                    } else {
                        db()->insert('lm_mirror_software', $data);
                        $success = '软件已发布';
                    }
                } catch (Exception $e) {
                    $error = '保存失败: ' . $e->getMessage();
                }
            }
        }
        $activeTab = 'software';
    }
}

$categories = getMirrorCategories(false);
$softwares = getAllMirrorSoftwares();

require_once LM_ROOT . '/admin/template/header.php';
?>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo e($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error"><?php echo e($error); ?></div>
<?php endif; ?>

<!-- Tab 切换 -->
<div class="mirror-admin-tabs">
    <a href="?tab=software" class="mirror-admin-tab <?php echo $activeTab === 'software' ? 'active' : ''; ?>">软件管理</a>
    <a href="?tab=category" class="mirror-admin-tab <?php echo $activeTab === 'category' ? 'active' : ''; ?>">分区管理</a>
</div>

<?php if ($activeTab === 'category'): ?>
<!-- ==================== 分区管理 ==================== -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <div class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            添加/编辑分区
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="?tab=category" data-validate>
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="save_category">
            <input type="hidden" name="category_id" id="mcat_id" value="0">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">分区名称 *</label>
                    <input type="text" name="name" class="form-input" placeholder="例如：开发工具" required id="mcat_name">
                </div>

                <div class="form-group">
                    <label class="form-label">图标 URL（Emoji 或图片地址）</label>
                    <input type="text" name="icon" class="form-input" placeholder="如 🛠️ 或 https://example.com/icon.png" id="mcat_icon">
                    <div class="form-hint">推荐使用 Emoji 直接作为分区图标</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">分区描述</label>
                <input type="text" name="description" class="form-input" placeholder="一句话描述这个分区" id="mcat_desc">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">排序</label>
                    <input type="number" name="sort_order" class="form-input" value="0" id="mcat_sort">
                    <div class="form-hint">数字越大越靠前</div>
                </div>

                <div class="form-group" style="display: flex; align-items: flex-end; gap: 8px; padding-bottom: 8px;">
                    <input type="checkbox" name="status" id="mcat_status" checked style="width: auto;">
                    <label for="mcat_status" style="margin-bottom: 0;">显示该分区</label>
                </div>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary" id="mcat_submit_btn">添加分区</button>
                <button type="button" class="btn btn-secondary" id="reset-mcat-form">重置</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>
            分区列表
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>图标</th>
                    <th>名称</th>
                    <th>描述</th>
                    <th>软件数</th>
                    <th>排序</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                <tr>
                    <td><?php echo (int)$cat['id']; ?></td>
                    <td class="mirror-cat-icon-cell">
                        <?php if ($cat['icon']): ?>
                            <?php if (preg_match('#^https?://#i', $cat['icon'])): ?>
                            <img src="<?php echo e($cat['icon']); ?>" alt="" class="mirror-cat-thumb">
                            <?php else: ?>
                            <span class="mirror-cat-emoji"><?php echo e($cat['icon']); ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="mirror-cat-emoji">📁</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo e($cat['name']); ?></td>
                    <td><?php echo e(truncate($cat['description'] ?: '-', 30)); ?></td>
                    <td>
                        <span class="badge <?php echo $cat['software_count'] > 0 ? 'badge-primary' : 'badge-secondary'; ?>">
                            <?php echo (int)$cat['software_count']; ?> 个
                        </span>
                    </td>
                    <td><?php echo (int)$cat['sort_order']; ?></td>
                    <td>
                        <span class="badge <?php echo $cat['status'] ? 'badge-success' : 'badge-danger'; ?>">
                            <?php echo $cat['status'] ? '显示' : '隐藏'; ?>
                        </span>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary"
                                data-edit-mcat-id="<?php echo (int)$cat['id']; ?>"
                                data-edit-mcat-name="<?php echo e($cat['name']); ?>"
                                data-edit-mcat-desc="<?php echo e($cat['description']); ?>"
                                data-edit-mcat-icon="<?php echo e($cat['icon']); ?>"
                                data-edit-mcat-sort="<?php echo (int)$cat['sort_order']; ?>"
                                data-edit-mcat-status="<?php echo (int)$cat['status']; ?>">编辑</button>
                        <a href="?action=delete&type=category&id=<?php echo (int)$cat['id']; ?>&tab=category&token=<?php echo Security::generateToken(); ?>"
                           class="btn btn-sm btn-danger"
                           data-confirm="确定要删除该分区吗？">删除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($categories)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--text-light); padding: 40px;">暂无分区，请先创建一个分区</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<!-- ==================== 软件管理 ==================== -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <div class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            添加/编辑软件
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="?tab=software" enctype="multipart/form-data" data-validate>
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="save_software">
            <input type="hidden" name="software_id" id="msw_id" value="0">

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">软件名称 *</label>
                    <input type="text" name="name" class="form-input" placeholder="例如：VS Code" required id="msw_name">
                </div>

                <div class="form-group">
                    <label class="form-label">所属分区</label>
                    <select name="category_id" class="form-select" id="msw_cat">
                        <option value="0">— 未分类 —</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo (int)$cat['id']; ?>"><?php echo e($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">软件介绍</label>
                <textarea name="description" class="form-input" rows="3" placeholder="简单描述软件功能、用途或镜像说明..." id="msw_desc"></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">图标 URL</label>
                    <input type="text" name="icon_url" class="form-input" placeholder="https://example.com/icon.png" id="msw_icon_url">
                    <div class="form-hint">填写图标直链，或下方上传本地图标</div>
                </div>

                <div class="form-group">
                    <label class="form-label">上传本地图标</label>
                    <input type="file" name="icon_file" class="form-input" accept="image/jpeg,image/png,image/gif,image/webp" id="msw_icon_file">
                    <div class="form-hint">jpg/png/gif/webp，≤5MB。上传后将覆盖 URL</div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">下载 URL *</label>
                    <input type="text" name="download_url" class="form-input" placeholder="https://mirror.example.com/file.zip" required id="msw_dl">
                </div>

                <div class="form-group">
                    <label class="form-label">官方链接</label>
                    <input type="url" name="official_url" class="form-input" placeholder="https://code.visualstudio.com/" id="msw_official">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">版本号</label>
                    <input type="text" name="version" class="form-input" placeholder="如 1.0.0（可选）" id="msw_version">
                </div>

                <div class="form-group">
                    <label class="form-label">排序</label>
                    <input type="number" name="sort_order" class="form-input" value="0" id="msw_sort">
                    <div class="form-hint">数字越大越靠前</div>
                </div>

                <div class="form-group" style="display: flex; align-items: flex-end; gap: 8px; padding-bottom: 8px;">
                    <input type="checkbox" name="status" id="msw_status" checked style="width: auto;">
                    <label for="msw_status" style="margin-bottom: 0;">发布（显示）</label>
                </div>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary" id="msw_submit_btn">发布软件</button>
                <button type="button" class="btn btn-secondary" id="reset-msw-form">重置</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
            软件列表
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>图标</th>
                    <th>名称</th>
                    <th>分区</th>
                    <th>下载/官方</th>
                    <th>版本</th>
                    <th>浏览</th>
                    <th>排序</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($softwares as $sw): ?>
                <tr>
                    <td><?php echo (int)$sw['id']; ?></td>
                    <td>
                        <?php if ($sw['icon_url']): ?>
                        <img src="<?php echo e($sw['icon_url']); ?>" alt="" class="mirror-sw-thumb" loading="lazy">
                        <?php else: ?>
                        <div class="mirror-sw-thumb mirror-sw-thumb-placeholder"><?php echo mb_substr($sw['name'], 0, 1); ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo e($sw['name']); ?></td>
                    <td>
                        <?php if (!empty($sw['category_name'])): ?>
                        <span class="badge badge-secondary"><?php echo e($sw['category_name']); ?></span>
                        <?php else: ?>
                        <span class="badge badge-secondary">未分类</span>
                        <?php endif; ?>
                    </td>
                    <td class="mirror-sw-links-cell">
                        <?php if ($sw['download_url']): ?>
                        <a href="<?php echo e($sw['download_url']); ?>" target="_blank" rel="noopener" title="下载">下载</a>
                        <?php endif; ?>
                        <?php if ($sw['official_url']): ?>
                        <a href="<?php echo e($sw['official_url']); ?>" target="_blank" rel="noopener" title="官网">官网</a>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $sw['version'] ? e($sw['version']) : '-'; ?></td>
                    <td><?php echo (int)$sw['views']; ?></td>
                    <td><?php echo (int)$sw['sort_order']; ?></td>
                    <td>
                        <span class="badge <?php echo $sw['status'] ? 'badge-success' : 'badge-danger'; ?>">
                            <?php echo $sw['status'] ? '已发布' : '草稿'; ?>
                        </span>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary"
                                data-edit-msw-id="<?php echo (int)$sw['id']; ?>"
                                data-edit-msw-name="<?php echo e($sw['name']); ?>"
                                data-edit-msw-cat="<?php echo (int)$sw['category_id']; ?>"
                                data-edit-msw-desc="<?php echo e($sw['description'] ?? ''); ?>"
                                data-edit-msw-icon="<?php echo e($sw['icon_url']); ?>"
                                data-edit-msw-dl="<?php echo e($sw['download_url']); ?>"
                                data-edit-msw-official="<?php echo e($sw['official_url']); ?>"
                                data-edit-msw-version="<?php echo e($sw['version']); ?>"
                                data-edit-msw-sort="<?php echo (int)$sw['sort_order']; ?>"
                                data-edit-msw-status="<?php echo (int)$sw['status']; ?>">编辑</button>
                        <a href="?action=delete&type=software&id=<?php echo (int)$sw['id']; ?>&tab=software&token=<?php echo Security::generateToken(); ?>"
                           class="btn btn-sm btn-danger"
                           data-confirm="确定要删除该软件吗？">删除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($softwares)): ?>
                <tr>
                    <td colspan="10" style="text-align: center; color: var(--text-light); padding: 40px;">暂无软件，请添加并发布</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script src="/assets/js/admin/admin-mirror.js?v=<?php echo LM_VERSION; ?>"></script>

<?php require_once LM_ROOT . '/admin/template/footer.php'; ?>
