# 林梦博客 (KSLinMeng Blog)

> 一个功能完善、安全可靠的 PHP 个人博客系统——记录生活，分享技术。

[![PHP](https://img.shields.io/badge/PHP-8.0+-8892BF?logo=php)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?logo=mysql)](https://www.mysql.com/)
[![License](https://img.shields.io/badge/License-MIT-4C1?style=flat-square)](LICENSE)
[![Version](https://img.shields.io/badge/Version-2.2.2-blue?style=flat-square)]()

---

## ✨ 特性概览

| 模块 | 说明 |
|------|------|
| 📝 文章管理 | 富文本编辑器、分类标签、置顶/推荐、搜索过滤、全文检索 |
| 💬 评论系统 | 盖楼回复、验证码防护、XSS/CSRF 双重过滤 |
| 👤 用户体系 | 注册/登录、个人资料、密码加密(bcrypt cost=12)、暴力破解防护 |
| 🖼️ 相册画廊 | 图片上传与重新处理、WebP 优化、懒加载、灯箱预览 |
| 🔗 友链管理 | 友链展示与申请、排序管理 |
| 🤖 AI 辅助 | 多 AI 提供商接入、文章智能摘要、摘要缓存 |
| 🎵 音乐功能 | 音乐播放器、代理服务、粒子动效背景 |
| 🌤️ 小部件 | 一言(Hitokoto)、天气组件、星空/粒子背景 |
| 🛡️ 安全防护 | SQL 注入防护、XSS 过滤、CSRF Token、安全响应头 |
| 📊 访问统计 | 访问量记录、访客日志 |
| 🎨 主题切换 | 深色/浅色主题、CSS 变量驱动、流畅过渡动画 |
| 📱 响应式 | 全端适配，移动端 sidebar 可折叠 |

---

## 🏗️ 技术架构

```
kslinmeng.cn/
├── admin/                    # 后台管理面板
│   ├── articles.php          #    文章管理
│   ├── article-edit.php      #    文章编辑
│   ├── categories.php        #    分类管理
│   ├── comments.php          #    评论管理
│   ├── gallery.php           #    相册管理
│   ├── links.php             #    友链管理
│   ├── users.php             #    用户管理
│   ├── settings.php          #    站点设置
│   ├── sponsors.php          #    赞助管理
│   ├── services.php          #    服务管理
│   ├── link-apply.php        #    友链申请管理
│   └── ai-summary.php        #    AI 摘要管理
├── api/                      # API 接口
│   ├── ai-summary.php        #    AI 文章摘要
│   ├── comment-reply.php     #    评论回复
│   ├── like.php              #    文章点赞
│   ├── visit.php             #    访问量统计
│   ├── github-upload.php     #    GitHub 图片上传
│   ├── hitokoto.php          #    一言接口
│   ├── lanzou-parse.php      #    蓝奏云解析
│   └── service-probe.php     #    服务探测
├── includes/                 # 核心组件
│   ├── config.php            #    配置文件 (.env 驱动)
│   ├── Database.php          #    PDO 数据库封装
│   ├── Security.php          #    安全核心类
│   ├── functions.php         #    公共函数库
│   ├── AiProvider.php        #    AI 提供商管理
│   └── ai-providers.php      #    AI 配置管理
├── assets/                   # 前端资源
│   ├── css/
│   │   ├── style.css         #    主样式表
│   │   └── design-system.css #    设计系统 (v6.0)
│   ├── js/
│   │   ├── main.js           #    主逻辑
│   │   ├── particles.js      #    粒子背景
│   │   ├── starfield.js      #    星空背景
│   │   ├── music-player.js   #    音乐播放器
│   │   ├── gallery.js        #    相册功能
│   │   ├── hitokoto.js       #    一言
│   │   ├── weather-widget.js #    天气组件
│   │   ├── ai-summary.js     #    AI 摘要
│   │   └── ui-enhancements.js#    UI 增强
│   ├── images/               #    静态图片
│   └── uploads/              #    用户上传 (.gitignore)
├── template/                 # 页面模板
│   ├── header.php
│   ├── sidebar.php
│   └── bottom-widgets.php
├── docs/                     # 文档
│   └── nginx.example.conf    #    Nginx 配置示例
├── setup.php                 # 安装向导
├── index.php                 # 首页
├── article.php               # 文章详情
├── gallery.php               # 相册
├── links.php                 # 友链
├── guestbook.php             # 留言板
├── about.php                 # 关于
├── donate.php                # 赞助
├── git.php                   # Git
├── profile.php               # 个人资料
├── tools.php                 # 工具集
└── .env.example              #    环境变量模板
```

---

## 📊 数据统计

| 指标 | 数值 |
|------|------|
| PHP 文件 | 57 |
| 数据库表 | 24 |
| CSS 代码行数 | ~9,500+ |
| JS 代码行数 | ~8,300+ |
| 功能页面 | 20+ |

---

## 🚀 快速开始

### 环境要求

- **PHP** >= 8.0 (推荐 8.1+)
- **MySQL** >= 5.7 (推荐 8.0+)
- **Nginx** (推荐) / Apache
- PHP 扩展: `pdo_mysql`, `mbstring`, `gd`, `openssl`

### 安装步骤

1. **部署代码**

```bash
git clone https://github.com/kslinmeng/blog.git
cd blog
```

2. **配置 Web 服务器**

将 Nginx 配置示例复制到你的服务器配置目录：

```bash
cp docs/nginx.example.conf /etc/nginx/conf.d/blog.conf
```

> 详见 [Nginx 配置示例](docs/nginx.example.conf)

3. **创建数据库**

```sql
CREATE DATABASE blog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

4. **创建 `.env` 文件**

```env
DB_HOST=localhost
DB_NAME=blog
DB_USER=root
DB_PASS=your_password
SITE_URL=https://yourdomain.com
SITE_PATH=/
SECRET_KEY=YOUR_RANDOM_SECRET_KEY
```

5. **运行安装向导**

访问 `https://yourdomain.com/setup.php`，按照向导完成：

- ✅ 环境检测 (PHP 版本 / 扩展 / 目录权限)
- ✅ 数据库配置与连接测试
- ✅ 数据表创建 + 默认设置 + 管理员账号
- ✅ `.env` 文件生成

6. **删除安装文件（推荐）**

```bash
rm setup.php
```

---

## 🔐 安全特性

本项目在安全方面做了大量工作：

| 防护措施 | 实现方式 |
|----------|----------|
| SQL 注入防护 | PDO 预处理语句 + 参数绑定 |
| XSS 过滤 | `htmlspecialchars` + 输出编码 |
| CSRF 防护 | 动态 Token + `hash_equals` 校验 |
| 密码安全 | bcrypt (cost=12) + 强度校验 |
| 暴力破解防护 | 登录次数限制 + 锁定时长 |
| 文件上传安全 | 类型白名单 + 图片重处理 + 禁止执行 |
| 敏感文件保护 | `.env` / `/includes/` / `/docs/` Nginx 拦截 |
| 安全响应头 | HSTS / CSP / X-Frame-Options / nosniff 等 |
| 会话安全 | HttpOnly + Secure + SameSite |

---

## 🗄️ 数据库表说明

| 表名 | 说明 |
|------|------|
| `lm_admin` | 管理员账号 |
| `lm_article` | 文章 |
| `lm_article_image` | 文章图片关联 |
| `lm_article_like` | 文章点赞 |
| `lm_category` | 文章分类 |
| `lm_comment` | 评论 |
| `lm_gallery` | 相册 |
| `lm_link` | 友链 |
| `lm_link_apply` | 友链申请 |
| `lm_sponsor` | 赞助商 |
| `lm_service` | 服务信息 |
| `lm_service_log` | 服务探测日志 |
| `lm_setting` | 站点设置 |
| `lm_ai_provider` | AI 提供商配置 |
| `lm_ai_summary_cache` | AI 摘要缓存 |
| `lm_visit_log` | 访问日志 |
| `lm_user_apply` | 用户申请 |
| `lm_login_log` | 登录日志 |
| `lm_login_lock` | 登录锁定记录 |
| `lm_rate_limit` | 速率限制 |

---

## 📝 开发指南

### 项目结构说明

- **`includes/`** — 核心类库与配置，所有入口文件统一从这里加载
- **`admin/`** — 后台管理模块，需登录访问
- **`api/`** — 前后端分离的 API 端点
- **`assets/`** — 静态资源，按 CSS / JS / images / uploads 分类

### 设计系统

本项目采用 **Design System v6.0**，涵盖：

- CSS 变量驱动的深色/浅色主题
- 统一的卡片、按钮、表单风格
- 响应式断点系统
- 微交互动画
- 无障碍 (a11y) 焦点状态

---

## 🤝 贡献指南

欢迎提交 Issue 和 Pull Request！

1. Fork 本仓库
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 提交 Pull Request

---

## 📜 许可证

本项目采用 [MIT](LICENSE) 许可证开源。

---

## 🙏 致谢

- [Hitokoto 一言](https://hitokoto.cn/) — 一言 API
- 各位 [赞助商](https://kslinmeng.cn/donate.php) 和 [友链](https://kslinmeng.cn/links.php) 伙伴
- 所有开源项目的贡献者们

---

<div align="center">

**Made with ❤️ by [林梦](https://kslinmeng.cn)**

© 2026 KSLinMeng Blog · Version 2.2.2

</div>
