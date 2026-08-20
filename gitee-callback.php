<?php
/**
 * Gitee OAuth 登录回调处理
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

// 检查是否启用 Gitee 登录
$giteeEnabled = (getSetting('gitee_oauth_enabled', '0') === '1');
$clientId = getSetting('gitee_client_id', '');
$clientSecret = getSetting('gitee_client_secret', '');

if (!$giteeEnabled || empty($clientId) || empty($clientSecret)) {
    $_SESSION['gitee_oauth_error'] = 'Gitee 登录未启用或配置不完整';
    Security::redirect('/login.php');
}

// 参数校验
$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$error = $_GET['error'] ?? '';
$errorDescription = $_GET['error_description'] ?? '';

if (!empty($error)) {
    $_SESSION['gitee_oauth_error'] = 'Gitee 授权失败：' . ($errorDescription ?: $error);
    Security::redirect('/login.php');
}

if (empty($code) || empty($state) || empty($_SESSION['gitee_oauth_state'])) {
    $_SESSION['gitee_oauth_error'] = '授权参数不完整，请重试';
    Security::redirect('/login.php');
}

if (!hash_equals((string)$_SESSION['gitee_oauth_state'], (string)$state)) {
    $_SESSION['gitee_oauth_error'] = '安全校验失败，请重试';
    Security::redirect('/login.php');
}

// 清理 state
unset($_SESSION['gitee_oauth_state']);

// 换取 access_token（Gitee token 接口为 form-encoded POST）
$redirectUri = rtrim(SITE_URL, '/') . '/gitee-callback.php';
$tokenResponse = Security::httpPostForm(
    'https://gitee.com/oauth/token',
    [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
    ],
    ['Accept: application/json'],
    30
);

if (!$tokenResponse['success']) {
    $_SESSION['gitee_oauth_error'] = '获取 Gitee 授权信息失败';
    Security::redirect('/login.php');
}

$tokenData = json_decode($tokenResponse['response'], true);
if (empty($tokenData['access_token'])) {
    $_SESSION['gitee_oauth_error'] = 'Gitee 未返回授权凭证：' . ($tokenData['error_description'] ?? ($tokenData['error'] ?? ($tokenData['message'] ?? '未知错误')));
    Security::redirect('/login.php');
}

$accessToken = $tokenData['access_token'];

// 获取 Gitee 用户信息（GET，access_token 作为查询参数）
function giteeApiGet($url, $accessToken) {
    if (!function_exists('curl_init')) {
        return ['success' => false, 'response' => null, 'error' => 'cURL 扩展未启用'];
    }
    $sep = (strpos($url, '?') === false) ? '?' : '&';
    $url = $url . $sep . 'access_token=' . urlencode($accessToken);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'User-Agent: LinMeng-Blog-Gitee-OAuth'
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'response' => null, 'error' => $error];
    }
    return ['success' => true, 'response' => $response, 'http_code' => $httpCode];
}

$userResponse = giteeApiGet('https://gitee.com/api/v5/user', $accessToken);
if (!$userResponse['success'] || $userResponse['http_code'] !== 200) {
    $_SESSION['gitee_oauth_error'] = '获取 Gitee 用户信息失败';
    Security::redirect('/login.php');
}

$giteeUser = json_decode($userResponse['response'], true);
if (empty($giteeUser['id'])) {
    $_SESSION['gitee_oauth_error'] = 'Gitee 用户数据异常';
    Security::redirect('/login.php');
}

$giteeId = (string)$giteeUser['id'];
$giteeLogin = trim($giteeUser['login'] ?? '');
$giteeName = trim($giteeUser['name'] ?? '');
$giteeEmail = trim($giteeUser['email'] ?? '');
$giteeAvatar = trim($giteeUser['avatar_url'] ?? '');
// Gitee 公开邮箱默认已验证；无公开邮箱时不用于绑定已有账号，仅用于新建账号
$giteeEmailVerified = !empty($giteeEmail);

try {
    // 尝试查找已绑定的用户
    $user = db()->fetchOne("SELECT * FROM lm_admin WHERE gitee_id = ?", [$giteeId]);

    if (!$user && !empty($giteeEmail) && $giteeEmailVerified) {
        // 如果邮箱已存在，允许绑定到同一账号（仅限 Gitee 已验证邮箱）
        $user = db()->fetchOne("SELECT * FROM lm_admin WHERE email = ?", [$giteeEmail]);
        if ($user) {
            db()->update('lm_admin', [
                'gitee_id' => $giteeId,
                'gitee_username' => $giteeLogin
            ], 'id = ?', [$user['id']]);
            $user['gitee_id'] = $giteeId;
        }
    }

    if (!$user) {
        // 创建新用户（Gitee 登录直接通过，无需管理员审核）
        $baseUsername = preg_replace('/[^a-zA-Z0-9_]/', '', $giteeLogin);
        $baseUsername = substr($baseUsername, 0, 20);
        if (strlen($baseUsername) < 3) {
            $baseUsername = 'gitee_' . substr($giteeId, 0, 14);
        }
        $username = $baseUsername;
        $counter = 1;
        while (db()->fetchColumn("SELECT COUNT(*) FROM lm_admin WHERE username = ?", [$username])) {
            $username = $baseUsername . '_' . $counter;
            $counter++;
            if ($counter > 100) {
                throw new Exception('无法生成可用用户名');
            }
        }

        $email = !empty($giteeEmail) ? $giteeEmail : $username . '@gitee.local';
        $nickname = !empty($giteeName) ? $giteeName : $giteeLogin;
        // 头像：保存 Gitee 头像地址，前台渲染时回退到默认头像
        $avatar = !empty($giteeAvatar) ? $giteeAvatar : '';

        db()->insert('lm_admin', [
            'username' => $username,
            'password' => Security::hashPassword(Security::randomString(32)),
            'email' => $email,
            'nickname' => $nickname,
            'avatar' => $avatar,
            'role' => 'user',
            'status' => 1,
            'gitee_id' => $giteeId,
            'gitee_username' => $giteeLogin,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $user = db()->fetchOne("SELECT * FROM lm_admin WHERE gitee_id = ?", [$giteeId]);
    }

    if (!$user || empty($user['id'])) {
        throw new Exception('用户创建失败');
    }

    if ((int)$user['status'] !== 1) {
        $_SESSION['gitee_oauth_error'] = '账号已被禁用，请联系管理员';
        Security::redirect('/login.php');
    }

    $ip = Security::getClientIp();

    // 写入登录信息
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
        'status' => 'success',
        'fail_reason' => 'gitee_oauth'
    ]);

    // 写入 Session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['is_admin'] = ($user['role'] === 'admin');

    session_regenerate_id(true);
    Security::redirect('/');
} catch (Exception $e) {
    $_SESSION['gitee_oauth_error'] = '登录处理失败：' . $e->getMessage();
    Security::redirect('/login.php');
}
