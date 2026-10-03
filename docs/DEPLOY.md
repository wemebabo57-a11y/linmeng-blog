# 部署说明

将 `src/v2.5.9/` 目录内的全部文件上传到站点根目录。使用 PHP 8.0+ 和 MySQL 5.7+，并启用 `pdo_mysql`、`gd`、`curl`、`fileinfo`、`openssl`、`mbstring`、`zip` 扩展。

确保 `assets/uploads/`、`includes/articles/`、`includes/cache/`、`cache/` 对 PHP 进程可写。访问 `/setup/` 配置数据库、站点和管理员账号。已有站点可选导入后台导出的 ZIP。安装后删除 `setup/`。

配置文件 `.env` 由向导生成；请勿提交该文件。程序版本定义在 `includes/config.php` 的 `APP_VERSION` 常量中。数据库使用 `app_` 表前缀。
