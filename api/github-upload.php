<?php
/**
 * GitHub 图库上传 API
 * 将用户上传的文件通过 GitHub API 存储到指定仓库，按用户名创建文件夹
 */

// 开启输出缓冲，防止任何非 JSON 输出污染响应
ob_start();

define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();

// 不调用 setSecurityHeaders，API 接口不需要 CSP 等限制
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// 注册错误处理，确保任何 PHP 错误都返回 JSON 格式
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_end_clean();
    error_log('github-upload error: [' . $errno . '] ' . $errstr . ' @ ' . $errfile . ':' . $errline);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '服务器内部错误，请稍后重试']);
    exit;
});

set_exception_handler(function($e) {
    ob_end_clean();
    error_log('github-upload exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '服务器异常，请稍后重试']);
    exit;
});

// 只允许 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方式不允许']);
    exit;
}

// 验证 CSRF Token
$token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!Security::validateToken($token)) {
    echo json_encode(['success' => false, 'message' => 'CSRF 验证失败']);
    exit;
}

// 需要登录
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

// 获取当前用户
$user = currentUser();
if (!$user) {
    echo json_encode(['success' => false, 'message' => '用户信息获取失败']);
    exit;
}

// 限流：每用户每 10 分钟最多 20 次图库上传，防滥用消耗 GitHub API 配额
if (!Security::checkRateLimit('user_' . $user['id'], 'gallery_upload', 20, 600)) {
    echo json_encode(['success' => false, 'message' => '上传过于频繁，请稍后再试']);
    exit;
}

$username = $user['username'];

// 获取 GitHub 配置
$githubToken = getSetting('github_gallery_token', '');
$githubRepo = getSetting('github_gallery_repo', '');
$githubBranch = getSetting('github_gallery_branch', 'main');

// 获取图库大小限制（MB）
$galleryMaxSize = (int) getSetting('gallery_max_size', '5');
if ($galleryMaxSize < 1) $galleryMaxSize = 1;
if ($galleryMaxSize > 100) $galleryMaxSize = 100;
$galleryMaxSizeBytes = $galleryMaxSize * 1024 * 1024;

if (empty($githubToken) || empty($githubRepo)) {
    echo json_encode(['success' => false, 'message' => 'GitHub 图库未配置，请联系管理员']);
    exit;
}

// 验证上传文件
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = '上传失败';
    if (isset($_FILES['file']['error'])) {
        switch ($_FILES['file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMsg = '文件大小超过限制';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMsg = '文件上传不完整';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMsg = '请选择要上传的文件';
                break;
        }
    }
    echo json_encode(['success' => false, 'message' => $errorMsg]);
    exit;
}

$file = $_FILES['file'];

// 验证文件大小（使用后台配置值）
if ($file['size'] > $galleryMaxSizeBytes) {
    echo json_encode(['success' => false, 'message' => '文件大小超过限制（最大' . $galleryMaxSize . 'MB）']);
    exit;
}

// 验证文件类型和大小
$validate = Security::validateUpload($file);
if (!$validate['valid']) {
    echo json_encode(['success' => false, 'message' => implode('，', $validate['errors'])]);
    exit;
}

// 重新编码图片以剥离 EXIF/嵌入式 payload（图片马）
// 使用与本地 uploads 相同的 Security::reprocessImage，确保上传到 GitHub 的产物已被清洗
$reprocessed = Security::reprocessImage($file['tmp_name'], $file['tmp_name'], $file['type']);
if (!$reprocessed) {
    echo json_encode(['success' => false, 'message' => '图片处理失败']);
    exit;
}

// 读取文件内容并转为 base64
$fileContent = file_get_contents($file['tmp_name']);
if ($fileContent === false) {
    echo json_encode(['success' => false, 'message' => '读取文件失败']);
    exit;
}

$base64Content = base64_encode($fileContent);

