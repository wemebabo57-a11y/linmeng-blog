<?php
/**
 * GitHub OAuth 登录回调处理
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

// 检查是否启用 GitHub 登录
$githubEnabled = (getSetting('github_oauth_enabled', '0') === '1');
$clientId = getSetting('github_client_id', '');
$clientSecret = getSetting('github_client_secret', '');

if (!$githubEnabled || empty($clientId) || empty($clientSecret)) {
    $_SESSION['github_oauth_error'] = 'GitHub 登录未启用或配置不完整';
    Security::redirect('/login.php');
}

// 参数校验
$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$error = $_GET['error'] ?? '';
$errorDescription = $_GET['error_description'] ?? '';

if (!empty($error)) {
    $_SESSION['github_oauth_error'] = 'GitHub 授权失败：' . ($errorDescription ?: $error);
    Security::redirect('/login.php');
}

if (empty($code) || empty($state) || empty($_SESSION['github_oauth_state'])) {
    $_SESSION['github_oauth_error'] = '授权参数不完整，请重试';
    Security::redirect('/login.php');
}

if (!hash_equals((string)$_SESSION['github_oauth_state'], (string)$state)) {
    $_SESSION['github_oauth_error'] = '安全校验失败，请重试';
    Security::redirect('/login.php');
}

// 清理 state
unset($_SESSION['github_oauth_state']);

// 换取 access_token
$redirectUri = rtrim(SITE_URL, '/') . '/github-callback.php';
$tokenResponse = Security::httpPostJson(
    'https://github.com/login/oauth/access_token',
    [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'code' => $code,
        'redirect_uri' => $redirectUri,
    ],
    ['Accept: application/json'],
    30
);

if (!$tokenResponse['success']) {
    $_SESSION['github_oauth_error'] = '获取 GitHub 授权信息失败';
    Security::redirect('/login.php');
}

$tokenData = json_decode($tokenResponse['response'], true);
if (empty($tokenData['access_token'])) {
    $_SESSION['github_oauth_error'] = 'GitHub 未返回授权凭证：' . ($tokenData['error_description'] ?? ($tokenData['error'] ?? '未知错误'));
    Security::redirect('/login.php');
}

$accessToken = $tokenData['access_token'];

// 获取 GitHub 用户信息
function githubApiGet($url, $accessToken) {
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
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: LinMeng-Blog-GitHub-OAuth'
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

$userResponse = githubApiGet('https://api.github.com/user', $accessToken);
if (!$userResponse['success'] || $userResponse['http_code'] !== 200) {
    $_SESSION['github_oauth_error'] = '获取 GitHub 用户信息失败';
    Security::redirect('/login.php');
}

$githubUser = json_decode($userResponse['response'], true);
if (empty($githubUser['id'])) {
    $_SESSION['github_oauth_error'] = 'GitHub 用户数据异常';
    Security::redirect('/login.php');
}

$githubId = (string)$githubUser['id'];
$githubLogin = trim($githubUser['login'] ?? '');
$githubName = trim($githubUser['name'] ?? '');
$githubEmail = trim($githubUser['email'] ?? '');
$githubAvatar = trim($githubUser['avatar_url'] ?? '');
// 跟踪 GitHub 邮箱是否已验证（公开邮箱默认已验证），防止未验证邮箱绑定导致账号接管
$githubEmailVerified = !empty($githubEmail);

// 若未公开邮箱，尝试获取用户邮箱列表
if (empty($githubEmail)) {
    $emailsResponse = githubApiGet('https://api.github.com/user/emails', $accessToken);
    if ($emailsResponse['success'] && $emailsResponse['http_code'] === 200) {
        $emails = json_decode($emailsResponse['response'], true);
        if (is_array($emails)) {
            // 仅选择 primary 且 verified 的邮箱用于绑定，防止未验证邮箱导致账号接管
            foreach ($emails as $emailItem) {
                if (!empty($emailItem['primary']) && !empty($emailItem['verified']) && !empty($emailItem['email'])) {
                    $githubEmail = trim($emailItem['email']);
                    $githubEmailVerified = true;
                    break;
                }
            }
            // 回退：未找到 primary+verified 邮箱时，使用任意邮箱仅用于新账号创建（不用于绑定）
            if (empty($githubEmail)) {
                foreach ($emails as $emailItem) {
                    if (!empty($emailItem['email'])) {
                        $githubEmail = trim($emailItem['email']);
                        break;
                    }
                }
            }
        }
    }
}

try {
    // 尝试查找已绑定的用户
    $user = db()->fetchOne("SELECT * FROM lm_admin WHERE github_id = ?", [$githubId]);

    if (!$user && !empty($githubEmail) && $githubEmailVerified) {
        // 如果邮箱已存在，允许绑定到同一账号（仅限 GitHub 已验证邮箱）
        $user = db()->fetchOne("SELECT * FROM lm_admin WHERE email = ?", [$githubEmail]);
        if ($user) {
            db()->update('lm_admin', [
                'github_id' => $githubId,
                'github_username' => $githubLogin
            ], 'id = ?', [$user['id']]);
            $user['github_id'] = $githubId;
        }
    }

    if (!$user) {
        // 创建新用户（GitHub 登录直接通过，无需管理员审核）
        $baseUsername = preg_replace('/[^a-zA-Z0-9_]/', '', $githubLogin);
        $baseUsername = substr($baseUsername, 0, 20);
        if (strlen($baseUsername) < 3) {
            $baseUsername = 'gh_' . substr($githubId, 0, 16);
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

        $email = !empty($githubEmail) ? $githubEmail : $username . '@github.local';
        $nickname = !empty($githubName) ? $githubName : $githubLogin;

        db()->insert('lm_admin', [
            'username' => $username,
            'password' => Security::hashPassword(Security::randomString(32)),
            'email' => $email,
            'nickname' => $nickname,
            'role' => 'user',
            'status' => 1,
            'github_id' => $githubId,
            'github_username' => $githubLogin,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $user = db()->fetchOne("SELECT * FROM lm_admin WHERE github_id = ?", [$githubId]);
    }

    if (!$user || empty($user['id'])) {
        throw new Exception('用户创建失败');
    }

    if ((int)$user['status'] !== 1) {
        $_SESSION['github_oauth_error'] = '账号已被禁用，请联系管理员';
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
        'fail_reason' => 'github_oauth'
    ]);

    // 写入 Session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['is_admin'] = ($user['role'] === 'admin');

    session_regenerate_id(true);
    Security::redirect('/');
} catch (Exception $e) {
    $_SESSION['github_oauth_error'] = '登录处理失败：' . $e->getMessage();
    Security::redirect('/login.php');
}
