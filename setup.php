<?php
/**
 * 林梦博客 - 安装程序 setup.php
 *
 * 访问 /setup.php 即可引导完成：
 *   1. 运行环境检测（PHP 版本 / 扩展 / 目录可写）
 *   2. 配置并测试数据库连接
 *   3. 创建数据表 + 默认设置 + 管理员账号
 *   4. 生成 .env（含 SECRET_KEY）与安装标记
 *
 * 安全说明：
 *   - 本文件自包含，不引入 includes/config.php（避免未配置时循环跳转）。
 *   - 全流程 CSRF 保护；表单输入严格校验；密码使用 bcrypt(cost=12) 哈希。
 *   - 安装完成后写入 includes/config_installed.php 标记，再次访问将拒绝运行。
 *   - 强烈建议安装成功后删除本文件。
 */

define('LM_ROOT', __DIR__);
define('CSRF_TOKEN_NAME', 'lm_csrf_token');
define('SETUP_VERSION', '1.0');

// 安装标记文件
$installedMarker = LM_ROOT . '/includes/config_installed.php';
$envFile         = LM_ROOT . '/.env';

// 基础响应头
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

// 会话（仅安装过程使用）
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

/* ----------------------------- 工具函数 ----------------------------- */

function setup_csrf_token() {
    if (empty($_SESSION['setup_csrf'])) {
        $_SESSION['setup_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['setup_csrf'];
}

function setup_csrf_field() {
    return '<input type="hidden" name="setup_csrf" value="' . htmlspecialchars(setup_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function setup_csrf_ok() {
    return isset($_POST['setup_csrf'], $_SESSION['setup_csrf'])
        && is_string($_POST['setup_csrf']) && is_string($_SESSION['setup_csrf'])
        && hash_equals($_SESSION['setup_csrf'], $_POST['setup_csrf']);
}

function setup_e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** 生成 .env 行的带引号值（转义反斜杠与双引号，去除换行） */
function setup_env_value($v) {
    $v = str_replace(["\r", "\n"], '', (string)$v);
    $v = str_replace(['\\', '"'], ['\\\\', '\\"'], $v);
    return '"' . $v . '"';
}

/** 密码强度校验（与 includes/Security.php::checkPasswordStrength 逻辑一致） */
function setup_password_strength($password) {
    $score = 0;
    $errors = [];
    if (strlen($password) < 8) {
        $errors[] = '密码长度至少8位';
    } else {
        $score++;
    }
    if (!preg_match('/[A-Z]/', $password)) { $errors[] = '需包含大写字母'; } else { $score++; }
    if (!preg_match('/[a-z]/', $password)) { $errors[] = '需包含小写字母'; } else { $score++; }
    if (!preg_match('/[0-9]/', $password)) { $errors[] = '需包含数字'; } else { $score++; }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) { $errors[] = '需包含特殊字符'; } else { $score++; }

    $common = ['123456', 'password', 'admin', 'root', 'qwerty', '111111', '12345678'];
    foreach ($common as $c) {
        if (stripos($password, $c) !== false) {
            $errors[] = '密码过于简单，包含常见弱口令';
            $score = 0;
            break;
        }
    }
    return ['score' => $score, 'errors' => $errors];
}

/** 推断当前站点 URL（用于预填） */
function setup_guess_site_url() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    return $scheme . '://' . $host;
}

/* ----------------------------- 数据表结构 ----------------------------- */
/** 全部数据表 DDL（经代码核对，CREATE TABLE IF NOT EXISTS，对已存在的库安全） */
function setup_schema_sql() {
    return <<<'SQL'
CREATE TABLE IF NOT EXISTS `lm_admin` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'user',
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `nickname` VARCHAR(50) DEFAULT NULL,
  `avatar` VARCHAR(500) DEFAULT NULL,
  `website` VARCHAR(255) DEFAULT NULL,
  `bio` TEXT,
  `github_id` VARCHAR(50) DEFAULT NULL,
  `github_username` VARCHAR(100) DEFAULT NULL,
  `last_login` DATETIME DEFAULT NULL,
  `last_ip` VARCHAR(45) DEFAULT NULL,
  `login_fail_count` INT NOT NULL DEFAULT 0,
  `lock_until` DATETIME DEFAULT NULL,
  `remember_token` VARCHAR(64) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  UNIQUE KEY `uk_email` (`email`),
  KEY `idx_github_id` (`github_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lm_article` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `content` LONGTEXT NOT NULL,
  `excerpt` VARCHAR(500) NOT NULL DEFAULT '',
  `cover_image` VARCHAR(500) NOT NULL DEFAULT '',
  `category_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `tags` VARCHAR(255) NOT NULL DEFAULT '',
  `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
  `is_top` TINYINT(1) NOT NULL DEFAULT 0,
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  `author_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  KEY `idx_status` (`status`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_author_id` (`author_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lm_article_image` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `article_id` INT UNSIGNED NOT NULL,
  `image_url` VARCHAR(500) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_article_id` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lm_article_like` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `article_id` INT UNSIGNED NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_article_ip` (`article_id`, `ip`),
  KEY `idx_article_id` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lm_category` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lm_comment` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `article_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `parent_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `nickname` VARCHAR(50) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `website` VARCHAR(255) DEFAULT NULL,
  `content` TEXT NOT NULL,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `user_agent` VARCHAR(512) NOT NULL DEFAULT '',
  `status` TINYINT(1) NOT NULL DEFAULT 0,
  `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_article_id` (`article_id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lm_gallery` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `github_path` VARCHAR(255) NOT NULL,
  `raw_url` VARCHAR(512) NOT NULL,
  `cdn_url` VARCHAR(512) NOT NULL DEFAULT '',
  `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
  `file_type` VARCHAR(50) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_username` (`username`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lm_link` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `url` VARCHAR(500) NOT NULL,
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `logo` VARCHAR(500) NOT NULL DEFAULT '',
  `sort_order` INT NOT NULL DEFAULT 0,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lm_link_apply` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_name` VARCHAR(100) NOT NULL,
  `site_url` VARCHAR(500) NOT NULL,
  `site_description` VARCHAR(255) NOT NULL DEFAULT '',
  `email` VARCHAR(100) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `reply` TEXT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lm_sponsor` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `url` VARCHAR(500) NOT NULL DEFAULT '',
  `detail` VARCHAR(255) NOT NULL DEFAULT '',
  `icon` VARCHAR(500) NOT NULL DEFAULT '',
  `sort_order` INT NOT NULL DEFAULT 0,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lm_service` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `host` VARCHAR(255) NOT NULL,
  `type` VARCHAR(10) NOT NULL DEFAULT 'http',
  `port` INT NOT NULL DEFAULT 80,
  `path` VARCHAR(255) NOT NULL DEFAULT '/',
  `sort_order` INT NOT NULL DEFAULT 0,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lm_service_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` INT UNSIGNED NOT NULL,
  `status` TINYINT(1) NOT NULL,
  `latency_ms` INT NOT NULL DEFAULT 0,
  `message` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_service_id` (`service_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lm_setting` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lm_ai_provider` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `api_url` VARCHAR(500) NOT NULL,
  `api_key` VARCHAR(500) NOT NULL,
  `model` VARCHAR(100) NOT NULL,
  `compatibility` VARCHAR(20) NOT NULL DEFAULT 'openai',
  `request_template` TEXT,
  `response_path` VARCHAR(255),
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lm_ai_summary_cache` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `article_id` INT UNSIGNED NOT NULL,
  `provider_id` INT UNSIGNED NOT NULL,
  `content_hash` VARCHAR(32) NOT NULL,
  `summary` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_article_provider` (`article_id`, `provider_id`),
  KEY `idx_provider_id` (`provider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lm_visit_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page` VARCHAR(255) NOT NULL,
  `referer` VARCHAR(512) DEFAULT NULL,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `user_agent` VARCHAR(512) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lm_user_apply` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `nickname` VARCHAR(50) DEFAULT NULL,
  `website` VARCHAR(255) DEFAULT NULL,
  `reason` TEXT NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `handled_at` DATETIME DEFAULT NULL,
  `handled_by` INT UNSIGNED DEFAULT NULL,
  `reply` TEXT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lm_login_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `username` VARCHAR(50) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) NOT NULL,
  `status` VARCHAR(10) NOT NULL,
  `fail_reason` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_username` (`username`),
  KEY `idx_ip` (`ip`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lm_login_lock` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier` VARCHAR(100) NOT NULL,
  `fail_count` INT NOT NULL DEFAULT 0,
  `locked_until` INT NOT NULL DEFAULT 0,
  `updated_at` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_identifier` (`identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lm_rate_limit` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier` VARCHAR(100) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `attempts` INT NOT NULL DEFAULT 0,
  `first_attempt` INT NOT NULL DEFAULT 0,
  `last_attempt` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_identifier_action` (`identifier`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
}

/* ----------------------------- 视图：页面骨架 ----------------------------- */

function setup_header($step) {
    $steps = ['check' => '环境检测', 'database' => '数据库', 'admin' => '管理员', 'finish' => '完成'];
    $order = ['check', 'database', 'admin', 'finish'];
    $currentIdx = array_search($step, $order, true);
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<meta name="robots" content="noindex,nofollow">';
    echo '<title>安装程序 - 林梦博客</title><style>';
    echo setup_css();
    echo '</style></head><body><div class="wrap"><div class="card">';
    echo '<div class="brand">林梦博客 · 安装程序</div>';
    // 步骤指示
    echo '<div class="steps">';
    foreach ($order as $i => $s) {
        $cls = $s === $step ? 'active' : ($i < $currentIdx ? 'done' : '');
        echo '<div class="step ' . $cls . '"><span class="num">' . ($i + 1) . '</span><span class="lbl">' . setup_e($steps[$s]) . '</span></div>';
    }
    echo '</div>';
}

function setup_footer() {
    echo '<div class="foot">林梦博客安装程序 v' . SETUP_VERSION . ' · 安装完成后请删除 setup.php</div>';
    echo '</div></div></body></html>';
}

function setup_css() {
    return <<<'CSS'
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#0f1115;color:#e8e8ea;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.wrap{width:100%;max-width:680px}
.card{background:#1a1d24;border:1px solid #2a2f3a;border-radius:16px;padding:36px;box-shadow:0 20px 60px rgba(0,0,0,.4)}
.brand{font-size:1.3rem;font-weight:600;text-align:center;margin-bottom:24px;color:#f0f0f2;letter-spacing:.5px}
.steps{display:flex;justify-content:space-between;margin-bottom:28px;position:relative}
.steps:before{content:"";position:absolute;top:14px;left:8%;right:8%;height:2px;background:#2a2f3a;z-index:0}
.step{position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;gap:6px;flex:1}
.step .num{width:30px;height:30px;border-radius:50%;background:#2a2f3a;color:#8b909c;display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:600;border:2px solid #2a2f3a}
.step .lbl{font-size:.72rem;color:#8b909c}
.step.active .num{background:#c8853a;border-color:#c8853a;color:#fff}
.step.active .lbl{color:#e8b66a}
.step.done .num{background:#3a7d44;border-color:#3a7d44;color:#fff}
.step.done .lbl{color:#7fb88a}
h2{font-size:1.05rem;margin-bottom:6px;color:#f0f0f2}
p.lead{color:#9aa0ac;font-size:.85rem;margin-bottom:20px;line-height:1.6}
.form-group{margin-bottom:16px}
label{display:block;font-size:.8rem;color:#c0c4cc;margin-bottom:6px}
input[type=text],input[type=password],input[type=email],input[type=url]{width:100%;padding:10px 12px;background:#0f1115;border:1px solid #2a2f3a;border-radius:8px;color:#e8e8ea;font-size:.88rem;transition:border-color .15s}
input:focus{outline:none;border-color:#c8853a}
.hint{font-size:.72rem;color:#6b7080;margin-top:5px}
.row{display:flex;gap:14px}.row .form-group{flex:1}
.checkbox{display:flex;align-items:center;gap:8px;font-size:.85rem;color:#c0c4cc}
.checkbox input{width:auto}
.btn{display:inline-block;width:100%;padding:11px 16px;background:#c8853a;color:#fff;border:none;border-radius:8px;font-size:.9rem;font-weight:600;cursor:pointer;transition:background .15s}
.btn:hover{background:#b3742f}
.btn.secondary{background:#2a2f3a;color:#c0c4cc}.btn.secondary:hover{background:#333845}
.alert{padding:10px 14px;border-radius:8px;margin-bottom:18px;font-size:.82rem}
.alert-error{background:#3a2230;border:1px solid #5a2d40;color:#e88aa0}
.alert-success{background:#223a2c;border:1px solid #2d5a3a;color:#8ae0a0}
.check-list{list-style:none;margin:14px 0}
.check-list li{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #2a2f3a;font-size:.85rem}
.check-list li:last-child{border-bottom:none}
.ok{color:#7fb88a}.bad{color:#e88aa0}.muted{color:#8b909c}
.actions{display:flex;gap:12px;margin-top:8px}
.actions .btn{flex:1}
.result{text-align:center;padding:30px 10px}
.result .ico{font-size:2.6rem;margin-bottom:12px}
.foot{text-align:center;margin-top:22px;font-size:.72rem;color:#6b7080}
a{color:#e8b66a;text-decoration:none}a:hover{text-decoration:underline}
@media(max-width:520px){.card{padding:24px}.row{flex-direction:column}.steps .lbl{display:none}}
CSS;
}

/* ----------------------------- 视图：各步骤 ----------------------------- */

function view_check($checks, $error = '') {
    setup_header('check');
    echo '<h2>运行环境检测</h2><p class="lead">请确认服务器满足最低运行要求后再继续安装。</p>';
    if ($error) echo '<div class="alert alert-error">' . setup_e($error) . '</div>';
    echo '<ul class="check-list">';
    foreach ($checks as $name => $ok) {
        $cls = $ok ? 'ok' : 'bad';
        $mark = $ok ? '✓' : '✕';
        echo '<li><span class="' . $cls . '">' . $mark . '</span><span>' . setup_e($name) . '</span></li>';
    }
    echo '</ul>';
    $allOk = !in_array(false, $checks, true);
    echo '<form method="post" action="setup.php"><div class="actions">';
    echo setup_csrf_field();
    echo '<input type="hidden" name="action" value="check_next">';
    echo '<button type="submit" class="btn"' . ($allOk ? '' : ' disabled') . '>下一步：配置数据库</button>';
    echo '</div></form>';
    if (!$allOk) echo '<p class="hint">存在未通过的检测项，请先修复后再继续。</p>';
    setup_footer();
}

function view_database($error = '', $old = []) {
    setup_header('database');
    echo '<h2>数据库配置</h2><p class="lead">填写 MySQL 数据库连接信息。可勾选「自动创建数据库」让程序创建一个新库。</p>';
    if ($error) echo '<div class="alert alert-error">' . setup_e($error) . '</div>';
    echo '<form method="post" action="setup.php">';
    echo setup_csrf_field();
    echo '<input type="hidden" name="action" value="db_test">';
    echo '<div class="row">';
    echo '<div class="form-group"><label>数据库主机</label><input type="text" name="db_host" value="' . setup_e($old['db_host'] ?? 'localhost') . '" required></div>';
    echo '<div class="form-group"><label>数据库名</label><input type="text" name="db_name" value="' . setup_e($old['db_name'] ?? '') . '" required></div>';
    echo '</div>';
    echo '<div class="row">';
    echo '<div class="form-group"><label>数据库用户名</label><input type="text" name="db_user" value="' . setup_e($old['db_user'] ?? '') . '" required></div>';
    echo '<div class="form-group"><label>数据库密码</label><input type="password" name="db_pass" value="" placeholder="留空表示无密码" autocomplete="new-password"></div>';
    echo '</div>';
    echo '<div class="form-group checkbox"><input type="checkbox" name="db_create" id="db_create"' . (!empty($old['db_create']) ? ' checked' : '') . '><label for="db_create">数据库不存在时自动创建</label></div>';
    echo '<div class="actions"><button type="submit" class="btn">测试连接并继续</button></div>';
    echo '<p class="hint">密码仅用于本次连接测试并写入 .env，不会被显示或回传。</p>';
    echo '</form>';
    setup_footer();
}

function view_admin($error = '', $old = []) {
    setup_header('admin');
    echo '<h2>站点与管理员设置</h2><p class="lead">数据库连接成功。请设置站点信息与首个管理员账号。</p>';
    if ($error) echo '<div class="alert alert-error">' . setup_e($error) . '</div>';
    echo '<form method="post" action="setup.php">';
    echo setup_csrf_field();
    echo '<input type="hidden" name="action" value="install">';
    echo '<div class="row">';
    echo '<div class="form-group"><label>站点名称</label><input type="text" name="site_name" value="' . setup_e($old['site_name'] ?? '林梦的博客') . '" required></div>';
    echo '<div class="form-group"><label>站点 URL</label><input type="url" name="site_url" value="' . setup_e($old['site_url'] ?? setup_guess_site_url()) . '" required></div>';
    echo '</div>';
    echo '<div class="form-group"><label>站点描述</label><input type="text" name="site_desc" value="' . setup_e($old['site_desc'] ?? '记录生活，分享技术') . '"></div>';
    echo '<div class="row">';
    echo '<div class="form-group"><label>站点关键词</label><input type="text" name="site_keywords" value="' . setup_e($old['site_keywords'] ?? '') . '" placeholder="多个关键词用逗号分隔"><span class="hint">用于 SEO meta，可留空</span></div>';
    echo '<div class="form-group"><label>ICP备案号</label><input type="text" name="site_icp" value="' . setup_e($old['site_icp'] ?? '') . '" placeholder="如：苏ICP备xxxxx号，可留空"><span class="hint">显示在页脚，可留空</span></div>';
    echo '</div>';
    echo '<hr style="border:0;border-top:1px solid #2a2f3a;margin:20px 0">';
    echo '<div class="row">';
    echo '<div class="form-group"><label>管理员用户名</label><input type="text" name="admin_user" value="' . setup_e($old['admin_user'] ?? 'admin') . '" required pattern="[a-zA-Z0-9_]{3,20}"><span class="hint">3-20 位字母、数字、下划线</span></div>';
    echo '<div class="form-group"><label>管理员邮箱</label><input type="email" name="admin_email" value="' . setup_e($old['admin_email'] ?? '') . '" required></div>';
    echo '</div>';
    echo '<div class="row">';
    echo '<div class="form-group"><label>管理员密码</label><input type="password" name="admin_pass" required autocomplete="new-password"><span class="hint">至少8位，含大小写字母、数字、特殊字符</span></div>';
    echo '<div class="form-group"><label>确认密码</label><input type="password" name="admin_pass2" required autocomplete="new-password"></div>';
    echo '</div>';
    echo '<div class="actions"><button type="submit" class="btn">开始安装</button></div>';
    echo '</form>';
    setup_footer();
}

function view_finish($siteUrl) {
    setup_header('finish');
    echo '<div class="result">';
    echo '<div class="ico ok">✓</div>';
    echo '<h2>安装完成！</h2>';
    echo '<p class="lead">数据表与默认设置已创建，.env 与安全密钥已生成，管理员账号已就绪。</p>';
    echo '<div class="actions" style="margin-top:18px">';
    echo '<a class="btn" href="' . setup_e($siteUrl) . '/">访问首页</a>';
    echo '<a class="btn secondary" href="' . setup_e($siteUrl) . '/login.php">登录后台</a>';
    echo '</div>';
    echo '<div class="alert alert-success" style="margin-top:22px;text-align:left">';
    echo '安全提示：请立即删除根目录下的 <strong>setup.php</strong> 文件，避免被他人重复执行安装。';
    echo '</div>';
    echo '</div>';
    setup_footer();
}

function view_already_installed() {
    setup_header('check');
    echo '<div class="result">';
    echo '<div class="ico muted">🔒</div>';
    echo '<h2>已安装</h2>';
    echo '<p class="lead">检测到安装标记（includes/config_installed.php）已存在，为安全起见安装程序已锁定。</p>';
    echo '<p class="lead">如需重新安装，请先删除该标记文件与 .env，再重新访问本页。</p>';
    echo '<div class="actions" style="margin-top:18px"><a class="btn secondary" href="/">返回首页</a></div>';
    echo '</div>';
    setup_footer();
}

/* ----------------------------- 控制器 ----------------------------- */

// 已安装则锁定
if (is_file($installedMarker)) {
    view_already_installed();
    exit;
}

// 环境检测项
$envChecks = [
    'PHP 版本 >= 7.4'                    => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO MySQL 扩展'                     => extension_loaded('pdo_mysql'),
    'OpenSSL 扩展'                       => extension_loaded('openssl'),
    'GD 扩展（图片处理）'                => extension_loaded('gd'),
    'cURL 扩展'                          => extension_loaded('curl'),
    'mbstring 扩展'                      => extension_loaded('mbstring'),
    '根目录可写（生成 .env）'            => is_writable(LM_ROOT),
    'includes/ 目录可写（生成标记）'     => is_writable(LM_ROOT . '/includes'),
    'assets/uploads/ 可写（上传）'       => (function () { $p = LM_ROOT . '/assets/uploads'; if (!is_dir($p)) { @mkdir($p, 0755, true); } return is_dir($p) && is_writable($p); })(),
];

$action = $_POST['action'] ?? '';

if ($action === '' || $action === 'check_next') {
    if ($action === 'check_next') {
        if (!setup_csrf_ok()) { view_check($envChecks, '安全验证失败，请刷新页面重试'); exit; }
        if (in_array(false, $envChecks, true)) { view_check($envChecks, '存在未通过的检测项，请先修复'); exit; }
        view_database();
        exit;
    }
    view_check($envChecks);
    exit;
}

if ($action === 'db_test') {
    if (!setup_csrf_ok()) { view_database('安全验证失败，请刷新页面重试'); exit; }
    $dbHost   = trim($_POST['db_host'] ?? '');
    $dbName   = trim($_POST['db_name'] ?? '');
    $dbUser   = trim($_POST['db_user'] ?? '');
    $dbPass   = (string)($_POST['db_pass'] ?? '');
    $dbCreate = isset($_POST['db_create']);

    if ($dbHost === '' || $dbName === '' || $dbUser === '') {
        view_database('请填写主机、数据库名与用户名', compact('db_host', 'db_name', 'db_user', 'db_create'));
        exit;
    }

    // 先连服务器（不指定库）以支持自动建库
    try {
        $serverDsn = 'mysql:host=' . $dbHost . ';charset=utf8mb4';
        $pdo = new PDO($serverDsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    } catch (PDOException $e) {
        view_database('无法连接数据库服务器：' . $e->getMessage(), compact('db_host', 'db_name', 'db_user', 'db_create'));
        exit;
    }

    // 检查库是否存在
    try {
        $stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
        $stmt->execute([$dbName]);
        $exists = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $exists = false;
    }

    if (!$exists) {
        if (!$dbCreate) {
            view_database('数据库不存在，请勾选「自动创建数据库」或先手动创建', compact('db_host', 'db_name', 'db_user', 'db_create'));
            exit;
        }
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '``', $dbName) . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            view_database('创建数据库失败：' . $e->getMessage() . '（请确认该用户有 CREATE 权限或手动创建）', compact('db_host', 'db_name', 'db_user', 'db_create'));
            exit;
        }
    }

    // 连接到目标库
    try {
        $pdo = new PDO('mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8mb4', $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    } catch (PDOException $e) {
        view_database('连接目标数据库失败：' . $e->getMessage(), compact('db_host', 'db_name', 'db_user', 'db_create'));
        exit;
    }

    // 暂存到 session（不回传表单）
    $_SESSION['setup_db'] = [
        'host' => $dbHost, 'name' => $dbName, 'user' => $dbUser, 'pass' => $dbPass,
    ];
    view_admin();
    exit;
}

if ($action === 'install') {
    if (!setup_csrf_ok()) { view_admin('安全验证失败，请刷新页面重试'); exit; }
    if (empty($_SESSION['setup_db'])) { view_check($envChecks, '会话已过期，请重新开始'); exit; }

    $siteName    = trim($_POST['site_name'] ?? '');
    $siteUrl     = trim($_POST['site_url'] ?? '');
    $siteDesc    = trim($_POST['site_desc'] ?? '');
    $siteKeywords = trim($_POST['site_keywords'] ?? '');
    $siteIcp     = trim($_POST['site_icp'] ?? '');
    $adminUser   = trim($_POST['admin_user'] ?? '');
    $adminMail   = trim($_POST['admin_email'] ?? '');
    $adminPass   = (string)($_POST['admin_pass'] ?? '');
    $adminPass2  = (string)($_POST['admin_pass2'] ?? '');

    $old = ['site_name' => $siteName, 'site_url' => $siteUrl, 'site_desc' => $siteDesc, 'site_keywords' => $siteKeywords, 'site_icp' => $siteIcp, 'admin_user' => $adminUser, 'admin_email' => $adminMail];

    if ($siteName === '' || $siteUrl === '' || $adminUser === '' || $adminMail === '') {
        view_admin('请填写所有必填项', $old); exit;
    }
    if (!filter_var($siteUrl, FILTER_VALIDATE_URL)) { view_admin('站点 URL 格式不正确', $old); exit; }
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $adminUser)) { view_admin('管理员用户名需为 3-20 位字母、数字、下划线', $old); exit; }
    if (!filter_var($adminMail, FILTER_VALIDATE_EMAIL)) { view_admin('管理员邮箱格式不正确', $old); exit; }
    if ($adminPass !== $adminPass2) { view_admin('两次输入的密码不一致', $old); exit; }
    $strength = setup_password_strength($adminPass);
    if ($strength['score'] < 3) { view_admin('密码强度不足：' . implode('；', $strength['errors']), $old); exit; }

    // 连接数据库
    $db = $_SESSION['setup_db'];
    try {
        $pdo = new PDO('mysql:host=' . $db['host'] . ';dbname=' . $db['name'] . ';charset=utf8mb4', $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    } catch (PDOException $e) {
        unset($_SESSION['setup_db']);
        view_database('数据库连接失败，请重新配置：' . $e->getMessage()); exit;
    }

    try {
        // 创建全部数据表
        // 注意：MySQL 中 DDL（CREATE TABLE）会隐式提交，不能放入事务。
        // CREATE TABLE IF NOT EXISTS 与 ON DUPLICATE KEY UPDATE 保证可安全重试。
        foreach (explode(';', setup_schema_sql()) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt !== '' && stripos($stmt, 'CREATE TABLE') === 0) {
                $pdo->exec($stmt);
            }
        }

        // 默认设置（upsert）
        $defaults = [
            'site_name'        => $siteName,
            'site_url'         => $siteUrl,
            'site_description' => $siteDesc !== '' ? $siteDesc : '记录生活，分享技术',
            'site_keywords'    => $siteKeywords,
            'site_icp'         => $siteIcp,
            'site_start_date'  => date('Y-m-d'),
            'site_time_offset' => '0',
            'site_visitor_count' => '0',
        ];
        $upsert = $pdo->prepare("INSERT INTO lm_setting (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($defaults as $k => $v) { $upsert->execute([$k, $v]); }

        // 创建管理员（若用户名已存在则报错，避免重复）
        $check = $pdo->prepare("SELECT COUNT(*) FROM lm_admin WHERE username = ?");
        $check->execute([$adminUser]);
        if ((int)$check->fetchColumn() > 0) {
            view_admin('该管理员用户名已存在，请更换', $old); exit;
        }
        $hash = password_hash($adminPass, PASSWORD_DEFAULT, ['cost' => 12]);
        $ins = $pdo->prepare("INSERT INTO lm_admin (username, password, email, role, status, nickname, created_at) VALUES (?, ?, ?, 'admin', 1, ?, NOW())");
        $ins->execute([$adminUser, $hash, $adminMail, $adminUser]);
    } catch (PDOException $e) {
        view_admin('安装失败：' . $e->getMessage(), $old); exit;
    }

    // 生成 SECRET_KEY
    $secretKey = bin2hex(random_bytes(32));

    // 写入 .env
    $envContent = "# 林梦博客 - 环境配置（由 setup.php 生成，请勿提交到版本库）\n";
    $envContent .= "# 生成时间：" . date('Y-m-d H:i:s') . "\n\n";
    $envContent .= 'DB_HOST=' . setup_env_value($db['host']) . "\n";
    $envContent .= 'DB_NAME=' . setup_env_value($db['name']) . "\n";
    $envContent .= 'DB_USER=' . setup_env_value($db['user']) . "\n";
    $envContent .= 'DB_PASS=' . setup_env_value($db['pass']) . "\n\n";
    $envContent .= 'SECRET_KEY=' . setup_env_value($secretKey) . "\n\n";
    $envContent .= 'SITE_URL=' . setup_env_value($siteUrl) . "\n";
    $envContent .= 'SITE_PATH=' . setup_env_value('') . "\n\n";
    $envContent .= "LM_TRUST_PROXY=false\n";

    if (@file_put_contents($envFile, $envContent) === false) {
        @unlink($envFile);
        view_admin('无法写入 .env 文件，请检查根目录权限后重试', $old); exit;
    }
    @chmod($envFile, 0600);

    // 写入安装标记
    $markerContent = "<?php\n// 安装标记文件 - 存在表示已安装，删除可重新运行 setup.php\n// 安装时间：" . date('Y-m-d H:i:s') . "\n";
    if (@file_put_contents($installedMarker, $markerContent) === false) {
        @unlink($envFile);
        view_admin('无法写入安装标记文件（includes/config_installed.php），请检查 includes 目录权限后重试', $old); exit;
    }
    @chmod($installedMarker, 0644);

    // 清理敏感会话数据
    unset($_SESSION['setup_db'], $_SESSION['setup_csrf']);
    session_destroy();

    view_finish($siteUrl);
    exit;
}

// 兜底
view_check($envChecks);
