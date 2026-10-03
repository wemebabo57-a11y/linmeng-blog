-- ============================================================================
-- 数据库结构定义（建表 SQL）
-- 安装向导 setup/ 会自动导入；也可手动导入：mysql -uUSER -pPASS DBNAME < setup/schema.sql
-- 表前缀：app_　字符集：utf8mb4 / utf8mb4_unicode_ci
-- ============================================================================

CREATE TABLE IF NOT EXISTS `app_admin` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL DEFAULT '',
    `email` VARCHAR(120) NOT NULL DEFAULT '',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '',
    `avatar` VARCHAR(500) NULL,
    `role` VARCHAR(20) NOT NULL DEFAULT 'user',
    `status` TINYINT NOT NULL DEFAULT 1,
    `github_id` VARCHAR(64) NULL,
    `github_username` VARCHAR(255) NULL,
    `gitee_id` VARCHAR(64) NULL,
    `gitee_username` VARCHAR(255) NULL,
    `gitcode_id` VARCHAR(64) NULL,
    `gitcode_username` VARCHAR(255) NULL,
    `remember_token` VARCHAR(64) NULL,
    `last_login` DATETIME NULL,
    `last_ip` VARCHAR(45) NULL,
    `login_fail_count` INT NOT NULL DEFAULT 0,
    `lock_until` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_username` (`username`),
    KEY `idx_email` (`email`),
    KEY `idx_github_id` (`github_id`),
    KEY `idx_gitee_id` (`gitee_id`),
    KEY `idx_gitcode_id` (`gitcode_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 站点设置（键值对）
