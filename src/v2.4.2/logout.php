<?php
/**
 * 退出登录
 */
define('LM_ROOT', __DIR__);

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();

// 使数据库中的“记住我”令牌失效，防止旧令牌在退出后仍可自动登录
if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
    try {
        db()->update('lm_admin', ['remember_token' => null], 'id = ?', [(int)$_SESSION['user_id']]);
    } catch (Exception $e) {
        // 忽略：即使清库失败，下方仍会清除本地 Cookie 并销毁会话
    }
}

// 清除会话
$_SESSION = [];

// 清除cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'secure' => lm_is_https(),
        'samesite' => 'Lax'
    ]);
}

if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'secure' => lm_is_https(),
        'samesite' => 'Lax'
    ]);
}

session_destroy();

Security::redirect('/');
