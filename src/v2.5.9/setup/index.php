<?php
/**
 * 博客系统 - 安装向导
 * 访问 /setup 或 /setup/ 即可开始配置。
 * 安装完成后请删除本 setup 目录。
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_SETUP', true);
define('APP_ROOT', dirname(__DIR__));

$envFile    = APP_ROOT . '/.env';
$markerFile = APP_ROOT . '/includes/config_installed.php';
$docsRoot   = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? dirname(APP_ROOT)), '/');
$selfUrl    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
            . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
            . (dirname($_SERVER['SCRIPT_NAME'] ?? '/setup/index.php') === '/' ? '' : dirname($_SERVER['SCRIPT_NAME'] ?? '/setup/index.php'));

/* ---------------- 小工具函数 ---------------- */

/** 读取 .env 中某个键（供回填表单 / 判断安装状态） */
function setup_env_value(string $key): string {
    $file = APP_ROOT . '/.env';
    if (is_readable($file)) {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            list($k, $v) = explode('=', $line, 2);
            $v = trim($v);
            if (strlen($v) >= 2 && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))) {
                $v = substr($v, 1, -1);
            }
            if (trim($k) === $key) {
                return $v;
            }
        }
    }
    $serverValue = getenv($key);
    return ($serverValue !== false) ? $serverValue : '';
}

function setup_e(string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** 校验常用标识符/主机名，避免 DSN 注入 */
function setup_check_identifier(string $v, string $label): string {
    if (!preg_match('/^[A-Za-z0-9._\-]+$/', $v) || strlen($v) > 128) {
        throw new RuntimeException('“' . $label . '”包含非法字符');
    }
    return $v;
}

/** 安装期间的图片上传（Logo/Favicon） */
function setup_save_image(array $file, string $prefix): array {
    if (!isset($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'msg' => '未选择文件', 'url' => ''];
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return ['ok' => false, 'msg' => '图片过大（最大 5MB）', 'url' => ''];
    }
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico'];
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        return ['ok' => false, 'msg' => '不支持的图片格式（jpg/png/gif/webp/ico）', 'url' => ''];
    }
    if ($ext !== 'ico') {
        $info = @getimagesize((string)$file['tmp_name']);
        if ($info === false) {
            return ['ok' => false, 'msg' => '无效的图片文件', 'url' => ''];
        }
    }
    if (!is_uploaded_file((string)$file['tmp_name'])) {
        return ['ok' => false, 'msg' => '非法上传', 'url' => ''];
    }
    $dir = APP_ROOT . '/assets/uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        return ['ok' => false, 'msg' => '上传目录不可写：/assets/uploads', 'url' => ''];
    }
    $name = $prefix . date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file((string)$file['tmp_name'], $dir . '/' . $name)) {
        return ['ok' => false, 'msg' => '文件保存失败', 'url' => ''];
    }
    return ['ok' => true, 'msg' => '', 'url' => '/assets/uploads/' . $name];
}

/* ---------------- 状态判断 ---------------- */
$installed = is_file($markerFile) || setup_env_value('DB_NAME') !== '';
$force     = isset($_GET['force']) && $_GET['force'] === '1';
$canReconfigure = false;
if ($installed && $force && is_file($markerFile)) {
    try {
        require_once APP_ROOT . '/includes/config.php';
        require_once APP_ROOT . '/includes/Database.php';
        require_once APP_ROOT . '/includes/functions.php';
        $canReconfigure = isAdmin();
    } catch (Throwable $ignored) {
        $canReconfigure = false;
    }
}
$errors    = [];
$done      = false;