CREATE TABLE IF NOT EXISTS `app_setting` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 分类
CREATE TABLE IF NOT EXISTS `app_category` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(150) NOT NULL DEFAULT '',
    `description` VARCHAR(255) NOT NULL DEFAULT '',
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 文章
CREATE TABLE IF NOT EXISTS `app_article` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(200) NOT NULL,
    `slug` VARCHAR(200) NOT NULL,
    `content` LONGTEXT NULL,
    `excerpt` VARCHAR(500) NOT NULL DEFAULT '',
    `cover_image` VARCHAR(500) NOT NULL DEFAULT '',
    `category_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `tags` VARCHAR(255) NOT NULL DEFAULT '',
    `status` VARCHAR(20) NOT NULL DEFAULT 'published',
    `is_top` TINYINT NOT NULL DEFAULT 0,
    `views` INT UNSIGNED NOT NULL DEFAULT 0,
    `author_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_slug` (`slug`),
    KEY `idx_status_top` (`status`, `is_top`),
    KEY `idx_author` (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 评论 / 留言
CREATE TABLE IF NOT EXISTS `app_comment` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `article_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `parent_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `nickname` VARCHAR(50) NOT NULL DEFAULT '',
    `email` VARCHAR(120) NOT NULL DEFAULT '',
    `website` VARCHAR(255) NULL,
    `content` TEXT NOT NULL,
    `ip` VARCHAR(45) NOT NULL DEFAULT '',
    `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
    `status` TINYINT NOT NULL DEFAULT 1,
    `is_admin` TINYINT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_article` (`article_id`),
    KEY `idx_parent` (`parent_id`),
    `city` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '评论者所在城市',
    `browser` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '评论者浏览器'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 友链
CREATE TABLE IF NOT EXISTS `app_link` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `url` VARCHAR(500) NOT NULL,
    `description` VARCHAR(255) NOT NULL DEFAULT '',
    `logo` VARCHAR(500) NOT NULL DEFAULT '',
    `sort_order` INT NOT NULL DEFAULT 0,
    `status` TINYINT NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status_sort` (`status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 友链申请
CREATE TABLE IF NOT EXISTS `app_link_apply` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `site_name` VARCHAR(100) NOT NULL,
    `site_url` VARCHAR(500) NOT NULL,
    `site_description` VARCHAR(255) NOT NULL DEFAULT '',
    `email` VARCHAR(120) NOT NULL DEFAULT '',
    `ip` VARCHAR(45) NOT NULL DEFAULT '',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
    `reply` VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    `site_avatar` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '头像直链'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 赞助商
CREATE TABLE IF NOT EXISTS `app_sponsor` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `url` VARCHAR(500) NOT NULL DEFAULT '',
    `detail` VARCHAR(500) NOT NULL DEFAULT '',
    `icon` VARCHAR(500) NOT NULL DEFAULT '',
    `sort_order` INT NOT NULL DEFAULT 0,
    `status` TINYINT NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status_sort` (`status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 文章图片
CREATE TABLE IF NOT EXISTS `app_article_image` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `article_id` INT UNSIGNED NOT NULL,
    `image_url` VARCHAR(500) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_article` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 文章点赞
CREATE TABLE IF NOT EXISTS `app_article_like` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `article_id` INT UNSIGNED NOT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_article_ip` (`article_id`, `ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 访问日志
CREATE TABLE IF NOT EXISTS `app_visit_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `page` VARCHAR(255) NOT NULL DEFAULT '',
    `referer` VARCHAR(500) NULL,
    `ip` VARCHAR(45) NOT NULL DEFAULT '',
    `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 登录日志
CREATE TABLE IF NOT EXISTS `app_login_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NULL,
    `username` VARCHAR(50) NOT NULL DEFAULT '',
    `ip` VARCHAR(45) NOT NULL DEFAULT '',
    `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
    `status` VARCHAR(20) NOT NULL DEFAULT 'fail',
    `fail_reason` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 登录锁定（防暴力破解）
CREATE TABLE IF NOT EXISTS `app_login_lock` (
    `identifier` VARCHAR(190) NOT NULL,
    `fail_count` INT NOT NULL DEFAULT 0,
    `locked_until` INT NOT NULL DEFAULT 0,
    `updated_at` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 滑动窗口限流
CREATE TABLE IF NOT EXISTS `app_rate_limit` (
    `identifier` VARCHAR(190) NOT NULL,
    `action` VARCHAR(64) NOT NULL,
    `attempts` INT NOT NULL DEFAULT 0,
    `first_attempt` INT NOT NULL DEFAULT 0,
    `last_attempt` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`identifier`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 账号申请（注册审核）
CREATE TABLE IF NOT EXISTS `app_user_apply` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `email` VARCHAR(120) NOT NULL DEFAULT '',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '',
    `website` VARCHAR(255) NULL,
    `reason` VARCHAR(500) NULL,
    `ip` VARCHAR(45) NOT NULL DEFAULT '',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
    `reply` VARCHAR(500) NULL,
    `handled_at` DATETIME NULL,
    `handled_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI Provider
CREATE TABLE IF NOT EXISTS `app_ai_provider` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `api_url` VARCHAR(500) NOT NULL,
    `model` VARCHAR(100) NOT NULL,
    `compatibility` VARCHAR(20) NOT NULL DEFAULT 'openai',
    `enabled` TINYINT NOT NULL DEFAULT 0,
    `sort_order` INT NOT NULL DEFAULT 0,
    `request_template` TEXT NULL,
    `response_path` VARCHAR(255) NULL,
    `api_key` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI 总结缓存（旧版数据库缓存，保留以兼容清理逻辑）
CREATE TABLE IF NOT EXISTS `app_ai_summary_cache` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `cache_key` VARCHAR(190) NOT NULL DEFAULT '',
    `result` MEDIUMTEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_provider` (`provider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 图库（GitHub 图床）
CREATE TABLE IF NOT EXISTS `app_gallery` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `username` VARCHAR(50) NOT NULL DEFAULT '',
    `original_name` VARCHAR(255) NOT NULL DEFAULT '',
    `github_path` VARCHAR(500) NOT NULL DEFAULT '',
    `raw_url` VARCHAR(500) NOT NULL DEFAULT '',
    `cdn_url` VARCHAR(500) NOT NULL DEFAULT '',
    `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
    `file_type` VARCHAR(100) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 服务监控
CREATE TABLE IF NOT EXISTS `app_service` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `host` VARCHAR(255) NOT NULL,
    `type` VARCHAR(10) NOT NULL DEFAULT 'http',
    `port` INT NOT NULL DEFAULT 80,
    `path` VARCHAR(255) NOT NULL DEFAULT '/',
    `sort_order` INT NOT NULL DEFAULT 0,
    `enabled` TINYINT NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 服务探测日志
CREATE TABLE IF NOT EXISTS `app_service_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `service_id` INT UNSIGNED NOT NULL,
    `status` TINYINT NOT NULL DEFAULT 0,
    `latency_ms` INT NOT NULL DEFAULT 0,
    `message` VARCHAR(250) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_service` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 镜像分区
CREATE TABLE IF NOT EXISTS `app_mirror_category` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NOT NULL DEFAULT '',
    `icon` VARCHAR(500) NOT NULL DEFAULT '',
    `sort_order` INT NOT NULL DEFAULT 0,
    `status` TINYINT NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 镜像软件
CREATE TABLE IF NOT EXISTS `app_mirror_software` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `name` VARCHAR(150) NOT NULL,
    `description` VARCHAR(500) NOT NULL DEFAULT '',
    `icon_url` VARCHAR(500) NOT NULL DEFAULT '',
    `download_url` VARCHAR(500) NOT NULL DEFAULT '',
    `official_url` VARCHAR(500) NOT NULL DEFAULT '',
    `version` VARCHAR(100) NOT NULL DEFAULT '',
    `sort_order` INT NOT NULL DEFAULT 0,
    `views` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` TINYINT NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 游戏
CREATE TABLE IF NOT EXISTS `app_game` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `description` VARCHAR(500) NOT NULL DEFAULT '',
    `image_url` VARCHAR(500) NOT NULL DEFAULT '',
    `sort_order` INT NOT NULL DEFAULT 0,
    `status` TINYINT NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status_sort` (`status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 开源仓库
CREATE TABLE IF NOT EXISTS `app_repo` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `url` VARCHAR(500) NOT NULL,
    `description` VARCHAR(500) NOT NULL DEFAULT '',
    `sort_order` INT NOT NULL DEFAULT 0,
    `status` TINYINT NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status_sort` (`status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 说说（说说页 / 后台管理）
CREATE TABLE IF NOT EXISTS `app_shuoshuo` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `content` TEXT NOT NULL,
    `images` TEXT NOT NULL,
    `mood` VARCHAR(30) NOT NULL DEFAULT '',
    `status` TINYINT NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 赞助者名单（赞助页）
CREATE TABLE IF NOT EXISTS `app_donor` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `url` VARCHAR(500) NOT NULL DEFAULT '',
    `amount` DECIMAL(10,2) NULL DEFAULT NULL COMMENT '捐赠金额',
    `sort_order` INT NOT NULL DEFAULT 0,
    `status` TINYINT NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status_sort` (`status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;