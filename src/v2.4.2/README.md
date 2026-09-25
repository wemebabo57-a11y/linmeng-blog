# 林梦博客 v2.4.2

> 当前推荐版本 ｜ 程序内版本号 `LM_VERSION = 2.4.2`（定义于 `includes/config.php`）

本目录是**完整可部署的站点根目录**。把本目录内的全部文件上传到网站根目录即可运行。

---

## 📥 部署步骤

### 1. 获取代码

**方式一：整包下载（推荐）**

到 [Releases](https://github.com/wemebabo57-a11y/linmeng-blog/releases) 下载 `linmeng-blog-v2.4.2.zip`，解压后得到站点文件。

**方式二：只取本目录**

```bash
git clone --depth 1 https://github.com/wemebabo57-a11y/linmeng-blog.git
# 站点文件在 linmeng-blog/src/v2.4.2/
```

### 2. 上传

把**本目录里面的内容**（不是本目录本身）上传到网站根目录。

正确结果：服务器根目录下能直接看到 `index.php`、`includes/`、`setup/`。

```
网站根目录/
├── index.php
├── includes/
├── setup/
├── admin/
└── ...
```

> ⚠️ 常见错误：上传成了 `网站根目录/v2.4.2/index.php`。这样访问域名会 404，把 `v2.4.2` 这一层去掉即可。

### 3. 运行安装向导

浏览器访问：

```
http://你的域名/setup/
```

向导会自动检测环境，然后引导你完成：配置数据库 → 设置站点与管理员 → 一键安装（生成 `.env`、导入表结构、创建管理员、写入默认设置）。

### 4. 删除安装目录（重要）

安装完成后**务必删除 `setup/`**，防止他人重复执行安装：

```bash
rm -rf setup/
```

### 5. 登录后台

访问 `http://你的域名/login.php`，用安装时设置的管理员账号登录，进入 `/admin/` 开始使用。

---

## 📦 环境要求

- **PHP** ≥ 7.4（推荐 8.0+），必需扩展：`pdo_mysql`、`openssl`、`mbstring`、`ctype`、`json`
- **MySQL** ≥ 5.7 或 **MariaDB** ≥ 10.3（utf8mb4）
- **Web 服务器**：Nginx（推荐）或 Apache（需 `mod_rewrite`）
- **目录权限**：站点根目录、`includes/`、`assets/uploads/` 需可写

---

## ✨ 本版本功能

### 内容
- 文章管理：Markdown / HTML 双模式、封面、摘要、标签、分类、置顶、草稿、图集
- 评论与留言板：楼中楼回复、审核机制、敏感词过滤
- 标签云、分类归档、时间轴归档
- RSS 订阅

### 社交与用户
- 用户系统：注册（需审核）/ 登录 / 资料编辑 / 头像上传
- 友情链接 + 友链自助申请（需审核）
- GitHub / Gitee / GitCode 三平台 OAuth 登录
- 相册：基于 GitHub 仓库的图床，自动转 jsDelivr CDN

### 工具与增强
- AI 文章摘要（兼容 OpenAI / 自定义接口，支持多 Provider）
- 服务状态监控（TCP/HTTP 探测、定时任务、历史日志）
- 天气挂件、一言（Hitokoto）、音乐播放器
- 工具页（蓝奏云解析等）、小游戏模块、赞助 / 捐赠页

### 安全与性能
- CSRF 令牌、XSS 过滤、SQL 预处理、上传图片重处理
- 通用限流、登录失败锁定、登录日志
- AES-256-CBC 加密存储 API Key 等敏感配置
- 静态资源长缓存、按页面分级的 CDN 缓存策略（Cloudflare 友好）
- 粒子背景、星空背景、深色模式、响应式布局

---

## 🗂 目录结构

```
.
├── admin/                 # 后台管理
│   ├── template/          #   后台模板
│   ├── article-edit.php   #   文章编辑
│   ├── articles.php       #   文章列表
│   ├── settings.php       #   站点设置
│   └── ...
├── api/                   # 前端接口（点赞、访问统计、AI 摘要、图床上传等）
├── assets/                # 静态资源
│   ├── css/               #   样式（含 design-system.css）
│   ├── js/                #   脚本（含 admin/ 子目录）
│   ├── images/            #   默认图片
│   └── uploads/           #   用户上传（运行时生成）
├── cache/                 # 页面缓存（运行时生成）
├── includes/              # 核心库
│   ├── config.php         #   配置入口（读取 .env）★ LM_VERSION 在此
│   ├── Database.php       #   PDO 数据库封装
│   ├── Security.php       #   安全：CSRF/XSS/限流/加密
│   ├── functions.php      #   公共函数
│   ├── AiProvider.php     #   AI Provider
│   └── Markdown.php       #   Markdown 解析
├── setup/                 # ⭐ 安装向导（安装后删除）
│   ├── index.php          #   向导主程序
│   ├── schema.sql         #   数据库结构
│   └── .htaccess
├── template/              # 前台模板
│   ├── header.php
│   ├── sidebar.php
│   └── bottom-widgets.php
├── .env.example           # 配置模板
├── .htaccess              # Apache 兼容规则
├── index.php              # 首页
├── article.php            # 文章页
├── login.php / register.php
├── guestbook.php          # 留言板
├── gallery.php            # 相册
├── rss.php                # RSS
└── ...                    # 其它页面
```

---

## 🔧 手动安装（不使用向导）

```bash
cp .env.example .env
```

编辑 `.env`：

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

导入表结构并创建管理员：

```bash
mysql -u 用户名 -p 你的库名 < setup/schema.sql
```

```sql
INSERT INTO lm_admin (username, password, email, nickname, role, status, created_at)
VALUES ('admin', '你的密码哈希', 'admin@example.com', 'admin', 'admin', 1, NOW());
```

（`你的密码哈希` 用 `php -r "echo password_hash('你的密码', PASSWORD_DEFAULT);"` 生成）

最后创建安装标记文件 `includes/config_installed.php`（内容为 `<?php return true;`）。

---

## 🔒 安全提示

1. 安装后立即删除 `setup/` 目录
2. 妥善保管 `.env` 中的 `SECRET_KEY`——它用于加密 API Key、OAuth Secret，丢失后已加密数据无法恢复
3. `.env` 已在 `.gitignore` 中，切勿提交到版本库
4. 配置 HTTPS 并开启 HSTS（参考 `docs/nginx.example.conf`）
5. 后台 `/admin/` 建议加 IP 白名单或 Basic Auth
6. 定期备份 `assets/uploads/` 与数据库

---

## 📄 许可

[MIT License](../LICENSE)
