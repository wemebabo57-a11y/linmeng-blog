/**
 * 林梦博客 UI 增强 v3.2
 * 包含：智能头部、全局搜索、移动端抽屉、返回顶部、阅读进度、
 *       文章收藏/分享、代码语言标签、图片骨架屏、Toast、键盘快捷键等
 */
(function () {
    'use strict';

    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ==================== DOM 就绪后统一初始化 ====================
    document.addEventListener('DOMContentLoaded', function () {
        initSmartHeader();
        initBackToTop();
        initSearchOverlay();
        initMobileDrawer();
        initToastContainer();
        initArticleEnhancements();
        initCodeLangLabels();
        initImageSkeleton();
        initKeyboardShortcuts();
        initPageTransition();
    });

    // ==================== Toast 提示系统 ====================
    function initToastContainer() {
        if (document.getElementById('toast-container')) return;
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    window.showToast = function (message, type) {
        type = type || 'info';
        const container = document.getElementById('toast-container');
        if (!container) initToastContainer();

        const icons = {
            success: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
            error: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" x2="9" y1="9" y2="15"/><line x1="9" x2="15" y1="9" y2="15"/></svg>',
            info: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>'
        };

        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.innerHTML = (icons[type] || icons.info) + '<span>' + escapeHtml(message) + '</span>';
        document.getElementById('toast-container').appendChild(toast);

        requestAnimationFrame(function () {
            toast.classList.add('show');
        });

        setTimeout(function () {
            toast.classList.remove('show');
            setTimeout(function () {
                toast.remove();
            }, 350);
        }, 3000);
    };

    // ==================== 智能头部（滚动隐藏/显示） ====================
    function initSmartHeader() {
        const header = document.getElementById('main-header');
        if (!header) return;

        let lastScrollY = 0;
        let ticking = false;

        function updateHeader() {
            const currentY = window.scrollY || window.pageYOffset;
            if (currentY > 10) {
                header.classList.add('is-scrolled');
            } else {
                header.classList.remove('is-scrolled');
            }

            if (currentY > 100 && currentY > lastScrollY) {
                header.classList.add('is-hidden');
            } else {
                header.classList.remove('is-hidden');
            }
            lastScrollY = currentY;
            ticking = false;
        }

        window.addEventListener('scroll', function () {
            if (!ticking) {
                requestAnimationFrame(updateHeader);
                ticking = true;
            }
        }, { passive: true });
    }

    // ==================== 返回顶部 + 阅读进度环 ====================
    function initBackToTop() {
        const btn = document.getElementById('back-to-top');
        const progressBar = document.getElementById('reading-progress');
        if (!btn) return;

        const ring = btn.querySelector('.back-to-top-ring circle');
        const circumference = 125.6;

        let ticking = false;

        function update() {
            const scrollTop = window.scrollY || window.pageYOffset;
            const docHeight = document.documentElement.scrollHeight - window.innerHeight;
            const progress = docHeight > 0 ? scrollTop / docHeight : 0;
            const percent = Math.min(Math.max(progress, 0), 1);

            if (progressBar) {
                progressBar.style.width = (percent * 100) + '%';
            }

            if (ring) {
                ring.style.strokeDashoffset = circumference * (1 - percent);
            }

            if (scrollTop > 300) {
                btn.classList.add('visible');
            } else {
                btn.classList.remove('visible');
            }
            ticking = false;
        }

        window.addEventListener('scroll', function () {
            if (!ticking) {
                requestAnimationFrame(update);
                ticking = true;
            }
        }, { passive: true });

        btn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: prefersReducedMotion ? 'auto' : 'smooth' });
        });

        update();
    }

    // ==================== 全局搜索浮层 ====================
    function initSearchOverlay() {
        const overlay = document.getElementById('search-overlay');
        const input = document.getElementById('global-search-input');
        const trigger = document.getElementById('search-trigger');
        const extraTriggers = document.querySelectorAll('[data-open-search]');
        const closeBtn = document.getElementById('search-overlay-close');
        const resultsContainer = document.getElementById('search-results');
        if (!overlay || !input) return;

        const recentKey = 'lm_recent_searches';
        let selectedIndex = -1;
        let resultItems = [];

        function open() {
            overlay.classList.add('active');
            overlay.setAttribute('aria-hidden', 'false');
            input.value = '';
            input.focus();
            selectedIndex = -1;
            renderHint();
            document.body.style.overflow = 'hidden';
        }

        function close() {
            overlay.classList.remove('active');
            overlay.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        function renderHint() {
            if (!resultsContainer) return;
            resultItems = [];
            selectedIndex = -1;
            const recent = getRecentSearches();
            if (!recent.length) {
                resultsContainer.innerHTML = '';
                return;
            }
            resultsContainer.innerHTML = '<div class="search-hint">最近搜索</div>';
            recent.forEach(function (keyword) {
                const el = document.createElement('button');
                el.type = 'button';
                el.className = 'search-recent-item';
                el.textContent = keyword;
                el.addEventListener('click', function () {
                    input.value = keyword;
                    renderResults(keyword);
                    input.focus();
                });
                resultsContainer.appendChild(el);
            });
        }

        function getRecentSearches() {
            try {
                return JSON.parse(localStorage.getItem(recentKey) || '[]').filter(Boolean).slice(0, 5);
            } catch (e) {
                return [];
            }
        }

        function saveRecentSearch(keyword) {
            keyword = keyword.trim();
            if (!keyword) return;
            const recent = getRecentSearches().filter(function (item) {
                return item !== keyword;
            });
            recent.unshift(keyword);
            localStorage.setItem(recentKey, JSON.stringify(recent.slice(0, 5)));
        }

        function goSearch(keyword) {
            keyword = keyword.trim();
            if (!keyword) {
                renderHint();
                return;
            }
            saveRecentSearch(keyword);
            window.location.href = '/?search=' + encodeURIComponent(keyword);
        }

        function renderResults(query) {
            if (!resultsContainer) return;
            resultsContainer.innerHTML = '';
            selectedIndex = -1;
            resultItems = [];

            if (!query.trim()) {
                renderHint();
                return;
            }

            // 从当前页面文章列表中提取匹配项
            const articles = Array.from(document.querySelectorAll('.article-item')).map(function (item) {
                const titleEl = item.querySelector('.article-title a');
                const metaEl = item.querySelector('.article-meta');
                const excerptEl = item.querySelector('.article-excerpt');
                return {
                    title: titleEl ? titleEl.textContent.trim() : '',
                    href: titleEl ? titleEl.getAttribute('href') : '#',
                    meta: metaEl ? metaEl.textContent.trim() : '',
                    excerpt: excerptEl ? excerptEl.textContent.trim() : ''
                };
            });

            const q = query.toLowerCase();
            const matched = articles.filter(function (a) {
                return a.title.toLowerCase().includes(q) ||
                    a.excerpt.toLowerCase().includes(q) ||
                    a.meta.toLowerCase().includes(q);
            }).slice(0, 6);

            if (matched.length === 0) {
                resultsContainer.innerHTML = '<div class="search-hint">当前页面无匹配，按 Enter 前往全站搜索</div>';
                return;
            }

            matched.forEach(function (item, index) {
                const el = document.createElement('a');
                el.className = 'search-result-item';
                el.href = item.href;
                el.innerHTML = '<div class="search-result-title">' + escapeHtml(item.title) + '</div>' +
                    '<div class="search-result-meta">' + escapeHtml(item.meta) + '</div>';
                el.addEventListener('click', function () {
                    close();
                });
                resultsContainer.appendChild(el);
                resultItems.push(el);
            });
        }

        function updateSelection() {
            resultItems.forEach(function (el, i) {
                el.classList.toggle('is-selected', i === selectedIndex);
            });
        }

        input.addEventListener('input', function () {
            renderResults(this.value);
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, resultItems.length - 1);
                updateSelection();
                if (resultItems[selectedIndex]) resultItems[selectedIndex].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, -1);
                updateSelection();
                if (resultItems[selectedIndex]) resultItems[selectedIndex].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (selectedIndex >= 0 && resultItems[selectedIndex]) {
                    window.location.href = resultItems[selectedIndex].getAttribute('href');
                    close();
                } else if (input.value.trim()) {
                    goSearch(input.value);
                    close();
                } else {
                    renderHint();
                }
            } else if (e.key === 'Escape') {
                close();
            }
        });

        if (trigger) trigger.addEventListener('click', open);
        extraTriggers.forEach(function (button) {
            button.addEventListener('click', open);
        });
        if (closeBtn) closeBtn.addEventListener('click', close);
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay || e.target.classList.contains('search-overlay-backdrop')) {
                close();
            }
        });
    }

    // ==================== 移动端侧滑菜单 ====================
    function initMobileDrawer() {
        const btn = document.getElementById('mobile-menu-btn');
        const drawer = document.getElementById('mobile-drawer');
        const overlay = document.getElementById('mobile-drawer-overlay');
        const closeBtn = document.getElementById('mobile-drawer-close');
        if (!btn || !drawer) return;

        function open() {
            drawer.classList.add('active');
            drawer.setAttribute('aria-hidden', 'false');
            btn.setAttribute('aria-expanded', 'true');
            if (overlay) {
                overlay.classList.add('active');
            }
            document.body.style.overflow = 'hidden';
        }

        function close() {
            drawer.classList.remove('active');
            drawer.setAttribute('aria-hidden', 'true');
            btn.setAttribute('aria-expanded', 'false');
            if (overlay) {
                overlay.classList.remove('active');
            }
            document.body.style.overflow = '';
        }

        btn.addEventListener('click', open);
        if (closeBtn) closeBtn.addEventListener('click', close);
        if (overlay) overlay.addEventListener('click', close);

        drawer.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', close);
        });
    }

    // ==================== 文章页增强 ====================
    function initArticleEnhancements() {
        const articleContent = document.querySelector('.article-content');
        const articleCard = document.querySelector('.article-detail-card') || document.querySelector('.article-page-card');
        if (!articleContent) return;

        // 计算阅读时间
        const text = articleContent.textContent || '';
        const wordCount = text.replace(/\s/g, '').length;
        const minutes = Math.max(1, Math.ceil(wordCount / 400));

        const readingTimeContainer = document.getElementById('reading-time');
        if (readingTimeContainer) {
            readingTimeContainer.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> ' +
                minutes + ' 分钟阅读';
        }

        // 初始化目录高亮
        initArticleTocHighlight();

        // 初始化收藏按钮
        initBookmarkButton();

        // 初始化分享弹窗
        initShareModal();

        // 初始化浮动操作按钮
        initArticleFAB();
    }

    function initArticleTocHighlight() {
        const content = document.querySelector('.article-content');
        const tocList = document.querySelector('.toc-list');
        if (!content || !tocList) return;

        const headings = Array.from(content.querySelectorAll('h2, h3, h4'));
        const tocLinks = Array.from(tocList.querySelectorAll('a'));
        if (!headings.length || !tocLinks.length) return;

        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    tocLinks.forEach(function (link) { link.classList.remove('active'); });
                    const activeLink = tocList.querySelector('a[href="#' + entry.target.id + '"]');
                    if (activeLink) activeLink.classList.add('active');
                }
            });
        }, { rootMargin: '-80px 0px -70% 0px' });

        headings.forEach(function (h) {
            if (h.id) observer.observe(h);
        });
    }

    function initBookmarkButton() {
        const btn = document.getElementById('article-bookmark-btn');
        if (!btn) return;

        const key = 'lm_bookmarks';
        const url = window.location.pathname + window.location.search;
        const title = document.title;

        function getBookmarks() {
            try {
                return JSON.parse(localStorage.getItem(key) || '[]');
            } catch (e) {
                return [];
            }
        }

        function isBookmarked() {
            return getBookmarks().some(function (b) { return b.url === url; });
        }

        function updateUI() {
            if (isBookmarked()) {
                btn.classList.add('active');
                btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg> 已收藏';
            } else {
                btn.classList.remove('active');
                btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg> 收藏文章';
            }
        }

        btn.addEventListener('click', function () {
            let bookmarks = getBookmarks();
            if (isBookmarked()) {
                bookmarks = bookmarks.filter(function (b) { return b.url !== url; });
                localStorage.setItem(key, JSON.stringify(bookmarks));
                window.showToast('已取消收藏', 'info');
            } else {
                bookmarks.unshift({ url: url, title: title, time: Date.now() });
                localStorage.setItem(key, JSON.stringify(bookmarks.slice(0, 100)));
                window.showToast('文章已收藏', 'success');
            }
            updateUI();
        });

        updateUI();
    }

    function initShareModal() {
        const modal = document.getElementById('share-modal');
        const trigger = document.getElementById('article-share-btn');
        if (!modal) return;

        const closeBtn = modal.querySelector('.share-modal-close');
        const backdrop = modal.querySelector('.share-modal-backdrop');
        const copyBtn = modal.querySelector('.share-copy-btn');
        const linkInput = modal.querySelector('.share-link-input');
        const url = window.location.href;

        function open() {
            modal.classList.add('active');
            if (linkInput) linkInput.value = url;
        }

        function close() {
            modal.classList.remove('active');
        }

        if (trigger) trigger.addEventListener('click', open);
        if (closeBtn) closeBtn.addEventListener('click', close);
        if (backdrop) backdrop.addEventListener('click', close);

        modal.querySelectorAll('.share-item[data-share]').forEach(function (item) {
            item.addEventListener('click', function () {
                const type = this.dataset.share;
                const text = document.title;
                let shareUrl = '';
                if (type === 'twitter') {
                    shareUrl = 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(text) + '&url=' + encodeURIComponent(url);
                } else if (type === 'facebook') {
                    shareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
                } else if (type === 'weibo') {
                    shareUrl = 'https://service.weibo.com/share/share.php?title=' + encodeURIComponent(text) + '&url=' + encodeURIComponent(url);
                } else if (type === 'copy') {
                    copyToClipboard(url);
                    return;
                }
                if (shareUrl) window.open(shareUrl, '_blank', 'width=600,height=400');
            });
        });

        if (copyBtn && linkInput) {
            copyBtn.addEventListener('click', function () {
                copyToClipboard(linkInput.value);
            });
        }
    }

    function initArticleFAB() {
        const content = document.querySelector('.article-content');
        if (!content) return;

        const fab = document.createElement('div');
        fab.className = 'article-fab';
        fab.innerHTML =
            '<button class="article-fab-btn" id="fab-toc" title="目录"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M3 12h18"/><path d="M3 18h18"/></svg></button>' +
            '<button class="article-fab-btn" id="fab-comment" title="评论"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg></button>';
        document.body.appendChild(fab);

        document.getElementById('fab-toc').addEventListener('click', function () {
            const toc = document.querySelector('.toc-container');
            if (toc) {
                toc.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'center' });
            } else {
                window.showToast('本页暂无目录', 'info');
            }
        });

        document.getElementById('fab-comment').addEventListener('click', function () {
            const comments = document.getElementById('comments') || document.querySelector('.comment-section') || document.querySelector('.comment-list');
            if (comments) {
                comments.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'start' });
            } else {
                window.showToast('本页暂无评论', 'info');
            }
        });
    }

    // ==================== 代码语言标签 ====================
    function initCodeLangLabels() {
        document.querySelectorAll('pre code').forEach(function (code) {
            const pre = code.parentElement;
            if (!pre || pre.querySelector('.code-lang-label')) return;

            let lang = '';
            const classes = Array.from(code.classList);
            classes.forEach(function (c) {
                if (c.indexOf('language-') === 0) {
                    lang = c.replace('language-', '');
                } else if (c.indexOf('lang-') === 0) {
                    lang = c.replace('lang-', '');
                }
            });

            if (lang) {
                const label = document.createElement('span');
                label.className = 'code-lang-label';
                label.textContent = lang.toUpperCase();
                pre.appendChild(label);
            }
        });
    }

    // ==================== 图片骨架屏懒加载 ====================
    function initImageSkeleton() {
        document.querySelectorAll('img[loading="lazy"]').forEach(function (img) {
            if (img.complete || !img.src) return;
            img.classList.add('img-skeleton');
            img.addEventListener('load', function () {
                img.classList.remove('img-skeleton');
            });
            img.addEventListener('error', function () {
                img.classList.remove('img-skeleton');
            });
        });
    }

    // ==================== 键盘快捷键 ====================
    function initKeyboardShortcuts() {
        document.addEventListener('keydown', function (e) {
            const tag = document.activeElement.tagName.toLowerCase();
            const isTyping = tag === 'input' || tag === 'textarea' || tag === 'select' || document.activeElement.isContentEditable;

            if (e.key === '/' && !isTyping) {
                e.preventDefault();
                const trigger = document.getElementById('search-trigger');
                if (trigger) trigger.click();
            }

            if (e.key === 'Escape') {
                const searchOverlay = document.getElementById('search-overlay');
                const shareModal = document.getElementById('share-modal');
                const drawer = document.getElementById('mobile-drawer');
                const lightbox = document.querySelector('.lightbox');

                if (searchOverlay && searchOverlay.classList.contains('active')) {
                    searchOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                } else if (shareModal && shareModal.classList.contains('active')) {
                    shareModal.classList.remove('active');
                } else if (drawer && drawer.classList.contains('active')) {
                    drawer.classList.remove('active');
                    const overlay = document.getElementById('mobile-drawer-overlay');
                    if (overlay) overlay.classList.remove('active');
                    document.body.style.overflow = '';
                } else if (lightbox && lightbox.classList.contains('active')) {
                    lightbox.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }
        });
    }

    // ==================== 页面过渡动画 ====================
    function initPageTransition() {
        document.body.classList.add('page-transition');
    }

    // ==================== 工具函数 ====================
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function () {
                window.showToast('链接已复制', 'success');
            }).catch(function () {
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    }

    function fallbackCopy(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            window.showToast('链接已复制', 'success');
        } catch (err) {
            window.showToast('复制失败', 'error');
        }
        document.body.removeChild(textarea);
    }

    window.lmCopyToClipboard = copyToClipboard;

    // ==================== 表单按钮加载态 ====================
    // 仅在表单通过 HTML5 校验且确实要提交时才进入加载态，
    // 避免 main.js 的 data-validate 在 preventDefault 后按钮被永久禁用。
    // 同时提供 pageshow (bfcache) 与安全超时还原机制。
    function restoreSubmitBtn(submitBtn) {
        if (!submitBtn) return;
        submitBtn.classList.remove('is-loading');
        submitBtn.disabled = false;
        if (submitBtn.dataset.originalText !== undefined) {
            submitBtn.innerHTML = submitBtn.dataset.originalText;
            delete submitBtn.dataset.originalText;
        }
    }

    document.querySelectorAll('form:not([data-no-loading])').forEach(function (form) {
        form.addEventListener('submit', function () {
            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (!submitBtn || submitBtn.disabled) return;

            // 保存原始内容，便于后续还原
            if (submitBtn.dataset.originalText === undefined) {
                submitBtn.dataset.originalText = submitBtn.innerHTML;
            }

            // 表单未通过 HTML5 校验则不进入加载态，避免 preventDefault 后按钮卡死
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return;
            }

            submitBtn.classList.add('is-loading');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="visually-hidden">加载中</span>';

            // 安全超时：若为客户端提交（fetch/AJAX）未导航离开，几秒后自动还原
            const safetyTimer = setTimeout(function () {
                restoreSubmitBtn(submitBtn);
            }, 8000);

            // bfcache 还原：从历史返回时恢复按钮
            window.addEventListener('pageshow', function () {
                clearTimeout(safetyTimer);
                restoreSubmitBtn(submitBtn);
            }, { once: true });
        });
    });

    // ==================== 文本域自适应高度 ====================
    document.querySelectorAll('textarea[data-autoresize]').forEach(function (textarea) {
        function resize() {
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
        }
        textarea.addEventListener('input', resize);
        resize();
    });

    // ==================== 窗口尺寸变化时关闭移动端抽屉 ====================
    window.addEventListener('resize', function () {
        if (window.innerWidth > 900) {
            const drawer = document.getElementById('mobile-drawer');
            const overlay = document.getElementById('mobile-drawer-overlay');
            if (drawer && drawer.classList.contains('active')) {
                drawer.classList.remove('active');
                drawer.setAttribute('aria-hidden', 'true');
                if (overlay) overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        }
    });

    // ==================== 滚动触发动画（reveal） ====================
    // 标记 JS 启用，CSS 据此决定是否隐藏待揭示元素
    document.documentElement.classList.add('js');

    const revealElements = document.querySelectorAll('.article-item, .reveal-item');
    if ('IntersectionObserver' in window && !prefersReducedMotion) {
        const revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

        revealElements.forEach(function (el, index) {
            el.style.transitionDelay = (index % 3 * 80) + 'ms';
            revealObserver.observe(el);
        });
    } else {
        revealElements.forEach(function (el) {
            el.classList.add('is-revealed');
        });
    }
})();
