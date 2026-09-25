<?php
/**
 * 403 页面
 * 与 Copper & Stone 主题视觉一致，独立运行
 */
http_response_code(403);
header('Content-Type: text/html; charset=UTF-8');
header('Referrer-Policy: no-referrer');
$theme = $_COOKIE['theme'] ?? 'auto';
?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="<?= htmlspecialchars($theme === 'auto' ? '' : $theme, ENT_QUOTES) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>403 - 访问被拒绝 | 林梦的博客</title>
    <style>
        :root {
            --bg: #f7f3ed;
            --text: #2b2724;
            --text-muted: #6b5d50;
            --accent: #a6682e;
            --accent-hover: #8b5526;
            --border: #e5dccf;
            color-scheme: light;
        }
        [data-theme="dark"] {
            color-scheme: dark;
            --bg: #1c1917;
            --text: #f5efe6;
            --text-muted: #a8a09a;
            --accent: #d69b5a;
            --accent-hover: #e0a868;
            --border: #3a342e;
        }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                color-scheme: dark;
                --bg: #1c1917;
                --text: #f5efe6;
                --text-muted: #a8a09a;
                --accent: #d69b5a;
                --accent-hover: #e0a868;
                --border: #3a342e;
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "LXGW WenKai", "PingFang SC", "Microsoft YaHei", system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            transition: background 0.3s ease, color 0.3s ease;
        }
        .container {
            text-align: center;
            max-width: 540px;
        }
        .code {
            font-family: "Playfair Display", Georgia, serif;
            font-size: clamp(6rem, 4rem + 10vw, 10rem);
            font-weight: 700;
            line-height: 1;
            color: var(--accent);
            letter-spacing: -0.04em;
            margin-bottom: 1rem;
        }
        .title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }
        .message {
            color: var(--text-muted);
            margin-bottom: 2rem;
            line-height: 1.7;
        }
        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            border: 1px solid var(--border);
        }
        .btn-primary {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }
        .btn-primary:hover {
            background: var(--accent-hover);
            border-color: var(--accent-hover);
        }
        .btn-secondary {
            background: transparent;
            color: var(--text);
        }
        .btn-secondary:hover {
            background: var(--border);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="code">403</div>
        <h1 class="title">访问被拒绝</h1>
        <p class="message">你没有权限访问此页面，或资源受保护。<br>如认为这是错误，请联系站点管理员。</p>
        <div class="actions">
            <a href="/" class="btn btn-primary">返回首页</a>
            <a href="javascript:history.back()" class="btn btn-secondary">返回上一页</a>
        </div>
    </div>
</body>
</html>
