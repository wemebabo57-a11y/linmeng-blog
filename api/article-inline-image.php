<?php
/**
 * 文章内联图片上传 API
 * 仅限管理员调用，用于在文章内容光标处插入图片
 * 返回 JSON: {success: bool, url?: string, message?: string}
 */
define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();

// 设置 JSON 响应头
header('Content-Type: application/json; charset=utf-8');
Security::setSecurityHeaders();

// 仅 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '仅支持 POST 请求']);
    exit;
}

// 鉴权：必须是管理员
// requireAdmin() 内部调用 Security::redirect('/')，会跳转而非返回 JSON，不适合 API，
// 因此直接使用 isAdmin()（检查 $_SESSION['is_admin'] === true）
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '无权限']);
    exit;
}

// CSRF 校验（兼容 X-CSRF-TOKEN 请求头与表单字段）
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST[CSRF_TOKEN_NAME] ?? '';
if (!Security::validateToken($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF 验证失败']);
    exit;
}

// 校验上传
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    $msg = '未接收到文件';
    if (isset($_FILES['image'])) {
        $msg = '上传错误码: ' . $_FILES['image']['error'];
    }
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$file = $_FILES['image'];

// 大小校验（5MB，与 UPLOAD_MAX_SIZE 一致）
if ($file['size'] > UPLOAD_MAX_SIZE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '图片大小不能超过 5MB']);
    exit;
}

// 类型校验（真实 MIME，与 UPLOAD_ALLOWED_TYPES 一致：JPG/PNG/GIF/WEBP）
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$realMime = $finfo->file($file['tmp_name']);
if (!in_array($realMime, $allowedMimes, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '仅支持 JPG/PNG/GIF/WEBP 图片']);
    exit;
}

// 调用既有安全保存逻辑（内部会再次校验大小/MIME/扩展名，并重新处理图片）
// 第二个参数为文件名前缀，最终文件名形如 article_inline_YYYYMMDD_<rand>.jpg
$result = saveUploadedImage($file, 'article_inline_');
if ($result['success']) {
    echo json_encode(['success' => true, 'url' => $result['url']]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $result['message'] ?? '保存失败']);
}
