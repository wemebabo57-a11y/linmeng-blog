# 部署指南

本文档说明如何把林梦博客部署到服务器，以及如何升级到新版本。

---

## 一、选择版本

站点源码按版本号归档在 `src/` 目录，每个版本是一个**完整、自包含**的站点根目录。

| 需求 | 选择 |
|------|------|
| 全新搭建 | 最新版 → [`src/v2.4.2/`](../src/v2.4.2/) |
| 下载 zip | [Releases](https://github.com/wemebabo57-a11y/linmeng-blog/releases) 页面的 `linmeng-blog-vX.Y.Z.zip` |
| 查看全部版本 | [`src/README.md`](../src/README.md) |

---

## 二、全新部署

### 1. 上传文件

> 📌 **把 `src/<版本号>/` 文件夹「里面的全部内容」上传到网站根目录。**

正确结果——服务器根目录下能直接看到 `index.php`：

```
网站根目录/
├── index.php
├── includes/
├── setup/
├── admin/
├── api/
├── assets/
└── template/
```

❌ 常见错误：上传成了 `网站根目录/v2.4.2/index.php`。这样访问域名会 404，把 `v2.4.2` 这一层去掉即可。

### 2. 设置目录权限

站点根目录、`includes/`、`assets/uploads/` 需要可写：

```bash
chown -R www:www /www/wwwroot/你的站点目录
chmod -R 755 /www/wwwroot/你的站点目录
chmod -R 775 includes assets/uploads
```

### 3. 运行安装向导

浏览器访问：

```
http://你的域名/setup/
```

向导流程：

1. **环境检测** —— 检查 PHP 版本、扩展、目录权限
2. **配置数据库** —— 主机、库名、用户名、密码（可选自动建库）
3. **设置站点与管理员** —— 站点 URL、站点名称、管理员账号密码
4. **一键安装** —— 生成 `.env`、导入表结构、创建管理员、写入默认设置

> 向导会生成 `SECRET_KEY` 用于加密 API Key 等敏感数据。**该密钥一经设定请勿变更**，否则已加密数据无法解密。

### 4. 删除安装目录（重要）

```bash
rm -rf setup/
```

不删除的话，任何人都能重新执行安装流程，覆盖你的站点配置。

### 5. 配置 Web 服务器

- Nginx：参考 [`nginx.example.conf`](nginx.example.conf)，把 `root` 指向你的站点根目录
- Apache：站点自带 `.htaccess`，确保开启 `mod_rewrite`

### 6. 登录后台

访问 `http://你的域名/login.php`，用管理员账号登录，进入 `/admin/`。

---

## 三、手动安装（不使用向导）

当服务器权限受限、无法运行向导时：

```bash
cp .env.example .env
```

编辑 `.env`：

```ini
DB_HOST=localhost
DB_PORT=3306
DB_NAME=你的库名
DB_USER=你的用户名
DB_PASS=你的密码
SITE_URL=https://你的域名
SITE_PATH=                     # 子目录部署时填 /blog；根目录留空
LM_TRUST_PROXY=false
SECRET_KEY=                    # 用 php -r "echo bin2hex(random_bytes(32));" 生成
```

导入表结构：

```bash
mysql -u 用户名 -p 你的库名 < setup/schema.sql
```

创建管理员（密码哈希用 `php -r "echo password_hash('你的密码', PASSWORD_DEFAULT);"` 生成）：

```sql
INSERT INTO lm_admin (username, password, email, nickname, role, status, created_at)
VALUES ('admin', '你的密码哈希', 'admin@example.com', 'admin', 'admin', 1, NOW());
```

创建安装标记文件 `includes/config_installed.php`：

```php
<?php
return true;
```

---

## 四、升级已有站点

> ⚠️ **升级前务必备份**：数据库 + `assets/uploads/` 目录 + `.env` 文件。

### 步骤

1. **备份**

```bash
mysqldump -u 用户名 -p 你的库名 > backup-$(date +%F).sql
tar czf uploads-backup-$(date +%F).tar.gz assets/uploads/
cp .env .env.backup
```

2. **下载新版本**源码，解压到临时目录

3. **覆盖站点文件**，但要**保留**以下内容：

| 保留项 | 原因 |
|--------|------|
| `.env` | 你的数据库配置与 `SECRET_KEY`，覆盖后站点会连不上库 / 已加密数据失效 |
| `includes/config_installed.php` | 安装标记，覆盖后会重新要求安装 |
| `assets/uploads/` | 你上传的所有图片 |
| `cache/` | 页面缓存（可删，会自动重建） |

4. **执行数据库变更**——查看新版本 `docs/migrations/` 下是否有新的 SQL 脚本，按序执行

5. **检查 `LM_VERSION`**——确认 `includes/config.php` 中的版本号已更新为目标版本

6. **验证**：访问首页、文章页、后台，确认功能正常

7. **清理**：删除 `setup/` 目录（若新版本带了）

---

## 五、目录安全要点

部署后请确认 Web 服务器**禁止访问**以下路径：

| 路径 | 说明 |
|------|------|
| `.env` | 数据库凭据与加密密钥 |
| `includes/` | 核心库，含安全逻辑 |
| `docs/` | 文档，无需对外 |
| `cache/` | 页面缓存 |
| `setup/schema.sql` | 数据库结构 |

并禁止 `assets/uploads/` 下的脚本执行（防上传木马）。

Nginx 示例配置已包含以上规则。

---

## 六、常见问题

**Q：访问域名显示 404？**
A：多半是上传时多套了一层目录。确认服务器根目录下能直接看到 `index.php`。

**Q：提示「网站配置缺失，请检查 .env 文件」？**
A：`.env` 不存在或缺少 `DB_NAME` / `DB_USER` / `SECRET_KEY`。重跑 `/setup/` 或按上面第三节手动创建。

**Q：安装向导打不开？**
A：确认 `setup/` 目录已上传，且 Web 服务器允许 PHP 执行。若之前已安装过，`includes/config_installed.php` 返回 `true` 会阻止重复安装，需先删除该文件。

**Q：改了 `SECRET_KEY` 之后 AI 摘要 / OAuth 登录报错？**
A：已加密存储的 API Key 无法解密了。到后台重新填写这些密钥即可。

**Q：升级后样式错乱？**
A：浏览器缓存。强制刷新（Ctrl+F5），或到后台清一次页面缓存（删除 `cache/` 目录内容）。
