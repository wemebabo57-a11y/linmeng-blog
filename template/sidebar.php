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
                    <img src="<?php echo e($avatar ?: '/assets/images/default-avatar.png'); ?>" alt="林梦的头像" class="profile-avatar" width="88" height="88">
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
                        <button type="button" class="wechat-btn btn btn-sm btn-secondary btn-block" aria-haspopup="dialog" aria-controls="wechat-modal">微信二维码</button>
                    </div>
                    <div class="wechat-modal modal" id="wechat-modal" role="dialog" aria-modal="true" aria-label="微信二维码" aria-hidden="true">
                        <div class="modal-content">
                            <button type="button" class="modal-close" aria-label="关闭">&times;</button>
                            <h3 class="modal-heading">扫码添加微信</h3>
                            <img src="<?php echo e($wechatQr); ?>" alt="微信二维码" class="modal-qr-img">
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($currentPage !== 'home'): ?>
            <!-- 快捷导航 -->
            <div class="widget">
                <div class="widget-header">
                    <span>快捷导航</span>
                </div>
                <div class="widget-body">
                    <div class="quick-nav-grid">
                        <?php
                        $isAllArticles = $currentPage === 'home' && isset($_GET['all']) && (string)$_GET['all'] === '1';
                        $quickNavItems = [
                            ['href' => '/archive.php', 'page' => 'archive', 'icon' => '<rect width="20" height="5" x="2" y="3" rx="1"/><rect width="20" height="5" x="2" y="10" rx="1"/><rect width="20" height="5" x="2" y="17" rx="1"/>', 'label' => '归档'],
                            ['href' => '/tags.php', 'page' => 'tags', 'icon' => '<path d="M12 2H2v10l9.29 9.29c.94.94 2.48 1.94 3.42 0l6.58-6.58c.94-.94 0-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/>', 'label' => '标签'],
                            ['href' => '/guestbook.php', 'page' => 'guestbook', 'icon' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>', 'label' => '留言'],
                            ['href' => '/links.php', 'page' => 'links', 'icon' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>', 'label' => '友链'],
                            ['href' => '/about.php', 'page' => 'about', 'icon' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>', 'label' => '关于'],
                            ['href' => '/?all=1', 'page' => 'all', 'icon' => '<path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/>', 'label' => '全部']
                        ];
                        foreach ($quickNavItems as $item):
                            $isCurrent = $item['page'] === 'all' ? $isAllArticles : $currentPage === $item['page'];
                        ?>
                        <a href="<?php echo e($item['href']); ?>" class="quick-nav-item<?php echo $isCurrent ? ' active' : ''; ?>"<?php echo $isCurrent ? ' aria-current="page"' : ''; ?>>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $item['icon']; ?></svg>
                            <span><?php echo e($item['label']); ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
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
                        <?php if (!empty($link['logo'])): ?>
                            <img src="<?php echo e($link['logo']); ?>" alt="<?php echo e($link['name']); ?>头像" class="link-avatar" loading="lazy" decoding="async" width="36" height="36">
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
                        <?php $sponsorUrl = trim((string)($sponsor['url'] ?? '')); ?>
                        <?php if ($sponsorUrl): ?>
                        <a href="<?php echo e($sponsorUrl); ?>" target="_blank" rel="noopener noreferrer" class="sponsor-item" title="<?php echo e($sponsor['detail'] ?: $sponsor['name']); ?>">
                        <?php else: ?>
                        <div class="sponsor-item" title="<?php echo e($sponsor['detail'] ?: $sponsor['name']); ?>">
                        <?php endif; ?>
                            <?php if ($sponsor['icon']): ?>
                            <img src="<?php echo e($sponsor['icon']); ?>" alt="<?php echo e($sponsor['name']); ?>" class="sponsor-icon" loading="lazy" decoding="async" width="40" height="40">
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
                        <?php if ($sponsorUrl): ?>
                        </a>
                        <?php else: ?>
                        </div>
                        <?php endif; ?>
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
            
            <!-- 创作日历 -->
            <?php
            $calendarDays = getArticleCalendarDays();
            $calendarToday = date('Y-m-d', siteTime());
            $calendarMonth = date('Y-m', siteTime());
            // 最早发文月份，用于限制往前翻的边界
            $calendarMinMonth = $calendarMonth;
            if (!empty($calendarDays)) {
                $calendarMinMonth = substr(min(array_keys($calendarDays)), 0, 7);
            }
            // 本月网格：周一起始，补齐前后空格
            $calendarFirst = strtotime($calendarMonth . '-01');
            $calendarDaysInMonth = (int)date('t', $calendarFirst);
            $calendarLead = ((int)date('N', $calendarFirst)) - 1;
            $calendarCells = $calendarLead + $calendarDaysInMonth;
            $calendarCells = (int)(ceil($calendarCells / 7) * 7);
            ?>
            <div class="widget">
                <div class="widget-header">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>
                    <span>创作日历</span>
                </div>
                <div class="widget-body">
                    <div class="creation-calendar" data-days="<?php echo e(json_encode($calendarDays, JSON_UNESCAPED_UNICODE)); ?>" data-month="<?php echo e($calendarMonth); ?>" data-min-month="<?php echo e($calendarMinMonth); ?>" data-today="<?php echo e($calendarToday); ?>">
                        <div class="calendar-nav">
                            <button type="button" class="calendar-nav-btn" data-calendar-prev aria-label="上一个月"<?php echo $calendarMonth <= $calendarMinMonth ? ' disabled' : ''; ?>>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                            </button>
                            <div class="calendar-title" data-calendar-title aria-live="polite"><?php echo date('Y年n月', $calendarFirst); ?></div>
                            <button type="button" class="calendar-nav-btn" data-calendar-next aria-label="下一个月" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                            </button>
                        </div>
                        <div class="calendar-weekdays" aria-hidden="true">
                            <?php foreach (['一', '二', '三', '四', '五', '六', '日'] as $calendarWeekday): ?>
                            <span><?php echo $calendarWeekday; ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="calendar-grid" data-calendar-grid>
                            <?php for ($calendarIndex = 0; $calendarIndex < $calendarCells; $calendarIndex++): ?>
                                <?php
                                $calendarDayNum = $calendarIndex - $calendarLead + 1;
                                if ($calendarDayNum < 1 || $calendarDayNum > $calendarDaysInMonth) {
                                    echo '<span class="calendar-day is-empty"></span>';
                                    continue;
                                }
                                $calendarDate = sprintf('%s-%02d', $calendarMonth, $calendarDayNum);
                                $calendarCount = $calendarDays[$calendarDate] ?? 0;
                                $calendarClass = 'calendar-day';
                                if ($calendarCount > 0) { $calendarClass .= ' has-post'; }
                                if ($calendarDate === $calendarToday) { $calendarClass .= ' is-today'; }
                                ?>
                                <?php if ($calendarCount > 0): ?>
                                <time class="<?php echo $calendarClass; ?>" datetime="<?php echo e($calendarDate); ?>" title="<?php echo $calendarCount; ?> 篇"><?php echo $calendarDayNum; ?></time>
                                <?php else: ?>
                                <time class="<?php echo $calendarClass; ?>" datetime="<?php echo e($calendarDate); ?>"><?php echo $calendarDayNum; ?></time>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
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
        </aside>
    </div>
    
    <!-- 页脚 -->
    <footer class="footer">
        <div class="container footer-inner">
            <!-- 第一列：品牌 + 描述 -->
            <div class="footer-brand-col">
                <div class="footer-brand-title"><?php echo e($siteName); ?></div>
                <div class="footer-brand-desc"><?php echo e(getSetting('site_description', '记录生活，分享技术')); ?></div>
            </div>

            <!-- 第二列：快捷导航 -->
            <div class="footer-nav-col">
                <div class="footer-nav-title">快捷导航</div>
                <div class="footer-nav-list">
                    <a href="/archive.php">归档</a>
                    <a href="/tags.php">标签</a>
                    <a href="/guestbook.php">留言板</a>
                    <a href="/donate.php">赞助</a>
                    <a href="/links.php">友链</a>
                    <a href="/about.php">关于</a>
                    <a href="/link-apply.php">友链申请</a>
                </div>
            </div>

            <!-- 第三列：社交 + 备案 -->
            <div class="footer-meta-col">
                <div class="footer-nav-title">关注与联系</div>
                <div class="footer-social-row">
                    <?php if (getSetting('github_url')): ?>
                    <a href="<?php echo e(getSetting('github_url')); ?>" target="_blank" rel="noopener noreferrer" title="GitHub" aria-label="GitHub">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/></svg>
                    </a>
                    <?php endif; ?>
                    <?php if (getSetting('bilibili_url')): ?>
                    <a href="<?php echo e(getSetting('bilibili_url')); ?>" target="_blank" rel="noopener noreferrer" title="Bilibili" aria-label="Bilibili">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.813 4.653h.854c1.51.054 2.769.578 3.773 1.574 1.004.995 1.524 2.249 1.56 3.76v7.36c-.036 1.51-.556 2.769-1.56 3.773s-2.262 1.524-3.773 1.56H5.333c-1.51-.036-2.769-.556-3.773-1.56S.036 18.858 0 17.347v-7.36c.036-1.511.556-2.765 1.56-3.76 1.004-.996 2.262-1.52 3.773-1.574h.774l-1.174-1.12a1.234 1.234 0 0 1-.373-.906c0-.356.124-.658.373-.907l.027-.027c.267-.249.573-.373.92-.373.347 0 .653.124.92.373L9.653 4.44c.071.071.134.142.187.213h4.267a.836.836 0 0 1 .16-.213l2.853-2.747c.267-.249.573-.373.92-.373.347 0 .662.124.929.373.25.249.383.551.4.907 0 .355-.124.657-.373.906zM5.333 7.24c-.746.018-1.373.276-1.88.773-.506.498-.769 1.13-.786-1.894v7.52c.017.764.28 1.395.786 1.893.507.498 1.134.756 1.88.773h13.334c.746-.017 1.373-.275 1.88-.773.506-.498.769-1.129.786-1.893v-7.52c-.017-.765-.28-1.396-.786-1.894-.507-.497-1.134-.755-1.88-.773zM8 11.107c.373 0 .684.124.933.373.25.249.383.569.4.96v1.173c-.017.391-.15.711-.4.96-.249.25-.56.374-.933.374s-.684-.125-.933-.374c-.25-.249-.383-.569-.4-.96V12.44c.017-.391.15-.711.4-.96.249-.249.56-.373.933-.373zm8 0c.373 0 .684.124.933.373.25.249.383.569.4.96v1.173c-.017.391-.15.711-.4.96-.249.25-.56.374-.933.374s-.684-.125-.933-.374c-.25-.249-.383-.569-.4-.96V12.44c.017-.391.15-.711.4-.96.249-.249.56-.373.933-.373z"/></svg>
                    </a>
                    <?php endif; ?>
                    <?php if (getSetting('telegram_url')): ?>
                    <a href="<?php echo e(getSetting('telegram_url')); ?>" target="_blank" rel="noopener noreferrer" title="Telegram" aria-label="Telegram">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.94 4.3a1.5 1.5 0 0 0-1.6-.24L3.4 11.2c-1.1.46-1.06 2.03.06 2.43l3.9 1.4 1.5 4.7c.33 1.05 1.7 1.3 2.38.43l2.02-2.6 3.9 2.87c.86.63 2.08.16 2.29-.88l2.7-13.5a1.5 1.5 0 0 0-.21-1.15ZM9.3 14.13l-.02.02-.62 3.06-1.06-3.3 8.53-5.6-6.83 5.82Z"/></svg>
                    </a>
                    <?php endif; ?>
                    <?php if (getSetting('contact_email')): ?>
                    <a href="mailto:<?php echo e(getSetting('contact_email')); ?>" title="邮箱" aria-label="邮箱">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                    </a>
                    <?php endif; ?>
                    <a href="/rss.php" title="RSS 订阅" aria-label="RSS 订阅">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 11a9 9 0 0 1 9 9"/><path d="M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1" fill="currentColor" stroke="none"/></svg>
                    </a>
                </div>
                <div class="footer-contact-list">
                    <?php if (getSetting('telegram_url')): ?>
                    <a href="<?php echo e(getSetting('telegram_url')); ?>" target="_blank" rel="noopener noreferrer">Telegram</a>
                    <?php endif; ?>
                    <?php if (getSetting('contact_email')): ?>
                    <a href="mailto:<?php echo e(getSetting('contact_email')); ?>">邮箱：<?php echo e(getSetting('contact_email')); ?></a>
                    <?php endif; ?>
                </div>
                <div class="footer-icp">
                    <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener"><?php echo e(getSetting('site_icp', '')); ?></a>
                </div>
            </div>

            <!-- 底部栏：版权 + 统计 -->
            <div class="footer-bottom-bar">
                &copy; <?php echo date('Y'); ?> <?php echo e($siteName); ?>. All rights reserved.
                <div class="footer-meta">
                    已有 <span class="visitor-count"><?php echo getVisitorCount(); ?></span> 个人访问此站 | 已安全运行 <?php echo getRunningDays(); ?> 天
                </div>
            </div>
        </div>
    </footer>
    
    <script defer src="/assets/js/main.js?v=<?php echo LM_VERSION; ?>"></script>
    <script defer src="/assets/js/ui-enhancements.js?v=<?php echo LM_VERSION; ?>"></script>

    <?php
    // 按需加载：仅在对应容器存在时加载脚本，减少不必要的下载
    // particles.js: 首页 / 留言板 等装饰性 canvas 页面
    // weather-widget.js: 侧栏天气组件
    // Prism.js: 文章详情页（含代码块）
    // hitokoto.js: 一言组件（侧栏底部）
    // creation-calendar.js: 侧栏创作日历翻月交互
    $needParticles = false; // CSS 装饰已足够，移除持续 canvas 动画以降低 CPU/GPU 占用。
    $needWeather   = !empty($weatherCity);
    $needCalendar  = true;
    $needPrism     = !empty($isArticlePage) || $currentPage === 'about';
    $needHitokoto  = $currentPage === 'home' || !empty($isArticlePage);
    ?>

    <?php if ($needParticles): ?>
    <script defer src="/assets/js/particles.js?v=<?php echo LM_VERSION; ?>"></script>
    <?php endif; ?>

    <?php if ($needWeather): ?>
    <script defer src="/assets/js/weather-widget.js?v=<?php echo LM_VERSION; ?>"></script>
    <?php endif; ?>

    <?php if ($needCalendar): ?>
    <script defer src="/assets/js/creation-calendar.js?v=<?php echo LM_VERSION; ?>"></script>
    <?php endif; ?>

    <?php if ($needPrism): ?>
    <!-- Prism.js 代码高亮（仅在文章详情页加载） -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism-tomorrow.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/prism.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
    <?php endif; ?>

    <?php if ($needHitokoto): ?>
    <!-- 一言组件 -->
    <script defer src="/assets/js/hitokoto.js?v=<?php echo LM_VERSION; ?>"></script>
    <?php endif; ?>

    <?php if (!empty($extraJs)): ?>
        <?php foreach ($extraJs as $js): ?>
        <script defer src="<?php echo e($js); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
