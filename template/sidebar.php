<?php
/**
 * 侧边栏模板 v2.0
 */
if (!defined('LM_ROOT')) {
    die('Access Denied');
}
?>
        </main>
        
        <!-- 侧边栏 -->
        <aside class="sidebar">
            <!-- 个人资料 -->
            <div class="widget">
                <div class="widget-body profile">
                    <?php 
                    $avatar = getSetting('site_logo', '');
                    $wechatQr = getSetting('wechat_qrcode', '');
                    ?>
                    <img src="<?php echo e($avatar ?: '/assets/images/default-avatar.png'); ?>" alt="林梦的头像" class="profile-avatar">
                    <div class="profile-name">林梦</div>
                    <div class="profile-desc"><?php echo e(getSetting('site_description', '记录生活，分享技术')); ?></div>
                    
                    <div class="profile-stats">
                        <div class="profile-stat">
                            <div class="profile-stat-value"><?php echo getArticleCount(); ?></div>
                            <div class="profile-stat-label">文章</div>
                        </div>
                        <div class="profile-stat">
                            <div class="profile-stat-value"><?php echo getCommentCount(); ?></div>
                            <div class="profile-stat-label">评论</div>
                        </div>
                        <div class="profile-stat">
                            <div class="profile-stat-value"><?php echo getRunningDays(); ?></div>
                            <div class="profile-stat-label">运行天数</div>
                        </div>
                    </div>
                    
                    <?php if (isLoggedIn()): ?>
                    <?php $profileUser = currentUser(); ?>
                    <?php if ($profileUser): ?>
                    <div class="widget-action">
                        <a href="/user.php?id=<?php echo (int)$profileUser['id']; ?>" class="btn btn-sm btn-secondary btn-block">个人主页</a>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($wechatQr): ?>
                    <div class="widget-action">
                        <a href="#" class="wechat-btn btn btn-sm btn-secondary btn-block">微信二维码</a>
                    </div>
                    <div class="wechat-modal modal" role="dialog" aria-modal="true" aria-label="微信二维码">
                        <div class="modal-content">
                            <button type="button" class="modal-close" aria-label="关闭">&times;</button>
                            <h3 class="modal-heading">扫码添加微信</h3>
                            <img src="<?php echo e($wechatQr); ?>" alt="微信二维码" class="modal-qr-img">
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 快捷导航 -->
            <div class="widget">
                <div class="widget-header">
                    <span>快捷导航</span>
                </div>
                <div class="widget-body">
                    <div class="quick-nav-grid">
                        <a href="/archive.php" class="quick-nav-item">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="5" x="2" y="3" rx="1"/><rect width="20" height="5" x="2" y="10" rx="1"/><rect width="20" height="5" x="2" y="17" rx="1"/></svg>
                            <span>归档</span>
                        </a>
                        <a href="/tags.php" class="quick-nav-item">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg>
                            <span>标签</span>
                        </a>
                        <a href="/guestbook.php" class="quick-nav-item">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>
                            <span>留言</span>
                        </a>
                        <a href="/links.php" class="quick-nav-item <?php echo $currentPage === 'links' ? 'active' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                            <span>友链</span>
                        </a>
                        <a href="/about.php" class="quick-nav-item">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                            <span>关于</span>
                        </a>
                        <a href="/?all=1" class="quick-nav-item">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></svg>
                            <span>全部</span>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- 友链 -->
            <div class="widget">
                <div class="widget-header">
                    <span>友链</span>
                    <a href="/links.php" class="widget-action-link">全部 →</a>
                </div>
                <div class="widget-body">
                    <div class="link-list">
                        <?php 
                        $links = getVisibleLinks();
                        if (!empty($links)): 
                            foreach ($links as $link): 
                        ?>
                        <a href="<?php echo e($link['url']); ?>" target="_blank" rel="noopener" class="link-item">
                            <?php if ($link['logo']): ?>
                            <img src="<?php echo e($link['logo']); ?>" alt="<?php echo e($link['name']); ?>头像" class="link-avatar">
                            <?php else: ?>
                            <div class="link-avatar link-avatar--fallback">
                                <?php echo mb_substr($link['name'], 0, 1); ?>
                            </div>
                            <?php endif; ?>
                            <div class="link-info">
                                <div class="link-name"><?php echo e($link['name']); ?></div>
                                <?php if ($link['description']): ?>
                                <div class="link-desc"><?php echo e($link['description']); ?></div>
                                <?php endif; ?>
                            </div>
                        </a>
                        <?php 
                            endforeach;
                        else: 
                        ?>
                        <div class="empty-state empty-state--compact">
                            <div class="empty-state-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                            </div>
                            <div>暂无友链</div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="widget-action">
                        <a href="/link-apply.php" class="btn btn-sm btn-secondary btn-block">申请友链</a>
                    </div>
                </div>
            </div>
            
            <!-- 赞助商 -->
            <div class="widget sponsor-widget">
                <div class="widget-header">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    <span>赞助商</span>
                </div>
                <div class="widget-body">
                    <div class="sponsor-list">
                        <?php 
                        $sponsors = getSponsors();
                        if (!empty($sponsors)): 
                            foreach ($sponsors as $sponsor): 
                        ?>
                        <a href="<?php echo e($sponsor['url'] ?: '#'); ?>" <?php if ($sponsor['url']) echo 'target="_blank" rel="noopener"'; ?> class="sponsor-item" title="<?php echo e($sponsor['detail'] ?: $sponsor['name']); ?>">
                            <?php if ($sponsor['icon']): ?>
                            <img src="<?php echo e($sponsor['icon']); ?>" alt="<?php echo e($sponsor['name']); ?>" class="sponsor-icon" loading="lazy">
                            <?php else: ?>
                            <div class="sponsor-icon sponsor-icon-placeholder">
                                <?php echo mb_substr($sponsor['name'], 0, 1); ?>
                            </div>
                            <?php endif; ?>
                            <div class="sponsor-info">
                                <div class="sponsor-name"><?php echo e($sponsor['name']); ?></div>
                                <?php if ($sponsor['detail']): ?>
                                <div class="sponsor-detail"><?php echo e($sponsor['detail']); ?></div>
                                <?php endif; ?>
                            </div>
                        </a>
                        <?php 
                            endforeach;
                        else: 
                        ?>
                        <div class="empty-state empty-state--compact">
                            <div class="empty-state-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            </div>
                            <div>暂无赞助商</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- 最新文章 -->
            <div class="widget">
                <div class="widget-header">
                    <span>最新文章</span>
                </div>
                <div class="widget-body">
                    <div class="link-list">
                        <?php 
                        $latestArticles = getLatestArticles(5);
                        if (!empty($latestArticles)): 
                            foreach ($latestArticles as $article): 
                        ?>
                        <a href="/article.php?slug=<?php echo e($article['slug']); ?>" class="link-item link-item--compact">
                            <div class="link-info">
                                <div class="link-name"><?php echo e($article['title']); ?></div>
                                <div class="link-desc"><?php echo timeAgo($article['created_at']); ?></div>
                            </div>
                        </a>
                        <?php 
                            endforeach;
                        else: 
                        ?>
                        <div class="empty-text">暂无文章</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- 标签云 -->
            <div class="widget">
                <div class="widget-header">
                    <span>热门标签</span>
                    <a href="/tags.php" class="widget-action-link">全部 →</a>
                </div>
                <div class="widget-body">
                    <div class="tag-cloud">
                        <?php
                        if (!isset($tagArticles)):
                        $tagArticles = db()->fetchAll("SELECT tags FROM lm_article WHERE status = 'published' AND tags != ''");
                        endif;
                        $tagCounts = [];
                        foreach ($tagArticles as $ta) {
                            foreach (explode(',', $ta['tags']) as $tag) {
                                $tag = trim($tag);
                                if ($tag) {
                                    $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
                                }
                            }
                        }
                        arsort($tagCounts);
                        $topTags = array_slice($tagCounts, 0, 15, true);
                        if (!empty($topTags)):
                            foreach ($topTags as $tag => $count):
                        ?>
                        <a href="/?search=<?php echo urlencode($tag); ?>" class="tag tag--sm"><?php echo e($tag); ?></a>
                        <?php
                            endforeach;
                        else:
                        ?>
                        <span class="empty-text empty-text--inline">暂无标签</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 天气 -->
            <?php $weatherCity = getSetting('weather_city', ''); ?>
            <?php if ($weatherCity): ?>
            <div class="widget">
                <div class="widget-header">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="M20 12h2"/><path d="m19.07 4.93-1.41 1.41"/><path d="M15.947 12.65a4 4 0 0 0-5.925-4.128"/><path d="M13 22H7a5 5 0 1 1 4.9-6H13a3 3 0 0 1 0 6Z"/></svg>
                    <span>天气</span>
                </div>
                <div class="widget-body">
                    <div class="weather-widget" data-city="<?php echo e($weatherCity); ?>"></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 音乐播放器 -->
            <?php
            $musicList = '';
            $musicEnabled = getSetting('music_enabled', '0') === '1';
            if ($musicEnabled) {
                $apiUrl = trim(getSetting('music_api_url', 'https://api.uomg.com/api/rand.music'));
                $apiServer = in_array(getSetting('music_api_server', 'netease'), ['netease', 'tencent']) ? getSetting('music_api_server', 'netease') : 'netease';
                $apiType = in_array(getSetting('music_api_type', 'playlist'), ['playlist', 'song']) ? getSetting('music_api_type', 'playlist') : 'playlist';
                $apiId = trim(getSetting('music_api_id', ''));
                $apiKey = trim(getSetting('music_api_key', ''));

                // 优先通过接口获取歌单
                if ($apiUrl !== '' && $apiId !== '' && filter_var($apiUrl, FILTER_VALIDATE_URL) && in_array(parse_url($apiUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
                    $requestUrl = rtrim($apiUrl, '/') . '?server=' . urlencode($apiServer) . '&type=' . urlencode($apiType) . '&id=' . urlencode($apiId);
                    if ($apiKey !== '') {
                        $requestUrl .= '&key=' . urlencode($apiKey);
                    }
                    $context = stream_context_create([
                        'http' => [
                            'method' => 'GET',
                            'timeout' => 10,
                            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                            'header' => "Accept: application/json\r\nReferer: https://music.163.com/\r\nOrigin: https://music.163.com\r\n"
                        ],
                        // 启用 SSL 证书校验，防止中间人攻击（符合项目安全约定）
                        'ssl' => [
                            'verify_peer' => true,
                            'verify_peer_name' => true,
                            'allow_self_signed' => false,
                        ]
                    ]);
                    $response = @file_get_contents($requestUrl, false, $context);
                    if ($response !== false) {
                        // 尝试解析为歌单格式
                        $songs = json_decode($response, true);
                        if (is_array($songs) && !empty($songs)) {
                            // 检查是否是单首歌的 API 响应格式
                            if (isset($songs['data']) && is_array($songs['data'])) {
                                // uomg API 格式
                                $songData = $songs['data'];
                                $playlist[] = [
                                    'title' => $songData['name'] ?? '未知歌曲',
                                    'artist' => $songData['artist'] ?? ($songData['singername'] ?? '未知艺术家'),
                                    'url' => $songData['url'] ?? '',
                                    'cover' => $songData['picurl'] ?? ($songData['pic'] ?? '')
                                ];
                            } else {
                                // 标准歌单格式
                                foreach ($songs as $song) {
                                    $playlist[] = [
                                        'title' => $song['name'] ?? '未知歌曲',
                                        'artist' => $song['artist'] ?? '未知艺术家',
                                        'url' => $song['url'] ?? '',
                                        'cover' => $song['picurl'] ?? ($song['pic'] ?? '')
                                    ];
                                }
                            }
                            $musicList = json_encode($playlist, JSON_UNESCAPED_UNICODE);
                        }
                    }
                }

                // 接口失败或未配置 ID，回退静态列表
                if ($musicList === '') {
                    $staticList = trim(getSetting('music_list', ''));
                    if ($staticList !== '') {
                        $decoded = json_decode($staticList, true);
                        if (is_array($decoded) && !empty($decoded)) {
                            $musicList = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                        }
                    }
                }
            }
            ?>
            <?php if ($musicList !== ''): ?>
            <div class="widget">
                <div class="widget-header">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
                    <span>音乐</span>
                </div>
                <div class="widget-body">
                    <div class="music-player" data-playlist='<?php echo e($musicList); ?>'></div>
                </div>
            </div>
            <?php endif; ?>
        </aside>
    </div>
    
    <!-- 页脚 -->
    <footer class="footer">
        <div class="container footer-inner">
            <div class="footer-brand">
                <div class="footer-links">
                    <?php if (getSetting('github_url')): ?>
                    <a href="<?php echo e(getSetting('github_url')); ?>" target="_blank" rel="noopener">GitHub</a>
                    <?php endif; ?>
                    <?php if (getSetting('bilibili_url')): ?>
                    <a href="<?php echo e(getSetting('bilibili_url')); ?>" target="_blank" rel="noopener">Bilibili</a>
                    <?php endif; ?>
                    <a href="/archive.php">归档</a>
                    <a href="/tags.php">标签</a>
                    <a href="/guestbook.php">留言板</a>
                    <a href="/link-apply.php">友链申请</a>
                </div>
                <div>
                    &copy; <?php echo date('Y'); ?> <?php echo e($siteName); ?>. All rights reserved.
                    <?php if (getSetting('site_icp')): ?>
                    <br><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener"><?php echo e(getSetting('site_icp')); ?></a>
                    <?php endif; ?>
                </div>
                <div class="footer-meta">
                    已有 <span class="visitor-count"><?php echo getVisitorCount(); ?></span> 个人访问此站 | 已安全运行 <?php echo getRunningDays(); ?> 天
                </div>
            </div>
        </div>
    </footer>
    
    <script src="/assets/js/main.js?v=<?php echo LM_VERSION; ?>"></script>
    <script src="/assets/js/ui-enhancements.js?v=<?php echo LM_VERSION; ?>"></script>

    <?php
    // 按需加载：仅在对应容器存在时加载脚本，减少不必要的下载
    // particles.js: 首页 / 留言板 等装饰性 canvas 页面
    // weather-widget.js: 侧栏天气组件
    // music-player.js: 侧栏音乐播放器
    // Prism.js: 文章详情页（含代码块）
    // hitokoto.js: 一言组件（侧栏底部）
    $needParticles = !empty($bodyClass) && in_array($currentPage, ['home', 'guestbook', 'donate', 'about'], true);
    $needWeather   = true;   // sidebar 始终渲染天气容器
    $needMusic     = true;   // sidebar 始终渲染音乐容器
    $needPrism     = in_array($currentPage, ['article', 'about'], true);
    $needHitokoto  = true;
    ?>

    <?php if ($needParticles): ?>
    <script src="/assets/js/particles.js?v=<?php echo LM_VERSION; ?>"></script>
    <?php endif; ?>

    <?php if ($needWeather): ?>
    <script src="/assets/js/weather-widget.js?v=<?php echo LM_VERSION; ?>"></script>
    <?php endif; ?>

    <?php if ($needMusic): ?>
    <script src="/assets/js/music-player.js?v=<?php echo LM_VERSION; ?>"></script>
    <?php endif; ?>

    <?php if ($needPrism): ?>
    <!-- Prism.js 代码高亮（仅在文章详情页加载） -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism-tomorrow.min.css">
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/prism.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
    <?php endif; ?>

    <?php if ($needHitokoto): ?>
    <!-- 一言组件 -->
    <script src="/assets/js/hitokoto.js?v=<?php echo LM_VERSION; ?>"></script>
    <?php endif; ?>

    <?php if (!empty($extraJs)): ?>
        <?php foreach ($extraJs as $js): ?>
        <script src="<?php echo e($js); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
