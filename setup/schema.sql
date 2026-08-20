-- ============================================================
-- 林梦博客 (LinMeng Blog) 数据库结构
-- 由安装向导 setup/index.php 自动导入
-- MySQL 5.7+ / MariaDB 10.3+，字符集 utf8mb4
-- ============================================================
-- 说明：
--   * 所有表使用 InnoDB 引擎、utf8mb4_unicode_ci 排序规则
--   * 表前缀 lm_ 与程序硬编码一致，请勿更改
--   * 本文件仅包含结构（CREATE TABLE IF NOT EXISTS），不含示例数据
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------
-- 用户 / 管理员表
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lm_admin` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`           VARCHAR(255) NOT NULL,
    `password`           VARCHAR(255) NOT NULL,
    `email`              VARCHAR(255) NOT NULL,
    `nickname`           VARCHAR(255) NOT NULL DEFAULT '',
    `avatar`             VARCHAR(500) DEFAULT NULL,
    `website`            VARCHAR(500) DEFAULT NULL,
    `bio`                TEXT DEFAULT NULL,
    `role`               VARCHAR(20)  NOT NULL DEFAULT 'user',
    `status`             TINYINT      NOT NULL DEFAULT 1,
    `github_id`          VARCHAR(64)  DEFAULT NULL,
    `github_username`    VARCHAR(255) DEFAULT NULL,
    `gitee_id`           VARCHAR(64)  DEFAULT NULL,
    `gitee_username`     VARCHAR(255) DEFAULT NULL,
    `gitcode_id`         VARCHAR(64)  DEFAULT NULL,
    `gitcode_username`   VARCHAR(255) DEFAULT NULL,
    `remember_token`     VARCHAR(255) DEFAULT NULL,
    `last_login`         DATETIME     DEFAULT NULL,
    `last_ip`            VARCHAR(45)  DEFAULT NULL,
    `login_fail_count`   INT          NOT NULL DEFAULT 0,
    `lock_until`         DATETIME     DEFAULT NULL,
    `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_username` (`username`),
    KEY `idx_role` (`role`),
    KEY `idx_email` (`email`),
    UNIQUE KEY `uniq_github_id` (`github_id`),
    UNIQUE KEY `uniq_gitee_id` (`gitee_id`),
    UNIQUE KEY `uniq_gitcode_id` (`gitcode_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 用户注册申请表（前台注册后由管理员审核）
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lm_user_apply` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`    VARCHAR(255) NOT NULL,
    `password`    VARCHAR(255) NOT NULL,
    `email`       VARCHAR(255) NOT NULL,
    `nickname`    VARCHAR(255) NOT NULL DEFAULT '',
    `website`     VARCHAR(500) DEFAULT NULL,
    `reason`      TEXT DEFAULT NULL,
    `ip`          VARCHAR(45)  NOT NULL DEFAULT '',
    `status`      VARCHAR(20)  NOT NULL DEFAULT 'pending',
    `handled_at`  DATETIME     DEFAULT NULL,
    `handled_by`  INT UNSIGNED DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_username` (`username`),
    KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 登录日志
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lm_login_log` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED DEFAULT NULL,
    `username`    VARCHAR(255) NOT NULL DEFAULT '',
    `ip`          VARCHAR(45)  NOT NULL DEFAULT '',
    `user_agent`  VARCHAR(500) NOT NULL DEFAULT '',
    `status`      VARCHAR(20)  NOT NULL DEFAULT '',
    `fail_reason` VARCHAR(255) DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_ip` (`ip`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 登录失败锁定
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lm_login_lock` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `identifier`   VARCHAR(255) NOT NULL,
    `fail_count`   INT          NOT NULL DEFAULT 0,
    `locked_until` DATETIME     DEFAULT NULL,
    `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_identifier` (`identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 通用限流计数
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lm_rate_limit` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `identifier`     VARCHAR(255) NOT NULL,
    `action`         VARCHAR(50)  NOT NULL,
    `attempts`       INT          NOT NULL DEFAULT 0,
    `first_attempt`  DATETIME     DEFAULT NULL,
    `last_attempt`   DATETIME     DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_identifier_action` (`identifier`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 分类表
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lm_category` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(255) NOT NULL,
    `slug`        VARCHAR(255) NOT NULL,
    `description` VARCHAR(500) NOT NULL DEFAULT '',
    `sort_order`  INT          NOT NULL DEFAULT 0,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_slug` (`slug`),
    UNIQUE KEY `uniq_name` (`name`),
    KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 文章表
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lm_article` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`        VARCHAR(255) NOT NULL,
    `slug`         VARCHAR(255) NOT NULL,
    `content`      LONGTEXT     NOT NULL,
    `excerpt`      VARCHAR(500) NOT NULL DEFAULT '',
    `cover_image`  VARCHAR(500) NOT NULL DEFAULT '',
    `category_id`  INT UNSIGNED NOT NULL DEFAULT 0,
    `tags`         VARCHAR(500) NOT NULL DEFAULT '',
    `status`       VARCHAR(20)  NOT NULL DEFAULT 'draft',
    `is_top`       TINYINT      NOT NULL DEFAULT 0,
    `author_id`    INT UNSIGNED NOT NULL DEFAULT 0,
    `views`        INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_slug` (`slug`),
    KEY `idx_category_id` (`category_id`),
    KEY `idx_author_id` (`author_id`),
    KEY `idx_status` (`status`),
    KEY `idx_is_top` (`is_top`),
    KEY `idx_views` (`views`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 文章图片表（一篇文章多图）
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lm_article_image` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `article_id` INT UNSIGNED NOT NULL,
    `image_url`  VARCHAR(500) NOT NULL,
    `sort_order` INT          NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_article_id` (`article_id`),
    KEY `idx_article_sort` (`article_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 文章点赞表（同一 IP 对同一文章唯一）
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lm_article_like` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `article_id` INT UNSIGNED NOT NULL,
    `ip`         VARCHAR(45)  NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_article_ip` (`article_id`, `ip`),
    KEY `idx_article_id` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 评论 / 留言表（article_id = 0 表示留言板）
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lm_comment` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `article_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `parent_id`  INT UNSIGNED NOT NULL DEFAULT 0,
    `user_id`    INT UNSIGNED DEFAULT NULL,
    `nickname`   VARCHAR(255) NOT NULL DEFAULT '',
    `email`      VARCHAR(255) NOT NULL DEFAULT '',
    `website`    VARCHAR(500) DEFAULT NULL,
    `content`    TEXT         NOT NULL,
    `ip`         VARCHAR(45)  NOT NULL DEFAULT '',
    `user_agent` VARCHAR(500) NOT NULL DEFAULT '',
    `status`     TINYINT      NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_article_id` (`article_id`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_status` (`status`),
    KEY `idx_article_status` (`article_id`, `status`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 友情链接表
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lm_link` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(255) NOT NULL,
    `url`         VARCHAR(500) NOT NULL,
    `description` VARCHAR(500) NOT NULL DEFAULT '',
    `logo`        VARCHAR(500) NOT NULL DEFAULT '',
    `sort_order`  INT          NOT NULL DEFAULT 0,
    `status`      TINYINT      NOT NULL DEFAULT 1,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 友链申请表
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lm_link_apply` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `site_name`        VARCHAR(255) NOT NULL,
    `site_url`         VARCHAR(500) NOT NULL,
    `site_description` VARCHAR(500) NOT NULL DEFAULT '',
    `email`            VARCHAR(255) NOT NULL DEFAULT '',
    `ip`               VARCHAR(45)  NOT NULL DEFAULT '',
    `status`           VARCHAR(20)  NOT NULL DEFAULT 'pending',
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_site_url` (`site_url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 赞助商表
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lm_sponsor` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(255) NOT NULL,
    `url`        VARCHAR(500) NOT NULL DEFAULT '',
    `detail`     VARCHAR(500) NOT NULL DEFAULT '',
    `icon`       VARCHAR(500) NOT NULL DEFAULT '',
    `sort_order` INT          NOT NULL DEFAULT 0,
    `status`     TINYINT      NOT NULL DEFAULT 1,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 服务状态监控表
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lm_service` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(255) NOT NULL,
    `host`       VARCHAR(255) NOT NULL,
    `type`       VARCHAR(20)  NOT NULL DEFAULT 'tcp',
    `port`       INT UNSIGNED NOT NULL DEFAULT 80,
    `path`       VARCHAR(500) NOT NULL DEFAULT '',
    `sort_order` INT          NOT NULL DEFAULT 0,
    `enabled`    TINYINT      NOT NULL DEFAULT 1,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_enabled` (`enabled`),
    KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 服务探测日志表
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lm_service_log` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `service_id`  INT UNSIGNED NOT NULL,
    `status`      TINYINT      NOT NULL DEFAULT 0,
    `latency_ms`  INT          DEFAULT NULL,
    `message`     VARCHAR(250) NOT NULL DEFAULT '',
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_service_id` (`service_id`),
    KEY `idx_service_created` (`service_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 访问日志表
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lm_visit_log` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `page`       VARCHAR(500) NOT NULL,
    `referer`    VARCHAR(500) DEFAULT NULL,
    `ip`         VARCHAR(45)  NOT NULL DEFAULT '',
    `user_agent` VARCHAR(500) NOT NULL DEFAULT '',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- AI 摘要 Provider 表
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lm_ai_provider` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`              VARCHAR(255) NOT NULL,
    `api_url`           VARCHAR(500) NOT NULL,
    `model`             VARCHAR(255) NOT NULL,
    `compatibility`     VARCHAR(20)  NOT NULL DEFAULT 'openai',
    `request_template`  TEXT DEFAULT NULL,
    `response_path`     VARCHAR(255) DEFAULT NULL,
    `api_key`           TEXT DEFAULT NULL,
    `enabled`           TINYINT      NOT NULL DEFAULT 0,
    `sort_order`        INT          NOT NULL DEFAULT 0,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_enabled` (`enabled`),
    KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 图床图库记录表（GitHub 图床上传）
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lm_gallery` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED NOT NULL,
    `username`      VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(500) NOT NULL,
    `github_path`   VARCHAR(500) NOT NULL,
    `raw_url`       VARCHAR(500) NOT NULL,
    `cdn_url`       VARCHAR(500) NOT NULL DEFAULT '',
    `file_size`     INT UNSIGNED NOT NULL DEFAULT 0,
    `file_type`     VARCHAR(100) NOT NULL DEFAULT '',
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_username` (`username`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 游戏表
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lm_game` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(150) NOT NULL,
    `description` VARCHAR(500) NOT NULL DEFAULT '',
    `image_url`   VARCHAR(500) NOT NULL DEFAULT '',
    `sort_order`  INT          NOT NULL DEFAULT 0,
    `status`      TINYINT      NOT NULL DEFAULT 1,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status_sort` (`status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 站点设置表（键值对）
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lm_setting` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key`   VARCHAR(100) NOT NULL,
    `setting_value` LONGTEXT     DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
