<?php
/**
 * 登录页面
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

$error = '';

// 读取 Turnstile 配置（登录专用密钥，留空回退到通用密钥）
$turnstileEnabled = (getSetting('turnstile_login_enabled', '0') === '1');
$turnstileSiteKey = getSetting('turnstile_login_site_key', '') ?: getSetting('turnstile_site_key', '');
$turnstileSecretKey = getSetting('turnstile_login_secret_key', '') ?: getSetting('turnstile_secret_key', '');

// 读取 GitHub 登录配置
$githubEnabled = (getSetting('github_oauth_enabled', '0') === '1');
$githubClientId = getSetting('github_client_id', '');
$githubClientSecret = getSetting('github_client_secret', '');
$githubLoginEnabled = $githubEnabled && !empty($githubClientId) && !empty($githubClientSecret);

// 显示 GitHub OAuth 错误
if (!empty($_SESSION['github_oauth_error'])) {
    $error = $_SESSION['github_oauth_error'];
    unset($_SESSION['github_oauth_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证CSRF
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        $error = '安全验证失败，请刷新页面重试';
    } else {
        // 验证 Cloudflare Turnstile 人机验证
        if ($turnstileEnabled && $turnstileSiteKey !== '' && $turnstileSecretKey !== '') {
            $turnstileToken = $_POST['cf-turnstile-response'] ?? '';
            $turnstileResult = Security::verifyTurnstileToken(
                $turnstileToken,
                $turnstileSecretKey,
                Security::getClientIp()
            );
            if (!$turnstileResult['success']) {
                $error = '人机验证失败：' . $turnstileResult['error'];
            }
        }

        if ($error === '') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $remember = isset($_POST['remember']);
            
            if (empty($username) || empty($password)) {
                $error = '请填写用户名和密码';
            } else {
                $ip = Security::getClientIp();
            
            // 检查是否被锁定
            $lockCheck = Security::checkLoginLock($username . '_' . $ip);
            if ($lockCheck['locked']) {
                $remaining = ceil($lockCheck['remaining'] / 60);
                $error = "登录尝试过多，请 {$remaining} 分钟后重试";
            } else {
                try {
                    $user = db()->fetchOne(
                        "SELECT * FROM lm_admin WHERE username = ? AND status = 1",
                        [$username]
                    );
                    
                    if ($user && Security::verifyPassword($password, $user['password'])) {
                        // 登录成功
                        Security::clearLoginFail($username . '_' . $ip);
                        
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['is_admin'] = ($user['role'] === 'admin');
                        
                        // 更新最后登录信息
                        db()->update('lm_admin', [
                            'last_login' => date('Y-m-d H:i:s'),
                            'last_ip' => $ip,
                            'login_fail_count' => 0,
                            'lock_until' => null
                        ], 'id = ?', [$user['id']]);
                        
                        // 记录登录日志
                        db()->insert('lm_login_log', [
                            'user_id' => $user['id'],
                            'username' => $user['username'],
                            'ip' => $ip,
                            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                            'status' => 'success'
                        ]);
                        
                        // 重新生成session ID防止会话固定攻击
                        session_regenerate_id(true);
                        
                        if ($remember) {
                            $rememberToken = Security::randomString(32);
                            // 将token哈希存储到数据库
                            db()->update('lm_admin', [
                                'remember_token' => hash('sha256', $rememberToken)
                            ], 'id = ?', [$user['id']]);
                            
                            setcookie('remember_token', $rememberToken, [
                                'expires' => time() + 30 * 86400,
                                'path' => '/',
                                'httponly' => true,
                                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                                'samesite' => 'Strict'
                            ]);
                        }
                        
                        Security::redirect('/');
                    } else {
                        // 登录失败
                        $isLocked = Security::recordLoginFail($username . '_' . $ip);
                        
                        // 记录失败日志
                        db()->insert('lm_login_log', [
                            'user_id' => $user ? $user['id'] : null,
                            'username' => $username,
                            'ip' => $ip,
                            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                            'status' => 'fail',
                            'fail_reason' => $user ? '密码错误' : '用户不存在'
                        ]);
                        
                        if ($isLocked) {
                            $error = '登录尝试过多，账号已锁定30分钟';
                        } else {
                            $error = '用户名或密码错误';
                        }
                    }
                } catch (Exception $e) {
                    $error = '登录失败，请稍后重试';
                }
            }
        }
    }
}
}

$pageTitle = '登录';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?> - <?php echo e(getSetting('site_name', '林梦的博客')); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo LM_VERSION; ?>">
    <link rel="stylesheet" href="/assets/css/design-system.css?v=<?php echo LM_VERSION; ?>">
    <!-- 与全站统一的字体：Playfair Display + LXGW WenKai -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/lxgw-wenkai-webfont@1.7.0/style.css" rel="stylesheet">
    <?php if ($turnstileEnabled && $turnstileSiteKey !== ''): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
</head>
<body>
    <div class="login-page">
        <div class="login-box">
            <div class="login-title">欢迎回来</div>
            <div class="login-subtitle">登录到 <?php echo e(getSetting('site_name', '林梦的博客')); ?></div>
            
            <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom: 20px;"><?php echo e($error); ?></div>
            <?php endif; ?>
            
            <?php if ($githubLoginEnabled): ?>
            <a href="<?php echo e(getGithubLoginUrl()); ?>" class="btn" style="width: 100%; background: #24292f; color: #fff; display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 16px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                使用 GitHub 登录
            </a>

            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px; color: var(--text-light); font-size: 0.875rem;">
                <span style="flex: 1; height: 1px; background: var(--border-color);"></span>
                <span>或使用账号密码登录</span>
                <span style="flex: 1; height: 1px; background: var(--border-color);"></span>
            </div>
            <?php endif; ?>

            <form method="POST" action="" data-validate>
                <?php echo Security::csrfField(); ?>
                
                <div class="form-group">
                    <label class="form-label">用户名</label>
                    <input type="text" name="username" class="form-input" placeholder="请输入用户名" required autofocus>
                </div>
                
                <div class="form-group">
                    <label class="form-label">密码</label>
                    <input type="password" name="password" class="form-input" placeholder="请输入密码" required>
                </div>
                
                <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="remember" id="remember" style="width: auto;">
                    <label for="remember" style="margin-bottom: 0; font-weight: normal;">记住我</label>
                </div>

                <?php if ($turnstileEnabled && $turnstileSiteKey !== ''): ?>
                <div class="form-group">
                    <div id="turnstile-widget" class="cf-turnstile" data-sitekey="<?php echo e($turnstileSiteKey); ?>" data-theme="light"></div>
                </div>
                <?php endif; ?>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">登录</button>
            </form>
            
            <div style="text-align: center; margin-top: 24px; color: var(--text-light); font-size: 0.875rem;">
                <p>没有 GitHub？<a href="/register.php">申请注册</a></p>
                <p style="margin-top: 8px;"><a href="/">← 返回首页</a></p>
            </div>
        </div>
    </div>
    
    <script src="/assets/js/main.js?v=<?php echo LM_VERSION; ?>"></script>
</body>
</html>
