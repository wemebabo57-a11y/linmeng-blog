<?php
/**
 * PageCache —— 纯静态内容层的文件级页面缓存
 *
 * 目标：降低 index/archive/tags/about/links 等“只读列表/静态页”的
 * PHP 渲染与数据库负载，让匿名访客的重复请求直接命中磁盘缓存，
 * 配合 CDN 时为边缘节点提供稳定、可复用的源站响应。
 *
 * 缓存策略：
 * - 仅匿名访客（未开启 PHP 会话）的 GET/HEAD 请求；
 * - 仅当 query 参数全部落在各页面声明的白名单内时才缓存，
 *   其余 query（如 search、msg、API 探针参数等）一律跳过，
 *   避免任意参数写入缓存文件造成污染；
 * - 粒度按 REQUEST_URI 键控（含 query），文件存 LM_ROOT/cache/pages/；
 * - TTL 默认 300 秒，可在入口用 define('LM_PAGE_CACHE_TTL', 秒) 覆盖。
 *
 * CSRF meta 处理（template/header.php 会输出 <meta name="csrf-token">）：
 * 缓存写入前将本次渲染得到的令牌值替换为占位符 {{LM_CSRF_TOKEN}}，
 * HIT 时再替换回当前请求的无状态令牌（Security::generateToken()），
 * 保证缓存文件不含任何一次性/会话令牌，HIT/MISS 均不串号。
 */
class PageCache {

    /** 默认缓存时长（秒），可被常量 LM_PAGE_CACHE_TTL 覆盖 */
    const DEFAULT_TTL = 300;

    /** 缓存文件内用于替换 CSRF 令牌的占位符 */
    const CSRF_PLACEHOLDER = '{{LM_CSRF_TOKEN}}';

    /**
     * 在页面入口顶部调用。
     *
     * 命中时直接输出缓存内容（带头 X-Page-Cache: HIT、Age）并 exit；
     * 未命中时启动输出缓冲（X-Page-Cache: MISS），脚本结束时自动落盘；
     * 不满足缓存条件时静默通过（X-Page-Cache: SKIP），页面正常渲染。
     *
     * @param array $allowedQueryKeys 该页面允许缓存的 GET 参数名白名单
     */
    public static function start(array $allowedQueryKeys = []) {
        if (!self::isCacheable($allowedQueryKeys)) {
            self::debugHeader('SKIP');
            return;
        }

        $file = self::fileFor($_SERVER['REQUEST_URI']);
        $ttl  = self::ttl();

        if (is_file($file) && (time() - filemtime($file)) < $ttl) {
            $html = @file_get_contents($file);
            if ($html !== false && $html !== '') {
                self::debugHeader('HIT');
                if (!headers_sent()) {
                    header('Age: ' . (time() - filemtime($file)));
                }
                echo str_replace(self::CSRF_PLACEHOLDER, Security::generateToken(), $html);
                exit;
            }
        }

        // MISS：捕获渲染输出，写入缓存后原样输出
        $token = Security::generateToken();
        self::debugHeader('MISS');
        ob_start(function ($buffer) use ($file, $token) {
            $status = http_response_code();
            // 仅缓存成功且非空的完整响应
            if ($status >= 200 && $status < 300 && $buffer !== '') {
                $cached = ($token !== '') ? str_replace($token, self::CSRF_PLACEHOLDER, $buffer) : $buffer;
                self::store($file, $cached);
            }
            return $buffer;
        });
    }

    /**
     * 判断当前请求是否可缓存。
     */
    protected static function isCacheable(array $allowedQueryKeys) {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'GET' && $method !== 'HEAD') {
            return false;
        }
        // 会话已开启（登录用户、记住我、携带会话 Cookie 的回头访客）：响应私有，不缓存
        if (session_status() === PHP_SESSION_ACTIVE) {
            return false;
        }
        if (!empty($_COOKIE['remember_token'])) {
            return false;
        }
        // query 白名单之外的参数一律不缓存
        foreach (array_keys($_GET) as $key) {
            if (!in_array($key, $allowedQueryKeys, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * REQUEST_URI → 缓存文件路径（sha1 键控，自动建目录、放空 index.html 防列目录）。
     */
    protected static function fileFor($uri) {
        $dir = LM_ROOT . '/cache/pages';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
            @file_put_contents($dir . '/index.html', '');
        }
        return $dir . '/' . sha1((string)$uri) . '.html';
    }

    protected static function ttl() {
        return defined('LM_PAGE_CACHE_TTL') ? max(1, (int)LM_PAGE_CACHE_TTL) : self::DEFAULT_TTL;
    }

    /**
     * 落盘（临时文件 + rename，防并发读到的半文件）。
     */
    protected static function store($file, $html) {
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $html, LOCK_EX) !== false) {
            @rename($tmp, $file);
        } else {
            @unlink($tmp);
        }
    }

    protected static function debugHeader($state) {
        if (!headers_sent()) {
            header('X-Page-Cache: ' . $state);
        }
    }

    /**
     * 手动失效：清空全部页面缓存（保留 index.html）。
     * 挂接点见 docs/cdn-caching.md（如文章发布/更新、友链/站点设置变更后调用）。
     */
    public static function purgeAll() {
        $dir = LM_ROOT . '/cache/pages';
        if (!is_dir($dir)) {
            return 0;
        }
        $count = 0;
        foreach (glob($dir . '/*.html') ?: [] as $f) {
            if (basename($f) === 'index.html') {
                continue;
            }
            if (@unlink($f)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * 失效单个 URL（按其 REQUEST_URI 键值删除，如 purgeUri('/?page=2')）。
     */
    public static function purgeUri($uri) {
        $file = self::fileFor($uri);
        return is_file($file) ? @unlink($file) : false;
    }
}
