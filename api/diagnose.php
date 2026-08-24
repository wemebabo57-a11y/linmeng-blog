<?php
/**
 * 图床上传诊断脚本（仅管理员）
 * 访问：https://你的域名/api/diagnose.php
 */

// ===== 管理员鉴权：本脚本输出服务器环境信息，严禁匿名访问 =====
if (!defined('LM_ROOT')) {
    define('LM_ROOT', dirname(__DIR__));
}
require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';
session_start();
if (!isAdmin()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Access Denied');
}

error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: text/plain; charset=utf-8');

echo "===== 图床上传环境诊断 =====\n";
echo "诊断时间: " . date('Y-m-d H:i:s') . "\n\n";

// 1. PHP 版本
echo "[1] PHP 版本\n";
echo "    版本: " . PHP_VERSION . "\n";
echo "    SAPI: " . php_sapi_name() . "\n\n";

// 2. 关键扩展
echo "[2] 关键扩展检查\n";
$extensions = ['gd', 'curl', 'fileinfo', 'json', 'pdo', 'pdo_mysql', 'openssl'];
foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    echo "    " . ($loaded ? '✓' : '✗') . " {$ext}\n";
    if (!$loaded && in_array($ext, ['gd', 'curl', 'fileinfo'])) {
        echo "        ⚠ {$ext} 缺失会导致上传失败！\n";
    }
}
echo "\n";

// 3. GD 详情
echo "[3] GD 扩展详情\n";
if (extension_loaded('gd')) {
    $gd = gd_info();
    echo "    GD 版本: " . ($gd['GD Version'] ?? 'unknown') . "\n";
    echo "    JPEG: " . ($gd['JPEG Support'] ? '✓' : '✗') . "\n";
    echo "    PNG: " . ($gd['PNG Support'] ? '✓' : '✗') . "\n";
    echo "    GIF: " . ($gd['GIF Read Support'] ? '✓' : '✗') . "\n";
    echo "    WebP: " . ($gd['WebP Support'] ?? false ? '✓' : '✗') . "\n";
} else {
    echo "    ✗ GD 扩展未安装\n";
}
echo "\n";

// 4. fileinfo 测试
echo "[4] fileinfo 扩展测试\n";
if (extension_loaded('fileinfo')) {
    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo !== false) {
        echo "    ✓ finfo_open 成功\n";
        finfo_close($finfo);
    } else {
        echo "    ✗ finfo_open 失败（可能是 magic 文件缺失）\n";
    }
} else {
    echo "    ✗ fileinfo 扩展未安装\n";
}
echo "\n";

// 5. cURL 测试
echo "[5] cURL 扩展测试\n";
if (extension_loaded('curl')) {
    $cv = curl_version();
    echo "    cURL 版本: " . $cv['version'] . "\n";
    echo "    SSL 版本: " . $cv['ssl_version'] . "\n";
    if (function_exists('curl_init')) {
        $ch = @curl_init();
        if ($ch !== false) {
            echo "    ✓ curl_init 成功\n";
            curl_close($ch);
        } else {
            echo "    ✗ curl_init 失败\n";
        }
    }
} else {
    echo "    ✗ cURL 扩展未安装\n";
}
echo "\n";

// 6. PHP 配置
echo "[6] PHP 上传相关配置\n";
$configs = [
    'file_uploads' => ini_get('file_uploads'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'open_basedir' => ini_get('open_basedir'),
    'upload_tmp_dir' => ini_get('upload_tmp_dir'),
];
foreach ($configs as $key => $val) {
    echo "    {$key} = " . ($val !== false && $val !== '' ? $val : '(空)') . "\n";
}
echo "\n";

// 7. 上传目录
echo "[7] 上传目录权限\n";
$uploadDir = dirname(__DIR__) . '/assets/uploads/';
echo "    路径: {$uploadDir}\n";
echo "    存在: " . (is_dir($uploadDir) ? '✓' : '✗') . "\n";
echo "    可写: " . (is_writable($uploadDir) ? '✓' : '✗') . "\n";
if (is_dir($uploadDir)) {
    $perms = substr(sprintf('%o', fileperms($uploadDir)), -4);
    echo "    权限: {$perms}\n";
}
echo "\n";

// 8. 临时目录
echo "[8] 临时目录\n";
$tmpDir = sys_get_temp_dir();
echo "    系统临时目录: {$tmpDir}\n";
echo "    存在: " . (is_dir($tmpDir) ? '✓' : '✗') . "\n";
echo "    可写: " . (is_writable($tmpDir) ? '✓' : '✗') . "\n";
if (is_dir($tmpDir)) {
    $df = @disk_free_space($tmpDir);
    if ($df !== false) {
        echo "    可用空间: " . round($df / 1024 / 1024, 2) . " MB\n";
        if ($df < 10 * 1024 * 1024) {
            echo "    ⚠ 可用空间不足 10MB，可能导致上传失败！\n";
        }
    }
}
echo "\n";

// 9. 配置文件
echo "[9] 配置文件检查\n";
$configFile = dirname(__DIR__) . '/includes/config.php';
echo "    config.php: " . (file_exists($configFile) ? '✓ 存在' : '✗ 不存在') . "\n";
if (file_exists($configFile)) {
    require_once $configFile;
    echo "    GITHUB_GALLERY_TOKEN: " . (defined('GITHUB_GALLERY_TOKEN') && !empty(GITHUB_GALLERY_TOKEN) ? '✓ 已配置' : '✗ 未配置') . "\n";
    echo "    GITHUB_GALLERY_REPO: " . (defined('GITHUB_GALLERY_REPO') && !empty(GITHUB_GALLERY_REPO) ? '✓ 已配置' : '✗ 未配置') . "\n";
}
echo "\n";

// 10. 函数可用性
echo "[10] 关键函数可用性\n";
$functions = [
    'imagecreatefromjpeg',
    'imagecreatefrompng',
    'imagecreatefromgif',
    'imagecreatefromwebp',
    'finfo_open',
    'finfo_file',
    'curl_init',
    'curl_exec',
    'getimagesize',
];
foreach ($functions as $func) {
    echo "    " . (function_exists($func) ? '✓' : '✗') . " {$func}()\n";
}
echo "\n";

// 11. 错误日志
echo "[11] PHP 错误日志配置\n";
echo "    log_errors: " . ini_get('log_errors') . "\n";
echo "    error_log: " . ini_get('error_log') . "\n";
echo "\n";

echo "===== 诊断完成 =====\n";
echo "\n提示：如果看到 ✗ 标记，说明该项存在问题，需要修复。\n";
