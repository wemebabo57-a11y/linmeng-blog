# 林梦博客 · LinMeng Blog

> 一个现代化、注重安全与性能的 PHP 个人博客系统。自带可视化安装向导，访问 `/setup` 三分钟即可上线。
>
> 🌐 **演示站**：<https://kslinmeng.cn/> ｜ 📦 **源码**：<https://github.com/wemebabo57-a11y/linmeng-blog>

[![GitHub](https://img.shields.io/static/v1?label=GitHub&message=linmeng-blog&color=181717&logo=github)](https://github.com/wemebabo57-a11y/linmeng-blog)
[![演示站](https://img.shields.io/badge/演示站-kslinmeng.cn-6366f1?logo=googlechrome&logoColor=white)](https://kslinmeng.cn/)
![License](https://img.shields.io/badge/License-MIT-blue)
![PHP](https://img.shields.io/badge/PHP-%E2%89%A57.4-777bb4)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479a1)
![NoFramework](https://img.shields.io/badge/Framework-原生PHP-4f46e1)

---

## 📖 项目简介

林梦博客是一个基于原生 PHP + MySQL 的轻量博客系统，不依赖任何框架，部署简单、运行高效。它内置了文章管理、评论留言、友链、相册、服务状态监控、AI 文章摘要、GitHub 图床、多平台 OAuth 登录等丰富功能，并具备完善的 CSRF / XSS / 限流 / 登录锁定等安全机制。

**亮点：自带可视化安装向导** —— 上传代码后访问 `你的域名/setup/`，按提示配置数据库与管理员账号即可完成安装，无需手动导入 SQL、无需手动写配置文件。

---

## ✨ 功能特性

### 内容
- 📝 文章管理：Markdown / HTML 双模式、封面、摘要、标签、分类、置顶、草稿、图集
- 💬 评论与留言板：楼中楼回复、审核机制、敏感词过滤
- 🏷 标签云、分类归档、时间轴归档
- 📡 RSS 订阅、站点地图友好

### 社交与用户
- 👤 用户系统：注册（需审核）/ 登录 / 资料编辑 / 头像上传
- 🔗 友情链接 + 友链自助申请（需审核）
- 🤝 GitHub / Gitee / GitCode 三平台 OAuth 登录
- 🖼 相册：基于 GitHub 仓库的图床，自动转 jsDelivr CDN

### 工具与增强
- 🤖 AI 文章摘要（兼容 OpenAI / 自定义接口，支持多 Provider）
- 📊 服务状态监控（TCP/HTTP 探测、定时任务、历史日志）
- 🌤 天气挂件、一言（Hitokoto）、音乐播放器
- 🛠 工具页（蓝奏云解析等）
- 🎮 小游戏模块
- 💰 赞助 / 捐赠页

### 安全与性能
- 🔒 CSRF 令牌、XSS 过滤、SQL 预处理、上传图片重处理
- ⏱ 通用限流、登录失败锁定、登录日志
- 📦 AES-256-CBC 加密存储 API Key 等敏感配置
- ⚡ 静态资源长缓存、按页面分级的 CDN 缓存策略（Cloudflare 友好）
- 🎨 粒子背景、星空背景、深色模式、响应式布局

---

## 🛠 技术栈

| 类别 | 技术 |
|------|------|
| 后端 | 原生 PHP ≥ 7.4（无框架） |
| 数据库 | MySQL 5.7+ / MariaDB 10.3+，utf8mb4 |
| 前端 | 原生 HTML/CSS/JS，自研设计系统 |
| 加密 | OpenSSL AES-256-CBC |
| 部署 | Nginx 推荐，兼容 Apache |

---

## 📦 环境要求

- **PHP** ≥ 7.4（推荐 8.0+）
  - 必需扩展：`pdo_mysql`、`openssl`、`mbstring`、`ctype`、`json`
- **MySQL** ≥ 5.7 或 **MariaDB** ≥ 10.3
- **Web 服务器**：Nginx（推荐）或 Apache
- 目录权限：项目根目录、`includes/`、`assets/uploads/` 需可写（用于生成 `.env`、安装标记、上传文件）

---

## 🚀 快速安装（推荐）

### 1. 获取代码

```bash
git clone https://github.com/wemebabo57-a11y/linmeng-blog.git
# 或直接下载 ZIP 上传到服务器
```

将代码上传到网站根目录。

### 2. 运行安装向导

浏览器访问：

```
http://你的域名/setup/
```

向导会自动完成 **环境检测**，然后引导你：

1. **配置数据库** —— 填写主机、库名、用户名、密码（可选「数据库不存在时自动创建」）
2. **设置站点与管理员** —— 站点 URL、站点名称、首个管理员账号密码
3. **一键安装** —— 自动生成 `.env`、导入数据库结构、创建管理员、写入默认设置

> 安装向导会自动生成 `SECRET_KEY`（用于加密 API Key 等敏感数据）。**该密钥一经设定请勿变更**，否则已加密的数据无法解密。

### 3. 删除安装目录（重要）

安装完成后，向导会提示你删除 `setup/` 目录。请务必删除，防止他人重复执行安装：

```bash
rm -rf setup/
```

（向导界面也提供「删除 setup 目录」按钮，一键完成。）

### 4. 登录后台

访问 `你的域名/login.php`，用刚才设置的管理员账号登录，进入 `你的域名/admin/` 开始发表文章与配置站点。

---

## 🔧 手动配置（可选）

如果服务器因权限等原因无法使用安装向导，也可手动配置：

```bash
cp .env.example .env
```

编辑 `.env` 填入真实值：

```ini
DB_HOST=localhost
DB_NAME=你的库名
DB_USER=你的用户名
DB_PASS=你的密码
SECRET_KEY=                    # 用 php -r "echo bin2hex(random_bytes(32));" 生成
SITE_URL=https://你的域名
SITE_PATH=                     # 子目录部署时填写，如 /blog；根目录留空
LM_TRUST_PROXY=false
```

然后导入数据库结构：

```bash
mysql -u 用户名 -p 你的库名 < setup/schema.sql
```

最后手动创建管理员：参考 `includes/Security.php` 的密码哈希方式，或直接在数据库执行（将 `你的密码哈希` 替换为 `php -r "echo password_hash('你的密码', PASSWORD_DEFAULT);"` 的输出）：

```sql
INSERT INTO lm_admin (username, password, email, nickname, role, status, created_at)
VALUES ('admin', '你的密码哈希', 'admin@example.com', 'admin', 'admin', 1, NOW());
```

并创建安装标记文件 `includes/config_installed.php`（内容可为空 PHP 文件）。

---

## 🗂 目录结构

```
.
├── admin/                 # 后台管理
│   ├── template/           #   后台模板
│   ├── article-edit.php    #   文章编辑
│   ├── articles.php        #   文章列表
│   ├── settings.php        #   站点设置
│   ├── users.php           #   用户管理
│   └── ...
├── api/                    # 前端接口
│   ├── like.php            #   点赞
│   ├── visit.php           #   访问统计
│   ├── ai-summary.php      #   AI 摘要
│   ├── github-upload.php   #   图床上传
│   └── ...
├── assets/                 # 静态资源
│   ├── css/                #   样式（含 design-system.css）
│   ├── js/                 #   脚本
│   ├── images/             #   默认图片
│   └── uploads/            #   用户上传（运行时生成，已 gitignore）
├── docs/
│   └── nginx.example.conf  # Nginx 配置示例
├── includes/               # 核心库
│   ├── config.php          #   配置入口（读取 .env）
│   ├── Database.php        #   PDO 数据库封装
│   ├── Security.php       #   安全：CSRF/XSS/限流/加密
│   ├── functions.php       #   公共函数
│   ├── AiProvider.php      #   AI Provider
│   └── Markdown.php        #   Markdown 解析
├── setup/                  # ⭐ 安装向导（安装后删除）
│   ├── index.php           #   向导主程序
│   ├── schema.sql          #   数据库结构
│   ├── style.css           #   向导样式
│   └── .htaccess           #   保护非 PHP 资源
├── template/               # 前台模板
│   ├── header.php
│   ├── sidebar.php
│   └── bottom-widgets.php
├── .env.example            # 配置模板
├── .htaccess               # Apache 兼容规则
├── index.php               # 首页
├── article.php             # 文章页
├── login.php / register.php
├── guestbook.php           # 留言板
├── gallery.php             # 相册
├── rss.php                 # RSS
└── ...                     # 其它页面
```

---

## 🌐 Web 服务器配置

### Nginx（推荐）

参考 `docs/nginx.example.conf`，核心要点：

```nginx
server {
    listen 443 ssl http2;
    server_name 你的域名;
    root /www/wwwroot/你的站点目录;
    index index.php;

    # 隐藏敏感文件
    location ~ /\.(env|git|user\.ini|htaccess) { deny all; return 404; }
    location ~ ^/includes/  { deny all; return 404; }
    location ~ ^/(storage|docs)/ { deny all; return 404; }

    # 上传目录禁止执行脚本
    location ~* /assets/uploads/.*\.(php|phtml|pl|py|sh|cgi)$ { deny all; return 403; }

    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass unix:/tmp/php-cgi-80.sock;   # 按实际 PHP 版本调整
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

### Apache

项目自带 `.htaccess`，已包含敏感文件拦截、核心目录保护、安全头、错误页等规则，通常无需额外配置（需开启 `mod_rewrite`）。

---

## 🔒 安全建议

1. **安装后立即删除 `setup/` 目录**。
2. **妥善保管 `SECRET_KEY`**：它用于加密 AI API Key、OAuth Secret 等敏感配置，一旦丢失已加密数据无法恢复。`.env` 已在 `.gitignore` 中，切勿提交到版本库。
3. 生产环境关闭 PHP 错误显示（`config.php` 默认已关闭 `display_errors`）。
4. 配置 HTTPS 并开启 HSTS（Nginx 配置示例已含）。
5. 后台路径 `/admin/` 建议增加 IP 白名单或 Basic Auth 加固。
6. 定期备份 `assets/uploads/` 与数据库。

---

## ⚙️ 后台配置入口

登录后台 `你的域名/admin/` 后，在 **设置** 中可配置：

- 站点基本信息（名称、描述、关键词、Logo、Favicon、背景图、ICP 备案号）
- 社交链接（GitHub、Bilibili、Telegram、邮箱 —— 留空则不在页脚显示）
- 评论审核开关、文章评论开关
- AI 摘要（Provider、模型、提示词）
- OAuth 登录（GitHub / Gitee / GitCode 的 Client ID/Secret）
- 人机验证（Cloudflare Turnstile / GeeTest）
- 图床（GitHub 仓库、Token、分支）
- 服务状态监控（探测间隔、密钥）
- 工具页、蓝奏云解析

---

## 🤝 参与贡献

欢迎提交 Issue 与 PR。开发时：

```bash
# 克隆并开发
git clone https://github.com/wemebabo57-a11y/linmeng-blog.git
# 修改代码后请确保：
#   - 不要在代码中硬编码任何密钥 / 密码 / Token
#   - 敏感配置一律走 .env 或 lm_setting 数据表
#   - 新增数据表请同步更新 setup/schema.sql
```

---

## 📄 开源协议

本项目基于 [MIT License](LICENSE) 开源，可自由使用、修改、分发。

> 如果本项目对你有帮助，欢迎点个 ⭐ Star，或在你的站点给它一个友链 :)
