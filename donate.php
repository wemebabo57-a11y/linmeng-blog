<?php
define('LM_ROOT', __DIR__);

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
Security::setSecurityHeaders();

$pageTitle = '捐赠页';
$currentPage = 'donate';

$donateTitle = getSetting('donate_title', '捐赠页');
$donateDescription = getSetting('donate_description', '如果这个网站对你有帮助，可以自愿捐赠。');
$alipayQrcode = getSetting('donate_alipay_qrcode', '');
$wechatQrcode = getSetting('donate_wechat_qrcode', '');

require_once LM_ROOT . '/template/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title">捐赠页</div>
    </div>
    <div class="card-body" style="text-align: center;">
        <h1 style="font-size: 1.5rem; margin-bottom: 12px;"><?php echo e($donateTitle); ?></h1>
        <?php if ($donateDescription): ?>
        <p style="color: var(--text-secondary); margin-bottom: 28px; line-height: 1.8;"><?php echo nl2br(e($donateDescription)); ?></p>
        <?php endif; ?>

        <?php if ($alipayQrcode || $wechatQrcode): ?>
        <div class="donate-qrcode-wrapper">
            <?php if ($alipayQrcode): ?>
            <div class="donate-qrcode-item">
                <img src="<?php echo e($alipayQrcode); ?>" alt="支付宝收款码" loading="lazy">
                <div class="donate-qrcode-label">支付宝</div>
            </div>
            <?php endif; ?>

            <?php if ($wechatQrcode): ?>
            <div class="donate-qrcode-item">
                <img src="<?php echo e($wechatQrcode); ?>" alt="微信收款码" loading="lazy">
                <div class="donate-qrcode-label">微信</div>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="empty-state" style="padding: 40px 20px;">
            <h3>暂未配置收款码</h3>
            <p>请在后台网站设置里填写收款码直链或上传图片。</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once LM_ROOT . '/template/sidebar.php'; ?>
