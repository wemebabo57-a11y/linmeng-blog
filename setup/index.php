<?php
/**
 * 林梦博客 - Web 安装向导
 *
 * 访问 http://你的域名/setup/ 即可进入安装向导：
 *   1. 环境检测（PHP 版本 / 扩展 / 目录可写性）
 *   2. 数据库配置（并测试连接 / 可选自动建库）
 *   3. 站点与管理员配置
 *   4. 写入 .env、导入数据库结构、创建管理员、写入安装标记
 *
 * 本脚本完全独立，不依赖 includes/config.php（因为此时 .env 尚未生成）。
 * 安装完成后请立即删除 setup/ 目录以保证安全。
 */

declare(strict_types=1);

define('LM_ROOT', dirname(__DIR__));
define('SETUP_ROOT', __DIR__);
define('LM_VERSION', '2.4.2');

// 安装向导自用 session（与前台 session 隔离，避免 .env 缺失时报错）
if (session_status() === PHP_SESSION_NONE) {
    session_name('lm_setup');
    session_start();
}

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '1');

$markerFile = LM_ROOT . '/includes/config_installed.php';
$envFile    = LM_ROOT . '/.env';
$schemaFile = SETUP_ROOT . '/schema.sql';

/* ============================ 工具函数 ============================ */

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function setup_csrf_token(): string {
    if (empty($_SESSION['setup_csrf'])) {
        $_SESSION['setup_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['setup_csrf'];
}

function setup_check_csrf(string $token): bool {
    return !empty($_SESSION['setup_csrf']) && is_string($token)
        && hash_equals($_SESSION['setup_csrf'], $token);
}

function isInstalled(): bool {
    global $markerFile, $envFile;
    if (file_exists($markerFile)) {
        return true;
    }
    // .env 存在且含真实数据库配置也视为已安装
    if (is_readable($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if (strpos($line, 'DB_NAME=') === 0 && trim(substr($line, 8)) !== '') {
                return true;
            }
        }
    }
    return false;
}

/** 检测站点 URL */
function detectSiteUrl(): string {
    $scheme = 'http';
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) {
        $scheme = 'https';
    }
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    return $scheme . '://' . $host;
}

/** 检测子目录路径（如站点部署在 /blog 下则为 /blog） */
function detectSitePath(): string {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/setup/index.php');
    $dir = dirname($script); // 形如 /setup 或 /blog/setup
    $base = preg_replace('#/setup$#', '', $dir);
    return $base === '/' ? '' : $base;
}

/** 连接 MySQL（仅到服务器，不带库名） */
function connectMysql(string $host, string $user, string $pass, string $charset = 'utf8mb4'): PDO {
    $dsn = 'mysql:host=' . $host . ';charset=' . $charset;
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

/** 写入 .env 文件 */
function writeEnvFile(string $host, string $name, string $user, string $pass, string $secret, string $siteUrl, string $sitePath, bool $trustProxy): bool {
    global $envFile;
    $lines = [
        '# 林梦博客 - 环境配置（由安装向导生成）',
        '# 数据库',
        'DB_HOST=' . $host,
        'DB_NAME=' . $name,
        'DB_USER=' . $user,
        'DB_PASS=' . $pass,
        '',
        '# 安全密钥（用于 AES-256-CBC 加密 / 一旦设定后不要变更，否则已加密数据无法解密）',
        'SECRET_KEY=' . $secret,
        '',
        '# 站点',
        'SITE_URL=' . $siteUrl,
        'SITE_PATH=' . $sitePath,
        '',
        '# 是否信任 X-Forwarded-For（仅当位于明确配置的反向代理后时启用）',
        '# Cloudflare 用户无需启用（走 HTTP_CF_CONNECTING_IP）',
        'LM_TRUST_PROXY=' . ($trustProxy ? 'true' : 'false'),
        '',
    ];
    $content = implode("\n", $lines);
    // 若已存在则备份
    if (file_exists($envFile)) {
        @copy($envFile, $envFile . '.bak');
    }
    return (bool)file_put_contents($envFile, $content, LOCK_EX);
}

/** 导入 schema.sql（按分号拆分，跳过注释行） */
function importSchema(PDO $pdo): array {
    global $schemaFile;
    if (!is_readable($schemaFile)) {
        throw new RuntimeException('找不到 schema.sql，请确认 setup 目录完整。');
    }
    $sql = file_get_contents($schemaFile);
    // 移除注释行与空行
    $codeLines = [];
    foreach (explode("\n", $sql) as $line) {
        $t = trim($line);
        if ($t === '' || strpos($t, '--') === 0) {
            continue;
        }
        $codeLines[] = $line;
    }
    $code = implode("\n", $codeLines);
    $statements = array_filter(array_map('trim', explode(';', $code)));
    $count = 0;
    foreach ($statements as $stmt) {
        if ($stmt === '') {
            continue;
        }
        $pdo->exec($stmt);
        $count++;
    }
    return ['statements' => $count];
}

/** 默认设置项 */
function defaultSettings(string $siteUrl, string $siteName): array {
    $today = date('Y-m-d');
    return [
        'site_name'                  => $siteName,
        'site_description'           => '记录生活，分享技术',
        'site_keywords'              => '博客,技术,生活',
        'site_url'                   => $siteUrl,
        'site_logo'                  => '',
        'site_favicon'              => '',
        'site_background'           => '',
        'site_background_position'  => 'center center',
        'site_background_size'      => 'cover',
        'site_background_overlay'   => '0.45',
        'site_start_date'           => $today,
        'site_time_offset'          => '0',
        'site_visitor_count'        => '0',
        'site_icp'                  => '',
        'site_footer'               => '',
        'wechat_qrcode'             => '',
        'github_url'                => '',
        'bilibili_url'              => '',
        'contact_email'             => '',
        'telegram_url'              => '',
        'weather_city'              => '',
        'comment_need_approve'      => '0',
        'article_comment_enable'    => '1',
        'ai_summary_enabled'        => '0',
        'ai_default_provider_id'    => '0',
        'ai_summary_prompt'         => '请用中文总结下面这篇文章。',
        'github_oauth_enabled'      => '0',
        'github_client_id'         => '',
        'github_client_secret'     => '',
        'gitee_oauth_enabled'       => '0',
        'gitee_client_id'           => '',
        'gitee_client_secret'       => '',
        'gitcode_oauth_enabled'     => '0',
        'gitcode_client_id'          => '',
        'gitcode_client_secret'     => '',
        'gitcode_oauth_scope'       => '',
        'turnstile_site_key'        => '',
        'turnstile_secret_key'      => '',
        'turnstile_login_enabled'   => '0',
        'turnstile_login_site_key'  => '',
        'turnstile_login_secret_key'=> '',
        'turnstile_guestbook_enabled'        => '0',
        'turnstile_guestbook_site_key'       => '',
        'turnstile_guestbook_secret_key'     => '',
        'geetest_captcha_id'        => '',
        'geetest_captcha_key'       => '',
        'tools_enabled'             => '1',
        'lanzou_parse_enabled'      => '1',
        'lanzou_parse_api_url'      => 'https://api.zxki.cn/api/lzy',
        'lanzou_parse_api_key'      => '',
        'gallery_max_size'          => '5',
        'github_gallery_token'      => '',
        'github_gallery_repo'       => '',
        'github_gallery_branch'     => 'main',
        'service_probe_interval'    => '5',
        'service_probe_key'         => '',
        'service_last_probe_at'     => '',
        'donate_title'              => '捐赠页',
        'donate_description'        => '如果这个网站对你有帮助，可以自愿捐赠。',
        'donate_alipay_qrcode'      => '',
        'donate_wechat_qrcode'      => '',
    ];
}

/** 创建管理员账号 */
function createAdmin(PDO $pdo, array $admin): void {
    $hash = password_hash($admin['password'], PASSWORD_DEFAULT);
    $now  = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        "INSERT INTO lm_admin
            (username, password, email, nickname, role, status, created_at, last_login, last_ip, login_fail_count)
         VALUES (?, ?, ?, ?, 'admin', 1, ?, ?, '', 0)"
    );
    $stmt->execute([
        $admin['username'],
        $hash,
        $admin['email'],
        $admin['nickname'] ?: $admin['username'],
        $now,
        $now,
    ]);
}

/** 写入安装标记 */
function writeMarker(): void {
    global $markerFile;
    $dir = dirname($markerFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    file_put_contents($markerFile, "<?php\n// 安装标记文件\n", LOCK_EX);
}

/** 递归删除 setup 目录（尽力而为） */
function deleteSetupDir(): array {
    $root = SETUP_ROOT;
    // 不能删除自身正在执行的脚本所在目录时，先尝试删除其余文件
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    $errors = [];
    foreach ($it as $file) {
        if ($file->getPathname() === __FILE__) {
            continue; // 跳过自身，最后删
        }
        try {
            if ($file->isDir()) {
                @rmdir($file->getRealPath());
            } else {
                @unlink($file->getRealPath());
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
    // 尝试删除自身后移除目录
    @unlink(__FILE__);
    @rmdir($root);
    return $errors;
}

/* ============================ 环境检测 ============================ */

function checkRequirements(): array {
    $checks = [];
    // PHP 版本
    $checks['php'] = [
        'label' => 'PHP 版本 ≥ 7.4',
        'ok'    => version_compare(PHP_VERSION, '7.4.0', '>='),
        'value' => 'PHP ' . PHP_VERSION,
    ];
    // 扩展
    foreach (['pdo_mysql', 'openssl', 'mbstring', 'ctype', 'json'] as $ext) {
        $checks['ext_' . $ext] = [
            'label' => 'PHP 扩展：' . $ext,
            'ok'    => extension_loaded($ext),
            'value' => extension_loaded($ext) ? '已安装' : '未安装',
        ];
    }
    // 函数
    $checks['func_random'] = [
        'label' => 'random_bytes() 可用',
        'ok'    => function_exists('random_bytes'),
        'value' => function_exists('random_bytes') ? '可用' : '不可用',
    ];
    // .env 可写
    global $envFile;
    $rootWritable = is_writable(LM_ROOT);
    $envWritable  = (!file_exists($envFile) && $rootWritable) || (file_exists($envFile) && is_writable($envFile));
    $checks['env_writable'] = [
        'label' => '根目录可写（用于生成 .env）',
        'ok'    => $envWritable,
        'value' => $envWritable ? '可写' : '不可写',
    ];
    // includes 可写（写安装标记）
    $includesDir = LM_ROOT . '/includes';
    $checks['includes_writable'] = [
        'label' => 'includes/ 目录可写（写安装标记）',
        'ok'    => is_dir($includesDir) && is_writable($includesDir),
        'value' => (is_dir($includesDir) && is_writable($includesDir)) ? '可写' : '不可写',
    ];
    // 上传目录
    $uploadDir = LM_ROOT . '/assets/uploads';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    $checks['uploads_writable'] = [
        'label' => 'assets/uploads/ 目录可写',
        'ok'    => is_dir($uploadDir) && is_writable($uploadDir),
        'value' => (is_dir($uploadDir) && is_writable($uploadDir)) ? '可写' : '不可写',
    ];
    // schema.sql 存在
    global $schemaFile;
    $checks['schema'] = [
        'label' => 'setup/schema.sql 存在',
        'ok'    => is_readable($schemaFile),
        'value' => is_readable($schemaFile) ? '存在' : '缺失',
    ];
    return $checks;
}

/* ============================ 渲染 ============================ */

function renderHead(string $title, string $stepName): void {
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow">'
        . '<title>' . h($title) . ' · 林梦博客安装向导</title>'
        . '<style>' . file_get_contents(__DIR__ . '/style.css') . '</style>'
        . '</head><body><div class="wrap">'
        . '<div class="brand"><div class="brand-logo">林</div><div class="brand-text"><div class="brand-title">林梦博客</div><div class="brand-sub">安装向导 · v' . LM_VERSION . '</div></div></div>'
        . renderSteps($stepName);
}

function renderSteps(string $current): string {
    $steps = [
        'env'      => ['step' => 'env',      'name' => '环境检测'],
        'database' => ['step' => 'database', 'name' => '数据库'],
        'site'     => ['step' => 'site',     'name' => '站点与管理员'],
        'install'  => ['step' => 'install',  'name' => '执行安装'],
    ];
    $order = array_keys($steps);
    $idx = array_search($current, $order, true);
    $html = '<div class="steps">';
    foreach ($order as $i => $key) {
        $s = $steps[$key];
        $num = $i + 1;
        $cls = 'step';
        if ($key === $current) {
            $cls .= ' step-active';
        } elseif ($idx !== false && $i < $idx) {
            $cls .= ' step-done';
        }
        $html .= '<div class="' . $cls . '"><span class="step-num">' . $num . '</span><span class="step-name">' . h($s['name']) . '</span></div>';
    }
    $html .= '</div>';
    return $html;
}

function renderFoot(): void {
    echo '<div class="foot">林梦博客 · 开源博客系统 · 安装完成后请删除 setup 目录</div></div></body></html>';
}

/* ============================ 主流程 ============================ */

$action = $_GET['action'] ?? ($_POST['action'] ?? 'index');

// 已安装：锁定界面
if (isInstalled() && $action !== 'delete_setup') {
    renderHead('已完成', 'install');
    echo '<div class="card"><div class="result result-warn"><h2>站点已安装</h2>'
        . '<p>检测到本站已完成安装。为保证安全，请立即删除 <code>setup/</code> 安装目录。</p></div>';
    echo '<form method="post" action="?action=delete_setup" onsubmit="return confirm(\'确定删除 setup 目录？此操作不可恢复。\');" class="center">'
        . '<input type="hidden" name="csrf" value="' . h(setup_csrf_token()) . '">'
        . '<button type="submit" class="btn btn-danger">删除 setup 目录</button> '
        . '<a class="btn" href="../">返回站点首页</a></form></div>';
    renderFoot();
    exit;
}

// 删除 setup 目录
if ($action === 'delete_setup') {
    if (!setup_check_csrf($_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('CSRF 校验失败');
    }
    $errors = deleteSetupDir();
    if (is_dir(SETUP_ROOT)) {
        renderHead('删除', 'install');
        echo '<div class="card"><div class="result result-warn"><h2>未能完全删除</h2>'
            . '<p>请通过 FTP/SSH 手动删除 <code>setup/</code> 目录。</p></div>'
            . '<div class="center"><a class="btn" href="../">返回站点首页</a></div></div>';
        renderFoot();
        exit;
    }
    // 成功删除则跳转首页
    header('Location: ' . detectSiteUrl() . detectSitePath() . '/');
    exit;
}

// 路由
$step = $_GET['step'] ?? ($_POST['step'] ?? 'env');

switch ($step) {
    case 'database':
        handleDatabase();
        break;
    case 'site':
        handleSite();
        break;
    case 'install':
        handleInstall();
        break;
    case 'env':
    default:
        handleEnv();
        break;
}

/* ============================ Step: 环境检测 ============================ */
function handleEnv(): void {
    $checks = checkRequirements();
    $allOk  = true;
    foreach ($checks as $c) {
        if (!$c['ok']) {
            $allOk = false;
            break;
        }
    }
    renderHead('环境检测', 'env');
    echo '<div class="card"><h2>环境检测</h2><p class="muted">请确认以下条件全部满足后再继续。</p>';
    echo '<table class="check-table"><tbody>';
    foreach ($checks as $c) {
        $icon = $c['ok'] ? '✓' : '✕';
        $cls  = $c['ok'] ? 'ok' : 'fail';
        echo '<tr class="' . $cls . '"><td class="c-icon">' . $icon . '</td><td class="c-label">' . h($c['label']) . '</td><td class="c-val">' . h($c['value']) . '</td></tr>';
    }
    echo '</tbody></table>';
    if ($allOk) {
        echo '<form method="post" action="?step=database"><button type="submit" class="btn btn-primary">下一步：配置数据库</button></form>';
    } else {
        echo '<div class="result result-warn">存在不满足的条件，请修复后刷新本页。<br>常见问题：目录权限不足时，请将根目录、includes/、assets/uploads/ 设为可写（chmod 755 或在宝塔/面板中调整）。</div>';
        echo '<form method="get" action=""><input type="hidden" name="step" value="env"><button type="submit" class="btn">重新检测</button></form>';
    }
    echo '</div>';
    renderFoot();
}

/* ============================ Step: 数据库 ============================ */
function handleDatabase(): void {
    $error = '';
    $ok    = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!setup_check_csrf($_POST['csrf'] ?? '')) {
            $error = '会话已过期，请重新提交';
        } else {
            $host    = trim($_POST['db_host'] ?? '');
            $name    = trim($_POST['db_name'] ?? '');
            $user    = trim($_POST['db_user'] ?? '');
            $pass    = (string)($_POST['db_pass'] ?? '');
            $create  = !empty($_POST['db_create']);

            if ($host === '' || $name === '' || $user === '') {
                $error = '主机、数据库名、用户名均为必填';
            } else {
                try {
                    $pdo = connectMysql($host, $user, $pass);
                    // 可选建库
                    if ($create) {
                        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '', $name) . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    }
                    // 切换到目标库并测试
                    $pdo->exec("USE `" . str_replace('`', '', $name) . "`");
                    $_SESSION['db'] = compact('host', 'name', 'user', 'pass');
                    $ok = true;
                } catch (PDOException $e) {
                    $error = '数据库连接失败：' . $e->getMessage();
                }
            }
        }
    }

    renderHead('数据库配置', 'database');
    echo '<div class="card"><h2>数据库配置</h2><p class="muted">填写 MySQL / MariaDB 连接信息。</p>';

    if ($error) {
        echo '<div class="result result-warn">' . h($error) . '</div>';
    }
    if ($ok) {
        echo '<div class="result result-ok">✓ 数据库连接成功！可进入下一步。</div>';
        echo '<form method="post" action="?step=site"><button type="submit" class="btn btn-primary">下一步：站点与管理员</button></form>';
        echo '</div>';
        renderFoot();
        return;
    }

    $host = h($_POST['db_host'] ?? ($_SESSION['db']['host'] ?? 'localhost'));
    $name = h($_POST['db_name'] ?? ($_SESSION['db']['name'] ?? ''));
    $user = h($_POST['db_user'] ?? ($_SESSION['db']['user'] ?? ''));
    echo '<form method="post" action="?step=database">'
        . '<input type="hidden" name="csrf" value="' . h(setup_csrf_token()) . '">'
        . '<div class="field"><label>数据库主机</label><input type="text" name="db_host" value="' . $host . '" required placeholder="localhost"></div>'
        . '<div class="field"><label>数据库名</label><input type="text" name="db_name" value="' . $name . '" required placeholder="blog"></div>'
        . '<div class="field"><label>数据库用户名</label><input type="text" name="db_user" value="' . $user . '" required></div>'
        . '<div class="field"><label>数据库密码</label><input type="password" name="db_pass" value="" autocomplete="new-password"></div>'
        . '<div class="field checkbox"><label><input type="checkbox" name="db_create" value="1"' . (!empty($_POST['db_create']) ? ' checked' : '') . '> 数据库不存在时自动创建</label></div>'
        . '<div class="actions"><button type="submit" class="btn btn-primary">测试并保存</button> '
        . '<a class="btn" href="?step=env">上一步</a></div>'
        . '</form></div>';
    renderFoot();
}

/* ============================ Step: 站点与管理员 ============================ */
function handleSite(): void {
    if (empty($_SESSION['db'])) {
        header('Location: ?step=database');
        exit;
    }
    renderHead('站点与管理员', 'site');
    echo '<div class="card"><h2>站点与管理员配置</h2><p class="muted">设置站点基本信息与首个管理员账号。</p>';

    $post = $_POST;
    $siteUrl  = h($post['site_url']  ?? detectSiteUrl());
    $siteName = h($post['site_name'] ?? '我的博客');
    $adminUser= h($post['admin_user'] ?? 'admin');
    $adminMail= h($post['admin_email'] ?? '');
    echo '<form method="post" action="?step=install">'
        . '<input type="hidden" name="csrf" value="' . h(setup_csrf_token()) . '">'
        . '<h3 class="section-title">站点</h3>'
        . '<div class="field"><label>站点 URL</label><input type="text" name="site_url" value="' . $siteUrl . '" required placeholder="https://example.com"></div>'
        . '<div class="field"><label>站点名称</label><input type="text" name="site_name" value="' . $siteName . '" required></div>'
        . '<div class="field checkbox"><label><input type="checkbox" name="trust_proxy" value="1"> 信任 X-Forwarded-For（仅当位于反向代理后时勾选）</label></div>'
        . '<h3 class="section-title">管理员账号</h3>'
        . '<div class="field"><label>管理员用户名</label><input type="text" name="admin_user" value="' . $adminUser . '" required minlength="3" pattern="[A-Za-z0-9_\-]{3,}"></div>'
        . '<div class="field"><label>管理员邮箱</label><input type="email" name="admin_email" value="' . $adminMail . '" required></div>'
        . '<div class="field"><label>管理员密码</label><input type="password" name="admin_pass" required minlength="8"></div>'
        . '<div class="field"><label>确认密码</label><input type="password" name="admin_pass2" required minlength="8"></div>'
        . '<div class="actions"><button type="submit" class="btn btn-primary">开始安装</button> '
        . '<a class="btn" href="?step=database">上一步</a></div>'
        . '</form></div>';
    renderFoot();
}

/* ============================ Step: 执行安装 ============================ */
function handleInstall(): void {
    if (empty($_SESSION['db'])) {
        header('Location: ?step=database');
        exit;
    }
    $error = '';
    $log   = [];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !setup_check_csrf($_POST['csrf'] ?? '')) {
        header('Location: ?step=site');
        exit;
    }

    $db        = $_SESSION['db'];
    $siteUrl   = rtrim(trim($_POST['site_url'] ?? ''), '/');
    $siteName  = trim($_POST['site_name'] ?? '');
    $adminUser = trim($_POST['admin_user'] ?? '');
    $adminMail = trim($_POST['admin_email'] ?? '');
    $adminPass = (string)($_POST['admin_pass'] ?? '');
    $adminPass2= (string)($_POST['admin_pass2'] ?? '');
    $trustProxy= !empty($_POST['trust_proxy']);
    $sitePath  = detectSitePath();

    // 校验
    if ($siteUrl === '' || $siteName === '') { $error = '站点 URL 与名称为必填'; }
    elseif (!filter_var($siteUrl, FILTER_VALIDATE_URL)) { $error = '站点 URL 格式不正确'; }
    elseif (strlen($adminUser) < 3 || !preg_match('/^[A-Za-z0-9_\-]+$/', $adminUser)) { $error = '管理员用户名至少 3 位且仅含字母、数字、下划线、连字符'; }
    elseif (!filter_var($adminMail, FILTER_VALIDATE_EMAIL)) { $error = '管理员邮箱格式不正确'; }
    elseif (strlen($adminPass) < 8) { $error = '管理员密码至少 8 位'; }
    elseif ($adminPass !== $adminPass2) { $error = '两次输入的密码不一致'; }

    renderHead('执行安装', 'install');

    if ($error) {
        echo '<div class="card"><div class="result result-warn">' . h($error) . '</div>'
            . '<div class="center"><a class="btn" href="?step=site">返回修改</a></div></div>';
        renderFoot();
        exit;
    }

    try {
        // 1. 生成密钥
        $secret = bin2hex(random_bytes(32));
        $log[] = '生成 SECRET_KEY 完成';

        // 2. 写入 .env
        if (!writeEnvFile($db['host'], $db['name'], $db['user'], $db['pass'], $secret, $siteUrl, $sitePath, $trustProxy)) {
            throw new RuntimeException('写入 .env 失败，请检查根目录写权限。');
        }
        $log[] = '写入 .env 配置文件完成';

        // 3. 连接数据库并导入结构
        $pdo = connectMysql($db['host'], $db['user'], $db['pass']);
        $pdo->exec("USE `" . str_replace('`', '', $db['name']) . "`");
        $r = importSchema($pdo);
        $log[] = '导入数据库结构完成（' . $r['statements'] . ' 条语句）';

        // 4. 创建管理员
        createAdmin($pdo, [
            'username' => $adminUser,
            'password' => $adminPass,
            'email'    => $adminMail,
            'nickname' => $adminUser,
        ]);
        $log[] = '创建管理员账号完成（' . $adminUser . '）';

        // 5. 写入默认设置
        $stmt = $pdo->prepare("INSERT INTO lm_setting (setting_key, setting_value) VALUES (?, ?)");
        foreach (defaultSettings($siteUrl, $siteName) as $k => $v) {
            $stmt->execute([$k, $v]);
        }
        $log[] = '写入默认站点设置完成';

        // 6. 写安装标记
        writeMarker();
        $log[] = '写入安装标记完成';

        // 清理敏感会话数据（保留 session 以便"删除 setup"按钮的 CSRF 校验）
        unset($_SESSION['db']);

        echo '<div class="card"><div class="result result-ok"><h2>安装成功！</h2>'
            . '<p>林梦博客已成功安装。下面是一些后续建议：</p></div>'
            . '<ul class="log">';
        foreach ($log as $l) {
            echo '<li>✓ ' . h($l) . '</li>';
        }
        echo '</ul>'
            . '<div class="result result-warn"><strong>重要：</strong>请立即删除 <code>setup/</code> 安装目录，以防被他人重复执行安装。</div>'
            . '<form method="post" action="?action=delete_setup" onsubmit="return confirm(\'确定删除 setup 目录？此操作不可恢复。\');" class="center">'
            . '<input type="hidden" name="csrf" value="' . h(setup_csrf_token()) . '">'
            . '<button type="submit" class="btn btn-danger">删除 setup 目录</button> '
            . '<a class="btn btn-primary" href="../">进入站点首页</a> '
            . '<a class="btn" href="../login.php">登录后台</a></form></div>';
        renderFoot();
        exit;
    } catch (Throwable $e) {
        echo '<div class="card"><div class="result result-warn">安装过程中出错：' . h($e->getMessage()) . '</div>';
        if ($log) {
            echo '<ul class="log">';
            foreach ($log as $l) {
                echo '<li>✓ ' . h($l) . '</li>';
            }
            echo '</ul>';
        }
        echo '<p class="muted">可清理已生成的 .env / 安装标记后重试，或检查错误信息手动修复。</p>'
            . '<div class="center"><a class="btn" href="?step=site">返回上一步</a></div></div>';
        renderFoot();
        exit;
    }
}
