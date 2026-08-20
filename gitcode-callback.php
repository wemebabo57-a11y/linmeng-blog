<?php
/**
 * GitCode OAuth 登录回调处理
 *
 * 端点（与 Gitee 不同）：
 *   授权：  https://gitcode.com/oauth/authorize
 *   换 token：POST https://gitcode.com/oauth/token  (参数走 query string，文档未含 redirect_uri)
 *   用户信息：GET https://api.gitcode.com/api/v5/user  (Authorization: Bearer)
 */
define('LM_ROOT', __dir__);

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

// 检查是否启用 GitCode 登录
$gitcodeEnabled = (getSetting('gitcode_oauth_enabled', '0') === '1');
$clientId = getSetting('gitcode_client_id', '');
$clientSecret = getSetting('gitcode_client_secret', '');

if (!$gitcodeEnabled || empty($clientId) || empty($clientSecret)) {
    $_SESSION['gitcode_oauth_error'] = 'GitCode 登录未启用或配置不完整';
    Security::redirect('/login.php');
}

// 参数校验
$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$error = $_GET['error'] ?? '';
$errorDescription = $_GET['error_description'] ?? '';

if (!empty($error)) {
    $_SESSION['gitcode_oauth_error'] = 'GitCode 授权失败：' . ($errorDescription ?: $error);
    Security::redirect('/login.php');
}

if (empty($code) || empty($state) || empty($_SESSION['gitcode_oauth_state'])) {
    $_SESSION['gitcode_oauth_error'] = '授权参数不完整，请重试';
    Security::redirect('/login.php');
}

if (!hash_equals((string)$_SESSION['gitcode_oauth_state'], (string)$state)) {
    $_SESSION['gitcode_oauth_error'] = '安全校验失败，请重试';
    Security::redirect('/login.php');
}

// 清理 state
unset($_SESSION['gitcode_oauth_state']);

// 换取 access_token
// 官方文档：grant_type/code/client_id 走 query string，client_secret 走 form-data body
// 不传 redirect_uri（文档未列出）
$tokenUrl = 'https://gitcode.com/oauth/token?' . http_build_query([
    'grant_type' => 'authorization_code',
    'code' => $code,
    'client_id' => $clientId,
]);
$tokenResponse = Security::httpPostForm(
    $tokenUrl,
    ['client_secret' => $clientSecret],
    ['Accept: application/json'],
    30
);

if (!$tokenResponse['success']) {
    $_SESSION['gitcode_oauth_error'] = '获取 GitCode 授权信息失败';
    Security::redirect('/login.php');
}

$tokenData = json_decode($tokenResponse['response'], true);
if (empty($tokenData['access_token'])) {
    $errMsg = $tokenData['error_description'] ?? ($tokenData['error'] ?? ($tokenData['message'] ?? '未知错误'));
    $_SESSION['gitcode_oauth_error'] = 'GitCode 未返回授权凭证：' . $errMsg;
    Security::redirect('/login.php');
}

$accessToken = $tokenData['access_token'];

// 获取 GitCode 用户信息（api.gitcode.com 子域，Authorization: Bearer）
function gitcodeApiGet($url, $accessToken) {
    if (!function_exists('curl_init')) {
        return ['success' => false, 'response' => null, 'error' => 'cURL 扩展未启用'];
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Accept: application/json',
        'User-Agent: LinMeng-Blog-GitCode-OAuth'
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

$userResponse = gitcodeApiGet('https://api.gitcode.com/api/v5/user', $accessToken);
if (!$userResponse['success'] || $userResponse['http_code'] !== 200) {
    $_SESSION['gitcode_oauth_error'] = '获取 GitCode 用户信息失败';
    Security::redirect('/login.php');
}

$gitcodeUser = json_decode($userResponse['response'], true);
if (empty($gitcodeUser['id'])) {
    $_SESSION['gitcode_oauth_error'] = 'GitCode 用户数据异常';
    Security::redirect('/login.php');
}

$gitcodeId = (string)$gitcodeUser['id'];
$gitcodeLogin = trim($gitcodeUser['login'] ?? '');
$gitcodeName = trim($gitcodeUser['name'] ?? '');
$gitcodeEmail = trim($gitcodeUser['email'] ?? '');
$gitcodeAvatar = trim($gitcodeUser['avatar_url'] ?? '');
// GitCode 公开邮箱默认已验证；无公开邮箱时不用于绑定已有账号，仅用于新建账号
$gitcodeEmailVerified = !empty($gitcodeEmail);

try {
    // 尝试查找已绑定的用户
    $user = db()->fetchOne("SELECT * FROM lm_admin WHERE gitcode_id = ?", [$gitcodeId]);

    if (!$user && !empty($gitcodeEmail) && $gitcodeEmailVerified) {
        // 如果邮箱已存在，允许绑定到同一账号（仅限 GitCode 已验证邮箱）
        $user = db()->fetchOne("SELECT * FROM lm_admin WHERE email = ?", [$gitcodeEmail]);
        if ($user) {
            db()->update('lm_admin', [
                'gitcode_id' => $gitcodeId,
                'gitcode_username' => $gitcodeLogin
            ], 'id = ?', [$user['id']]);
            $user['gitcode_id'] = $gitcodeId;
        }
    }

    if (!$user) {
        // 创建新用户（GitCode 登录直接通过，无需管理员审核）
        $baseUsername = preg_replace('/[^a-zA-Z0-9_]/', '', $gitcodeLogin);
        $baseUsername = substr($baseUsername, 0, 20);
        if (strlen($baseUsername) < 3) {
            $baseUsername = 'gitcode_' . substr($gitcodeId, 0, 12);
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

        $email = !empty($gitcodeEmail) ? $gitcodeEmail : $username . '@gitcode.local';
        $nickname = !empty($gitcodeName) ? $gitcodeName : $gitcodeLogin;
        $avatar = !empty($gitcodeAvatar) ? $gitcodeAvatar : '';

        db()->insert('lm_admin', [
            'username' => $username,
            'password' => Security::hashPassword(Security::randomString(32)),
            'email' => $email,
            'nickname' => $nickname,
            'avatar' => $avatar,
            'role' => 'user',
            'status' => 1,
            'gitcode_id' => $gitcodeId,
            'gitcode_username' => $gitcodeLogin,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $user = db()->fetchOne("SELECT * FROM lm_admin WHERE gitcode_id = ?", [$gitcodeId]);
    }

    if (!$user || empty($user['id'])) {
        throw new Exception('用户创建失败');
    }

    if ((int)$user['status'] !== 1) {
        $_SESSION['gitcode_oauth_error'] = '账号已被禁用，请联系管理员';
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
        'fail_reason' => 'gitcode_oauth'
    ]);

    // 写入 Session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['is_admin'] = ($user['role'] === 'admin');

    session_regenerate_id(true);
    Security::redirect('/');
} catch (Exception $e) {
    $_SESSION['gitcode_oauth_error'] = '登录处理失败：' . $e->getMessage();
    Security::redirect('/login.php');
}
