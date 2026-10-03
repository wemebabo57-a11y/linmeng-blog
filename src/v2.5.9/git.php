<?php
/**
 * Git 展示页面
 */
define('APP_ROOT', __DIR__);

require_once APP_ROOT . '/includes/config.php';
require_once APP_ROOT . '/includes/Security.php';
require_once APP_ROOT . '/includes/Database.php';
require_once APP_ROOT . '/includes/functions.php';

app_session_start();
Security::setSecurityHeaders();
app_public_cache_headers();

$pageTitle = 'Git';
$currentPage = 'git';

$githubUrl = getSetting('github_url', '');
$repos = getVisibleRepos();

require_once APP_ROOT . '/template/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/></svg> Git</div>
    </div>
    <div class="card-body">
        <?php if ($githubUrl): ?>
        <div style="text-align: center; padding: 40px 20px;">
            <div class="social-hero-icon github"><svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/></svg></div>
            <h2 style="margin-bottom: 16px;">我的 GitHub</h2>
            <p style="color: var(--text-light); margin-bottom: 24px;">开源项目、代码片段和技术分享</p>
            <a href="<?php echo e($githubUrl); ?>" target="_blank" rel="noopener" class="btn btn-primary" style="font-size: 1.1rem; padding: 12px 32px;">
                前往 GitHub 主页
            </a>
        </div>
        <?php endif; ?>

        <div style="<?php echo $githubUrl ? 'margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--border-color);' : ''; ?>">
            <h3 style="margin-bottom: 16px;">我的仓库</h3>
            <?php if (!empty($repos)): ?>
            <style>
                .repo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; }
                .repo-card { display: flex; flex-direction: column; gap: 10px; padding: 18px; border: 1px solid var(--border-color); border-radius: 12px; color: inherit; text-decoration: none; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
                .repo-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.08); border-color: var(--primary, #6366f1); }
                .repo-card-head { display: flex; align-items: center; gap: 8px; color: var(--text); font-weight: 600; word-break: break-all; }
                .repo-card-head svg { flex-shrink: 0; }
                .repo-card-desc { color: var(--text-light); font-size: .9rem; line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
                .repo-card-link { margin-top: auto; display: inline-flex; align-items: center; gap: 6px; color: var(--primary, #6366f1); font-size: .85rem; }
            </style>
            <div class="repo-grid">
                <?php foreach ($repos as $repo): ?>
                <a href="<?php echo e($repo['url']); ?>" target="_blank" rel="noopener" class="repo-card">
                    <span class="repo-card-head">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                        <?php echo e($repo['name']); ?>
                    </span>
                    <?php if (!empty($repo['description'])): ?>
                    <span class="repo-card-desc"><?php echo e($repo['description']); ?></span>
                    <?php endif; ?>
                    <span class="repo-card-link">
                        访问仓库
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding: 40px 20px;">
                <div class="empty-state-icon"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg></div>
                <p>博主还没有添加开源仓库</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/template/sidebar.php'; ?>
