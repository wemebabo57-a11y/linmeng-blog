<?php
/**
 * 退出登录
 */
define('LM_ROOT', __DIR__);

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';

session_start();

// 清除会话
$_SESSION = [];

// 清除cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'samesite' => 'Strict'
    ]);
}

if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'samesite' => 'Strict'
    ]);
}

session_destroy();

Security::redirect('/');
