<?php
/**
 * 游戏管理
 */
define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
requireAdmin();

$pageTitle = '游戏管理';
$currentPage = 'games';

$error = '';
$success = '';

// 处理删除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_game' && isset($_POST['id'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        die('CSRF验证失败');
    }

    $id = (int)$_POST['id'];
    try {
        db()->delete('lm_game', 'id = ?', [$id]);
        $success = '游戏已删除';
    } catch (Exception $e) {
        $error = '删除失败';
    }
}

// 处理添加/编辑
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_game') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        $error = 'CSRF验证失败';
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $imageUrl = trim($_POST['image_url'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;
        $gameId = isset($_POST['game_id']) ? (int)$_POST['game_id'] : 0;

        if (empty($name)) {
            $error = '请填写游戏名称';
        } else {
            // 处理图片上传（上传文件优先于 URL 字段）
            if (!empty($_FILES['image_file']['tmp_name'])) {
                $upload = saveUploadedImage($_FILES['image_file'], 'game_');
                if (!$upload['success']) {
                    $error = '图片上传失败：' . $upload['message'];
                } else {
                    $imageUrl = $upload['url'];
                }
            }

            // 校验图片 URL 格式（非空时）
            if (empty($error) && $imageUrl !== '' && !isValidImageUrl($imageUrl)) {
                $error = '图片地址格式不正确';
            }

            if (empty($error)) {
                try {
                    $data = [
                        'name' => Security::xssClean($name),
                        'description' => Security::xssClean($description),
                        'image_url' => Security::xssClean($imageUrl),
                        'sort_order' => $sortOrder,
                        'status' => $status
                    ];

                    if ($gameId > 0) {
                        db()->update('lm_game', $data, 'id = ?', [$gameId]);
                        $success = '游戏已更新';
                    } else {
                        db()->insert('lm_game', $data);
                        $success = '游戏已添加';
                    }
                } catch (Exception $e) {
                    $error = '保存失败: ' . $e->getMessage();
                }
            }
        }
    }
}

// 获取全部游戏（包含隐藏）
$allGames = getAllGames();

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
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M5 12h14"/><path d="M12 5v14"/></svg>添加/编辑游戏</div>
    </div>
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data" data-validate>
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="save_game">
            <input type="hidden" name="game_id" id="game_id" value="0">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">游戏名称 *</label>
                    <input type="text" name="name" class="form-input" placeholder="游戏名称" required id="game_name">
                </div>

                <div class="form-group">
                    <label class="form-label">排序</label>
                    <input type="number" name="sort_order" class="form-input" value="0" id="game_sort">
                    <div class="form-hint">数字越大越靠前</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">游戏简介</label>
                <textarea name="description" class="form-input" rows="3" placeholder="简短介绍该游戏" id="game_desc"></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">图片地址</label>
                    <input type="text" name="image_url" class="form-input" placeholder="https://... 直链，或下方本地上传" id="game_image_url">
                </div>

                <div class="form-group">
                    <label class="form-label">上传本地图片</label>
                    <input type="file" name="image_file" class="form-input" accept="image/*" id="game_image_file">
                    <div class="form-hint">上传文件会覆盖上方图片地址</div>
                </div>
            </div>

            <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="status" id="game_status" checked style="width: auto;">
                <label for="game_status" style="margin-bottom: 0;">显示该游戏</label>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary" id="game_submit_btn">添加游戏</button>
                <button type="button" class="btn btn-secondary" id="reset-game-form">重置</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><line x1="6" y1="11" x2="10" y2="11"/><line x1="8" y1="9" x2="8" y2="13"/><line x1="15" y1="12" x2="15.01" y2="12"/><line x1="18" y1="10" x2="18.01" y2="10"/><path d="M17.32 5H6.68a4 4 0 0 0-3.978 3.59c-.006.052-.01.101-.017.152C2.604 9.416 2 14.456 2 16a3 3 0 0 0 3 3c1 0 1.5-.5 2-1l1.414-1.414A2 2 0 0 1 9.828 16h4.344a2 2 0 0 1 1.414.586L17 18c.5.5 1 1 2 1a3 3 0 0 0 3-3c0-1.545-.604-6.584-.685-7.258-.007-.05-.011-.1-.017-.152A4 4 0 0 0 17.32 5z"/></svg>游戏列表</div>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>缩略图</th>
                    <th>名称</th>
                    <th>简介</th>
                    <th>排序</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allGames as $g): ?>
                <tr>
                    <td><?php echo $g['id']; ?></td>
                    <td>
                        <?php if (!empty($g['image_url'])): ?>
                        <img src="<?php echo e($g['image_url']); ?>" alt="" class="game-sw-thumb" loading="lazy">
                        <?php else: ?>
                        <div class="game-sw-thumb game-sw-thumb-placeholder"><?php echo e(mb_substr($g['name'], 0, 1)); ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo e($g['name']); ?></td>
                    <td><?php echo e(truncate($g['description'] ?: '-', 30)); ?></td>
                    <td><?php echo $g['sort_order']; ?></td>
                    <td>
                        <span class="badge <?php echo $g['status'] ? 'badge-success' : 'badge-danger'; ?>">
                            <?php echo $g['status'] ? '显示' : '隐藏'; ?>
                        </span>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary"
                                data-edit-game-id="<?php echo (int)$g['id']; ?>"
                                data-edit-game-name="<?php echo e($g['name']); ?>"
                                data-edit-game-desc="<?php echo e($g['description']); ?>"
                                data-edit-game-image="<?php echo e($g['image_url']); ?>"
                                data-edit-game-sort="<?php echo (int)$g['sort_order']; ?>"
                                data-edit-game-status="<?php echo (int)$g['status']; ?>">编辑</button>
                        <form method="POST" action="" class="form-delete-game" style="display: inline;">
                            <?php echo Security::csrfField(); ?>
                            <input type="hidden" name="action" value="delete_game">
                            <input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger" data-confirm="确定要删除该游戏吗？">删除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($allGames)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-light); padding: 40px;">暂无游戏，请添加</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="/assets/js/admin/admin-games.js?v=<?php echo LM_VERSION; ?>"></script>

<?php require_once LM_ROOT . '/admin/template/footer.php'; ?>
