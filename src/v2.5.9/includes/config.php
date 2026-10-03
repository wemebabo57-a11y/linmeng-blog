<?php
/**
 * 博客 - 配置文件
 * 由安装向导（/setup/）生成 .env 后读取；敏感配置不提供硬编码回退值。
 */

// 防止直接访问
if (!defined('APP_ROOT')) {
    die('Access Denied');
}

// 程序版本号
define('APP_VERSION', '2.5.9');

// 从项目根目录 .env 和服务器环境变量读取配置。敏感配置没有硬编码回退值。
$env = [];
$envFile = APP_ROOT . '/.env';
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
define('DB_PASS', $requiredEnv('DB_PASS'));
define('DB_CHARSET', 'utf8mb4');

// 网站基础配置
define('SITE_URL', $envValue('SITE_URL', 'https://example.com'));
define('SITE_PATH', $envValue('SITE_PATH', ''));

// 代理/Cloudflare 信任开关：仅在站点位于可信反向代理或 Cloudflare 之后时设为 true
// 开启后才会采信 X-Forwarded-For / X-Forwarded-Proto / CF-Connecting-IP 等头部
if ($envValue('APP_TRUST_PROXY', 'false') === 'true') {
    define('APP_TRUST_PROXY', true);
}

// 安全密钥（AES-256-GCM 优先，不支持时回退 AES-256-CBC；一旦设定后不要变更）
define('SECRET_KEY', $requiredEnv('SECRET_KEY'));
define('CSRF_TOKEN_NAME', 'app_csrf_token');

// 会话配置
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (defined('APP_TRUST_PROXY') && APP_TRUST_PROXY
            && !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

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
define('UPLOAD_PATH', APP_ROOT . '/assets/uploads/');
