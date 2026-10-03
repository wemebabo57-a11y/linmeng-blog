<?php
/**
 * 网站设置 v2.0
 * 增加主题设置、更多功能开关
 */
define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/includes/config.php';
require_once APP_ROOT . '/includes/Security.php';
require_once APP_ROOT . '/includes/Database.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/Mailer.php';

session_start();
requireAdmin();

$pageTitle = '网站设置';
$currentPage = 'settings';

$error = '';
$success = '';
$smtpDiagnostics = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        $error = 'CSRF验证失败';
    } else {
        // 允许直接粘贴 "smtp.qq.com:465"、"ssl://smtp.qq.com" 这类写法：
        // 统一清洗并拆出端口，否则拼成 "ssl://ssl://host:465:465" 会在传输层就连接失败。
        $rawSmtpHost = trim((string)($_POST['smtp_host'] ?? ''));
        if ($rawSmtpHost !== '') {
            $parsedSmtpHost = AppMailer::parseHost($rawSmtpHost);
            if ($parsedSmtpHost['host'] !== '') {
                $_POST['smtp_host'] = $parsedSmtpHost['host'];
            }
            if (!empty($parsedSmtpHost['port'])) {
                $_POST['smtp_port'] = (string)$parsedSmtpHost['port'];
            }
        }

        $settings = [
            'site_name',
            'site_description',
            'site_keywords',
            'site_author',
            'site_email',
            'site_telegram',
            'site_icp',
            'site_icp_links',
            'site_footer',
            'admin_email',
            'github_url',
            'bilibili_url',
            'github_oauth_enabled',
            'github_client_id',
            'github_client_secret',
            'gitcode_oauth_enabled',
            'gitcode_client_id',
            'gitcode_client_secret',
            'gitcode_oauth_scope',
            'comment_need_approve',
            'site_start_date',
            'site_time_offset',
            'site_theme',
            'article_comment_enable',
            'guestbook_enable',
            'site_analytics',
            'site_cdn',
            'site_maintenance',
            'donate_title',
            'donate_description',
            'github_gallery_token',
            'github_gallery_repo',
            'github_gallery_branch',
            'gallery_max_size',
            'turnstile_login_enabled',
            'turnstile_guestbook_enabled',
            'turnstile_login_site_key',
            'turnstile_login_secret_key',
            'turnstile_guestbook_site_key',
            'turnstile_guestbook_secret_key',
            'geetest_captcha_id',
            'geetest_captcha_key',
            'site_background_position',
            'site_background_size',
            'site_background_overlay',
            'ai_summary_enabled',
            'ai_default_provider_id',
            'ai_summary_prompt',
            'weather_popup_enabled',
            'weather_city',
            'seniverse_public_key',
            'seniverse_private_key',
            'smtp_enabled',
            'smtp_host',
            'smtp_port',
            'smtp_encryption',
            'smtp_username',
            'smtp_password',
            'smtp_from_email',
            'smtp_from_name',
            'smtp_skip_verify',
            'notify_admin_email',
            'notify_link_apply',
            'notify_comment_reply',
        ];

        try {
            // 一键校准时间：根据管理员电脑时间计算偏移量
            $calibrated = false;
            if (isset($_POST['calibrate_time']) && is_numeric($_POST['calibrate_time'])) {
                $clientTimestampMs = (float)$_POST['calibrate_time'];
                $clientTimestamp = (int)round($clientTimestampMs / 1000);
                $serverTimestamp = time();
                $offset = $clientTimestamp - $serverTimestamp;
                setSetting('site_time_offset', (string)$offset);
                $success = '时间已校准为管理员电脑时间（偏移量：' . $offset . ' 秒）';
                $calibrated = true;
            }

            foreach ($settings as $key) {
                // SMTP 端口只允许有效范围，邮箱字段统一校验。
                if ($key === 'smtp_port') {
                    $port = (int)($_POST[$key] ?? 465);
                    if ($port < 1 || $port > 65535) {
                        $error = 'SMTP 端口必须在 1-65535 之间';
                        continue;
                    }
                }
                if ($key === 'smtp_encryption' && !in_array($_POST[$key] ?? '', ['ssl', 'tls', 'none'], true)) {
                    $error = 'SMTP 加密方式不合法';
                    continue;
                }
                if (in_array($key, ['smtp_from_email', 'notify_admin_email'], true)) {
                    $emailValue = trim($_POST[$key] ?? '');
                    if ($emailValue !== '' && !filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
                        $error = '邮箱格式不正确：' . $key;
                        continue;
                    }
                }

                // 一键校准时，避免表单中的旧偏移量覆盖刚刚计算出的值
                if ($calibrated && $key === 'site_time_offset') {
                    continue;
                }

                // GitHub Token 留空时保留原值，不回显
                if ($key === 'github_gallery_token') {
                    $value = isset($_POST[$key]) ? trim($_POST[$key]) : '';
                    if ($value !== '') {
                        setSetting($key, $value);
                    }
                    continue;
                }

                // Turnstile Secret Key 留空时保留原值，不回显
                if ($key === 'turnstile_login_secret_key' || $key === 'turnstile_guestbook_secret_key') {
                    $value = isset($_POST[$key]) ? trim($_POST[$key]) : '';
                    if ($value !== '') {
                        setSetting($key, $value);
                    }
                    continue;
                }

                if ($key === 'geetest_captcha_key') {
                    $value = isset($_POST[$key]) ? trim($_POST[$key]) : '';
                    if ($value !== '') {
                        setSetting($key, $value);
                    }
                    continue;
                }

                // GitHub OAuth Client Secret 留空时保留原值，不回显
                if ($key === 'github_client_secret') {
                    $value = isset($_POST[$key]) ? trim($_POST[$key]) : '';
                    if ($value !== '') {
                        setSetting($key, $value);
                    }
                    continue;
                }

                // GitCode OAuth Client Secret 留空时保留原值，不回显
                if ($key === 'gitcode_client_secret') {
                    $value = isset($_POST[$key]) ? trim($_POST[$key]) : '';
                    if ($value !== '') {
                        setSetting($key, $value);
                    }
                    continue;
                }

                // 心知天气私钥 留空时保留原值，不回显
                if ($key === 'seniverse_private_key') {
                    $value = isset($_POST[$key]) ? trim($_POST[$key]) : '';
                    if ($value !== '') {
                        setSetting($key, $value);
                    }
                    continue;
                }

                // SMTP 密码 留空时保留原值，不回显
                if ($key === 'smtp_password') {
                    $value = isset($_POST[$key]) ? trim($_POST[$key]) : '';
                    if ($value !== '') {
                        setSetting($key, $value);
                    }
                    continue;
                }

                // 统计代码白名单校验
                if ($key === 'site_analytics') {
                    $value = isset($_POST[$key]) ? trim($_POST[$key]) : '';
                    if ($value !== '' && !isValidAnalyticsCode($value)) {
                        $error = '统计代码包含不允许的域名或标签';
                        continue;
                    }
                    setSetting($key, $value);
                    continue;
                }

                $value = isset($_POST[$key]) ? trim($_POST[$key]) : '';
                setSetting($key, $value);
            }

            $uploadFields = [
                'site_avatar' => 'avatar_',
                'site_logo' => 'logo_',
                'site_background' => 'bg_',
                'wechat_qrcode' => 'wechat_',
                'site_favicon' => 'favicon_',
                'donate_alipay_qrcode' => 'donate_alipay_',
                'donate_wechat_qrcode' => 'donate_wechat_'
            ];

            // 先处理直链：直链输入框为空时才用上传
            $directLinks = array_keys($uploadFields);
            $savedByDirect = [];
            foreach ($directLinks as $key) {
                $directKey = $key . '_direct';
                $val = isset($_POST[$directKey]) ? trim($_POST[$directKey]) : '';
                if ($val !== '') {
                    if (isValidImageUrl($val)) {
                        setSetting($key, $val);
                        $savedByDirect[$key] = true;
                    } else {
                        $error = '图片链接格式不正确：' . htmlspecialchars($val, ENT_QUOTES);
                    }
                }
            }

            // 上传图片（若该字段未通过直链保存或用户同时上传了）
            foreach ($uploadFields as $key => $prefix) {
                if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = saveUploadedImage($_FILES[$key], $prefix);
                    if ($uploadResult['success']) {
                        setSetting($key, $uploadResult['url']);
                    } else {
                        $error = $uploadResult['message'];
                    }
                }
            }

            // 发送测试邮件（勾选后随保存一起触发，使用刚保存的配置）
            if ($error === '' && !empty($_POST['smtp_test'])) {
                require_once APP_ROOT . '/includes/Mailer.php';
                try {
                    $mailer = AppMailer::fromSettings();
                    if (!$mailer) {
                        $error = '测试邮件未发送：请先启用 SMTP 并填写服务器与账号';
                    } else {
                        $testTo = trim(getSetting('notify_admin_email', ''));
                        if ($testTo === '') {
                            $testTo = trim(getSetting('admin_email', ''));
                        }
                        if ($testTo === '' || !filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
                            $error = '测试邮件未发送：请填写有效的「通知接收邮箱」或站长邮箱';
                        } else {
                            $mailer->send(
                                $testTo,
                                '【' . getSetting('site_name', '') . '】SMTP 测试邮件',
                                app_mail_template('SMTP 测试邮件', '<p>这是一封测试邮件，收到即表示邮件提醒配置正确。</p>'),
                                '这是一封测试邮件，收到即表示邮件提醒配置正确。'
                            );
                            $success = '设置已保存，测试邮件已发送至 ' . $testTo;
                        }
                    }
                } catch (Exception $e) {
                    $error = '测试邮件发送失败：' . $e->getMessage();
                    $smtpDiagnostics = app_smtp_diagnose();
                }
            }

            if ($error === '' && $success === '') {
                $success = '设置已保存';
            }
        } catch (Exception $e) {
            $error = '保存失败: ' . $e->getMessage();
        }
    }
}

