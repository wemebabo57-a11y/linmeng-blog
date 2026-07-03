<?php
/**
 * 网站设置 v2.0
 * 增加主题设置、更多功能开关
 */
define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
requireAdmin();

$pageTitle = '网站设置';
$currentPage = 'settings';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        $error = 'CSRF验证失败';
    } else {
        $settings = [
            'site_name',
            'site_description',
            'site_keywords',
            'site_icp',
            'site_footer',
            'admin_email',
            'github_url',
            'bilibili_url',
            'github_oauth_enabled',
            'github_client_id',
            'github_client_secret',
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
            'music_enabled',
            'music_api_url',
            'music_api_server',
            'music_api_type',
            'music_api_id',
            'music_api_key',
            'music_list',
            'tools_enabled',
            'lanzou_parse_enabled',
            'lanzou_parse_api_url',
            'lanzou_parse_api_key',
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

                // 蓝奏云解析 API Key 留空时保留原值，不回显
                if ($key === 'lanzou_parse_api_key') {
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
    $rows = db()->fetchAll("SELECT setting_key, setting_value FROM lm_setting");
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $settings = [];
}

// 获取启用的 AI Provider 列表（用于默认模型下拉框）
$aiProviders = [];
try {
    $aiProviders = db()->fetchAll("SELECT id, name, model FROM lm_ai_provider WHERE enabled = 1 ORDER BY sort_order DESC, id ASC");
} catch (Exception $e) {
    $aiProviders = [];
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
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>网站设置</div>
    </div>
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data">
            <?php echo Security::csrfField(); ?>
            
            <h3 style="margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color);">基本信息</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">网站名称</label>
                    <input type="text" name="site_name" class="form-input" value="<?php echo e($settings['site_name'] ?? '林梦的博客'); ?>">
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
                <label class="form-label">网站Logo/头像</label>
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

            <h3 style="margin: 24px 0 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color);">音乐模块设置</h3>

            <div style="background: var(--bg-subtle); padding: 16px; border-radius: var(--radius); margin-bottom: 16px;">
                <p style="font-size: 0.85rem; color: var(--text-light); margin-bottom: 12px;">
                    启用后，侧边栏将显示音乐播放器。支持通过 Meting API 接口（如 <code>https://api.zxki.cn/api/wyy</code>）动态获取歌单/歌曲，接口返回 JSON 格式。若接口调用失败，将自动回退到下方静态播放列表。
                </p>
            </div>

            <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="music_enabled" value="1" id="music_enabled"
                       <?php echo ($settings['music_enabled'] ?? '0') === '1' ? 'checked' : ''; ?> style="width: auto;">
                <label for="music_enabled" style="margin-bottom: 0;">启用侧边栏音乐播放器</label>
            </div>

            <div class="form-group">
                <label class="form-label">接口地址</label>
                <input type="url" name="music_api_url" class="form-input" placeholder="https://api.zxki.cn/api/wyy"
                       value="<?php echo e($settings['music_api_url'] ?? 'https://api.zxki.cn/api/wyy'); ?>">
                <div class="form-hint">Meting 兼容接口，必须以 http:// 或 https:// 开头</div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">数据源</label>
                    <select name="music_api_server" class="form-select">
                        <option value="netease" <?php echo ($settings['music_api_server'] ?? 'netease') === 'netease' ? 'selected' : ''; ?>>网易云音乐</option>
                        <option value="tencent" <?php echo ($settings['music_api_server'] ?? 'netease') === 'tencent' ? 'selected' : ''; ?>>QQ 音乐</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">请求类型</label>
                    <select name="music_api_type" class="form-select">
                        <option value="playlist" <?php echo ($settings['music_api_type'] ?? 'playlist') === 'playlist' ? 'selected' : ''; ?>>歌单</option>
                        <option value="song" <?php echo ($settings['music_api_type'] ?? 'playlist') === 'song' ? 'selected' : ''; ?>>单曲</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">歌单/歌曲 ID</label>
                <input type="text" name="music_api_id" class="form-input" placeholder="如 2619366284"
                       value="<?php echo e($settings['music_api_id'] ?? ''); ?>">
                <div class="form-hint">填写后优先通过接口获取音乐；留空则使用下方静态列表</div>
            </div>

            <div class="form-group">
                <label class="form-label">接口 Key（选填）</label>
                <input type="password" name="music_api_key" class="form-input" placeholder="如接口需要鉴权请填写"
                       value="<?php echo e($settings['music_api_key'] ?? ''); ?>">
                <div class="form-hint">部分私有 Meting 接口需要；公开接口可留空</div>
            </div>

            <div class="form-group">
                <label class="form-label">静态播放列表（备用）</label>
                <textarea name="music_list" class="form-textarea" style="min-height: 120px; font-family: monospace;" placeholder='[{"title":"歌曲名","artist":"歌手","url":"音频链接","cover":"封面链接"}]'><?php echo e($settings['music_list'] ?? ''); ?></textarea>
                <div class="form-hint">JSON 格式，接口不可用或 ID 为空时作为备用</div>
            </div>

            <h3 style="margin: 24px 0 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color);">工具页设置</h3>

            <div style="background: var(--bg-subtle); padding: 16px; border-radius: var(--radius); margin-bottom: 16px;">
                <p style="font-size: 0.85rem; color: var(--text-light); margin-bottom: 12px;">
                    工具页（<a href="/tools.php" target="_blank" rel="noopener">/tools.php</a>）收录实用小工具。蓝奏云直链解析通过本站服务器代理请求第三方接口，接口地址与 Key 不会暴露到前端。默认接口：<code>https://api.zxki.cn/api/lzy</code>
                </p>
            </div>

            <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="tools_enabled" value="1" id="tools_enabled"
                       <?php echo ($settings['tools_enabled'] ?? '1') === '1' ? 'checked' : ''; ?> style="width: auto;">
                <label for="tools_enabled" style="margin-bottom: 0;">启用工具页</label>
            </div>

            <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="lanzou_parse_enabled" value="1" id="lanzou_parse_enabled"
                       <?php echo ($settings['lanzou_parse_enabled'] ?? '1') === '1' ? 'checked' : ''; ?> style="width: auto;">
                <label for="lanzou_parse_enabled" style="margin-bottom: 0;">启用蓝奏云直链解析工具</label>
            </div>

            <div class="form-group">
                <label class="form-label">解析接口地址</label>
                <input type="url" name="lanzou_parse_api_url" class="form-input" placeholder="https://api.zxki.cn/api/lzy"
                       value="<?php echo e($settings['lanzou_parse_api_url'] ?? 'https://api.zxki.cn/api/lzy'); ?>">
                <div class="form-hint">蓝奏云解析 API 地址，必须以 http:// 或 https:// 开头；查询参数由系统自动拼接</div>
            </div>

            <div class="form-group">
                <label class="form-label">接口 Key（选填）</label>
                <input type="password" name="lanzou_parse_api_key" class="form-input" placeholder="<?php echo !empty($settings['lanzou_parse_api_key']) ? '已保存，留空不修改' : '如接口需要鉴权请填写'; ?>" value="">
                <div class="form-hint">部分私有接口需要 Key 鉴权；公开接口可留空。Key 仅在服务端使用，不会输出到前端。</div>
            </div>

            <h3 style="margin: 24px 0 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color);">人机验证设置</h3>

            <div style="background: var(--bg-subtle); padding: 16px; border-radius: var(--radius); margin-bottom: 16px;">
                <p style="font-size: 0.85rem; color: var(--text-light); margin-bottom: 12px;">
                    配置 Cloudflare Turnstile 后，可在登录、留言等场景要求用户完成人机验证，有效防止暴力破解和垃圾留言。请前往 <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener">Cloudflare Turnstile 控制台</a> 创建站点并获取密钥。
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
                <label for="turnstile_guestbook_enabled" style="margin-bottom: 0;">留言时启用 Turnstile 人机验证</label>
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

<script src="/assets/js/admin/admin-settings.js?v=<?php echo LM_VERSION; ?>"></script>

<?php require_once LM_ROOT . '/admin/template/footer.php'; ?>
