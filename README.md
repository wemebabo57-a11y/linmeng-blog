# Blog System

基于 PHP 8 与 MySQL 的个人博客系统。当前开源版本为 **v2.5.9**，源码位于 [`src/v2.5.9/`](src/v2.5.9/)。`src/v2.4.2/` 保留为历史版本。

## 功能

文章、分类、标签、评论、留言板、友链、说说、旅行、图库、服务状态、AI 总结、OAuth 登录、后台管理、数据备份与恢复、镜像软件和在线工具。

## 安装

1. 准备 PHP 8.0+、MySQL 5.7+。PHP 需启用 `pdo_mysql`、`gd`、`curl`、`fileinfo`、`openssl`、`mbstring` 和 `zip`。
2. 把 `src/v2.5.9/` **里面的内容**上传到网站根目录，确保 `assets/uploads/`、`includes/articles/`、`includes/cache/` 和 `cache/` 可写。
3. 打开 `/setup/`，填写数据库、站点和管理员信息。可选上传本站后台导出的 ZIP，恢复数据库与 Markdown 文章。旧版 `lm_` 表会转换为 `app_`。
4. 安装完成后删除服务器上的 `setup/` 目录，访问 `/admin/` 登录。

站点名称、域名、联系资料与密钥均由部署者设置。`.env` 与 `includes/config_installed.php` 不应提交到仓库。配置模板见 [`.env.example`](src/v2.5.9/.env.example)。

## 版本与许可证

- v2.5.9：以商业版更新前的功能为基线，并补充 ZIP 安装恢复。
- v2.4.2：历史版本，保留供已有部署参考。

MIT，见 [LICENSE](LICENSE)。
