<?php
/**
 * 说说管理
 */
define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/includes/config.php';
require_once APP_ROOT . '/includes/Security.php';
require_once APP_ROOT . '/includes/Database.php';
require_once APP_ROOT . '/includes/functions.php';

session_start();
requireAdmin();

$pageTitle = '说说管理';
$currentPage = 'shuoshuo';

$error = '';
$success = '';

// 发布、编辑、删除前确保旧环境也能自动补齐说说表。
ensureShuoshuoTables();

// 处理删除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_shuoshuo' && isset($_POST['id'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        die('CSRF验证失败');
    }

    $id = (int)$_POST['id'];
    try {
        db()->delete('app_shuoshuo', 'id = ?', [$id]);
        $success = '说说已删除';
    } catch (Exception $e) {
        $error = '删除失败';
    }
}

// 处理添加/编辑
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_shuoshuo') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        $error = 'CSRF验证失败';
    } else {
        $content = trim($_POST['content'] ?? '');
        $mood = trim($_POST['mood'] ?? '');
        $imagesRaw = trim($_POST['images'] ?? '');
        $status = isset($_POST['status']) ? 1 : 0;
        $createdAt = trim($_POST['created_at'] ?? '');
        $id = (int)($_POST['shuoshuo_id'] ?? 0);

        if ($content === '' && $imagesRaw === '') {
            $error = '内容和图片至少填写一项';
        } else {
            // 处理图片上传（上传文件会追加到图片列表）
            if (!empty($_FILES['image_file']['tmp_name'])) {
                $upload = saveUploadedImage($_FILES['image_file'], 'shuoshuo_');
                if (!$upload['success']) {
                    $error = '图片上传失败：' . $upload['message'];
                } else {
                    $imagesRaw = trim($imagesRaw . "\n" . $upload['url']);
                }
            }

            // 统一校验并规范化图片 URL 列表（每行一个）
            $imageUrls = [];
            foreach (preg_split('/\r\n|\r|\n/', $imagesRaw) as $line) {
                $line = trim($line);
                if ($line === '') continue;
                if (!isValidImageUrl($line)) {
                    $error = '图片地址格式不正确：' . $line;
                    break;
                }
                $imageUrls[] = $line;
            }

            if (empty($error)) {
                // 发布时间：留空用当前时间，填写则校验格式
                $createdAtValue = null;
                if ($createdAt !== '') {
                    $parsed = strtotime($createdAt);
                    if ($parsed === false) {
                        $error = '发布时间格式不正确';
                    } else {
                        $createdAtValue = date('Y-m-d H:i:s', $parsed);
                    }
                }

                if (empty($error)) {
                    try {
                        $data = [
                            'content' => Security::xssClean($content),
                            'mood' => Security::xssClean(mb_substr($mood, 0, 30)),
                            'images' => Security::xssClean(implode("\n", $imageUrls)),
                            'status' => $status
                        ];
                        if ($createdAtValue !== null) {
                            $data['created_at'] = $createdAtValue;
                        }

                        if ($id > 0) {
                            db()->update('app_shuoshuo', $data, 'id = ?', [$id]);
                            $success = '说说已更新';
                        } else {
                            db()->insert('app_shuoshuo', $data);
                            $success = '说说已发布';
                        }
                    } catch (Exception $e) {
                        $error = '保存失败: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// 获取全部说说（包含隐藏）
$allShuoshuo = getAllShuoshuo();

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
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/><path d="M8 12h.01"/><path d="M12 12h.01"/><path d="M16 12h.01"/></svg>发布/编辑说说</div>
    </div>
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data" data-validate>
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="save_shuoshuo">
            <input type="hidden" name="shuoshuo_id" id="shuoshuo_id" value="0">

            <div class="form-group">
                <label class="form-label">内容 *</label>
                <textarea name="content" class="form-input" rows="4" placeholder="这一刻的想法..." required id="shuoshuo_content"></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">心情（可选）</label>
                    <input type="text" name="mood" class="form-input" placeholder="例如：开心、摸鱼中 🐟" id="shuoshuo_mood" maxlength="30">
                </div>
                <div class="form-group">
                    <label class="form-label">发布时间（可选）</label>
                    <input type="text" name="created_at" class="form-input" placeholder="留空为当前时间，如 <?php echo date('Y-m-d H:i'); ?>" id="shuoshuo_created_at">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">图片地址（每行一个，可选）</label>
                <textarea name="images" class="form-input" rows="3" placeholder="https://... 直链，每行一个，可留空" id="shuoshuo_images"></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">上传本地图片（追加到列表）</label>
                <input type="file" name="image_file" class="form-input" accept="image/*" id="shuoshuo_image_file">
                <div class="form-hint">上传后会追加到图片列表末尾</div>
            </div>

            <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="status" id="shuoshuo_status" checked style="width: auto;">
                <label for="shuoshuo_status" style="margin-bottom: 0;">公开展示</label>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary" id="shuoshuo_submit_btn">发布说说</button>
                <button type="button" class="btn btn-secondary" id="reset-shuoshuo-form">重置</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>说说列表（<?php echo count($allShuoshuo); ?> 条）</div>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>内容</th>
                    <th>图片</th>
                    <th>心情</th>
                    <th>发布时间</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allShuoshuo as $s): ?>
                <?php $sImages = parseShuoshuoImages($s['images']); ?>
                <tr>
                    <td><?php echo $s['id']; ?></td>
                    <td style="max-width: 320px;"><?php echo e(truncate($s['content'] ?: '（仅图片）', 40)); ?></td>
                    <td>
                        <?php if (!empty($sImages)): ?>
                        <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                            <?php foreach (array_slice($sImages, 0, 3) as $img): ?>
                            <img src="<?php echo e($img); ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;" loading="lazy">
                            <?php endforeach; ?>
                            <?php if (count($sImages) > 3): ?>
                            <span style="font-size: 12px; color: var(--text-light, #999); align-self: center;">+<?php echo count($sImages) - 3; ?></span>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                    <td><?php echo $s['mood'] ? e($s['mood']) : '-'; ?></td>
                    <td><?php echo e($s['created_at']); ?></td>
                    <td>
                        <span class="badge <?php echo $s['status'] ? 'badge-success' : 'badge-danger'; ?>">
                            <?php echo $s['status'] ? '公开' : '隐藏'; ?>
                        </span>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary"
                                data-edit-shuoshuo-id="<?php echo (int)$s['id']; ?>"
                                data-edit-shuoshuo-content="<?php echo e($s['content']); ?>"
                                data-edit-shuoshuo-images="<?php echo e(implode("\n", $sImages)); ?>"
                                data-edit-shuoshuo-mood="<?php echo e($s['mood']); ?>"
                                data-edit-shuoshuo-status="<?php echo (int)$s['status']; ?>">编辑</button>
                        <form method="POST" action="" class="form-delete-shuoshuo" style="display: inline;">
                            <?php echo Security::csrfField(); ?>
                            <input type="hidden" name="action" value="delete_shuoshuo">
                            <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger" data-confirm="确定要删除这条说说吗？">删除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($allShuoshuo)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-light); padding: 40px;">暂无说说，发布第一条吧</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script nonce="<?php echo Security::cspNonce(); ?>">
(function () {
    var idInput = document.getElementById('shuoshuo_id');
    var contentInput = document.getElementById('shuoshuo_content');
    var imagesInput = document.getElementById('shuoshuo_images');
    var moodInput = document.getElementById('shuoshuo_mood');
    var statusInput = document.getElementById('shuoshuo_status');
    var submitBtn = document.getElementById('shuoshuo_submit_btn');

    document.querySelectorAll('[data-edit-shuoshuo-id]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            idInput.value = btn.getAttribute('data-edit-shuoshuo-id');
            contentInput.value = btn.getAttribute('data-edit-shuoshuo-content') || '';
            imagesInput.value = btn.getAttribute('data-edit-shuoshuo-images') || '';
            moodInput.value = btn.getAttribute('data-edit-shuoshuo-mood') || '';
            statusInput.checked = btn.getAttribute('data-edit-shuoshuo-status') === '1';
            submitBtn.textContent = '保存修改';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    var resetBtn = document.getElementById('reset-shuoshuo-form');
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            idInput.value = '0';
            contentInput.value = '';
            imagesInput.value = '';
            moodInput.value = '';
            statusInput.checked = true;
            submitBtn.textContent = '发布说说';
        });
    }
})();
</script>

<?php
// 内容变更成功后清空静态页面缓存：首页/归档/标签/关于/友链都依赖这些数据，
// 否则访客最长会看到 TTL（默认 300 秒）的旧内容。
if ($success !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    app_purge_page_cache();
}
?>
<?php require_once APP_ROOT . '/admin/template/footer.php'; ?>