// 获取当前设置
$settings = [];
try {
    $rows = db()->fetchAll("SELECT setting_key, setting_value FROM app_setting");
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $settings = [];
}

// 获取启用的 AI Provider 列表（用于默认模型下拉框）
$aiProviders = [];
try {
    $aiProviders = db()->fetchAll("SELECT id, name, model FROM app_ai_provider WHERE enabled = 1 ORDER BY sort_order DESC, id ASC");
} catch (Exception $e) {
    $aiProviders = [];
}

require_once APP_ROOT . '/admin/template/header.php';
?>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo e($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error"><?php echo e($error); ?></div>
<?php endif; ?>

<?php if (!empty($smtpDiagnostics)): ?>
<div class="alert alert-error" style="display: block;">
    <strong>SMTP 自检结果</strong>
    <ul style="margin: 8px 0 0 18px; line-height: 1.9;">
        <?php foreach ($smtpDiagnostics as $diagLine): ?>
        <li><?php echo e($diagLine); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>网站设置</div>
    </div>
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data">
            <?php echo Security::csrfField(); ?>
            
            <h3 style="margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color);">基本信息</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">网站名称</label>
                    <input type="text" name="site_name" class="form-input" value="<?php echo e($settings['site_name'] ?? '我的博客'); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">网站标题</label>
                    <input type="text" name="site_description" class="form-input" value="<?php echo e($settings['site_description'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">关键词</label>
                <input type="text" name="site_keywords" class="form-input" value="<?php echo e($settings['site_keywords'] ?? ''); ?>">
                <div class="form-hint">多个关键词用逗号分隔</div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">站长昵称</label>
                    <input type="text" name="site_author" class="form-input" value="<?php echo e($settings['site_author'] ?? ''); ?>" placeholder="留空则使用网站名称">
                    <div class="form-hint">显示在侧边栏头像下、文章作者与分享卡片中</div>
                </div>

                <div class="form-group">
                    <label class="form-label">联系邮箱</label>
                    <input type="email" name="site_email" class="form-input" value="<?php echo e($settings['site_email'] ?? ''); ?>" placeholder="留空则不显示邮箱入口">
                    <div class="form-hint">前台「邮箱」按钮与友链申请均使用该地址</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Telegram 链接</label>
                <input type="text" name="site_telegram" class="form-input" value="<?php echo e($settings['site_telegram'] ?? ''); ?>" placeholder="https://t.me/yourname（留空则不显示）">
            </div>

            <div class="form-group">
                <label class="form-label">额外备案/资质链接</label>
                <textarea name="site_icp_links" class="form-input" rows="3" placeholder="每行一条，格式：名称|URL"><?php echo e($settings['site_icp_links'] ?? ''); ?></textarea>
                <div class="form-hint">每行一条，格式「名称|链接」；只填名称则纯文本展示，留空则不显示</div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">ICP备案号</label>
                    <input type="text" name="site_icp" class="form-input" value="<?php echo e($settings['site_icp'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">站长邮箱</label>
                    <input type="email" name="admin_email" class="form-input" value="<?php echo e($settings['admin_email'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">网站底部信息</label>
                <textarea name="site_footer" class="form-textarea" style="min-height: 80px;"><?php echo e($settings['site_footer'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">网站启动时间</label>
                <?php
                $startDateValue = $settings['site_start_date'] ?? date('Y-m-d');
                if ($startDateValue) {
                    $startTimestamp = strtotime($startDateValue);
                    $startDateValue = $startTimestamp ? date('Y-m-d\TH:i:s', $startTimestamp) : '';
                }
                ?>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <input type="datetime-local" name="site_start_date" id="site_start_date" class="form-input" value="<?php echo e($startDateValue); ?>">
                    <button type="button" class="btn btn-secondary" id="btn-set-start-now">填入当前时间</button>
                </div>
                <div class="form-hint">用于计算运行天数，可精确到秒。点击按钮可一键填入管理员的电脑当前时间。</div>
            </div>

            <div class="form-group">
                <label class="form-label">时间校准</label>
                <?php
                $timeOffset = (int)($settings['site_time_offset'] ?? 0);
                $serverTime = date('Y-m-d H:i:s');
                $calibratedTime = date('Y-m-d H:i:s', siteTime());
                $offsetHours = floor(abs($timeOffset) / 3600);
                $offsetMinutes = floor((abs($timeOffset) % 3600) / 60);
                $offsetSeconds = abs($timeOffset) % 60;
                $offsetSign = $timeOffset >= 0 ? '+' : '-';
                $offsetReadable = [];
                if ($offsetHours > 0) $offsetReadable[] = $offsetHours . '小时';
                if ($offsetMinutes > 0) $offsetReadable[] = $offsetMinutes . '分钟';
                if ($offsetSeconds > 0 || empty($offsetReadable)) $offsetReadable[] = $offsetSeconds . '秒';
                $offsetReadableStr = $offsetSign . implode('', $offsetReadable);
                ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; font-size: 0.9rem;">
                    <div style="background: var(--bg-subtle); padding: 10px 12px; border-radius: var(--radius);">
                        <div style="color: var(--text-light); font-size: 0.8rem;">服务器时间</div>
                        <div><?php echo e($serverTime); ?></div>
                    </div>
                    <div style="background: var(--bg-subtle); padding: 10px 12px; border-radius: var(--radius);">
                        <div style="color: var(--text-light); font-size: 0.8rem;">校准后时间</div>
                        <div><?php echo e($calibratedTime); ?></div>
                    </div>
                </div>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <input type="number" name="site_time_offset" id="site_time_offset" class="form-input" value="<?php echo e($timeOffset); ?>">
                    <button type="button" class="btn btn-secondary" id="btn-calibrate-time">一键校准为当前电脑时间</button>
                </div>
                <input type="hidden" name="calibrate_time" id="calibrate_time" value="">
                <div class="form-hint">当前偏移量：<?php echo e($offsetReadableStr); ?>。若前台时间不准（如显示“9小时前”），点击按钮可自动以管理员电脑时间为基准计算偏移量，保存后生效。</div>
            </div>
            
            <h3 style="margin: 24px 0 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color);">外观设置</h3>
            
            <div class="form-group">
                <label class="form-label">默认主题</label>
                <select name="site_theme" class="form-select">
                    <option value="auto" <?php echo ($settings['site_theme'] ?? 'auto') === 'auto' ? 'selected' : ''; ?>>跟随系统</option>
                    <option value="light" <?php echo ($settings['site_theme'] ?? '') === 'light' ? 'selected' : ''; ?>>浅色模式</option>
                    <option value="dark" <?php echo ($settings['site_theme'] ?? '') === 'dark' ? 'selected' : ''; ?>>深色模式</option>
                </select>
                <div class="form-hint">用户可以在前台手动切换主题</div>
            </div>
            
            <h3 style="margin: 24px 0 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color);">图片设置</h3>

            <div class="form-group">
                <label class="form-label">站长头像</label>
                <div style="display: flex; gap: 12px; align-items: flex-start;">
                    <div style="flex: 1;">
                        <input type="text" name="site_avatar_direct" class="form-input" placeholder="外部图片链接或留空使用上传" value="<?php echo e($settings['site_avatar'] ?? ''); ?>">
                        <div class="form-hint">显示在侧边栏、说说与评论区；留空则回退到下方 Logo</div>
                    </div>
                    <div>
                        <input type="file" name="site_avatar" class="form-input" accept="image/*" style="padding: 8px;">
                    </div>
                </div>
                <?php if (!empty($settings['site_avatar'])): ?>
                <img src="<?php echo e($settings['site_avatar']); ?>" style="max-width: 100px; margin-top: 8px; border-radius: 50%;">
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">网站Logo</label>
                <div style="display: flex; gap: 12px; align-items: flex-start;">
                    <div style="flex: 1;">
                        <input type="text" name="site_logo_direct" class="form-input" placeholder="外部图片链接或留空使用上传" value="<?php echo e($settings['site_logo'] ?? ''); ?>">
                        <div class="form-hint">填写直链，或选择本地图片上传</div>
                    </div>
                    <div>
                        <input type="file" name="site_logo" class="form-input" accept="image/*" style="padding: 8px;">
                    </div>
                </div>
                <?php if (!empty($settings['site_logo'])): ?>
                <img src="<?php echo e($settings['site_logo']); ?>" style="max-width: 100px; margin-top: 8px; border-radius: var(--radius);">
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label class="form-label">网站背景图</label>
                <div style="display: flex; gap: 12px; align-items: flex-start;">
                    <div style="flex: 1;">
                        <input type="text" name="site_background_direct" class="form-input" placeholder="外部图片链接或留空使用上传" value="<?php echo e($settings['site_background'] ?? ''); ?>">
                        <div class="form-hint">填写直链，或选择本地图片上传</div>
                    </div>
                    <div>
                        <input type="file" name="site_background" class="form-input" accept="image/*" style="padding: 8px;">
                    </div>
                </div>
                <?php if (!empty($settings['site_background'])): ?>
                <img src="<?php echo e($settings['site_background']); ?>" style="max-width: 200px; margin-top: 8px; border-radius: var(--radius);">
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">背景图展示位置</label>
                <div class="form-hint" style="margin-bottom: 8px;">壁纸尺寸不合时，选择展示哪一部分。点击对应方位。</div>
                <?php $bgPos = $settings['site_background_position'] ?? 'center center'; ?>
                <div class="bg-position-grid" role="radiogroup" aria-label="背景图展示位置">
                    <?php
                    $posOptions = [
                        'left top' => '左上', 'center top' => '上', 'right top' => '右上',
                        'left center' => '左', 'center center' => '中', 'right center' => '右',
                        'left bottom' => '左下', 'center bottom' => '下', 'right bottom' => '右下',
                    ];
                    foreach ($posOptions as $pos => $label):
                        $checked = ($bgPos === $pos) ? ' checked' : '';
                    ?>
                    <label class="bg-pos-cell<?php echo $checked ? ' is-active' : ''; ?>" data-pos="<?php echo e($pos); ?>">
                        <input type="radio" name="site_background_position" value="<?php echo e($pos); ?>"<?php echo $checked; ?> style="display:none;">
                        <span><?php echo e($label); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">背景图缩放方式</label>
                <?php $bgSize = $settings['site_background_size'] ?? 'cover'; ?>
                <select name="site_background_size" class="form-input">
                    <option value="cover"<?php echo $bgSize === 'cover' ? ' selected' : ''; ?>>填充裁切（cover，默认，可能裁掉边缘）</option>
                    <option value="contain"<?php echo $bgSize === 'contain' ? ' selected' : ''; ?>>完整展示（contain，可能留白）</option>
                    <option value="100% 100%"<?php echo $bgSize === '100% 100%' ? ' selected' : ''; ?>>拉伸铺满（100% 100%，可能变形）</option>
                    <option value="auto"<?php echo $bgSize === 'auto' ? ' selected' : ''; ?>>原始尺寸（auto，居中显示）</option>
                </select>
                <div class="form-hint">竖图建议用"完整展示"或"原始尺寸"；宽图建议用"填充裁切"。</div>
            </div>

            <div class="form-group">
                <label class="form-label">背景遮罩强度：<span id="bg-overlay-value"><?php echo e($settings['site_background_overlay'] ?? '0.45'); ?></span></label>
                <input type="range" name="site_background_overlay" min="0" max="0.85" step="0.05" value="<?php echo e($settings['site_background_overlay'] ?? '0.45'); ?>" class="form-range" oninput="document.getElementById('bg-overlay-value').textContent=this.value;" style="width: 100%;">
                <div class="form-hint">壁纸过亮导致文字看不清时调高；过暗想突出壁纸时调低。0 = 无遮罩，0.85 = 最暗。</div>
            </div>
            
            <div class="form-group">
                <label class="form-label">微信二维码</label>
                <div style="display: flex; gap: 12px; align-items: flex-start;">
                    <div style="flex: 1;">
                        <input type="text" name="wechat_qrcode_direct" class="form-input" placeholder="外部图片链接或留空使用上传" value="<?php echo e($settings['wechat_qrcode'] ?? ''); ?>">
                        <div class="form-hint">填写直链，或选择本地图片上传</div>
                    </div>
                    <div>
                        <input type="file" name="wechat_qrcode" class="form-input" accept="image/*" style="padding: 8px;">
                    </div>
                </div>
                <?php if (!empty($settings['wechat_qrcode'])): ?>
                <img src="<?php echo e($settings['wechat_qrcode']); ?>" style="max-width: 100px; margin-top: 8px; border-radius: var(--radius);">
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label class="form-label">网站图标 (Favicon)</label>
                <div style="display: flex; gap: 12px; align-items: flex-start;">
                    <div style="flex: 1;">
                        <input type="text" name="site_favicon_direct" class="form-input" placeholder="外部图片链接或留空使用上传" value="<?php echo e($settings['site_favicon'] ?? ''); ?>">
                        <div class="form-hint">填写直链，或选择本地图片上传</div>
                    </div>
                    <div>
                        <input type="file" name="site_favicon" class="form-input" accept="image/*" style="padding: 8px;">
                    </div>
                </div>
                <?php if (!empty($settings['site_favicon'])): ?>
                <img src="<?php echo e($settings['site_favicon']); ?>" style="max-width: 32px; margin-top: 8px;">
                <?php endif; ?>
            </div>
            

            <h3 style="margin: 24px 0 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color);">捐赠设置</h3>

            <div class="form-group">
                <label class="form-label">捐赠页标题</label>
                <input type="text" name="donate_title" class="form-input" value="<?php echo e($settings['donate_title'] ?? '捐赠页'); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">捐赠说明</label>
                <textarea name="donate_description" class="form-textarea" style="min-height: 80px;"><?php echo e($settings['donate_description'] ?? '如果这个网站对你有帮助，可以自愿捐赠。'); ?></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">支付宝收款码</label>
                    <input type="text" name="donate_alipay_qrcode_direct" class="form-input" placeholder="收款码图片直链" value="<?php echo (isset($settings['donate_alipay_qrcode']) && strpos($settings['donate_alipay_qrcode'], 'http') === 0) ? e($settings['donate_alipay_qrcode']) : ''; ?>">
                    <input type="file" name="donate_alipay_qrcode" class="form-input" accept="image/*" style="padding: 8px; margin-top: 8px;">
                    <?php if (!empty($settings['donate_alipay_qrcode'])): ?>
                    <img src="<?php echo e($settings['donate_alipay_qrcode']); ?>" style="max-width: 140px; margin-top: 8px; border-radius: var(--radius);">
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">微信收款码</label>
                    <input type="text" name="donate_wechat_qrcode_direct" class="form-input" placeholder="收款码图片直链" value="<?php echo (isset($settings['donate_wechat_qrcode']) && strpos($settings['donate_wechat_qrcode'], 'http') === 0) ? e($settings['donate_wechat_qrcode']) : ''; ?>">
                    <input type="file" name="donate_wechat_qrcode" class="form-input" accept="image/*" style="padding: 8px; margin-top: 8px;">
                    <?php if (!empty($settings['donate_wechat_qrcode'])): ?>
                    <img src="<?php echo e($settings['donate_wechat_qrcode']); ?>" style="max-width: 140px; margin-top: 8px; border-radius: var(--radius);">
                    <?php endif; ?>
                </div>
            </div>

            <h3 style="margin: 24px 0 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color);">社交链接</h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">GitHub</label>
                    <input type="url" name="github_url" class="form-input" placeholder="https://github.com/username" value="<?php echo e($settings['github_url'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Bilibili</label>
                    <input type="url" name="bilibili_url" class="form-input" placeholder="https://space.bilibili.com/xxx" value="<?php echo e($settings['bilibili_url'] ?? ''); ?>">
                </div>
            </div>

            <h3 style="margin: 24px 0 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color);">GitHub 登录设置</h3>

            <div style="background: var(--bg-subtle); padding: 16px; border-radius: var(--radius); margin-bottom: 16px;">
                <p style="font-size: 0.85rem; color: var(--text-light); margin-bottom: 12px;">
                    启用后，前台登录/注册页面将显示「使用 GitHub 登录」按钮，用户授权后即可自动创建账号并登录。请前往 <a href="https://github.com/settings/developers" target="_blank" rel="noopener">GitHub Developer settings</a> 创建 OAuth App，Authorization callback URL 填写 <code><?php echo e(rtrim(SITE_URL, '/') . '/github-callback.php'); ?></code>。
                </p>
            </div>

            <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="github_oauth_enabled" value="1" id="github_oauth_enabled"
                       <?php echo ($settings['github_oauth_enabled'] ?? '0') === '1' ? 'checked' : ''; ?> style="width: auto;">
                <label for="github_oauth_enabled" style="margin-bottom: 0;">启用 GitHub 登录</label>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Client ID</label>
                    <input type="text" name="github_client_id" class="form-input" placeholder="Ov23liXxxXXXXxxXXXXx" value="<?php echo e($settings['github_client_id'] ?? ''); ?>">
                    <div class="form-hint">OAuth App 的 Client ID，可公开</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Client secrets</label>
                    <input type="password" name="github_client_secret" class="form-input" placeholder="<?php echo !empty($settings['github_client_secret']) ? '已保存，留空不修改' : 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'; ?>" value="">
                    <div class="form-hint">OAuth App 的 Client Secret，请勿泄露。留空将保留已保存的 Secret。</div>
                </div>
            </div>

            <h3 style="margin: 24px 0 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color);">GitCode 登录设置</h3>

            <div style="background: var(--bg-subtle); padding: 16px; border-radius: var(--radius); margin-bottom: 16px;">
                <p style="font-size: 0.85rem; color: var(--text-light); margin-bottom: 12px;">
                    启用后，前台登录/注册页面将显示「使用 GitCode 登录」按钮，用户授权后即可自动创建账号并登录。请在 GitCode 创建 OAuth 应用，<strong>回调地址</strong>填写 <code><?php echo e(rtrim(SITE_URL, '/') . '/gitcode-callback.php'); ?></code>。授权端点 <code>https://gitcode.com/oauth/authorize</code>，换 token <code>POST https://gitcode.com/oauth/token</code>，用户信息 <code>GET https://api.gitcode.com/api/v5/user</code>（Bearer 鉴权）。
                </p>
            </div>

            <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="gitcode_oauth_enabled" value="1" id="gitcode_oauth_enabled"
                       <?php echo ($settings['gitcode_oauth_enabled'] ?? '0') === '1' ? 'checked' : ''; ?> style="width: auto;">
                <label for="gitcode_oauth_enabled" style="margin-bottom: 0;">启用 GitCode 登录</label>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Client ID</label>
                    <input type="text" name="gitcode_client_id" class="form-input" placeholder="应用 Client ID" value="<?php echo e($settings['gitcode_client_id'] ?? ''); ?>">
                    <div class="form-hint">GitCode 应用的 Client ID，可公开</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Client Secret</label>
                    <input type="password" name="gitcode_client_secret" class="form-input" placeholder="<?php echo !empty($settings['gitcode_client_secret']) ? '已保存，留空不修改' : '应用 Client Secret'; ?>" value="">
                    <div class="form-hint">GitCode 应用的 Client Secret，请勿泄露。留空将保留已保存的 Secret。</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">权限范围 (scope)</label>
                <input type="text" name="gitcode_oauth_scope" class="form-input" placeholder="all_user" value="<?php echo e($settings['gitcode_oauth_scope'] ?? ''); ?>">
                <div class="form-hint">留空默认 <code>all_user</code>（读取用户基础资料，足够用于登录）。GitCode 文档列出的可用 scope：<code>all_user</code>、<code>all_key</code>、<code>all_groups</code>、<code>all_projects</code>、<code>all_pr</code>、<code>all_issue</code>、<code>all_note</code>、<code>all_hook</code>、<code>all_repository</code>，多个用空格分隔。</div>
            </div>

            <h3 style="margin: 24px 0 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color);">注册极验人机验证</h3>

            <div style="background: var(--bg-subtle); padding: 16px; border-radius: var(--radius); margin-bottom: 16px;">
                <p style="font-size: 0.85rem; color: var(--text-light); margin-bottom: 12px;">
                    填写验证 ID 和验证密钥后，前台注册页会先显示极验验证，通过后才显示 GitHub 注册和账号申请表单。验证密钥只保存在服务端，请勿泄露。
                </p>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">验证 ID</label>
                    <input type="text" name="geetest_captcha_id" class="form-input" placeholder="你的极验验证 ID" value="<?php echo e($settings['geetest_captcha_id'] ?? ''); ?>">
                    <div class="form-hint">前端公开使用的 captchaId</div>
                </div>

                <div class="form-group">
                    <label class="form-label">验证密钥</label>
                    <input type="password" name="geetest_captcha_key" class="form-input" placeholder="<?php echo !empty($settings['geetest_captcha_key']) ? '已保存，留空不修改' : '你的极验验证密钥'; ?>" value="">
                    <div class="form-hint">服务端二次校验使用，留空将保留已保存的验证密钥。</div>
                </div>
            </div>

            <h3 style="margin: 24px 0 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color);">GitHub 图库设置</h3>
            <div style="background: var(--bg-subtle); padding: 16px; border-radius: var(--radius); margin-bottom: 16px;">
                <p style="font-size: 0.85rem; color: var(--text-light); margin-bottom: 12px;">
                    配置 GitHub 图库后，用户可以在「免费图床」页面上传图片，文件将存储在指定的 GitHub 仓库中，按用户名自动创建文件夹分类存储。
                </p>
            </div>

            <div class="form-group">
                <label class="form-label">GitHub Personal Access Token</label>
                <input type="password" name="github_gallery_token" class="form-input" placeholder="<?php echo !empty($settings['github_gallery_token']) ? '已保存，留空不修改' : 'ghp_xxxxxxxxxxxxxxxxxxxx'; ?>" value="">
                <div class="form-hint">需要 <code>repo</code> 权限的 Token，<a href="https://github.com/settings/tokens" target="_blank" rel="noopener">点击生成</a>。留空将保留已保存的 Token。</div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">仓库名称</label>
                    <input type="text" name="github_gallery_repo" class="form-input" placeholder="username/repo-name" value="<?php echo e($settings['github_gallery_repo'] ?? ''); ?>">
                    <div class="form-hint">格式：用户名/仓库名</div>
                </div>

                <div class="form-group">
                <label class="form-label">分支名称</label>
                <input type="text" name="github_gallery_branch" class="form-input" placeholder="main" value="<?php echo e($settings['github_gallery_branch'] ?? 'main'); ?>">
                <div class="form-hint">默认 main</div>
            </div>
            </div>

            <div class="form-group">
                <label class="form-label">图库单文件大小限制 (MB)</label>
                <input type="number" name="gallery_max_size" class="form-input" placeholder="5" min="1" max="100" value="<?php echo e($settings['gallery_max_size'] ?? '5'); ?>">
                <div class="form-hint">用户上传单张图片的最大允许大小，单位 MB（1-100）</div>
            </div>

            <h3 style="margin: 24px 0 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color);">邮件提醒</h3>
            <div style="background: var(--bg-subtle); padding: 16px; border-radius: var(--radius); margin-bottom: 16px;">
                <p style="font-size: 0.85rem; color: var(--text-light); margin: 0;">配置 SMTP 后，新的友链申请会通知管理员；管理员回复评论时，会通知评论者填写的邮箱。短内容会直接显示在邮件正文中。密码留空表示保留已保存的密码。</p>
            </div>
            <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="smtp_enabled" value="1" id="smtp_enabled" <?php echo ($settings['smtp_enabled'] ?? '0') === '1' ? 'checked' : ''; ?> style="width: auto;">
                <label for="smtp_enabled" style="margin-bottom: 0;">启用 SMTP 邮件发送</label>
            </div>
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 16px;">
                <div class="form-group"><label class="form-label">SMTP 服务器</label><input type="text" name="smtp_host" class="form-input" placeholder="smtp.qq.com" value="<?php echo e($settings['smtp_host'] ?? ''); ?>"><div class="form-hint">只填域名即可；粘贴 <code>smtp.qq.com:465</code> 或 <code>ssl://smtp.qq.com</code> 也会自动清洗。</div></div>
                <div class="form-group"><label class="form-label">端口</label><input type="number" name="smtp_port" class="form-input" min="1" max="65535" value="<?php echo e($settings['smtp_port'] ?? '465'); ?>"></div>
                <div class="form-group"><label class="form-label">加密方式</label><select name="smtp_encryption" class="form-select"><option value="ssl" <?php echo ($settings['smtp_encryption'] ?? 'ssl') === 'ssl' ? 'selected' : ''; ?>>SSL</option><option value="tls" <?php echo ($settings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : ''; ?>>STARTTLS</option><option value="none" <?php echo ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>无加密</option></select></div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group"><label class="form-label">SMTP 用户名</label><input type="email" name="smtp_username" class="form-input" value="<?php echo e($settings['smtp_username'] ?? ''); ?>"></div>
                <div class="form-group"><label class="form-label">SMTP 密码</label><input type="password" name="smtp_password" class="form-input" placeholder="<?php echo !empty($settings['smtp_password']) ? '已保存，留空不修改' : 'SMTP 密码或授权码'; ?>" value=""></div>
                <div class="form-group"><label class="form-label">发件邮箱</label><input type="email" name="smtp_from_email" class="form-input" placeholder="默认使用 SMTP 用户名" value="<?php echo e($settings['smtp_from_email'] ?? ''); ?>"></div>
                <div class="form-group"><label class="form-label">发件人名称</label><input type="text" name="smtp_from_name" class="form-input" value="<?php echo e($settings['smtp_from_name'] ?? ''); ?>"></div>
            </div>
            <div class="form-group"><label class="form-label">通知接收邮箱</label><input type="email" name="notify_admin_email" class="form-input" placeholder="默认使用站长邮箱" value="<?php echo e($settings['notify_admin_email'] ?? ''); ?>"><div class="form-hint">友链申请提醒发送到这里，留空则使用上方的站长邮箱。</div></div>
            <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: center; margin-bottom: 8px;">
                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="notify_link_apply" value="1" <?php echo ($settings['notify_link_apply'] ?? '1') === '1' ? 'checked' : ''; ?> style="width:auto;">友链申请提醒</label>
                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="notify_comment_reply" value="1" <?php echo ($settings['notify_comment_reply'] ?? '1') === '1' ? 'checked' : ''; ?> style="width:auto;">评论回复提醒</label>
                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="smtp_test" value="1" style="width:auto;">保存后发送测试邮件</label>
                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="smtp_skip_verify" value="1" <?php echo ($settings['smtp_skip_verify'] ?? '0') === '1' ? 'checked' : ''; ?> style="width:auto;">跳过 SSL 证书校验</label>
            </div>
            <div class="form-hint" style="margin-bottom: 8px;">「跳过 SSL 证书校验」会降低连接安全性，仅在服务器证书链异常、且确认地址无误时才勾选。</div>

            <h3 style="margin: 24px 0 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color);">功能设置</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="comment_need_approve" value="1" id="comment_need_approve" 
                           <?php echo ($settings['comment_need_approve'] ?? '0') === '1' ? 'checked' : ''; ?> style="width: auto;">
                    <label for="comment_need_approve" style="margin-bottom: 0;">评论需要审核</label>
                </div>
                
                <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="article_comment_enable" value="1" id="article_comment_enable" 
                           <?php echo ($settings['article_comment_enable'] ?? '1') === '1' ? 'checked' : ''; ?> style="width: auto;">
                    <label for="article_comment_enable" style="margin-bottom: 0;">启用文章评论</label>
                </div>
                
                <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="guestbook_enable" value="1" id="guestbook_enable" 
                           <?php echo ($settings['guestbook_enable'] ?? '1') === '1' ? 'checked' : ''; ?> style="width: auto;">
                    <label for="guestbook_enable" style="margin-bottom: 0;">启用留言板</label>
                </div>
                
                <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="site_maintenance" value="1" id="site_maintenance" 
                           <?php echo ($settings['site_maintenance'] ?? '0') === '1' ? 'checked' : ''; ?> style="width: auto;">
                    <label for="site_maintenance" style="margin-bottom: 0;">维护模式</label>
                </div>
            </div>

            <h3 style="margin: 24px 0 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color);">AI 总结设置</h3>

            <div style="background: var(--bg-subtle); padding: 16px; border-radius: var(--radius); margin-bottom: 16px;">
                <p style="font-size: 0.85rem; color: var(--text-light); margin-bottom: 12px;">
                    启用后，前台文章详情页将显示 AI 总结面板，访客可切换不同 AI 模型生成总结。AI Provider 请在 <a href="ai-providers.php">AI 管理</a> 中配置。
                </p>
            </div>

            <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="ai_summary_enabled" value="1" id="ai_summary_enabled"
                       <?php echo ($settings['ai_summary_enabled'] ?? '0') === '1' ? 'checked' : ''; ?> style="width: auto;">
                <label for="ai_summary_enabled" style="margin-bottom: 0;">启用文章页 AI 总结</label>
            </div>

            <div class="form-group">
                <label class="form-label">默认 AI 模型</label>
                <select name="ai_default_provider_id" class="form-select">
                    <option value="0">不指定</option>
                    <?php foreach ($aiProviders as $p): ?>
                    <option value="<?php echo (int)$p['id']; ?>"
                        <?php echo ((int)($settings['ai_default_provider_id'] ?? 0) === (int)$p['id']) ? 'selected' : ''; ?>>
                        <?php echo e($p['name']); ?>（<?php echo e($p['model']); ?>）
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-hint">前台默认选中的 AI Provider</div>
            </div>

            <div class="form-group">
                <label class="form-label">总结提示词</label>
                <textarea name="ai_summary_prompt" class="form-textarea" style="min-height: 100px;"><?php echo e($settings['ai_summary_prompt'] ?? '请用中文对下面这篇文章生成一段精炼总结，保留核心观点。'); ?></textarea>
                <div class="form-hint">发送给 AI 的系统提示词</div>
            </div>

            <h3 style="margin: 24px 0 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color);">天气弹窗设置</h3>

            <div style="background: var(--bg-subtle); padding: 16px; border-radius: var(--radius); margin-bottom: 16px;">
                <p style="font-size: 0.85rem; color: var(--text-light); margin-bottom: 0;">
                    启用后，前台每天向每位访客显示一次欢迎弹窗（"欢迎来自 XX 的朋友，目前天气 XX"），并根据访客 IP 自动定位城市；背景也会随实时天气变化（晴天/雨天/雪天等不同色调与雨雪粒子）。密钥请在 <a href="https://www.seniverse.com/products" target="_blank" rel="noopener">心知天气控制台</a> 获取（免费版即可）。私钥只在服务端使用，不会泄露到前端。
                </p>
            </div>

            <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="weather_popup_enabled" value="1" id="weather_popup_enabled"
                       <?php echo ($settings['weather_popup_enabled'] ?? '0') === '1' ? 'checked' : ''; ?> style="width: auto;">
                <label for="weather_popup_enabled" style="margin-bottom: 0;">启用天气欢迎弹窗与实时天气背景</label>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">心知天气公钥</label>
                    <input type="text" name="seniverse_public_key" class="form-input" placeholder="例如 PKwiV7auWJE3iBJ8d" value="<?php echo e($settings['seniverse_public_key'] ?? ''); ?>">
                    <div class="form-hint">用于签名验证（uid），填写后请求中不出现明文私钥，更安全</div>
                </div>

                <div class="form-group">
                    <label class="form-label">心知天气私钥</label>
                    <input type="password" name="seniverse_private_key" class="form-input" placeholder="<?php echo !empty($settings['seniverse_private_key']) ? '已保存，留空不修改' : '例如 SMEieQjde1C9eXnbE'; ?>" value="">
                    <div class="form-hint">API 密钥中的私钥，请勿泄露。留空将保留已保存的私钥。</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">默认城市</label>
                <input type="text" name="weather_city" class="form-input" placeholder="北京" value="<?php echo e($settings['weather_city'] ?? ''); ?>">
                <div class="form-hint">访客 IP 无法定位时的兜底城市（留空默认北京）；填写后侧边栏也会显示该城市的天气组件。填"城市名"或"省 市名"，如：赣州 / 山西 榆林</div>
            </div>

            <h3 style="margin: 24px 0 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color);">人机验证设置</h3>

            <div style="background: var(--bg-subtle); padding: 16px; border-radius: var(--radius); margin-bottom: 16px;">
                <p style="font-size: 0.85rem; color: var(--text-light); margin-bottom: 12px;">
                    配置 Cloudflare Turnstile 后，可在登录、留言板、文章评论等场景要求用户完成人机验证，有效防止暴力破解和垃圾留言。请前往 <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener">Cloudflare Turnstile 控制台</a> 创建站点并获取密钥。
                </p>
            </div>

            <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="turnstile_login_enabled" value="1" id="turnstile_login_enabled"
                       <?php echo ($settings['turnstile_login_enabled'] ?? '0') === '1' ? 'checked' : ''; ?> style="width: auto;">
                <label for="turnstile_login_enabled" style="margin-bottom: 0;">登录时启用 Turnstile 人机验证</label>
            </div>

            <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="turnstile_guestbook_enabled" value="1" id="turnstile_guestbook_enabled"
                       <?php echo ($settings['turnstile_guestbook_enabled'] ?? '0') === '1' ? 'checked' : ''; ?> style="width: auto;">
                <label for="turnstile_guestbook_enabled" style="margin-bottom: 0;">留言板 / 文章评论启用 Turnstile 人机验证</label>
            </div>

            <div style="margin-top: 8px; margin-bottom: 12px;">
                <h4 style="margin: 0 0 4px; font-size: 0.95rem;">登录验证密钥</h4>
                <p style="font-size: 0.8rem; color: var(--text-light); margin: 0;">对应「登录时启用」开关。留空将不启用登录验证。</p>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div class="form-group">
                    <label class="form-label">登录 Site Key</label>
                    <input type="text" name="turnstile_login_site_key" class="form-input" placeholder="0x4AAAA..." value="<?php echo e($settings['turnstile_login_site_key'] ?? ''); ?>">
                    <div class="form-hint">用于登录页前端加载验证组件，可公开</div>
                </div>

                <div class="form-group">
                    <label class="form-label">登录 Secret Key</label>
                    <input type="password" name="turnstile_login_secret_key" class="form-input" placeholder="<?php echo !empty($settings['turnstile_login_secret_key']) ? '已保存，留空不修改' : '0x4AAAA...'; ?>" value="">
                    <div class="form-hint">用于登录服务端校验，请勿泄露。留空将保留已保存的 Key。</div>
                </div>
            </div>

            <div style="margin-top: 16px; margin-bottom: 12px;">
                <h4 style="margin: 0 0 4px; font-size: 0.95rem;">留言板验证密钥</h4>
                <p style="font-size: 0.8rem; color: var(--text-light); margin: 0;">对应「留言时启用」开关。留空将不启用留言验证。</p>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div class="form-group">
                    <label class="form-label">留言板 Site Key</label>
                    <input type="text" name="turnstile_guestbook_site_key" class="form-input" placeholder="0x4AAAA..." value="<?php echo e($settings['turnstile_guestbook_site_key'] ?? ''); ?>">
                    <div class="form-hint">用于留言板前端加载验证组件，可公开</div>
                </div>

                <div class="form-group">
                    <label class="form-label">留言板 Secret Key</label>
                    <input type="password" name="turnstile_guestbook_secret_key" class="form-input" placeholder="<?php echo !empty($settings['turnstile_guestbook_secret_key']) ? '已保存，留空不修改' : '0x4AAAA...'; ?>" value="">
                    <div class="form-hint">用于留言板服务端校验，请勿泄露。留空将保留已保存的 Key。</div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">统计代码</label>
                <textarea name="site_analytics" class="form-textarea" placeholder="如百度统计、Google Analytics代码" style="min-height: 80px;"><?php echo e($settings['site_analytics'] ?? ''); ?></textarea>
                <div class="form-hint">支持HTML/JS代码，会插入到页面底部</div>
            </div>
            
            <div class="form-group">
                <label class="form-label">CDN地址</label>
                <input type="url" name="site_cdn" class="form-input" placeholder="https://cdn.example.com" value="<?php echo e($settings['site_cdn'] ?? ''); ?>">
                <div class="form-hint">静态资源CDN加速地址，留空不使用</div>
            </div>
            
            <div style="margin-top: 24px;">
                <button type="submit" class="btn btn-primary">保存设置</button>
            </div>
        </form>
    </div>
</div>

<script src="/assets/js/admin/admin-settings.js?v=<?php echo APP_VERSION; ?>"></script>

<?php
// 内容变更成功后清空静态页面缓存：首页/归档/标签/关于/友链都依赖这些数据，
// 否则访客最长会看到 TTL（默认 300 秒）的旧内容。
if ($success !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    app_purge_page_cache();
}
?>
<?php require_once APP_ROOT . '/admin/template/footer.php'; ?>
