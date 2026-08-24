<?php
/**
 * Gitee OAuth 登录数据库升级脚本
 * 运行一次即可，为 lm_admin 表添加 Gitee 关联字段
 */
define('LM_ROOT', __DIR__);

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Database.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = Database::getInstance()->getPdo();

    // 检查 gitee_id 字段是否存在
    $stmt = $pdo->prepare("SHOW COLUMNS FROM lm_admin LIKE 'gitee_id'");
    $stmt->execute();
    $hasGiteeId = $stmt->fetch();

    if ($hasGiteeId) {
        echo "gitee_id 字段已存在，无需重复升级。\n";
    } else {
        $pdo->exec("ALTER TABLE lm_admin ADD COLUMN gitee_id VARCHAR(64) NULL UNIQUE COMMENT 'Gitee 用户 ID' AFTER github_username");
        echo "已添加 gitee_id 字段。\n";
    }

    // 检查 gitee_username 字段是否存在
    $stmt = $pdo->prepare("SHOW COLUMNS FROM lm_admin LIKE 'gitee_username'");
    $stmt->execute();
    $hasGiteeUsername = $stmt->fetch();

    if ($hasGiteeUsername) {
        echo "gitee_username 字段已存在，无需重复升级。\n";
    } else {
        $pdo->exec("ALTER TABLE lm_admin ADD COLUMN gitee_username VARCHAR(255) NULL COMMENT 'Gitee 用户名' AFTER gitee_id");
        echo "已添加 gitee_username 字段。\n";
    }

    // 检查索引
    $stmt = $pdo->prepare("SHOW INDEX FROM lm_admin WHERE Key_name = 'idx_gitee_id'");
    $stmt->execute();
    $hasIndex = $stmt->fetch();

    if (!$hasIndex) {
        $pdo->exec("ALTER TABLE lm_admin ADD INDEX idx_gitee_id (gitee_id)");
        echo "已添加 idx_gitee_id 索引。\n";
    }

    echo "\n升级完成，请删除本文件或妥善保存。\n";
} catch (Exception $e) {
    http_response_code(500);
    echo "升级失败: " . $e->getMessage() . "\n";
}
