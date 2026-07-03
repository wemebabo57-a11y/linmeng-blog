<?php
/**
 * 友链申请页面
 */
define('LM_ROOT', __DIR__);

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
Security::setSecurityHeaders();

$pageTitle = '友链申请';
$currentPage = 'link-apply';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST[CSRF_TOKEN_NAME] ?? '');
    if (!Security::validateToken($token)) {
        $error = '安全验证失败，请刷新页面重试';
    } elseif (!Security::checkRateLimit(Security::getClientIp(), 'link_apply', 3, 3600)) {
        // 限速：每 IP 3 次/小时，防申请队列被刷
        $error = '提交过于频繁，请稍后再试';
    } else {
        $siteName = mb_substr(trim($_POST['site_name'] ?? ''), 0, 50);
        $siteUrl = mb_substr(trim($_POST['site_url'] ?? ''), 0, 200);
        $siteDescription = mb_substr(trim($_POST['site_description'] ?? ''), 0, 200);
        $email = mb_substr(trim($_POST['email'] ?? ''), 0, 100);

        if (empty($siteName) || empty($siteUrl) || empty($email)) {
            $error = '请填写必填项';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '邮箱格式不正确';
        } elseif (!filter_var($siteUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($siteUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            // 强制 http/https 协议，阻止 ftp:/javascript: 等
            $error = '网站地址格式不正确（仅支持 http/https）';
        } elseif (strlen($siteName) > 50) {
            $error = '网站名称不能超过50个字符';
        } else {
            try {
                // 检查是否已存在相同的申请
                $exists = db()->fetchColumn(
                    "SELECT COUNT(*) FROM lm_link_apply WHERE site_url = ? AND status = 'pending'",
                    [$siteUrl]
                );
                
                if ($exists) {
                    $error = '该网站已有待处理的申请，请勿重复提交';
                } else {
                    db()->insert('lm_link_apply', [
                        'site_name' => Security::xssClean($siteName),
                        'site_url' => Security::xssClean($siteUrl),
                        'site_description' => Security::xssClean($siteDescription),
                        'email' => Security::xssClean($email),
                        'ip' => Security::getClientIp(),
                        'status' => 'pending'
                    ]);
                    
                    $success = '友链申请已提交，管理员审核通过后会显示在侧边栏中';
                    $_POST = [];
                }
            } catch (Exception $e) {
                $error = '提交失败，请稍后重试';
            }
        }
    }
}

require_once LM_ROOT . '/template/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg> 友链申请</div>
    </div>
    <div class="card-body">
        <div class="info-box" style="background: var(--bg-color); padding: 20px; border-radius: var(--radius); margin-bottom: 24px;">
            <h3 style="margin-bottom: 12px;">申请须知</h3>
            <ul style="padding-left: 20px; color: var(--text-light);">
                <li>请确保您的网站内容健康、合法</li>
                <li>申请前请先将本站链接添加到您的网站</li>
                <li>本站信息：林梦的博客 (<?php echo e(SITE_URL); ?>)</li>
                <li>审核通过后，您的网站将显示在侧边栏友链区域</li>
            </ul>
        </div>
        
        <?php if ($success): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" data-validate class="link-apply-form">
            <?php echo Security::csrfField(); ?>
            
            <div class="form-group">
                <label class="form-label">网站名称 *</label>
                <input type="text" name="site_name" class="form-input" placeholder="您的网站名称" required
                       value="<?php echo isset($_POST['site_name']) ? e($_POST['site_name']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">网站地址 *</label>
                <input type="url" name="site_url" class="form-input" placeholder="https://example.com" required
                       value="<?php echo isset($_POST['site_url']) ? e($_POST['site_url']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">网站描述</label>
                <input type="text" name="site_description" class="form-input" placeholder="简要描述您的网站（选填）"
                       value="<?php echo isset($_POST['site_description']) ? e($_POST['site_description']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">联系邮箱 *</label>
                <input type="email" name="email" class="form-input" placeholder="your@email.com" required
                       value="<?php echo isset($_POST['email']) ? e($_POST['email']) : ''; ?>">
            </div>
            
            <button type="submit" class="btn btn-primary">提交申请</button>
        </form>
    </div>
</div>

<?php require_once LM_ROOT . '/template/sidebar.php'; ?>
