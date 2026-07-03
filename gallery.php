<?php
/**
 * GitHub 图库页面
 * 用户可以上传文件和查看自己上传的文件
 */
define('LM_ROOT', __DIR__);

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
Security::setSecurityHeaders();

$pageTitle = '免费图床';
$currentPage = 'gallery';

$isLoggedIn = isLoggedIn();
$user = null;
$username = '';
if ($isLoggedIn) {
    $user = currentUser();
    $username = $user['username'] ?? '';
}

// 获取 GitHub 配置状态
$githubToken = getSetting('github_gallery_token', '');
$githubRepo = getSetting('github_gallery_repo', '');
$isConfigured = !empty($githubToken) && !empty($githubRepo);

// 获取图库大小限制（MB）
$galleryMaxSize = (int) getSetting('gallery_max_size', '5');
if ($galleryMaxSize < 1) $galleryMaxSize = 1;
if ($galleryMaxSize > 100) $galleryMaxSize = 100;
$galleryMaxSizeBytes = $galleryMaxSize * 1024 * 1024;

// 获取当前用户的图片列表
$images = [];
if ($isLoggedIn && $isConfigured) {
    try {
        $images = db()->fetchAll(
            "SELECT * FROM lm_gallery WHERE user_id = ? ORDER BY created_at DESC",
            [$user['id']]
        );
    } catch (Exception $e) {
        $images = [];
    }
}

require_once LM_ROOT . '/template/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
            免费图床
        </div>
    </div>
    <div class="card-body">
        <?php if (!$isLoggedIn): ?>
        <div class="empty-state" style="padding: 60px 20px;">
            <div class="empty-state-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
            </div>
            <h3>免费图床需登录后使用</h3>
            <p>登录账号后即可上传图片并管理你的图库。</p>
            <p style="font-size: 0.875rem; margin-top: 8px; color: var(--text-light);">还没有账号？可以申请一个。</p>
            <div style="margin-top: 24px; display: flex; justify-content: center; gap: 12px;">
                <a href="/login.php" class="btn btn-primary">登录</a>
                <a href="/register.php" class="btn btn-secondary">申请账号</a>
            </div>
        </div>
        <?php elseif (!$isConfigured): ?>
        <div class="empty-state" style="padding: 60px 20px;">
            <div class="empty-state-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/></svg>
            </div>
            <h3>图库功能未配置</h3>
            <p>管理员尚未配置 GitHub 图库，请稍后再试</p>
        </div>
        <?php else: ?>

        <!-- 上传区域 -->
        <div class="gallery-upload-area" id="uploadArea" data-max-size="<?php echo $galleryMaxSizeBytes; ?>" data-max-size-text="<?php echo $galleryMaxSize; ?>">
            <input type="file" id="fileInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">
            <div class="gallery-upload-inner" id="uploadTrigger">
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
                <p style="margin-top: 12px; font-size: 1rem; font-weight: 500;">点击或拖拽上传图片</p>
                <p style="margin-top: 6px; font-size: 0.85rem; color: var(--text-light);">支持 JPG、PNG、GIF、WebP，最大 <?php echo $galleryMaxSize; ?>MB</p>
            </div>
        </div>

        <!-- 上传进度/结果 -->
        <div id="uploadStatus" style="display: none; margin-top: 16px;"></div>

        <!-- 图片列表 -->
        <div style="margin-top: 32px;">
            <h3 style="margin-bottom: 16px; font-size: 1.1rem; font-weight: 600;">
                我的图片 (<?php echo count($images); ?>)
            </h3>

            <?php if (empty($images)): ?>
            <div class="empty-state" style="padding: 40px 20px;">
                <div class="empty-state-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                </div>
                <p>还没有上传过图片</p>
                <p style="font-size: 0.875rem; margin-top: 8px; color: var(--text-light);">点击上方区域上传你的第一张图片</p>
            </div>
            <?php else: ?>
            <div class="gallery-grid">
                <?php foreach ($images as $img): ?>
                <div class="gallery-item" data-id="<?php echo $img['id']; ?>">
                    <div class="gallery-item-img-wrapper">
                        <img src="<?php echo e($img['cdn_url'] ?: $img['raw_url']); ?>" alt="<?php echo e($img['original_name']); ?>" loading="lazy" class="gallery-item-img">
                        <div class="gallery-item-overlay">
                            <button class="gallery-btn gallery-btn-copy" data-url="<?php echo e($img['cdn_url'] ?: $img['raw_url']); ?>" title="复制链接">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                            </button>
                            <button class="gallery-btn gallery-btn-view" data-src="<?php echo e($img['cdn_url'] ?: $img['raw_url']); ?>" title="查看大图">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="gallery-item-info">
                        <div class="gallery-item-name" title="<?php echo e($img['original_name']); ?>"><?php echo e($img['original_name']); ?></div>
                        <div class="gallery-item-meta">
                            <span><?php echo formatFileSize($img['file_size']); ?></span>
                            <span><?php echo timeAgo($img['created_at']); ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php endif; ?>
    </div>
</div>

<style>
.gallery-upload-area {
    border: 2px dashed var(--border-color);
    border-radius: var(--radius);
    padding: 40px 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    background: var(--bg-subtle);
}
.gallery-upload-area:hover {
    border-color: var(--primary-color);
    background: rgba(59, 130, 246, 0.05);
}
.gallery-upload-area.dragover {
    border-color: var(--primary-color);
    background: rgba(59, 130, 246, 0.1);
}
.gallery-upload-inner {
    color: var(--text-light);
    pointer-events: none;
}
.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 16px;
}
.gallery-item {
    border-radius: var(--radius);
    overflow: hidden;
    border: 1px solid var(--border-color);
    background: var(--card-bg);
    transition: box-shadow 0.2s;
}
.gallery-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.gallery-item-img-wrapper {
    position: relative;
    aspect-ratio: 1;
    overflow: hidden;
    background: var(--bg-subtle);
}
.gallery-item-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}
.gallery-item:hover .gallery-item-img {
    transform: scale(1.05);
}
.gallery-item-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    opacity: 0;
    transition: opacity 0.2s;
}
.gallery-item:hover .gallery-item-overlay {
    opacity: 1;
}
.gallery-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    background: rgba(255,255,255,0.9);
    color: #333;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.gallery-btn:hover {
    background: #fff;
    transform: scale(1.1);
}
.gallery-item-info {
    padding: 10px 12px;
}
.gallery-item-name {
    font-size: 0.85rem;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.gallery-item-meta {
    font-size: 0.75rem;
    color: var(--text-light);
    margin-top: 4px;
    display: flex;
    justify-content: space-between;
}
</style>

<?php if ($isLoggedIn && $isConfigured): ?>
<script src="/assets/js/gallery.js?v=<?php echo LM_VERSION; ?>"></script>
<?php endif; ?>

<?php
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

require_once LM_ROOT . '/template/sidebar.php';
?>
