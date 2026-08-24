-- OAuth 绑定字段迁移（原根目录 github/gitee/gitcode-oauth-upgrade.php，因无鉴权暴露已删除）
-- 仅当 lm_admin 表缺少以下字段时执行；生产库此前已执行过可忽略。

ALTER TABLE lm_admin ADD COLUMN github_id VARCHAR(64) NULL UNIQUE COMMENT 'GitHub 用户ID';
ALTER TABLE lm_admin ADD COLUMN github_username VARCHAR(255) NULL COMMENT 'GitHub 用户名';
ALTER TABLE lm_admin ADD INDEX idx_github_id (github_id);

ALTER TABLE lm_admin ADD COLUMN gitee_id VARCHAR(64) NULL UNIQUE COMMENT 'Gitee 用户ID';
ALTER TABLE lm_admin ADD COLUMN gitee_username VARCHAR(255) NULL COMMENT 'Gitee 用户名';
ALTER TABLE lm_admin ADD INDEX idx_gitee_id (gitee_id);

ALTER TABLE lm_admin ADD COLUMN gitcode_id VARCHAR(64) NULL UNIQUE COMMENT 'GitCode 用户ID';
ALTER TABLE lm_admin ADD COLUMN gitcode_username VARCHAR(255) NULL COMMENT 'GitCode 用户名';
ALTER TABLE lm_admin ADD INDEX idx_gitcode_id (gitcode_id);
