<?php
/**
 * GitHub OAuth 登录数据库升级脚本
 * 运行一次即可，为 lm_admin 表添加 GitHub 关联字段
 */
define('LM_ROOT', __DIR__);

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Database.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = Database::getInstance()->getPdo();

    // 检查 github_id 字段是否存在
    $stmt = $pdo->prepare("SHOW COLUMNS FROM lm_admin LIKE 'github_id'");
    $stmt->execute();
    $hasGithubId = $stmt->fetch();

    if ($hasGithubId) {
        echo "github_id 字段已存在，无需重复升级。\n";
    } else {
        $pdo->exec("ALTER TABLE lm_admin ADD COLUMN github_id VARCHAR(64) NULL UNIQUE COMMENT 'GitHub 用户 ID' AFTER status");
        echo "已添加 github_id 字段。\n";
    }

    // 检查 github_username 字段是否存在
    $stmt = $pdo->prepare("SHOW COLUMNS FROM lm_admin LIKE 'github_username'");
    $stmt->execute();
    $hasGithubUsername = $stmt->fetch();

    if ($hasGithubUsername) {
        echo "github_username 字段已存在，无需重复升级。\n";
    } else {
        $pdo->exec("ALTER TABLE lm_admin ADD COLUMN github_username VARCHAR(255) NULL COMMENT 'GitHub 用户名' AFTER github_id");
        echo "已添加 github_username 字段。\n";
    }

    // 检查索引
    $stmt = $pdo->prepare("SHOW INDEX FROM lm_admin WHERE Key_name = 'idx_github_id'");
    $stmt->execute();
    $hasIndex = $stmt->fetch();

    if (!$hasIndex) {
        $pdo->exec("ALTER TABLE lm_admin ADD INDEX idx_github_id (github_id)");
        echo "已添加 idx_github_id 索引。\n";
    }

    echo "\n升级完成，请删除本文件或妥善保存。\n";
} catch (Exception $e) {
    http_response_code(500);
    echo "升级失败: " . $e->getMessage() . "\n";
}