/* ---------------- CSRF ---------------- */
if (empty($_SESSION['app_setup_csrf'])) {
    $_SESSION['app_setup_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['app_setup_csrf'];

/* ---------------- 表单回填 ---------------- */
$old = [
    'db_host'    => setup_env_value('DB_HOST') !== '' ? setup_env_value('DB_HOST') : 'localhost',
    'db_port'    => setup_env_value('DB_PORT') !== '' ? setup_env_value('DB_PORT') : '3306',
    'db_name'    => setup_env_value('DB_NAME'),
    'db_user'    => setup_env_value('DB_USER'),
    'db_pass'    => '',
    'site_name'  => '',
    'site_desc'  => '',
    'site_url'   => setup_env_value('SITE_URL') !== '' ? setup_env_value('SITE_URL') : 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
    'admin_user' => '',
    'admin_email'=> '',
    'admin_pass' => '',
    'logo'       => '',
    'favicon'    => '',
    'site_keywords'=> '',
    'site_author'  => '',
    'site_email'   => '',
    'site_telegram'=> '',
    'site_icp'   => '',
    'site_path'    => '',
    'avatar'       => '',
    'background'   => '',
];

/* ---------------- 处理提交 ---------------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (!$installed || ($force && $canReconfigure))) {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($csrfToken, (string)$token)) {
        $errors[] = '安全校验失败，请刷新页面后重试';
    } else {
        $old = [
            'db_host'    => trim($_POST['db_host'] ?? 'localhost'),
            'db_port'    => trim($_POST['db_port'] ?? '3306'),
            'db_name'    => trim($_POST['db_name'] ?? ''),
            'db_user'    => trim($_POST['db_user'] ?? ''),
            'db_pass'    => $_POST['db_pass'] ?? '',
            'site_name'  => trim($_POST['site_name'] ?? ''),
            'site_desc'  => trim($_POST['site_desc'] ?? ''),
            'site_url'   => trim($_POST['site_url'] ?? ''),
            'admin_user' => trim($_POST['admin_user'] ?? ''),
            'admin_email'=> trim($_POST['admin_email'] ?? ''),
            'admin_pass' => $_POST['admin_pass'] ?? '',
            'logo'       => trim($_POST['logo_url'] ?? ''),
            'favicon'    => trim($_POST['favicon_url'] ?? ''),
            'site_keywords'=> trim($_POST['site_keywords'] ?? ''),
            'site_author'  => trim($_POST['site_author'] ?? ''),
            'site_email'   => trim($_POST['site_email'] ?? ''),
            'site_telegram'=> trim($_POST['site_telegram'] ?? ''),
            'site_icp'   => trim($_POST['site_icp'] ?? ''),
            'site_path'    => trim($_POST['site_path'] ?? ''),
            'avatar'       => trim($_POST['avatar_url'] ?? ''),
            'background'   => trim($_POST['background_url'] ?? ''),
        ];

        try {
            // 基本校验
            if ($old['db_name'] === '' || $old['db_user'] === '') {
                throw new RuntimeException('数据库名和用户名不能为空');
            }
            setup_check_identifier($old['db_host'], '数据库主机');
            if (!preg_match('/^\d{1,5}$/', $old['db_port']) || (int)$old['db_port'] < 1 || (int)$old['db_port'] > 65535) {
                throw new RuntimeException('数据库端口不合法');
            }
            setup_check_identifier($old['db_name'], '数据库名');
            if ($old['site_name'] === '') {
                throw new RuntimeException('网站标题不能为空');
            }
            if ($old['site_url'] === '' || !preg_match('#^https?://#i', $old['site_url'])) {
                throw new RuntimeException('站点地址必须以 http:// 或 https:// 开头');
            }
            if ($old['site_email'] !== '' && !filter_var($old['site_email'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('站点联系邮箱格式不正确');
            }
            if ($old['site_path'] !== '' && strpos($old['site_path'], '/') !== 0) {
                throw new RuntimeException('站点子路径必须以 / 开头（如 /blog），根目录部署请留空');
            }
            if ($old['admin_user'] === '' || strlen($old['admin_user']) < 3) {
                throw new RuntimeException('管理员用户名至少 3 个字符');
            }
            if (!filter_var($old['admin_email'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('管理员邮箱格式不正确');
            }
            if (strlen($old['admin_pass']) < 8) {
                throw new RuntimeException('管理员密码至少 8 位');
            }

            // 连接数据库（先不带库名，用于建库）
            $dsn = 'mysql:host=' . $old['db_host'] . ';port=' . (int)$old['db_port'] . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $old['db_user'], $old['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // 建库
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $old['db_name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('USE `' . $old['db_name'] . '`');

            // 执行建表
            $schemaSql = file_get_contents(APP_ROOT . '/setup/schema.sql');
            if ($schemaSql === false) {
                throw new RuntimeException('找不到 setup/schema.sql');
            }
            $schemaSql = preg_replace('/^\s*--.*$/m', '', $schemaSql);
            $statements = array_values(array_filter(array_map('trim', explode(';', $schemaSql))));
            foreach ($statements as $stmt) {
                $pdo->exec($stmt);
            }

            // 可选：导入由后台「数据备份」导出的 ZIP。旧版 lm_ 表名在导入时映射到 app_。
            $backupFile = $_FILES['backup_zip'] ?? null;
            if (is_array($backupFile) && ($backupFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if (($backupFile['error'] ?? null) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($backupFile['tmp_name'] ?? ''))) {
                    throw new RuntimeException('备份 ZIP 上传失败，请检查 PHP 上传大小限制。');
                }
                if (($backupFile['size'] ?? 0) > 100 * 1024 * 1024) {
                    throw new RuntimeException('备份 ZIP 不能超过 100 MB。');
                }
                require_once APP_ROOT . '/includes/Backup.php';
                $backupInfo = Backup::inspectZip((string)$backupFile['tmp_name']);
                if (!$backupInfo['valid'] || $backupInfo['sql_size'] > 200 * 1024 * 1024) {
                    throw new RuntimeException($backupInfo['message'] ?: '备份内容无效或 SQL 文件过大。');
                }
                Backup::importZip((string)$backupFile['tmp_name'], $pdo, APP_ROOT, true, true);
            }

            // 管理员账号（密码使用 password_hash）
            $passwordHash = password_hash($old['admin_pass'], PASSWORD_DEFAULT, ['cost' => 12]);
            $exists = $pdo->prepare('SELECT id FROM `app_admin` WHERE username = ? LIMIT 1');
            $exists->execute([$old['admin_user']]);
            $adminId = $exists->fetchColumn();
            if ($adminId) {
                $upd = $pdo->prepare('UPDATE `app_admin` SET password = ?, email = ?, nickname = ?, status = 1 WHERE id = ?');
                $upd->execute([$passwordHash, $old['admin_email'], $old['site_author'] !== '' ? $old['site_author'] : $old['admin_user'], $adminId]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO `app_admin` (username, password, email, nickname, role, status, created_at)
                     VALUES (?, ?, ?, ?, ?, 1, NOW())'
                );
                $stmt->execute([$old['admin_user'], $passwordHash, $old['admin_email'], $old['site_author'] !== '' ? $old['site_author'] : $old['admin_user'], 'admin']);
            }

            // 图标上传
            $logoUrl = $old['logo'];
            if (!empty($_FILES['logo_upload']['name'])) {
                $up = setup_save_image($_FILES['logo_upload'], 'logo_');
                if (!$up['ok']) {
                    throw new RuntimeException('Logo 上传失败：' . $up['msg']);
                }
                $logoUrl = $up['url'];
            }
            $faviconUrl = $old['favicon'];
            if (!empty($_FILES['favicon_upload']['name'])) {
                $up = setup_save_image($_FILES['favicon_upload'], 'favicon_');
                if (!$up['ok']) {
                    throw new RuntimeException('Favicon 上传失败：' . $up['msg']);
                }
                $faviconUrl = $up['url'];
            }
            $avatarUrl = $old['avatar'];
            if (!empty($_FILES['avatar_upload']['name'])) {
                $up = setup_save_image($_FILES['avatar_upload'], 'avatar_');
                if (!$up['ok']) {
                    throw new RuntimeException('头像上传失败：' . $up['msg']);
                }
                $avatarUrl = $up['url'];
            }
            $bgUrl = $old['background'];
            if (!empty($_FILES['background_upload']['name'])) {
                $up = setup_save_image($_FILES['background_upload'], 'bg_');
                if (!$up['ok']) {
                    throw new RuntimeException('背景图上传失败：' . $up['msg']);
                }
                $bgUrl = $up['url'];
            }

            // 写入默认设置
            $defaults = [
                'site_name'                => $old['site_name'],
                'site_description'         => $old['site_desc'],
                'site_keywords'            => $old['site_keywords'] !== '' ? $old['site_keywords'] : '博客,技术,生活',
                'site_author'              => $old['site_author'] !== '' ? $old['site_author'] : $old['site_name'],
                'site_email'               => $old['site_email'],
                'site_telegram'            => $old['site_telegram'],
                'site_icp'                 => $old['site_icp'],
                'site_icp_links'           => '',
                'site_avatar'              => $avatarUrl !== '' ? $avatarUrl : $logoUrl,
                'site_background'          => $bgUrl,
                'site_background_overlay'  => '0.45',
                'site_background_position' => 'center center',
                'site_background_size'     => 'cover',
                'site_footer'              => '',
                'admin_email'              => $old['admin_email'],
                'site_start_date'          => date('Y-m-d H:i:s'),
                'site_time_offset'         => '0',
                'site_theme'               => 'auto',
                'site_logo'                => $logoUrl,
                'site_favicon'             => $faviconUrl,
                'comment_need_approve'     => '0',
                'article_comment_enable'   => '1',
                'guestbook_enable'         => '1',
                'site_maintenance'         => '0',
                'ai_summary_enabled'       => '0',
                'github_oauth_enabled'     => '0',
                'gitcode_oauth_enabled'    => '0',
            ];
            $ins = $pdo->prepare('INSERT INTO `app_setting` (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            foreach ($defaults as $k => $v) {
                $ins->execute([$k, $v]);
            }

            $secretKey = bin2hex(random_bytes(32));

            // 写 .env
            $envLines = [
                '# 博客系统 - 环境配置（由安装向导生成，请勿提交到版本库）',
                '',
                '# 数据库',
                'DB_HOST=' . $old['db_host'],
                'DB_PORT=' . (int)$old['db_port'],
                'DB_NAME=' . $old['db_name'],
                'DB_USER=' . $old['db_user'],
                'DB_PASS=' . $old['db_pass'],
                '',
                '# 安全密钥（AES-256-GCM 优先，不支持时回退 CBC；不要修改）',
                'SECRET_KEY=' . $secretKey,
                '',
                '# 站点',
                'SITE_URL=' . rtrim($old['site_url'], '/'),
                'SITE_PATH=' . $old['site_path'],
                '',
                '# 反向代理（仅当位于反向代理后且代理已覆盖该头时启用）',
                'APP_TRUST_PROXY=false',
                '',
            ];
            if (@file_put_contents($envFile, implode("\n", $envLines)) === false) {
                throw new RuntimeException('无法写入 .env 文件，请检查目录权限');
            }

            // 写安装标记
            $marker = "<?php\n// 安装完成标记，生成于 " . date('Y-m-d H:i:s') . "\n";
            if (@file_put_contents($markerFile, $marker) === false) {
                throw new RuntimeException('无法写入 includes/config_installed.php，请检查目录权限');
            }

            // 清空 setup 会话 token
            unset($_SESSION['app_setup_csrf']);
            $done = true;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?php echo $done ? '安装完成' : '安装向导'; ?> · 博客系统</title>
<style>
:root{
  --bg:#f7f3ed; --card:#fffdf9; --text:#2b2724; --muted:#6b5d50;
  --accent:#a6682e; --accent-hover:#8b5526; --border:#e5dccf;
  --danger:#b3433a; --ok:#4a7c59;
}
*{box-sizing:border-box;margin:0;padding:0}
body{
  font-family:"LXGW WenKai","PingFang SC","Microsoft YaHei",system-ui,sans-serif;
  background:var(--bg); color:var(--text); line-height:1.7;
  min-height:100vh; padding:2rem 1rem;
}
.wrap{max-width:760px;margin:0 auto}
.brand{text-align:center;margin-bottom:1.5rem}
.brand h1{font-size:1.6rem;font-weight:700;letter-spacing:.02em}
.brand p{color:var(--muted);font-size:.9rem}
.card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:1.5rem 1.75rem;margin-bottom:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,.04)}
h2{font-size:1.05rem;margin-bottom:1rem;padding-bottom:.5rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.5rem}
h2 .no{display:inline-flex;align-items:center;justify-content:center;width:1.3rem;height:1.3rem;border-radius:50%;background:var(--accent);color:#fff;font-size:.75rem;flex-shrink:0}
label{display:block;font-size:.88rem;color:var(--muted);margin:.8rem 0 .3rem}
input[type=text],input[type=url],input[type=email],input[type=password],input[type=number]{width:100%;padding:.55rem .75rem;border:1px solid var(--border);border-radius:8px;font-size:.95rem;background:#fff;color:var(--text)}
input:focus{outline:none;border-color:var(--accent)}
.row{display:grid;grid-template-columns:1fr 1fr;gap:0 1rem}
.hint{font-size:.78rem;color:var(--muted);margin-top:.25rem}
.alert{padding:.7rem .9rem;border-radius:8px;margin-bottom:1rem;font-size:.9rem}
.alert-error{background:#f7e6e3;color:var(--danger);border:1px solid #e6bfb8}
.btn{display:inline-block;padding:.65rem 1.6rem;border-radius:8px;border:none;cursor:pointer;font-size:.95rem;font-weight:600;text-decoration:none;transition:opacity .2s}
.btn-primary{background:var(--accent);color:#fff}
.btn-primary:hover{background:var(--accent-hover);opacity:1}
.btn-ghost{background:transparent;color:var(--accent);border:1px solid var(--accent)}
.footer{text-align:center;color:var(--muted);font-size:.8rem;margin-top:1rem}
.footer a{color:inherit;text-decoration:underline}
.success-box{text-align:center;padding:2rem 1rem}
.success-box .check{font-size:3rem;color:var(--ok);line-height:1}
.success-box h1{font-size:1.4rem;margin:1rem 0 .5rem}
.success-box ul{list-style:none;margin:1.25rem auto;max-width:420px;text-align:left}
.success-box li{padding:.45rem .8rem;background:var(--bg);border-radius:8px;margin-bottom:.5rem;font-size:.88rem}
code{background:#f2eadf;padding:.1rem .35rem;border-radius:4px;font-size:.85em}
.warn{color:var(--danger);font-weight:600}
@media(max-width:560px){.row{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand">
    <h1>博客系统 · 安装向导</h1>
    <p>配置数据库、站点信息与管理员账号，完成后即可开始使用</p>
  </div>

<?php if ($done): ?>
  <div class="card success-box">
    <div class="check">✓</div>
    <h1>安装完成</h1>
    <ul>
      <li>前台地址：<a href="<?php echo setup_e($old['site_url']); ?>"><?php echo setup_e($old['site_url']); ?></a></li>
      <li>后台地址：<code><?php echo setup_e(rtrim($old['site_url'], '/') . '/admin/'); ?></code></li>
      <li>管理员账号：<code><?php echo setup_e($old['admin_user']); ?></code></li>
    </ul>
    <p class="warn" style="margin-bottom:1.5rem">请立即删除服务器上的 <code>setup/</code> 目录，避免被他人重复安装。</p>
    <a class="btn btn-primary" href="<?php echo setup_e(rtrim($old['site_url'], '/') . '/admin/'); ?>">进入后台</a>
    <a class="btn btn-ghost" href="<?php echo setup_e($old['site_url']); ?>">访问前台</a>
  </div>

<?php elseif ($installed && !$force): ?>
  <div class="card">
    <h2>站点已安装</h2>
    <p>检测到 <code>.env</code> 或 <code>includes/config_installed.php</code> 已存在，站点已被安装。</p>
    <p class="hint" style="margin:.75rem 0 1rem">如需重新配置，请先以管理员身份登录后台，再返回此处。</p>
    <a class="btn btn-ghost" href="/login.php">管理员登录</a>
  </div>

<?php elseif ($installed && !$canReconfigure): ?>
  <div class="card"><h2>需要管理员权限</h2><p>重新配置仅允许当前站点管理员操作。</p><a class="btn btn-ghost" href="/login.php">管理员登录</a>
  </div>

<?php else: ?>
  <?php if (!empty($errors)): ?>
    <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?php echo setup_e($err); ?></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <form method="POST" action="" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?php echo setup_e($csrfToken); ?>">

    <div class="card">
      <h2><span class="no">1</span>数据库连接</h2>
      <div class="row">
        <div>
          <label>数据库主机</label>
          <input type="text" name="db_host" value="<?php echo setup_e($old['db_host']); ?>">
        </div>
        <div>
          <label>端口</label>
          <input type="number" name="db_port" value="<?php echo setup_e($old['db_port']); ?>">
        </div>
      </div>
      <div class="row">
        <div>
          <label>数据库名</label>
          <input type="text" name="db_name" value="<?php echo setup_e($old['db_name']); ?>" placeholder="my_blog">
          <div class="hint">不存在时将自动创建</div>
        </div>
        <div>
          <label>数据库用户名</label>
          <input type="text" name="db_user" value="<?php echo setup_e($old['db_user']); ?>">
        </div>
      </div>
      <label>数据库密码</label>
      <input type="password" name="db_pass" value="" autocomplete="new-password">
    </div>

    <div class="card">
      <h2><span class="no">2</span>站点信息</h2>
      <label>网站标题</label>
      <input type="text" name="site_name" value="<?php echo setup_e($old['site_name']); ?>" placeholder="我的博客">
      <label>网站副标题 / 描述</label>
      <input type="text" name="site_desc" value="<?php echo setup_e($old['site_desc']); ?>" placeholder="记录生活，分享技术">
      <label>站点地址</label>
      <input type="url" name="site_url" value="<?php echo setup_e($old['site_url']); ?>" placeholder="https://example.com">
      <div class="hint">用于 RSS、OAuth 回调等场景，以 / 结尾可有可无</div>
      <label>关键词（英文逗号分隔）</label>
      <input type="text" name="site_keywords" value="<?php echo setup_e($old['site_keywords']); ?>" placeholder="博客,技术,生活">
      <div class="row">
        <div>
          <label>站长昵称</label>
          <input type="text" name="site_author" value="<?php echo setup_e($old['site_author']); ?>" placeholder="默认与网站标题相同">
        </div>
        <div>
          <label>联系邮箱（前台展示，可留空）</label>
          <input type="email" name="site_email" value="<?php echo setup_e($old['site_email']); ?>">
        </div>
      </div>
      <div class="row">
        <div>
          <label>Telegram 链接（可留空）</label>
          <input type="text" name="site_telegram" value="<?php echo setup_e($old['site_telegram']); ?>" placeholder="https://t.me/yourname">
        </div>
        <div>
          <label>ICP 备案号（可留空）</label>
          <input type="text" name="site_icp" value="<?php echo setup_e($old['site_icp']); ?>">
        </div>
      </div>
      <label>站点子路径（根目录部署请留空）</label>
      <input type="text" name="site_path" value="<?php echo setup_e($old['site_path']); ?>" placeholder="/blog">
    </div>

    <div class="card">
      <h2><span class="no">3</span>管理员账号</h2>
      <div class="row">
        <div>
          <label>用户名</label>
          <input type="text" name="admin_user" value="<?php echo setup_e($old['admin_user']); ?>" autocomplete="username">
        </div>
        <div>
          <label>邮箱</label>
          <input type="email" name="admin_email" value="<?php echo setup_e($old['admin_email']); ?>">
        </div>
      </div>
      <label>密码</label>
      <input type="password" name="admin_pass" value="" autocomplete="new-password">
      <div class="hint">至少 8 位；建议包含大小写字母、数字与特殊字符</div>
    </div>

    <div class="card">
      <h2><span class="no">4</span>导入站点备份（可选）</h2>
      <p class="hint">选择后台「数据备份」导出的 ZIP，可恢复数据库和文章。导入会覆盖同名数据表；上面填写的新管理员账号会在导入后写入。</p>
      <label>备份 ZIP</label>
      <input type="file" name="backup_zip" accept=".zip,application/zip">
    </div>

    <div class="card">
      <h2><span class="no">5</span>头像与背景（可选）</h2>
      <div class="hint" style="margin-bottom:.5rem">头像与背景图都可在后台「网站设置」中随时更换。</div>
      <div class="row">
        <div>
          <label>站点头像（直链）</label>
          <input type="url" name="avatar_url" value="<?php echo setup_e($old['avatar']); ?>" placeholder="https://... 或留空上传">
          <label style="margin-top:.6rem">… 或本地上传</label>
          <input type="file" name="avatar_upload" accept="image/*">
        </div>
        <div>
          <label>网站 Logo（直链）</label>
          <input type="url" name="logo_url" value="<?php echo setup_e($old['logo']); ?>" placeholder="https://... 或留空上传">
          <label style="margin-top:.6rem">… 或本地上传</label>
          <input type="file" name="logo_upload" accept="image/*">
        </div>
      </div>
      <div class="row">
        <div>
          <label>网站图标 Favicon（直链）</label>
          <input type="url" name="favicon_url" value="<?php echo setup_e($old['favicon']); ?>" placeholder="https://... 或留空上传">
          <label style="margin-top:.6rem">… 或本地上传</label>
          <input type="file" name="favicon_upload" accept="image/*,image/x-icon,image/vnd.microsoft.icon">
        </div>
        <div>
          <label>网站背景图（直链）</label>
          <input type="url" name="background_url" value="<?php echo setup_e($old['background']); ?>" placeholder="https://... 或留空上传；留空则使用纯色背景">
          <label style="margin-top:.6rem">… 或本地上传</label>
          <input type="file" name="background_upload" accept="image/*">
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary" style="width:100%">开始安装</button>
  </form>
<?php endif; ?>

  <div class="footer">安装完成后请删除服务器上的 <code>setup/</code> 目录，避免重复安装</div>
</div>
</body>
</html>
