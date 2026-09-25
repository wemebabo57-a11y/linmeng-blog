<?php
/**
 * 林梦博客 - 配置文件
 * 由 /setup/ 安装向导生成运行配置
 */

// 防止直接访问
if (!defined('LM_ROOT')) {
    die('Access Denied');
}

// 程序版本号
define('LM_VERSION', '2.4.2');

// 从项目根目录 .env 和服务器环境变量读取配置。敏感配置没有硬编码回退值。
$env = [];
$envFile = LM_ROOT . '/.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $value = trim($value);
        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }
        $env[trim($key)] = $value;
    }
}
$envValue = static function ($key, $fallback = '') use ($env) {
    if (array_key_exists($key, $env) && $env[$key] !== '') {
        return $env[$key];
    }
    $serverValue = getenv($key);
    return $serverValue !== false && $serverValue !== '' ? $serverValue : $fallback;
};
$requiredEnv = static function ($key) use ($envValue) {
    $value = $envValue($key, '');
    if ($value === '') {
        error_log('Missing required configuration: ' . $key);
        die('网站配置缺失，请检查 .env 文件');
    }
    return $value;
};

// 数据库配置
define('DB_HOST', $envValue('DB_HOST', 'localhost'));
define('DB_PORT', (int)$envValue('DB_PORT', '3306'));
define('DB_NAME', $requiredEnv('DB_NAME'));
define('DB_USER', $requiredEnv('DB_USER'));
define('DB_PASS', $envValue('DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');

// 网站基础配置
define('SITE_URL', $envValue('SITE_URL', 'https://example.com'));
define('SITE_PATH', $envValue('SITE_PATH', ''));

// 此参数由安装向导在服务器本地生成，不应提交至版本库。
define('SECRET_KEY', $requiredEnv('SECRET_KEY'));
define('CSRF_TOKEN_NAME', 'lm_csrf_token');

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

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 错误显示（生产环境建议关闭）
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// 上传配置
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024);
define('UPLOAD_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('UPLOAD_PATH', LM_ROOT . '/assets/uploads/');
