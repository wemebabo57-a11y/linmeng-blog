<?php
/**
 * 林梦博客 - 配置文件
 *
 * 数据库凭据、密钥等敏感配置统一从 .env 文件读取。.env 由 setup.php 在安装时
 * 生成，且已在 .gitignore 中忽略，永远不会进入版本库。本文件本身不含任何敏感信息。
 *
 * 首次部署时若 .env 不存在或尚未配置数据库 / 密钥，会自动跳转到 /setup.php
 * 引导安装（setup.php 自身不引入本文件，不会产生循环跳转）。
 */

// 防止直接访问
if (!defined('LM_ROOT')) {
    die('Access Denied');
}

// 程序版本号
define('LM_VERSION', '2.2.2');

/**
 * 解析 .env 文件为键值数组（仅做 KEY=VALUE 解析，不写入超全局）
 */
function lm_load_env($path) {
    $env = [];
    if (!is_file($path)) {
        return $env;
    }
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return $env;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $key = trim(substr($line, 0, $eq));
        $val = substr($line, $eq + 1);
        $len = strlen($val);
        // 去除两侧成对引号；双引号值需反转义 setup.php 写入的转义序列
        if ($len >= 2) {
            $first = $val[0];
            $last  = $val[$len - 1];
            if ($first === '"' && $last === '"') {
                $val = substr($val, 1, -1);
                // 反转义 \\ → \，\" → "（与 setup.php 的写入逻辑对应）
                $val = preg_replace_callback('/\\\\(.)/', function ($m) {
                    return $m[1];
                }, $val);
            } elseif ($first === "'" && $last === "'") {
                $val = substr($val, 1, -1);
            }
        }
        if ($key !== '') {
            $env[$key] = $val;
        }
    }
    return $env;
}

$lmEnv = lm_load_env(LM_ROOT . '/.env');

// 数据库配置
define('DB_HOST', isset($lmEnv['DB_HOST']) && $lmEnv['DB_HOST'] !== '' ? $lmEnv['DB_HOST'] : 'localhost');
define('DB_NAME', $lmEnv['DB_NAME'] ?? '');
define('DB_USER', $lmEnv['DB_USER'] ?? '');
define('DB_PASS', $lmEnv['DB_PASS'] ?? '');
define('DB_CHARSET', 'utf8mb4');

// 网站基础配置
define('SITE_URL', $lmEnv['SITE_URL'] ?? '');
define('SITE_PATH', $lmEnv['SITE_PATH'] ?? '');

// 安全密钥（AES-256-CBC 加密用，一旦设定后不要变更，否则已加密数据无法解密）
define('SECRET_KEY', $lmEnv['SECRET_KEY'] ?? '');
define('CSRF_TOKEN_NAME', 'lm_csrf_token');

// 是否信任 X-Forwarded-For（仅当服务器明确位于反向代理后且代理已覆盖此头时设为 true）
// Cloudflare 用户无需启用（自动走 HTTP_CF_CONNECTING_IP）
define('LM_TRUST_PROXY', isset($lmEnv['LM_TRUST_PROXY']) ? (strtolower($lmEnv['LM_TRUST_PROXY']) === 'true' || $lmEnv['LM_TRUST_PROXY'] === '1') : false);

// 登录防暴力破解参数（可选，Security 类内置默认值）
define('LM_LOGIN_MAX_ATTEMPTS', 5);
define('LM_LOGIN_LOCKOUT_TIME', 1800);

// 会话配置
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

session_set_cookie_params([
    'lifetime' => 7200,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax'
]);

ini_set('session.gc_maxlifetime', 7200);
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 错误显示（生产环境关闭显示，仅记录日志）
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// 上传配置
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024);
define('UPLOAD_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('UPLOAD_PATH', LM_ROOT . '/assets/uploads/');

// 释放临时变量，避免污染包含方作用域
unset($lmEnv);

// ------------------------------------------------------------------
// 安装引导：尚未配置数据库或密钥时，跳转到 setup.php
// ------------------------------------------------------------------
if (DB_NAME === '' || SECRET_KEY === '') {
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'setup.php') {
        $docRoot  = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/')) : '';
        $appRoot  = str_replace('\\', '/', LM_ROOT);
        $sitePath = ($docRoot !== '' && strpos($appRoot, $docRoot) === 0) ? substr($appRoot, strlen($docRoot)) : '';
        $setupUrl = rtrim($sitePath, '/') . '/setup.php';

        if (!headers_sent()) {
            header('Location: ' . $setupUrl, true, 302);
        }
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><title>安装引导</title></head><body>';
        echo '<p style="font-family:sans-serif;text-align:center;margin-top:15vh">尚未安装，正在跳转到安装程序…';
        echo '如未自动跳转，请访问 <a href="' . htmlspecialchars($setupUrl, ENT_QUOTES, 'UTF-8') . '">setup.php</a></p>';
        echo '</body></html>';
        exit;
    }
}
