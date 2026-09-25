<?php
/**
 * 用户资料编辑
 */
define('LM_ROOT', __DIR__);

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
Security::setSecurityHeaders();
requireLogin();

$pageTitle = '编辑资料';
$currentPage = 'profile';

$user = currentUser();
if (!$user) {
    Security::redirect('/login.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST[CSRF_TOKEN_NAME] ?? '');
    if (!Security::validateToken($token)) {
        $error = '安全验证失败，请刷新页面重试';
    } elseif (!Security::checkRateLimit($user['id'], 'profile_update', 10, 600)) {
        // 限速：每用户 10 次/10 分钟，防头像上传等耗 CPU 操作被刷
        $error = '操作过于频繁，请稍后再试';
    } else {
        $nickname = trim($_POST['nickname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $avatar = trim($_POST['avatar'] ?? '');

        if (empty($nickname)) {
            $error = '请填写昵称';
        } elseif (empty($email)) {
            $error = '请填写邮箱';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '邮箱格式不正确';
        } elseif ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
            $error = '个人网站地址格式不正确';
        } elseif ($avatar !== '' && Security::sanitizeUrl($avatar) === '#') {
            // 仅允许 http/https 协议，阻止 javascript:/data: 等
            $error = '头像链接格式不正确';
        } else {
            // 处理头像上传
            if (isset($_FILES['avatar_upload']) && $_FILES['avatar_upload']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = saveUploadedImage($_FILES['avatar_upload'], 'avatar_');
                if ($uploadResult['success']) {
                    $avatar = $uploadResult['url'];
                } else {
                    $error = '头像上传失败：' . $uploadResult['message'];
                }
            }

            if (empty($error)) {
                try {
                    db()->update('lm_admin', [
                        'nickname' => Security::xssClean($nickname),
                        'email' => Security::xssClean($email),
                        'website' => $website ? Security::xssClean($website) : null,
                        'bio' => Security::xssClean($bio),
                        'avatar' => $avatar ? Security::xssClean($avatar) : null
                    ], 'id = ?', [$user['id']]);

                    // 刷新 session 显示
                    $_SESSION['username'] = $nickname;
                    $success = '资料已更新';

                    // 重新加载用户
                    $user = currentUser();
                } catch (Exception $e) {
                    // 不向用户暴露 SQL 错误细节
                    error_log('Profile update failed for user ' . $user['id'] . ': ' . $e->getMessage());
                    $error = '保存失败，请稍后重试';
                }
            }
        }
    }
}

require_once LM_ROOT . '/template/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> 编辑个人资料</div>
    </div>
    <div class="card-body">
        <?php if ($success): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" data-validate>
            <?php echo Security::csrfField(); ?>

            <div style="text-align: center; margin-bottom: 24px;">
                <img src="<?php echo e($user['avatar'] ?: '/assets/images/default-avatar.png'); ?>" alt="" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin-bottom: 12px;">
            </div>

            <div class="form-group">
                <label class="form-label">昵称 *</label>
                <input type="text" name="nickname" class="form-input" required
                       value="<?php echo e($user['nickname'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">邮箱 *</label>
                <input type="email" name="email" class="form-input" required
                       value="<?php echo e($user['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">个人网站</label>
                <input type="url" name="website" class="form-input" placeholder="https://example.com"
                       value="<?php echo e($user['website'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">头像</label>
                <div style="display: flex; gap: 12px; align-items: flex-start;">
                    <div style="flex: 1;">
                        <input type="text" name="avatar" class="form-input" placeholder="头像图片链接或上传"
                               value="<?php echo e($user['avatar'] ?? ''); ?>">
                        <div class="form-hint">支持外部图片链接或本地上传</div>
                    </div>
                    <div>
                        <input type="file" name="avatar_upload" class="form-input" accept="image/*" style="padding: 8px;">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">个人简介</label>
                <textarea name="bio" class="form-textarea" placeholder="介绍一下自己..." style="min-height: 120px;"><?php echo e($user['bio'] ?? ''); ?></textarea>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary">保存资料</button>
                <a href="/user.php?id=<?php echo (int)$user['id']; ?>" class="btn btn-secondary">查看主页</a>
            </div>
        </form>
    </div>
</div>

<?php require_once LM_ROOT . '/template/sidebar.php'; ?>
