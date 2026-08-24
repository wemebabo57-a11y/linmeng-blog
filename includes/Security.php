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
     * 富文本XSS过滤（允许部分HTML标签及安全属性）
     *
     * 采用 DOMDocument 白名单方式：保留指定标签的指定属性，
     * 既防 XSS 又保留作者在后台所做的排版（style / class / align / width 等）。
     * 旧的 strip_tags 实现会无差别删除所有属性，导致排版丢失，故废弃。
     *
     * 仅用于受信任作者（后台管理员）提交的富文本，仍做严格属性过滤。
     */
    public static function xssCleanHtml($html) {
        if ($html === '' || $html === null) {
            return '';
        }

        // 标签白名单 => 允许的属性白名单
        $allowed = [
            'p'          => ['style', 'class', 'align'],
            'span'       => ['style', 'class'],
            'div'        => ['style', 'class', 'align'],
            'br'         => ['style', 'class'],
            'hr'         => ['style', 'class'],
            'strong'     => ['style', 'class'],
            'b'          => ['style', 'class'],
            'em'         => ['style', 'class'],
            'i'          => ['style', 'class'],
            'u'          => ['style', 'class'],
            's'          => ['style', 'class'],
            'del'        => ['style', 'class'],
            'ins'        => ['style', 'class'],
            'mark'       => ['style', 'class'],
            'small'      => ['style', 'class'],
            'sub'        => ['style', 'class'],
            'sup'        => ['style', 'class'],
            'abbr'       => ['style', 'class', 'title'],
            'cite'       => ['style', 'class'],
            'q'          => ['style', 'class', 'cite'],
            'time'       => ['style', 'class', 'datetime'],
            'h1'         => ['style', 'class', 'align', 'id'],
            'h2'         => ['style', 'class', 'align', 'id'],
            'h3'         => ['style', 'class', 'align', 'id'],
            'h4'         => ['style', 'class', 'align', 'id'],
            'h5'         => ['style', 'class', 'align', 'id'],
            'h6'         => ['style', 'class', 'align', 'id'],
            'ul'         => ['style', 'class'],
            'ol'         => ['style', 'class', 'start', 'type', 'reversed'],
            'li'         => ['style', 'class', 'value'],
            'dl'         => ['style', 'class'],
            'dt'         => ['style', 'class'],
            'dd'         => ['style', 'class'],
            'blockquote' => ['style', 'class', 'cite'],
            'pre'        => ['style', 'class'],
            'code'       => ['style', 'class'],
            'kbd'        => ['style', 'class'],
            'samp'       => ['style', 'class'],
            'var'        => ['style', 'class'],
            'a'          => ['href', 'title', 'target', 'rel', 'style', 'class', 'id'],
            'img'        => ['src', 'alt', 'title', 'width', 'height', 'style', 'class', 'loading'],
            'figure'     => ['style', 'class'],
            'figcaption' => ['style', 'class'],
            'table'      => ['style', 'class', 'border', 'cellpadding', 'cellspacing', 'width', 'align', 'summary'],
            'caption'    => ['style', 'class', 'align'],
            'colgroup'   => ['style', 'class', 'span'],
            'col'        => ['style', 'class', 'span', 'width'],
            'thead'      => ['style', 'class', 'align', 'valign'],
            'tbody'      => ['style', 'class', 'align', 'valign'],
            'tfoot'      => ['style', 'class', 'align', 'valign'],
            'tr'         => ['style', 'class', 'align', 'valign'],
            'td'         => ['style', 'class', 'align', 'valign', 'colspan', 'rowspan', 'width', 'height', 'headers'],
            'th'         => ['style', 'class', 'align', 'valign', 'colspan', 'rowspan', 'width', 'height', 'scope', 'headers', 'abbr'],
            'details'    => ['style', 'class', 'open'],
            'summary'    => ['style', 'class'],
            'iframe'     => ['src', 'title', 'width', 'height', 'style', 'class', 'allow', 'allowfullscreen', 'frameborder', 'loading'],
            'video'      => ['src', 'poster', 'width', 'height', 'style', 'class', 'controls', 'preload', 'muted', 'loop', 'playsinline'],
            'audio'      => ['src', 'style', 'class', 'controls', 'preload', 'muted', 'loop'],
            'source'     => ['src', 'type', 'media'],
        ];

        // 连同内容一起删除的标签（其子节点一并移除）
        $dropWithContent = [
            'script', 'style', 'noscript', 'template', 'head', 'title',
            'meta', 'link', 'base', 'object', 'embed', 'applet', 'param',
            'form', 'input', 'button', 'textarea', 'select', 'option', 'optgroup',
            'fieldset', 'legend', 'label', 'output', 'progress', 'meter',
        ];

        // 回退：环境不支持 DOMDocument 时，退化到 strip_tags（会丢失属性，仅保底）
        if (!class_exists('DOMDocument') || !function_exists('libxml_use_internal_errors')) {
            $allowedTagsStr = '<' . implode('><', array_keys($allowed)) . '>';
            $fallback = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/iu', '', $html);
            $fallback = strip_tags($fallback, $allowedTagsStr);
            $fallback = preg_replace('/\s*on\w+\s*=\s*["\']?[^"\'>]*["\']?/iu', '', $fallback);
            $fallback = preg_replace('/expression\s*\(/iu', '', $fallback);
            return $fallback;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        // 包装根 div + xml 声明以确保 UTF-8 正确解析
        $dom->loadHTML(
            '<?xml encoding="UTF-8"?><div id="__lm_root__">' . $html . '</div>',
            LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        // 定位包装根（getElementById 在无 DTD 时不可靠，改用 XPath）
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//*[@id="__lm_root__"]');
        $root = $nodes->length > 0 ? $nodes->item(0) : $dom->documentElement;

        if ($root === null) {
            // 极端情况解析失败，回退到 strip_tags
            $allowedTagsStr = '<' . implode('><', array_keys($allowed)) . '>';
            $fallback = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/iu', '', $html);
            $fallback = strip_tags($fallback, $allowedTagsStr);
            $fallback = preg_replace('/\s*on\w+\s*=\s*["\']?[^"\'>]*["\']?/iu', '', $fallback);
            return $fallback;
        }

        self::cleanHtmlNode($root, $allowed, $dropWithContent);

        // 输出根内的 HTML 片段（不包含包装 div）
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }
        return trim($out);
    }

    /**
     * 递归清理 DOM 节点：
     * - 移除黑名单标签（连同内容）
     * - 非白名单标签用其子节点替换（保留文本内容，去掉标签）
     * - 白名单标签清理属性后继续递归
     */
    private static function cleanHtmlNode($node, array $allowed, array $dropWithContent) {
        if ($node === null) {
            return;
        }

        // 1. 先删除"连同内容删除"的子节点
        if ($node->hasChildNodes()) {
            $toRemove = [];
            foreach ($node->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    $tag = strtolower($child->nodeName);
                    if (in_array($tag, $dropWithContent, true)) {
                        $toRemove[] = $child;
                    }
                }
            }
            foreach ($toRemove as $n) {
                $n->parentNode->removeChild($n);
            }
        }

        // 2. 收集剩余元素子节点（递归处理，避免在遍历中修改 live NodeList）
        $children = [];
        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    $children[] = $child;
                }
            }
        }
        foreach ($children as $child) {
            $tag = strtolower($child->nodeName);
            if (!isset($allowed[$tag])) {
                // 非白名单：先递归清理其子节点，再用子节点替换它（保留文本内容）
                self::cleanHtmlNode($child, $allowed, $dropWithContent);
                $frag = $node->ownerDocument->createDocumentFragment();
                while ($child->firstChild) {
                    $frag->appendChild($child->firstChild);
                }
                if ($child->parentNode) {
                    $child->parentNode->replaceChild($frag, $child);
                }
            } else {
                // 白名单：清理属性后递归处理子节点
                self::cleanHtmlAttributes($child, $allowed[$tag]);
                self::cleanHtmlNode($child, $allowed, $dropWithContent);
            }
        }
    }

    /**
     * 清理单个元素的属性：
     * - 移除 on* 事件处理器
     * - 移除 libxml 注入的命名空间属性
     * - 净化 style 属性（剔除 CSS 中的 XSS 向量）
     * - 校验 URL 类属性协议（仅允许 http/https/mailto/相对路径）
     * - 移除不在白名单内的属性
     */
    private static function cleanHtmlAttributes($el, array $allowedAttrs) {
        $toRemove = [];
        foreach ($el->attributes as $attr) {
            $name = strtolower($attr->nodeName);
            $value = $attr->nodeValue;

            // 移除所有 on* 事件处理器
            if (strncmp($name, 'on', 2) === 0) {
                $toRemove[] = $attr->nodeName;
                continue;
            }
            // 移除 libxml 可能注入的命名空间属性
            if ($name === 'xmlns' || $name === 'xml:lang' || strpos($name, 'xmlns:') === 0) {
                $toRemove[] = $attr->nodeName;
                continue;
            }
            // style 属性：净化危险 CSS
            if ($name === 'style') {
                $cleaned = self::sanitizeStyle($value);
                if ($cleaned === '') {
                    $toRemove[] = $attr->nodeName;
                } else {
                    $el->setAttribute('style', $cleaned);
                }
                continue;
            }
            // URL 类属性：协议白名单校验
            if (in_array($name, ['href', 'src', 'cite', 'poster', 'action', 'formaction', 'data', 'background', 'longdesc', 'usemap'], true)) {
                $safe = self::sanitizeUrl($value);
                $el->setAttribute($name, $safe);
                continue;
            }
            // 其他属性必须在白名单内
            if (!in_array($name, $allowedAttrs, true)) {
                $toRemove[] = $attr->nodeName;
            }
        }
        foreach ($toRemove as $n) {
            $el->removeAttribute($n);
        }

        // a 标签若有 target=_blank/_top，强制 rel=noopener noreferrer 防反向钓鱼
        if (strtolower($el->nodeName) === 'a' && $el->hasAttribute('target')) {
            $target = strtolower(trim($el->getAttribute('target')));
            if ($target === '_blank' || $target === '_top') {
                $existingRel = trim($el->getAttribute('rel'));
                $el->setAttribute('rel', trim($existingRel . ' noopener noreferrer'));
            }
        }
    }

    /**
     * 净化内联 style 属性，移除 CSS 中的 XSS 向量
     */
    private static function sanitizeStyle($style) {
        if ($style === '' || $style === null) {
            return '';
        }
        // 移除危险关键字（IE expression、伪协议行为绑定等）
        $style = preg_replace('/expression\s*\(/iu', '', $style);
        $style = preg_replace('/javascript\s*:/iu', '', $style);
        $style = preg_replace('/vbscript\s*:/iu', '', $style);
        $style = preg_replace('/-moz-binding\s*:/iu', '', $style);
        $style = preg_replace('/behavior\s*:/iu', '', $style);
        // 净化 url() 中的危险协议
        $style = preg_replace_callback(
            '/url\s*\(\s*(["\']?)([^)\'"]*)\1\s*\)/iu',
            function ($m) {
                $u = trim($m[2]);
                $scheme = strtolower((string)(parse_url($u, PHP_URL_SCHEME) ?: ''));
                if (in_array($scheme, ['javascript', 'vbscript', 'data', 'file'], true)) {
                    return 'url()';
                }
                return $m[0];
            },
            $style
        );
        return trim($style);
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
        // 会话已开启（登录用户、表单提交等）：使用绑定会话的随机令牌
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (empty($_SESSION[CSRF_TOKEN_NAME])) {
                $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
            }
            return $_SESSION[CSRF_TOKEN_NAME];
        }
        // 无会话的匿名请求（可被 CDN 缓存的页面）：派生无状态令牌，
        // 不依赖 $_SESSION，缓存页面上的令牌对所有匿名访客均可校验通过。
        return self::anonymousToken();
    }

    /**
     * 匿名无状态 CSRF 令牌：HMAC(密钥, 用途|日期|客户端IP)
     * 跨站攻击者无法读取页面令牌（同源策略），也无法伪造 HMAC，防护等价于会话令牌；
     * 按天轮换，校验时允许今天与昨天（兼容跨零点提交）。
     */
    private static function anonymousToken($day = null) {
        $ip = self::getClientIp();
        $day = $day ?? date('Ymd');
        return hash_hmac('sha256', 'lm-anon-csrf|' . $day . '|' . $ip, SECRET_KEY);
    }
    
    /**
     * 验证CSRF Token（双模式）：
     * 1) 会话令牌：登录用户与已开启会话的请求，行为与旧版一致；
     * 2) 匿名无状态令牌：CDN 缓存页面上的表单/API 提交；
     * 3) 惰性恢复会话：API 入口未提前 session_start 时，已登录用户仍可校验。
     */
    public static function validateToken($token) {
        if (!is_string($token) || $token === '') {
            return false;
        }
        // 1) 会话令牌
        if (session_status() === PHP_SESSION_ACTIVE
            && !empty($_SESSION[CSRF_TOKEN_NAME])
            && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token)) {
            return true;
        }
        // 2) 匿名无状态令牌（今天 / 昨天）
        if (hash_equals(self::anonymousToken(), $token)
            || hash_equals(self::anonymousToken(date('Ymd', strtotime('-1 day'))), $token)) {
            return true;
        }
        // 3) 惰性恢复会话再校验（携带会话 Cookie 的已登录用户）
        if (session_status() === PHP_SESSION_NONE && isset($_COOKIE[session_name()])) {
            session_start();
            if (!empty($_SESSION[CSRF_TOKEN_NAME])
                && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token)) {
                return true;
            }
        }
        return false;
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
    public static function jsonResponse($data, $statusCode = 200) {
        $statusCode = (int)$statusCode;
        if ($statusCode >= 100 && $statusCode <= 599) {
            http_response_code($statusCode);
        }
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
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
