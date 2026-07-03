<?php
/**
 * 在线工具页 v1.0
 * 已内置：蓝奏云直链解析
 * 预留：后续可在此页添加更多工具
 */
define('LM_ROOT', __DIR__);

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
Security::setSecurityHeaders();

$pageTitle = '在线工具';
$currentPage = 'tools';

// 全局开关
$toolsEnabled = getSetting('tools_enabled', '1') === '1';
$lanzouEnabled = getSetting('lanzou_parse_enabled', '1') === '1';

require_once LM_ROOT . '/template/header.php';
?>

<div class="tools-page">
    <!-- 工具页头部 -->
    <div class="card tools-hero-card">
        <div class="card-body">
            <div class="tools-hero">
                <div class="tools-hero-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                </div>
                <div class="tools-hero-text">
                    <h1 class="tools-hero-title">在线工具箱</h1>
                    <p class="tools-hero-desc">收录实用小工具，持续更新中。所有解析均通过本站服务器代理，不直接请求第三方接口，保护你的隐私。</p>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$toolsEnabled): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state" style="padding: 60px 20px;">
                <div class="empty-state-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                </div>
                <h3>工具页暂未开放</h3>
                <p>管理员尚未启用工具页功能，请稍后再来。</p>
            </div>
        </div>
    </div>
    <?php else: ?>

    <!-- 工具列表 -->
    <div class="tools-grid">

        <!-- 工具 1：蓝奏云直链解析 -->
        <div class="card tool-card" id="tool-lanzou" data-tool="lanzou">
            <div class="card-header">
                <div class="card-title">
                    <span class="tool-badge-icon" style="background: var(--primary-light); color: var(--primary-color);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    </span>
                    蓝奏云直链解析
                </div>
                <span class="tool-tag">网盘</span>
            </div>
            <div class="card-body">
                <?php if (!$lanzouEnabled): ?>
                <div class="tool-disabled-notice">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <span>该工具已被管理员关闭</span>
                </div>
                <?php else: ?>
                <p class="tool-desc">输入蓝奏云分享链接，自动解析获取文件直链下载地址。支持带密码的分享链接。</p>

                <form class="tool-form" id="lanzou-form" data-api="/api/lanzou-parse.php">
                    <?php echo Security::csrfField(); ?>
                    <div class="form-group">
                        <label class="form-label" for="lanzou-url">文件链接 <span class="req">*</span></label>
                        <input type="url" name="url" id="lanzou-url" class="form-input" placeholder="https://wwe.lanzou.com/iXXXX 或 https://lanzoui.com/iXXXX" required autocomplete="off">
                        <div class="form-hint">仅支持蓝奏云官方域名</div>
                    </div>
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label" for="lanzou-pwd">访问密码</label>
                            <input type="text" name="pwd" id="lanzou-pwd" class="form-input" placeholder="无密码请留空" autocomplete="off" maxlength="50">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="lanzou-type">解析模式</label>
                            <select name="type" id="lanzou-type" class="form-select">
                                <option value="">获取直链（JSON）</option>
                                <option value="down">直接跳转下载</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary tool-submit-btn" id="lanzou-submit">
                        <span class="btn-text">开始解析</span>
                        <span class="btn-loading" style="display:none;">
                            <span class="spinner"></span> 解析中...
                        </span>
                    </button>
                </form>

                <!-- 解析结果 -->
                <div class="tool-result" id="lanzou-result" style="display:none;">
                    <div class="tool-result-header">
                        <span class="tool-result-title">解析结果</span>
                        <span class="tool-result-time" id="lanzou-result-time"></span>
                    </div>
                    <div class="tool-result-body" id="lanzou-result-body"></div>
                </div>

                <!-- 错误提示 -->
                <div class="alert alert-error tool-error" id="lanzou-error" style="display:none;"></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 工具 2：占位（预留位置，后续添加更多工具） -->
        <div class="card tool-card tool-card-placeholder" id="tool-placeholder-1">
            <div class="card-header">
                <div class="card-title">
                    <span class="tool-badge-icon" style="background: var(--bg-subtle); color: var(--text-light);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                    </span>
                    更多工具敬请期待
                </div>
                <span class="tool-tag tool-tag-muted">即将上线</span>
            </div>
            <div class="card-body">
                <div class="tool-placeholder-inner">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                    <p>更多实用工具正在开发中，欢迎在留言板提出你需要的工具建议。</p>
                </div>
            </div>
        </div>

        <!-- 工具 3：占位 -->
        <div class="card tool-card tool-card-placeholder" id="tool-placeholder-2">
            <div class="card-header">
                <div class="card-title">
                    <span class="tool-badge-icon" style="background: var(--bg-subtle); color: var(--text-light);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                    </span>
                    待开发工具位
                </div>
                <span class="tool-tag tool-tag-muted">预留</span>
            </div>
            <div class="card-body">
                <div class="tool-placeholder-inner">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                    <p>此位置预留给后续新增的工具。</p>
                </div>
            </div>
        </div>

    </div>
    <?php endif; ?>
</div>

<script src="/assets/js/tools.js?v=<?php echo LM_VERSION; ?>"></script>

<?php require_once LM_ROOT . '/template/sidebar.php'; ?>
