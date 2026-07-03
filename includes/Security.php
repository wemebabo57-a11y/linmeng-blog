<?php
/**
 * 安全核心类
 * 包含SQL防注入、XSS过滤、CSRF防护、密码加密等
 */

class Security {
    
    private static $instance = null;
    private $db;
    
    private function __construct() {}
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 设置数据库连接
     */
    public function setDb($db) {
        $this->db = $db;
    }

    /**
     * 获取全局 PDO 实例
     */
    private static function getDb() {
        if (!empty($GLOBALS['db']) && $GLOBALS['db'] instanceof PDO) {
            return $GLOBALS['db'];
        }
        return Database::getInstance()->getPdo();
    }
    
    /**
     * SQL参数绑定 - 防止SQL注入
     * 已废弃，请使用PDO预处理语句
     */
    public static function param($value, $type = 'string') {
        if ($type === 'int') {
            return (int)$value;
        } elseif ($type === 'float') {
            return (float)$value;
        } elseif ($type === 'bool') {
            return (bool)$value ? 1 : 0;
        } else {
            // 使用PDO的quote方法（如果可用）
            if (class_exists('Database')) {
                try {
                    $pdo = Database::getInstance()->getPdo();
                    return $pdo->quote((string)$value);
                } catch (Exception $e) {
                    // 回退到htmlspecialchars
                }
            }
            return "'" . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . "'";
        }
    }
    