// 生成文件名：用户名/时间戳_随机字符串.扩展名
$safeUsername = preg_replace('/[^a-zA-Z0-9_-]/', '', $username);
if (empty($safeUsername)) {
    $safeUsername = 'user_' . $user['id'];
}

// 文件扩展名白名单校验 + 清洗原始文件名（防 stored-XSS）
$fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
    echo json_encode(['success' => false, 'message' => '不支持的图片格式']);
    exit;
}
$safeFilename = date('Ymd_His') . '_' . Security::randomString(8) . '.' . $fileExt;
$githubPath = $safeUsername . '/' . $safeFilename;

// 调用 GitHub API 上传文件
$apiUrl = 'https://api.github.com/repos/' . $githubRepo . '/contents/' . $githubPath;

$commitMessage = 'Upload image by ' . $username . ' via gallery';

$payload = json_encode([
    'message' => $commitMessage,
    'content' => $base64Content,
    'branch' => $githubBranch
]);

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $githubToken,
    'Content-Type: application/json',
    'User-Agent: LM-Blog-Gallery/1.0',
    'Accept: application/vnd.github.v3+json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    error_log('github-upload curl error: ' . $curlError);
    echo json_encode(['success' => false, 'message' => '网络请求失败，请稍后重试']);
    exit;
}

if ($httpCode === 201) {
    $responseData = json_decode($response, true);
    $rawUrl = $responseData['content']['download_url'] ?? '';
    // 使用 jsDelivr CDN 加速
    $cdnUrl = '';
    if (!empty($rawUrl) && preg_match('#https://raw\.githubusercontent\.com/([^/]+)/([^/]+)/([^/]+)/(.+)#', $rawUrl, $matches)) {
        $cdnUrl = 'https://cdn.jsdelivr.net/gh/' . $matches[1] . '/' . $matches[2] . '@' . $matches[3] . '/' . $matches[4];
    }

    // 记录到数据库
    try {
        db()->insert('lm_gallery', [
            'user_id' => $user['id'],
            'username' => $username,
            'original_name' => $file['name'],
            'github_path' => $githubPath,
            'raw_url' => $rawUrl,
            'cdn_url' => $cdnUrl,
            'file_size' => $file['size'],
            'file_type' => $validate['mime'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        // 数据库记录失败不影响返回，但记录日志
        error_log('Gallery DB insert failed: ' . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => '上传成功',
        'data' => [
            'filename' => $safeFilename,
            'path' => $githubPath,
            'raw_url' => $rawUrl,
            'cdn_url' => $cdnUrl,
            'size' => $file['size']
        ]
    ]);
    exit;
} elseif ($httpCode === 422) {
    // 可能是文件已存在或路径问题
    $responseData = json_decode($response, true);
    $errorMsg = $responseData['message'] ?? '文件可能已存在或路径无效';
    echo json_encode(['success' => false, 'message' => 'GitHub API 错误: ' . $errorMsg]);
    exit;
} elseif ($httpCode === 401 || $httpCode === 403) {
    $responseData = json_decode($response, true);
    $ghMsg = $responseData['message'] ?? '';
    $ghDocs = $responseData['documentation_url'] ?? '';
    $detail = [];
    if ($ghMsg) $detail[] = $ghMsg;
    if ($ghDocs) $detail[] = '文档: ' . $ghDocs;
    $detailStr = empty($detail) ? '' : ' (' . implode(' | ', $detail) . ')';
    echo json_encode(['success' => false, 'message' => 'GitHub Token 无效或权限不足 (HTTP ' . $httpCode . ')' . $detailStr . '，请联系管理员']);
    exit;
} elseif ($httpCode === 404) {
    echo json_encode(['success' => false, 'message' => 'GitHub 仓库不存在，请联系管理员检查配置']);
    exit;
} else {
    $responseData = json_decode($response, true);
    $errorMsg = $responseData['message'] ?? '未知错误 (HTTP ' . $httpCode . ')';
    echo json_encode(['success' => false, 'message' => '上传失败: ' . $errorMsg]);
    exit;
}
