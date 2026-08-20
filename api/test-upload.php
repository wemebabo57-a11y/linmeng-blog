<?php
/**
 * 图床上传流程测试脚本（仅管理员）
 * 访问：https://你的域名/api/test-upload.php
 */

// ===== 管理员鉴权：本脚本执行上传流程测试，严禁匿名访问 =====
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
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

echo "===== 图床上传流程测试 =====\n\n";

echo "[1] 加载配置文件...\n";
try {
    require_once LM_ROOT . '/includes/config.php';
    echo "    ✓ config.php 加载成功\n";
} catch (Throwable $e) {
    echo "    ✗ config.php 加载失败: " . $e->getMessage() . "\n";
    exit;
}

echo "\n[2] 加载 Security 类...\n";
try {
    require_once LM_ROOT . '/includes/Security.php';
    echo "    ✓ Security.php 加载成功\n";
} catch (Throwable $e) {
    echo "    ✗ Security.php 加载失败: " . $e->getMessage() . "\n";
    exit;
}

echo "\n[3] 加载 Database 类...\n";
try {
    require_once LM_ROOT . '/includes/Database.php';
    echo "    ✓ Database.php 加载成功\n";
} catch (Throwable $e) {
    echo "    ✗ Database.php 加载失败: " . $e->getMessage() . "\n";
    exit;
}

echo "\n[4] 加载 functions.php...\n";
try {
    require_once LM_ROOT . '/includes/functions.php';
    echo "    ✓ functions.php 加载成功\n";
} catch (Throwable $e) {
    echo "    ✗ functions.php 加载失败: " . $e->getMessage() . "\n";
    exit;
}

echo "\n[5] 测试 finfo_open...\n";
try {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        echo "    ✗ finfo_open 返回 false\n";
    } else {
        echo "    ✓ finfo_open 成功\n";
        finfo_close($finfo);
    }
} catch (Throwable $e) {
    echo "    ✗ finfo_open 抛出异常: " . get_class($e) . ': ' . $e->getMessage() . "\n";
}

echo "\n[6] 测试创建测试图片...\n";
try {
    $testImg = imagecreatetruecolor(100, 100);
    if ($testImg === false) {
        echo "    ✗ imagecreatetruecolor 失败\n";
    } else {
        echo "    ✓ imagecreatetruecolor 成功\n";
        
        // 测试写入临时目录
        $tmpFile = sys_get_temp_dir() . '/test_' . uniqid() . '.jpg';
        echo "    测试临时文件: {$tmpFile}\n";
        $saved = imagejpeg($testImg, $tmpFile, 90);
        if (!$saved) {
            echo "    ✗ imagejpeg 写入临时目录失败\n";
        } else {
            echo "    ✓ imagejpeg 写入临时目录成功\n";
            
            // 测试 finfo_file
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $tmpFile);
                echo "    检测到的 MIME: {$mime}\n";
                finfo_close($finfo);
            }
            
            unlink($tmpFile);
        }
        imagedestroy($testImg);
    }
} catch (Throwable $e) {
    echo "    ✗ 图片处理异常: " . get_class($e) . ': ' . $e->getMessage() . "\n";
}

echo "\n[7] 测试上传目录写入...\n";
$uploadDir = LM_ROOT . '/assets/uploads/';
echo "    上传目录: {$uploadDir}\n";
if (!is_dir($uploadDir)) {
    echo "    ✗ 上传目录不存在\n";
} elseif (!is_writable($uploadDir)) {
    echo "    ✗ 上传目录不可写\n";
} else {
    $testFile = $uploadDir . 'test_' . uniqid() . '.txt';
    try {
        $written = file_put_contents($testFile, 'test');
        if ($written === false) {
            echo "    ✗ file_put_contents 失败\n";
        } else {
            echo "    ✓ 写入测试文件成功\n";
            unlink($testFile);
        }
    } catch (Throwable $e) {
        echo "    ✗ 写入异常: " . $e->getMessage() . "\n";
    }
}

echo "\n[8] 测试 Security::validateUpload (模拟)...\n";
// 创建一个模拟的上传文件数组
$testImgPath = sys_get_temp_dir() . '/mock_upload_' . uniqid() . '.jpg';
$img = imagecreatetruecolor(200, 200);
imagejpeg($img, $testImgPath, 90);
imagedestroy($img);

$mockFile = [
    'name' => 'test.jpg',
    'type' => 'image/jpeg',
    'tmp_name' => $testImgPath,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($testImgPath)
];

echo "    模拟文件大小: " . $mockFile['size'] . " bytes\n";

try {
    // 直接调用 validateUpload
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        echo "    ✗ finfo_open 失败\n";
    } else {
        $mime = finfo_file($finfo, $mockFile['tmp_name']);
        finfo_close($finfo);
        echo "    检测到的 MIME: {$mime}\n";
        
        if (defined('UPLOAD_ALLOWED_TYPES')) {
            echo "    UPLOAD_ALLOWED_TYPES: " . implode(', ', UPLOAD_ALLOWED_TYPES) . "\n";
            if (in_array($mime, UPLOAD_ALLOWED_TYPES)) {
                echo "    ✓ MIME 类型允许\n";
            } else {
                echo "    ✗ MIME 类型不在白名单中\n";
            }
        }
    }
} catch (Throwable $e) {
    echo "    ✗ validateUpload 异常: " . get_class($e) . ': ' . $e->getMessage() . "\n";
    echo "    异常位置: " . $e->getFile() . ':' . $e->getLine() . "\n";
}

unlink($testImgPath);

echo "\n[9] 测试 Security::reprocessImage (模拟)...\n";
$srcPath = sys_get_temp_dir() . '/src_' . uniqid() . '.jpg';
$dstPath = sys_get_temp_dir() . '/dst_' . uniqid() . '.jpg';
$img = imagecreatetruecolor(150, 150);
imagejpeg($img, $srcPath, 90);
imagedestroy($img);

try {
    $result = Security::reprocessImage($srcPath, $dstPath, 'image/jpeg');
    if ($result) {
        echo "    ✓ reprocessImage 成功\n";
        if (file_exists($dstPath)) {
            echo "    ✓ 输出文件存在，大小: " . filesize($dstPath) . " bytes\n";
            unlink($dstPath);
        }
    } else {
        echo "    ✗ reprocessImage 返回 false\n";
    }
} catch (Throwable $e) {
    echo "    ✗ reprocessImage 异常: " . get_class($e) . ': ' . $e->getMessage() . "\n";
    echo "    异常位置: " . $e->getFile() . ':' . $e->getLine() . "\n";
}

unlink($srcPath);

echo "\n[10] 测试 cURL (GitHub API)...\n";
if (defined('GITHUB_GALLERY_TOKEN') && !empty(GITHUB_GALLERY_TOKEN)) {
    echo "    GitHub Token: " . substr(GITHUB_GALLERY_TOKEN, 0, 6) . "****\n";
} else {
    echo "    ✗ GitHub Token 未配置\n";
}

if (defined('GITHUB_GALLERY_REPO') && !empty(GITHUB_GALLERY_REPO)) {
    echo "    GitHub Repo: " . GITHUB_GALLERY_REPO . "\n";
} else {
    echo "    ✗ GitHub Repo 未配置\n";
}

echo "\n===== 测试完成 =====\n";