    /**
     * 清理字符串输入
     */
    public static function clean($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::clean($value);
            }
        } else {
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        }
        return $data;
    }
    
    /**
     * XSS过滤
     */
    public static function xssClean($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::xssClean($value);
            }
            return $data;
        }
        
        $data = (string)$data;
        
        // 移除不可见字符
        $data = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', '', $data);
        
        // 转义HTML实体
        $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $data;
    }
    
    /**
     * URL 协议白名单校验
     * 仅允许 http://、https://、/、#、mailto:（可选校验邮箱格式）
     */
    public static function sanitizeUrl($url) {
        $url = trim((string)$url);
        if ($url === '' || $url === '#') {
            return '#';
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme !== null) {
            $scheme = strtolower($scheme);
            if (in_array($scheme, ['http', 'https', 'mailto'], true)) {
                return $url;
            }
            return '#';
        }

        // 无协议：仅允许相对路径（以 / 开头）或纯锚点
        if (strpos($url, '/') === 0 || strpos($url, '#') === 0) {
            return $url;
        }

        // 其它包含冒号的内容视为危险协议
        if (preg_match('#^[^/]*:#', $url)) {
            return '#';
        }

        return $url;
    }

    /**
     * 富文本XSS过滤（允许部分HTML标签）
     * 使用更安全的白名单方式
     */
    public static function xssCleanHtml($html) {
        // 定义允许的标签和属性白名单
        $allowedTags = [
            'p' => [],
            'br' => [],
            'strong' => [],
            'b' => [],
            'em' => [],
            'i' => [],
            'u' => [],
            'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'blockquote' => [],
            'code' => [],
            'pre' => [],
            'a' => ['href', 'title'],
            'img' => ['src', 'alt', 'title'],
            'span' => [],
            'div' => []
        ];

        // 先显式移除 <script> 标签块
        $html = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/iu', '', $html);

        // 使用strip_tags进行基础过滤，只允许指定标签
        $allowedTagsStr = '<' . implode('><', array_keys($allowedTags)) . '>';
        $html = strip_tags($html, $allowedTagsStr);

        // 清理所有事件处理器属性（on*）
        $html = preg_replace('/\s*on\w+\s*=\s*["\']?[^"\'>]*["\']?/iu', '', $html);

        // 对 href/src 属性进行协议白名单校验
        $html = preg_replace_callback(
            '/(href|src)\s*=\s*(["\']?)([^"\'>\s]*)\2/iu',
            function ($matches) {
                $attr = strtolower($matches[1]);
                $url = self::sanitizeUrl($matches[3]);
                return $attr . '="' . $url . '"';
            },
            $html
        );

        // 清理 expression (IE)
        $html = preg_replace('/expression\s*\(/iu', '', $html);

        return $html;
    }
    
    /**
     * 生成密码哈希 - 使用强加密
     */
    public static function hashPassword($password) {
        // 使用PHP默认的PASSWORD_DEFAULT（当前是bcrypt）
        return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
    }
    
    /**
     * 验证密码
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * 检查密码强度
     */
    public static function checkPasswordStrength($password) {
        $score = 0;
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = '密码长度至少8位';
        } else {
            $score++;
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = '需包含大写字母';
        } else {
            $score++;
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = '需包含小写字母';
        } else {
            $score++;
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = '需包含数字';
        } else {
            $score++;
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = '需包含特殊字符';
        } else {
            $score++;
        }
        
        // 检查常见弱口令
        $commonPasswords = ['123456', 'password', 'admin', 'root', 'qwerty', '111111', '12345678'];
        foreach ($commonPasswords as $common) {
            if (stripos($password, $common) !== false) {
                $errors[] = '密码过于简单，包含常见弱口令';
                $score = 0;
                break;
            }
        }
        
        return [
            'score' => $score,
            'strong' => $score >= 4,
            'errors' => $errors
        ];
    }
    
    /**
     * 生成CSRF Token
     */
    public static function generateToken() {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    /**
     * 验证CSRF Token
     */
    public static function validateToken($token) {
        if (empty($_SESSION[CSRF_TOKEN_NAME]) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    /**
     * 获取CSRF Token输入框HTML
     */
    public static function csrfField() {
        $token = self::generateToken();
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . $token . '">';
    }
    
    /**
     * 验证CSRF（用于POST请求）
     */
    public static function checkCsrf() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!self::validateToken($token)) {
                http_response_code(403);
                die('CSRF Token验证失败');
            }
        }
    }
    
    /**
     * 获取客户端真实IP
     * 注意：如果服务器没有使用CDN/代理，应优先使用REMOTE_ADDR
     */
    public static function getClientIp() {
        // 1. Cloudflare 环境优先使用 HTTP_CF_CONNECTING_IP（由 CF 边缘设置，客户端无法伪造）
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        // 2. 代理环境：仅当显式启用 LM_TRUST_PROXY 时才信任 X-Forwarded-For
        //    否则客户端可任意伪造此头，绕过登录锁定与速率限制
        if (defined('LM_TRUST_PROXY') && LM_TRUST_PROXY && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        // 3. 回退到 REMOTE_ADDR
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return '0.0.0.0';
    }
    
    /**
     * 生成随机字符串
     */
    public static function randomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * 安全的重定向
     */
    public static function redirect($url) {
        // 防止开放式重定向
        $allowedHosts = [];
        $parsed = parse_url($url);
        
        if (!empty($parsed['host']) && !in_array($parsed['host'], $allowedHosts)) {
            $url = '/';
        }
        
        // 清理URL
        $url = str_replace(["\r", "\n", "\0"], '', $url);
        
        header("Location: $url");
        exit;
    }
    
    /**
     * 安全的JSON输出
     */
    public static function jsonResponse($data) {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * 设置安全响应头
     *
     * 注意：HSTS、CSP 等头若同时在 Web 服务器（Nginx/Apache，如宝塔面板）中配置，
     * 浏览器会收到重复响应头。请在服务器侧移除同名配置，统一由此函数管理。
     */
    public static function setSecurityHeaders() {
        // 先清除 PHP 侧已设置的同类头，避免重复
        header_remove('X-Frame-Options');
        header_remove('X-Content-Type-Options');
        header_remove('X-XSS-Protection');
        header_remove('Referrer-Policy');
        header_remove('Strict-Transport-Security');
        header_remove('Permissions-Policy');
        header_remove('Content-Security-Policy');

        header('X-Frame-Options: DENY', true);
        header('X-Content-Type-Options: nosniff', true);
        header('X-XSS-Protection: 1; mode=block', true);
        header('Referrer-Policy: strict-origin-when-cross-origin', true);

        // HSTS：强制 HTTPS（仅 HTTPS 环境下发送，防止 HTTP 首次劫持）
        // 使用 replace=true 强制覆盖任何已存在的同名头，避免重复
        if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        ) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload', true);
        }

        // Permissions-Policy：限制浏览器 API 权限，仅放行必要能力
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=(), interest-cohort=()', true);

        header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://challenges.cloudflare.com https://static.geetest.com https://*.geetest.com https://*.geetest.com.cn https://*.geevisit.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://*.geetest.com https://*.geetest.com.cn; img-src 'self' data: https: http:; font-src 'self' data: https://fonts.gstatic.com https://*.geetest.com https://*.geetest.com.cn; frame-ancestors 'none'; connect-src 'self' https://challenges.cloudflare.com https://*.geetest.com https://*.geetest.com.cn https://*.geevisit.com; frame-src 'self' https://challenges.cloudflare.com https://*.geetest.com https://*.geetest.com.cn https://*.geevisit.com; worker-src 'self' blob: https://*.geetest.com https://*.geetest.com.cn; object-src 'none'; base-uri 'self'; form-action 'self';", true);
    }

    public static function verifyGeetestCaptcha($captchaId, $captchaKey, $lotNumber, $captchaOutput, $passToken, $genTime) {
        if (empty($captchaId) || empty($captchaKey) || empty($lotNumber) || empty($captchaOutput) || empty($passToken) || empty($genTime)) {
            return ['success' => false, 'error' => '缺少极验验证参数'];
        }

        $result = self::httpPostForm(
            'https://gcaptcha4.geetest.com/validate?captcha_id=' . rawurlencode($captchaId),
            [
                'lot_number' => $lotNumber,
                'captcha_output' => $captchaOutput,
                'pass_token' => $passToken,
                'gen_time' => $genTime,
                'sign_token' => hash_hmac('sha256', $lotNumber, $captchaKey)
            ],
            [],
            10
        );

        if (!$result['success']) {
            return ['success' => false, 'error' => '极验验证服务请求失败'];
        }

        $data = json_decode($result['response'], true);
        if (!is_array($data)) {
            return ['success' => false, 'error' => '极验验证服务返回异常'];
        }

        if (($data['result'] ?? '') === 'success') {
            return ['success' => true, 'error' => null];
        }

        return ['success' => false, 'error' => $data['reason'] ?? '极验人机验证失败'];
    }

    /**
     * 验证 Cloudflare Turnstile 人机验证 Token
     *
     * @param string $token 前端返回的 token
     * @param string $secret Cloudflare Turnstile Secret Key
     * @param string|null $ip 用户真实 IP（可选）
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function verifyTurnstileToken($token, $secret, $ip = null) {
        if (empty($token) || empty($secret)) {
            return ['success' => false, 'error' => '缺少验证参数'];
        }

        if (!function_exists('curl_init')) {
            return ['success' => false, 'error' => '服务器未启用 cURL 扩展，无法完成验证'];
        }

        $payload = [
            'secret' => $secret,
            'response' => $token,
        ];
        if ($ip !== null) {
            $payload['remoteip'] = $ip;
        }

        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'error' => '验证服务请求失败: ' . $curlErr];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return ['success' => false, 'error' => '验证服务返回异常'];
        }

        if (!empty($data['success']) && $data['success'] === true) {
            return ['success' => true, 'error' => null];
        }

        $errorCodes = $data['error-codes'] ?? [];
        $errorMap = [
            'missing-input-secret' => 'Secret Key 缺失',
            'invalid-input-secret' => 'Secret Key 无效',
            'missing-input-response' => '人机验证未完成',
            'invalid-input-response' => '人机验证响应无效',
            'bad-request' => '验证请求格式错误',
            'timeout-or-duplicate' => '验证已过期或重复使用',
            'internal-error' => 'Cloudflare 内部错误',
        ];
        $messages = [];
        foreach ($errorCodes as $code) {
            $messages[] = $errorMap[$code] ?? $code;
        }

        return [
            'success' => false,
            'error' => empty($messages) ? '人机验证失败' : implode('，', $messages),
        ];
    }
    
    /**
     * 防暴力破解 - 检查登录锁定
     */
    public static function checkLoginLock($identifier) {
        $db = self::getDb();
        $maxAttempts = defined('LM_LOGIN_MAX_ATTEMPTS') ? (int)LM_LOGIN_MAX_ATTEMPTS : 5;

        $stmt = $db->prepare("SELECT locked_until FROM lm_login_lock WHERE identifier = ?");
        $stmt->execute([$identifier]);
        $lockedUntil = (int)$stmt->fetchColumn();

        if ($lockedUntil > time()) {
            return [
                'locked' => true,
                'remaining' => $lockedUntil - time()
            ];
        }

        return ['locked' => false];
    }

    /**
     * 记录登录失败
     */
    public static function recordLoginFail($identifier) {
        $db = self::getDb();
        $maxAttempts = defined('LM_LOGIN_MAX_ATTEMPTS') ? (int)LM_LOGIN_MAX_ATTEMPTS : 5;
        $lockoutTime = defined('LM_LOGIN_LOCKOUT_TIME') ? (int)LM_LOGIN_LOCKOUT_TIME : 1800;

        $stmt = $db->prepare(
            "INSERT INTO lm_login_lock (identifier, fail_count, locked_until, updated_at)
             VALUES (?, 1, 0, UNIX_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
             fail_count = fail_count + 1,
             updated_at = UNIX_TIMESTAMP(),
             locked_until = IF(fail_count >= ?, UNIX_TIMESTAMP() + ?, locked_until)"
        );
        $stmt->execute([$identifier, $maxAttempts, $lockoutTime]);

        // 判断当前是否已触发锁定
        $stmt = $db->prepare("SELECT fail_count, locked_until FROM lm_login_lock WHERE identifier = ?");
        $stmt->execute([$identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && (int)$row['fail_count'] >= $maxAttempts && (int)$row['locked_until'] > time()) {
            return true;
        }

        return false;
    }

    /**
     * 清除登录失败记录
     */
    public static function clearLoginFail($identifier) {
        $db = self::getDb();
        $stmt = $db->prepare("DELETE FROM lm_login_lock WHERE identifier = ?");
        $stmt->execute([$identifier]);
    }
    
    /**
     * 验证上传文件
     */
    public static function validateUpload($file) {
        $errors = [];
        
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'errors' => ['非法上传']];
        }
        
        // 检查文件大小
        if ($file['size'] > UPLOAD_MAX_SIZE) {
            $errors[] = '文件大小超过限制（最大5MB）';
        }
        
        // 检查MIME类型
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, UPLOAD_ALLOWED_TYPES)) {
            $errors[] = '不支持的文件类型';
        }
        
        // 检查文件扩展名
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowedExts)) {
            $errors[] = '不支持的文件扩展名';
        }
        
        // 检查图片尺寸（防止图片炸弹）
        if (empty($errors)) {
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                $errors[] = '无效的图片文件';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'mime' => $mimeType,
            'ext' => $ext
        ];
    }
    
    /**
     * 生成安全文件名
     */
    public static function generateFileName($ext) {
        return date('Ymd') . '_' . self::randomString(16) . '.' . $ext;
    }
    
    /**
     * 重新处理图片（防止图片马）
     */
    public static function reprocessImage($sourcePath, $targetPath, $mimeType) {
        try {
            if (!function_exists('imagecreatetruecolor')) {
                return false;
            }

            switch ($mimeType) {
                case 'image/jpeg':
                    $src = imagecreatefromjpeg($sourcePath);
                    break;
                case 'image/png':
                    $src = imagecreatefrompng($sourcePath);
                    // 保留透明度
                    imagealphablending($src, false);
                    imagesavealpha($src, true);
                    break;
                case 'image/gif':
                    $src = imagecreatefromgif($sourcePath);
                    break;
                case 'image/webp':
                    if (function_exists('imagecreatefromwebp')) {
                        $src = imagecreatefromwebp($sourcePath);
                    } else {
                        return false;
                    }
                    break;
                default:
                    return false;
            }
            
            if (!$src) {
                return false;
            }
            
            // 获取原始尺寸
            $width = imagesx($src);
            $height = imagesy($src);
            
            // 如果图片太大，进行缩放
            $maxDimension = 2000;
            if ($width > $maxDimension || $height > $maxDimension) {
                $ratio = min($maxDimension / $width, $maxDimension / $height);
                $newWidth = (int)($width * $ratio);
                $newHeight = (int)($height * $ratio);
                
                $dst = imagecreatetruecolor($newWidth, $newHeight);
                if ($mimeType === 'image/png') {
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                }
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($src);
                $src = $dst;
            }
            
            // 保存处理后的图片
            switch ($mimeType) {
                case 'image/jpeg':
                    imagejpeg($src, $targetPath, 90);
                    break;
                case 'image/png':
                    imagepng($src, $targetPath, 6);
                    break;
                case 'image/gif':
                    imagegif($src, $targetPath);
                    break;
                case 'image/webp':
                    if (function_exists('imagewebp')) {
                        imagewebp($src, $targetPath, 90);
                    }
                    break;
            }
            
            imagedestroy($src);
            return true;
            
        } catch (Throwable $e) {
            return false;
        }
    }
    
    /**
     * AES-256-CBC 加密
     * 密钥派生自 SECRET_KEY，适合加密 API Key 等敏感配置
     */
    public static function encrypt($plaintext) {
        if (!function_exists('openssl_encrypt')) {
            throw new Exception('openssl 扩展未启用');
        }
        $key = hash('sha256', SECRET_KEY, true);
        $iv = random_bytes(16);
        $cipher = openssl_encrypt((string)$plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new Exception('加密失败');
        }
        return base64_encode($iv . $cipher);
    }
    
    /**
     * AES-256-CBC 解密
     * 失败返回 false
     */
    public static function decrypt($encoded) {
        if (!function_exists('openssl_decrypt')) {
            return false;
        }
        $data = base64_decode((string)$encoded, true);
        if ($data === false || strlen($data) < 16) {
            return false;
        }
        $iv = substr($data, 0, 16);
        $cipher = substr($data, 16);
        $key = hash('sha256', SECRET_KEY, true);
        $result = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $result === false ? false : $result;
    }
    
    /**
     * 通用 JSON POST 请求
     *
     * @return array ['success' => bool, 'response' => string|null, 'http_code' => int|null, 'error' => string|null]
     */
    public static function httpPostJson($url, $payload, $headers = [], $timeout = 30) {
        if (!function_exists('curl_init')) {
            return ['success' => false, 'response' => null, 'http_code' => null, 'error' => 'cURL 扩展未启用'];
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json'
        ], $headers));
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            return ['success' => false, 'response' => null, 'http_code' => $httpCode, 'error' => $error];
        }
        
        return ['success' => true, 'response' => $response, 'http_code' => $httpCode, 'error' => null];
    }

    public static function httpPostForm($url, $payload, $headers = [], $timeout = 30) {
        if (!function_exists('curl_init')) {
            return ['success' => false, 'response' => null, 'http_code' => null, 'error' => 'cURL 扩展未启用'];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
            'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
            'Accept: application/json'
        ], $headers));

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'response' => null, 'http_code' => $httpCode, 'error' => $error];
        }

        return ['success' => true, 'response' => $response, 'http_code' => $httpCode, 'error' => null];
    }
    
    /**
     * 滑动窗口限流
     *
     * @param string $identifier 限流标识，如 IP
     * @param string $action 操作类型
     * @param int $maxAttempts 窗口内最大次数
     * @param int $windowSeconds 窗口时长（秒）
     * @return bool 是否允许继续
     */
    public static function checkRateLimit($identifier, $action, $maxAttempts = 10, $windowSeconds = 3600) {
        try {
            $db = self::getDb();
            $now = time();
            
            $stmt = $db->prepare("INSERT INTO lm_rate_limit (identifier, action, attempts, first_attempt, last_attempt) VALUES (?, ?, 1, ?, ?) ON DUPLICATE KEY UPDATE attempts = IF(first_attempt + ? <= ?, 1, attempts + 1), first_attempt = IF(first_attempt + ? <= ?, ?, first_attempt), last_attempt = ?");
            $stmt->execute([$identifier, $action, $now, $now, $windowSeconds, $now, $windowSeconds, $now, $now, $now]);
            
            $stmt = $db->prepare("SELECT attempts FROM lm_rate_limit WHERE identifier = ? AND action = ?");
            $stmt->execute([$identifier, $action]);
            $attempts = (int)$stmt->fetchColumn();
            
            return $attempts <= $maxAttempts;
        } catch (Throwable $e) {
            // 限流异常时默认允许，避免影响正常功能
            error_log('Rate limit check failed: ' . $e->getMessage());
            return true;
        }
    }
}
