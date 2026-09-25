# 林梦博客 · LinMeng Blog

> 一个现代化、注重安全与性能的 PHP 个人博客系统。自带可视化安装向导，访问 `/setup` 三分钟即可上线。
>
> 🌐 **演示站**：<https://kslinmeng.cn/> ｜ 📦 **源码**：<https://github.com/wemebabo57-a11y/linmeng-blog>

[![GitHub](https://img.shields.io/static/v1?label=GitHub&message=linmeng-blog&color=181717&logo=github)](https://github.com/wemebabo57-a11y/linmeng-blog)
[![演示站](https://img.shields.io/badge/演示站-kslinmeng.cn-6366f1?logo=googlechrome&logoColor=white)](https://kslinmeng.cn/)
[![Release](https://img.shields.io/github/v/release/wemebabo57-a11y/linmeng-blog?color=6366f1&label=最新版本)](https://github.com/wemebabo57-a11y/linmeng-blog/releases)
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

## 🚀 快速开始

### 1. 获取源码

各版本源码统一放在 [`src/`](src/) 目录下，**每个版本一个独立文件夹**。历史版本也在其中，按需自行选择。

- **推荐**：到 [Releases](https://github.com/wemebabo57-a11y/linmeng-blog/releases) 下载最新版的 zip 源码包
- **或**用 Git 获取整个仓库，然后从 `src/` 中选择需要的版本：

```bash
git clone --depth 1 https://github.com/wemebabo57-a11y/linmeng-blog.git
# 进入 src/ 目录，里面每个文件夹就是一个完整版本
```

### 2. 上传

> 📌 **把 `src/` 中你选定的那个版本文件夹「里面的全部内容」上传到网站根目录。**

```
仓库里的位置                          上传到服务器后
─────────────────────────────────    ─────────────────────────
src/<版本>/index.php          ───►   网站根目录/index.php
src/<版本>/includes/          ───►   网站根目录/includes/
src/<版本>/setup/             ───►   网站根目录/setup/
src/<版本>/admin/             ───►   网站根目录/admin/
```

✅ 正确：服务器根目录下能直接看到 `index.php`
❌ 错误：变成 `网站根目录/<版本>/index.php`（多套了一层，把版本号这层去掉）

> 🚨 **注意**：`src/` 本身不是网站目录。**不要把 `src/` 整个上传到服务器**，要上传其中某一个版本文件夹里面的内容。

### 3. 运行安装向导

浏览器访问 `http://你的域名/setup/`，按提示完成数据库与管理员配置。

> ⚠️ **安装完成后务必删除 `setup/` 目录**，防止他人重复安装。

### 4. 登录后台

访问 `http://你的域名/login.php`，用安装时设置的管理员账号登录，进入 `/admin/` 开始使用。

详细步骤（含手动安装、Nginx 配置、升级指南）见：
- 📗 [部署指南](docs/DEPLOY.md)
- 📙 [版本索引](src/README.md)

---

## 🗂 仓库结构

本仓库是**源码归档仓库**：根目录只放文档，实际站点代码统一归档在 `src/` 下，**每个版本一个文件夹**。

```
.
├── README.md              # 本文件：项目介绍与使用说明
├── LICENSE                # MIT 许可证
├── src/                   # ⭐ 各版本源码，每个版本一个文件夹
│   ├── README.md          #   版本索引
│   └── <版本>/            #   某个完整版本，可直接部署
│       ├── README.md      #     该版本详细说明
│       ├── index.php      #     首页
│       ├── admin/         #     后台
│       ├── api/           #     接口
│       ├── assets/        #     静态资源
│       ├── includes/      #     核心库
│       ├── setup/         #     安装向导
│       └── template/      #     前台模板
└── docs/                  # 文档
    ├── DEPLOY.md          #   部署与升级指南
    ├── CHANGELOG.md       #   变更记录
    ├── nginx.example.conf #   Nginx 配置示例
    ├── cdn-caching.md     #   CDN 缓存策略
    └── migrations/        #   数据库迁移脚本
```

---

## 🌐 Web 服务器配置

### Nginx（推荐）

参考 [`docs/nginx.example.conf`](docs/nginx.example.conf)，核心要点：

```nginx
server {
    listen 443 ssl http2;
    server_name 你的域名;
    root /www/wwwroot/你的站点目录;      # 指向你上传的站点根目录
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

站点自带 `.htaccess`，已包含敏感文件拦截、核心目录保护、安全头、错误页等规则，通常无需额外配置（需开启 `mod_rewrite`）。

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

欢迎提交 Issue 与 PR。开发时请**在 `src/` 中最新的那个版本目录里修改**：

```bash
git clone https://github.com/wemebabo57-a11y/linmeng-blog.git
cd linmeng-blog/src               # ← 站点代码在各版本目录里
# 修改代码后请确保：
#   - 不要在代码中硬编码任何密钥 / 密码 / Token
#   - 敏感配置一律走 .env 或 lm_setting 数据表
#   - 新增数据表请同步更新 setup/schema.sql
#   - 同步提升 includes/config.php 中的 LM_VERSION
```

> 修改完成后，请把改动同步到 `src/` 下的新版本目录，而不是直接改已发布的历史版本。

---

## 📄 开源协议

本项目基于 [MIT License](LICENSE) 开源，可自由使用、修改、分发。

> 如果本项目对你有帮助，欢迎点个 ⭐ Star，或在你的站点给它一个友链 :)
