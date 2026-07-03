<?php
/**
 * 前台账号申请/注册页面 v2.0
 */
define('LM_ROOT', __DIR__);

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
Security::setSecurityHeaders();

// 已登录则跳转
if (isLoggedIn()) {
    Security::redirect('/');
}

$pageTitle = '账号申请';
$currentPage = 'register';

// 读取 GitHub 登录配置
$githubEnabled = (getSetting('github_oauth_enabled', '0') === '1');
$githubClientId = getSetting('github_client_id', '');
$githubClientSecret = getSetting('github_client_secret', '');
$githubLoginEnabled = $githubEnabled && !empty($githubClientId) && !empty($githubClientSecret);

$geetestCaptchaId = getSetting('geetest_captcha_id', '');
$geetestCaptchaKey = getSetting('geetest_captcha_key', '');
$geetestEnabled = $geetestCaptchaId !== '' && $geetestCaptchaKey !== '';

// 一次性门禁 token：验证通过后生成，绑定 session，10 分钟有效
// 必须通过 URL ?gate=TOKEN 携带，且 token 与 session 中存的匹配才算通过
// 这样复制 URL（不带 token）或换浏览器（session 不同）都会重新要求验证
$gateToken = isset($_GET['gate']) ? (string)$_GET['gate'] : '';
$geetestVerified = !$geetestEnabled;
if ($geetestEnabled && !empty($_SESSION['register_geetest_token'])) {
    $t = $_SESSION['register_geetest_token'];
    if (isset($t['token'], $t['expires'])
        && $t['expires'] > time()
        && hash_equals($t['token'], $gateToken)) {
        $geetestVerified = true;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['geetest_action'] ?? '') === 'verify_register_gate') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        http_response_code(403);
        Security::jsonResponse(['success' => false, 'message' => '安全验证失败，请刷新页面重试']);
    }
    if (!$geetestEnabled) {
        Security::jsonResponse(['success' => true, 'redirect' => '/register.php']);
    }
    $verifyResult = Security::verifyGeetestCaptcha(
        $geetestCaptchaId,
        $geetestCaptchaKey,
        $_POST['lot_number'] ?? '',
        $_POST['captcha_output'] ?? '',
        $_POST['pass_token'] ?? '',
        $_POST['gen_time'] ?? ''
    );
    if (!$verifyResult['success']) {
        http_response_code(403);
        Security::jsonResponse(['success' => false, 'message' => $verifyResult['error']]);
    }
    // 生成一次性 token，绑定 session，10 分钟有效
    $newToken = bin2hex(random_bytes(32));
    $_SESSION['register_geetest_token'] = [
        'token' => $newToken,
        'expires' => time() + 600,
    ];
    Security::jsonResponse(['success' => true, 'redirect' => '/register.php?gate=' . $newToken]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    $csrfOk = Security::validateToken($token);

    // 极验门禁二次校验：POST 时基于表单 hidden gate token 重新校验
    // 防止有人直接 POST 绕过 URL 检查
    $gateOk = true;
    if ($csrfOk && $geetestEnabled) {
        $postGate = (string)($_POST['gate'] ?? '');
        $gateOk = false;
        if (!empty($_SESSION['register_geetest_token'])) {
            $t = $_SESSION['register_geetest_token'];
            if (isset($t['token'], $t['expires'])
                && $t['expires'] > time()
                && hash_equals($t['token'], $postGate)) {
                $gateOk = true;
            }
        }
    }

    if (!$csrfOk) {
        $error = '安全验证失败，请刷新页面重试';
    } elseif (!$gateOk) {
        $error = '人机验证已失效，请刷新页面重新验证';
    } elseif (!Security::checkRateLimit(Security::getClientIp(), 'register_apply', 3, 3600)) {
        // 限流：每 IP 每小时最多 3 次注册申请，防刷
        $error = '申请提交过于频繁，请稍后再试';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $nickname = trim($_POST['nickname'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        // 校验网站 URL（仅 http/https，防 javascript: 等危险协议）
        if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
            $website = '';
        }

        // 验证
        if (empty($username) || empty($password) || empty($confirmPassword) || empty($email)) {
            $error = '请填写所有必填项';
        } elseif (strlen($username) < 3 || strlen($username) > 20) {
            $error = '用户名长度需在3-20个字符之间';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $error = '用户名只能包含字母、数字和下划线';
        } elseif ($password !== $confirmPassword) {
            $error = '两次输入的密码不一致';
        } elseif (strlen($password) < 6) {
            $error = '密码长度至少6位';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '邮箱格式不正确';
        } elseif (strlen($reason) < 10) {
            $error = '申请理由至少10个字符';
        } else {
            // 强密码策略：≥8位且包含大小写字母、数字、特殊字符，并拒绝常见弱口令
            // 与上面的长度检查分离，避免在密码短于 6 位时仍提示强度要求
            $strength = Security::checkPasswordStrength($password);
            if (!$strength['strong'] && $strength['score'] < 3) {
                $error = '密码强度不足：' . implode('；', $strength['errors']);
            }
        }

        if (empty($error)) {
            try {
                // 检查用户名是否已存在
                $exists = db()->fetchColumn(
                    "SELECT COUNT(*) FROM lm_admin WHERE username = ?",
                    [$username]
                );

                if ($exists) {
                    $error = '用户名已被注册';
                } else {
                    // 检查邮箱是否已存在
                    $emailExists = db()->fetchColumn(
                        "SELECT COUNT(*) FROM lm_admin WHERE email = ?",
                        [$email]
                    );

                    if ($emailExists) {
                        $error = '邮箱已被注册';
                    } else {
                        // 插入用户申请（状态为pending，需要管理员审核）
                        db()->insert('lm_user_apply', [
                            'username' => Security::xssClean($username),
                            'password' => Security::hashPassword($password),
                            'email' => Security::xssClean($email),
                            'nickname' => Security::xssClean($nickname),
                            'website' => $website ? Security::xssClean($website) : null,
                            'reason' => Security::xssClean($reason),
                            'ip' => Security::getClientIp(),
                            'status' => 'pending'
                        ]);

                        $success = '账号申请已提交，管理员审核通过后即可登录！';
                        // 提交成功后立即失效 token，防止复用
                        unset($_SESSION['register_geetest_token']);
                        $geetestVerified = false;
                        $gateToken = '';
                        $_POST = [];
                    }
                }
            } catch (Exception $e) {
                error_log('Register apply failed: ' . $e->getMessage());
                $error = '申请提交失败，请稍后重试';
            }
        }
    }
}

require_once LM_ROOT . '/template/header.php';
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/></svg> 账号申请</div>
    </div>
    <div class="card-body">
        <?php if ($geetestEnabled && !$geetestVerified): ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>
        <div id="geetest-register-gate" data-captcha-id="<?php echo e($geetestCaptchaId); ?>" data-csrf-name="<?php echo e(CSRF_TOKEN_NAME); ?>" data-csrf="<?php echo e(Security::generateToken()); ?>">
            <p style="color: var(--text-secondary); margin-bottom: 20px;">请先完成极验人机验证，通过后才能使用 GitHub 注册或提交账号申请。</p>
            <div id="geetest-container" style="margin-bottom: 16px;"></div>
            <button type="button" class="btn btn-primary" id="geetest-gate-btn" style="width: 100%;">开始验证</button>
            <div id="geetest-gate-message" style="margin-top: 12px; color: var(--text-light); font-size: 0.875rem;"></div>
        </div>
        <div style="text-align: center; margin-top: 24px; color: var(--text-light); font-size: 0.875rem;">
            <p>已有账号？<a href="/login.php">立即登录</a></p>
            <p style="margin-top: 8px;"><a href="/">← 返回首页</a></p>
        </div>
        <?php else: ?>
        <?php if ($githubLoginEnabled): ?>
        <a href="<?php echo e(getGithubLoginUrl()); ?>" class="btn" style="width: 100%; background: #24292f; color: #fff; display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 16px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
            使用 GitHub 注册
        </a>

        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px; color: var(--text-light); font-size: 0.875rem;">
            <span style="flex: 1; height: 1px; background: var(--border-color);"></span>
            <span>没有 GitHub？填写下方表单申请注册</span>
            <span style="flex: 1; height: 1px; background: var(--border-color);"></span>
        </div>
        <?php else: ?>
        <p style="color: var(--text-light); margin-bottom: 24px;">填写以下信息申请账号，管理员审核通过后即可登录使用。</p>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" data-validate>
            <?php echo Security::csrfField(); ?>
            <?php if ($geetestEnabled && $geetestVerified): ?>
            <input type="hidden" name="gate" value="<?php echo e($gateToken); ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">用户名 *</label>
                    <input type="text" name="username" class="form-input" placeholder="3-20位字母数字下划线" required
                           value="<?php echo isset($_POST['username']) ? e($_POST['username']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">昵称</label>
                    <input type="text" name="nickname" class="form-input" placeholder="显示名称"
                           value="<?php echo isset($_POST['nickname']) ? e($_POST['nickname']) : ''; ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">密码 *</label>
                    <input type="password" name="password" class="form-input" placeholder="至少6位" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">确认密码 *</label>
                    <input type="password" name="confirm_password" class="form-input" placeholder="再次输入密码" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">邮箱 *</label>
                    <input type="email" name="email" class="form-input" placeholder="your@email.com" required
                           value="<?php echo isset($_POST['email']) ? e($_POST['email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">个人网站</label>
                    <input type="url" name="website" class="form-input" placeholder="https://example.com"
                           value="<?php echo isset($_POST['website']) ? e($_POST['website']) : ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">申请理由 *</label>
                <textarea name="reason" class="form-textarea" placeholder="请简要说明您申请账号的原因和用途..." required style="min-height: 100px;"><?php echo isset($_POST['reason']) ? e($_POST['reason']) : ''; ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">提交申请</button>
        </form>
        
        <div style="text-align: center; margin-top: 24px; color: var(--text-light); font-size: 0.875rem;">
            <p>已有账号？<a href="/login.php">立即登录</a></p>
            <p style="margin-top: 8px;"><a href="/">← 返回首页</a></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($geetestEnabled && !$geetestVerified): ?>
<script src="https://static.geetest.com/v4/gt4.js"></script>
<script src="/assets/js/geetest-register.js?v=<?php echo LM_VERSION; ?>"></script>
<?php endif; ?>

<?php require_once LM_ROOT . '/template/sidebar.php'; ?>
