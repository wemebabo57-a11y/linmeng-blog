# Blog System

基于 PHP 8 和 MySQL 的个人博客。此目录对应商业版更新前的完整功能版本，并加入独立安装向导的 ZIP 数据恢复能力。项目没有预设站点名称、域名或站长资料。

## 功能

文章、分类、标签、评论、留言板、友链、说说、旅行、图库、服务状态、AI 总结、OAuth 登录、后台管理、数据备份与恢复、镜像软件和在线工具。

## 安装

1. 使用 PHP 8.0+、MySQL 5.7+，启用 `pdo_mysql`、`gd`、`curl`、`fileinfo`、`openssl`、`mbstring` 和 `zip` 扩展。
2. 上传项目到站点目录，确保 `assets/uploads/`、`includes/articles/`、`includes/cache/` 和 `cache/` 可写。
3. 访问 `/setup/`，填写数据库、站点和管理员信息。可选上传由后台「数据备份」导出的 ZIP，以恢复数据库与 Markdown 文章。旧版 `lm_` 表名会自动转换为 `app_`。
4. 安装完成后删除服务器上的 `setup/` 目录。后台入口为 `/admin/`。

安装向导生成 `.env` 与 `includes/config_installed.php`。这些文件包含或关联实例配置，不应提交到仓库。已有站点的重新配置需要先登录管理员。

## 目录

- `admin/`：后台页面
- `api/`：接口和计划任务
- `assets/`：样式、脚本和上传目录
- `includes/`：核心类、函数和文章内容
- `setup/`：安装向导与数据库结构
- `template/`：公共页面模板

数据库使用 `app_` 表前缀。配置模板见 `.env.example`，部署说明见 `docs/`。

## 许可证

MIT，详见 [LICENSE](LICENSE)。
