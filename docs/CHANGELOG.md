# 版本变更记录

本文件记录仓库结构与程序版本的变化。程序自身的版本号定义在 `src/<版本>/includes/config.php` 的 `LM_VERSION` 常量。

---

## 仓库结构整理（版本归档）

### 变更内容

对仓库做了一次结构整理：

- **源码按版本号归档** —— 原根目录下的站点代码整体移入 `src/v2.4.2/`（当前版本号取自 `includes/config.php` 的 `LM_VERSION = 2.4.2`）
- **根目录只保留文档与许可证** —— 根 `README.md` 改为项目介绍与「该用哪个版本」的索引，`LICENSE` 保留在根
- **新增版本索引** —— `src/README.md` 说明版本列表与新增版本的流程
- **新增各版本 README** —— `src/v2.4.2/README.md` 说明该版本的部署方式与功能
- **整理 docs/** —— 补充 `docs/DEPLOY.md`（部署与升级指南）与本文件
- **同步 `.gitignore`** —— 忽略规则从根锚定（`/includes/`、`/cache/`）改为指向 `src/` 下的版本目录

### 文件迁移对照

本次是**纯路径迁移**，文件内容未修改（README 与文档除外）。

| 原路径 | 新路径 |
|--------|--------|
| `admin/` | `src/v2.4.2/admin/` |
| `api/` | `src/v2.4.2/api/` |
| `assets/` | `src/v2.4.2/assets/` |
| `cache/` | `src/v2.4.2/cache/` |
| `docs/` | `src/v2.4.2/docs/` |
| `includes/` | `src/v2.4.2/includes/` |
| `setup/` | `src/v2.4.2/setup/` |
| `template/` | `src/v2.4.2/template/` |
| `index.php`、`article.php`、`admin/*.php` 等 37 个根级文件 | `src/v2.4.2/<同名>` |
| `README.md` | 保留在根，内容重写为版本索引 |
| `LICENSE` | 保留在根 |

### ⚠️ 对已有部署的影响

**如果你的服务器是从本仓库根目录拉取部署的，本次整理后需要调整：**

原来的部署方式（站点根 = 仓库根）已不再适用。请改为：

```
把 src/v2.4.2/ 里面的内容上传到网站根目录
```

或把服务器上的部署目录指向 `src/v2.4.2/`。

程序代码本身**没有改动**，因此数据库、`.env`、`SECRET_KEY`、`assets/uploads/` 都不受影响，无需重新安装。

### 为什么这样整理

本站采用「整包上传」的部署方式，用户需要的是一个可以直接上传的完整目录。按版本号归档的好处：

- 任意历史版本都能直接重新下载部署，不必回滚 Git 历史
- 用户可以明确知道自己装的是哪个版本
- 多版本可以并存，便于对照排查

代价是仓库体积随版本数增长，因此旧版本停止维护后可移入 `archive/` 或删除。

---

## v2.4.2

**程序内版本号**：`LM_VERSION = 2.4.2`（`includes/config.php`）

当前推荐版本。包含文章管理、评论留言、友链、相册、服务状态监控、AI 文章摘要、GitHub 图床、GitHub/Gitee/GitCode 三平台 OAuth 登录，以及 CSRF / XSS / 限流 / 登录锁定等安全机制。

完整功能清单见 [`src/v2.4.2/README.md`](../src/v2.4.2/README.md)。

### 数据库迁移

本版本相关的迁移脚本见 [`migrations/`](migrations/)：

- `oauth-columns.sql` —— OAuth 登录所需的用户表字段

---

## 新增版本的流程

1. 复制上一版本目录：`cp -r src/v2.4.2 src/v2.5.0`
2. 修改 `src/v2.5.0/includes/config.php` 中的 `LM_VERSION`
3. 新增/更新 `src/v2.5.0/README.md`
4. 更新 `src/README.md` 的版本列表
5. 在本文件顶部追加新版本段落
6. 如有数据库变更，在 `docs/migrations/` 添加 SQL 脚本
7. 打 tag 并创建 Release：`v2.5.0`，附 zip 源码包
