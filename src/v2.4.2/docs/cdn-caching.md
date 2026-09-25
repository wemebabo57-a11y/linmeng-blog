# CDN 缓存与源站页面缓存指南

本站是纯 PHP 博客，内容页开销主要在列表/静态页的模板渲染与数据库查询。两层缓存配合使用：

1. **源站文件缓存**（`includes/PageCache.php`）：把匿名访客的整页 HTML 写入 `cache/pages/`，命中时直接读盘输出，不碰数据库。
2. **CDN 边缘缓存**：把稳定响应再向访客推近一层，进一步削减回源请求。

---

## 一、可以缓存的 URL

### CDN 可缓存（HTML，短 TTL）

| 路径 | 条件 |
| --- | --- |
| `/`（含 `?page/all/sort/category`） | 仅匿名访客 |
| `/archive.php` | 仅匿名访客 |
| `/tags.php` | 仅匿名访客 |
| `/about.php` | 仅匿名访客 |
| `/links.php` | 仅匿名访客 |

源站对以上页面输出 `Cache-Control: public, max-age=0, s-maxage=600, stale-while-revalidate=3600`（仅限匿名 GET/HEAD），CDN 只要「尊重源站 Cache-Control」即可安全缓存。

### CDN 不可缓存

- `/article.php`、`/guestbook.php`：含按 IP 区分的点赞状态/实时评论数据（源站输出 `private, no-cache`）；
- `/login.php`、`/register.php`、`/profile.php`、`/user.php`、`/logout.php`、OAuth 回调、`/api/*`、`/admin/*`：会话/写操作；
- 任何带 `?search=`（或 `msg` 等提示参数）的 URL：源站输出 `private`，且任意关键词会污染边缘缓存。

## 二、CDN 规则建议（以 Cloudflare 为例，其他 CDN 同思路）

1. **Cache Rule：缓存所有内容，尊重源站头**——Edge TTL 选 “Respect origin”。实时页靠源站的 `private` 兜底。
2. **Cache Rule：Cookie 绕过**——请求 Cookie 含 `PHPSESSID` 或 `remember_token` 时 Bypass cache，登录用户/互动过的访客永远看到实时内容。
3. **`/assets/*` 长缓存**——URL 带 `?v=` 版本指纹（`LM_VERSION`），CDN 上给最长 TTL，内容变了指纹也变，可安全 immutable。
4. **HTML 短 TTL**——不要用 “Cache Everything + 固定 Edge TTL” 强行覆盖 HTML；源站 s-maxage=600 已给出收敛窗口，文章发布后最多 10 分钟，也可在 CDN 控制台按 URL 手动 Purge。
5. **绕过路径**——`/admin/`、`/api/`、`/login.php`、`/register.php`、`/profile.php`、`/logout.php`、`/github-callback.php`、`/gitee-callback.php`、`/gitcode-callback.php`、`/rss.php` 加入 Bypass。

## 三、源站 Nginx 配套（见 docs/nginx.example.conf）

- `location ^~ /assets/ { expires 365d; add_header Cache-Control "public, max-age=31536000, immutable"; }`（版本指纹保证安全）。
- `location ~ ^/cache/ { deny all; return 404; }`：页面缓存落盘目录，禁止直连访问。

## 四、源站页面缓存（PageCache）行为说明

- **作用范围**：只在 `index.php / archive.php / tags.php / about.php / links.php` 顶部挂钩，键按 `REQUEST_URI`（含 query）隔离。query 参数必须全部落在该页白名单内，其余一律跳过（首页白名单：`page, all, sort, category`；其余四页无参数）。
- **仅匿名**：非 GET/HEAD、开口了 PHP 会话（含 `remember_token`）的请求全部跳过。
- **CSRF meta**：写入缓存前把模板输出的 meta csrf-token 值替换为占位符 `{{LM_CSRF_TOKEN}}`，HIT 时再替换回当前请求的令牌（匿名访客为按 IP 派生的无状态令牌），缓存文件内不留任何真实令牌，HIT/MISS 不串号。
- **调试头**：响应带 `X-Page-Cache: HIT|MISS|SKIP`，HIT 附带 `Age`。
- **TTL**：默认 300 秒；在入口（`PageCache::start()` 之前）`define('LM_PAGE_CACHE_TTL', 600);` 即可覆盖。
- **完整性**：仅当响应状态码为 2xx 且输出非空时落盘；临时文件 + rename 避免半文件被读。

## 五、手动失效

- 清全部：`PageCache::purgeAll()`（保留 `index.html` 防列目录文件）。
- 清单个 URL：`PageCache::purgeUri('/?page=2')`。

### 建议挂接点（按需在 admin 代码里补一行，本任务未修改 admin/ 文件）

    require_once LM_ROOT . '/includes/PageCache.php';
    PageCache::purgeAll();

推荐挂在下列动作成功后：

- `admin/article-edit.php` 发布/更新/删除文章成功后；
- `admin/categories.php`、`admin/links.php`、`admin/settings.php` 变更保存成功后；
- 或给 `admin/index.php` 加一个「清缓存」按钮调用一次 `purgeAll()`。

> 提示：TTL 只有 5 分钟、CDN 边缘缓存约 10 分钟，不挂手动失效也能在约 15 分钟内自愈；洁癖党可再叠加 CDN 按 URL Purge。
