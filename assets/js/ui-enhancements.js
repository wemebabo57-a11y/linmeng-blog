/**
 * 林梦博客 UI 增强 v3.2
 * 包含：智能头部、全局搜索、移动端抽屉、返回顶部、阅读进度、
 *       文章收藏/分享、代码语言标签、图片骨架屏、Toast、键盘快捷键等
 */
(function () {
    'use strict';

    const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const scrollLock = {
        count: 0,
        previousOverflow: '',
        lock: function () {
            if (this.count === 0) this.previousOverflow = document.body.style.overflow;
            this.count += 1;
            document.body.style.overflow = 'hidden';
        },
        unlock: function () {
            if (this.count === 0) return;
            this.count -= 1;
            if (this.count === 0) document.body.style.overflow = this.previousOverflow;
        }
    };

    function lockScroll() {
        if (window.lmLockScroll) window.lmLockScroll();
        else scrollLock.lock();
    }

    function unlockScroll() {
        if (window.lmUnlockScroll) window.lmUnlockScroll();
        else scrollLock.unlock();
    }

    // ==================== 桌面导航更多菜单 ====================
    function initNavMoreMenu() {
        const wrapper = document.querySelector('[data-nav-more]');
        if (!wrapper) return;

        const trigger = wrapper.querySelector('.nav-more-trigger');
        const menu = wrapper.querySelector('.nav-more-menu');
        if (!trigger || !menu) return;

        const menuItems = Array.from(menu.querySelectorAll('[role="menuitem"]'));

        function setOpen(open, focusMenu) {
            wrapper.classList.toggle('is-open', open);
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            menu.setAttribute('aria-hidden', open ? 'false' : 'true');
            if (open && focusMenu && menuItems[0]) menuItems[0].focus();
        }

        trigger.addEventListener('click', function () {
            setOpen(!wrapper.classList.contains('is-open'), false);
        });

        trigger.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                setOpen(true, true);
            } else if (event.key === 'Escape') {
                setOpen(false, false);
            }
        });

        menu.addEventListener('keydown', function (event) {
            const currentIndex = menuItems.indexOf(document.activeElement);
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                menuItems[(currentIndex + 1) % menuItems.length].focus();
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                menuItems[(currentIndex - 1 + menuItems.length) % menuItems.length].focus();
            } else if (event.key === 'Home') {
                event.preventDefault();
                menuItems[0].focus();
            } else if (event.key === 'End') {
                event.preventDefault();
                menuItems[menuItems.length - 1].focus();
            } else if (event.key === 'Escape') {
                event.preventDefault();
                setOpen(false, false);
                trigger.focus();
            } else if (event.key === 'Tab') {
                setOpen(false, false);
            }
        });

        document.addEventListener('click', function (event) {
            if (!wrapper.contains(event.target)) setOpen(false, false);
        });
    }

    // ==================== DOM 就绪后统一初始化 ====================
    document.addEventListener('DOMContentLoaded', function () {
        initNavMoreMenu();
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
            const viewport = window.innerHeight;
            // 文章页优先按正文计算阅读进度（避免侧栏/评论影响比例），其它页面回退整页
            const articleContent = document.querySelector('.article-content');
            let progress;
            if (articleContent) {
                const top = articleContent.getBoundingClientRect().top + scrollTop;
                const height = articleContent.offsetHeight;
                const scrolled = scrollTop - top + viewport;
                progress = height > 0 ? scrolled / (height + viewport) : 0;
            } else {
                const docHeight = document.documentElement.scrollHeight - viewport;
                progress = docHeight > 0 ? scrollTop / docHeight : 0;
            }
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
        let lastFocused = null;

        function open() {
            if (overlay.classList.contains('active')) return;
            lastFocused = document.activeElement;
            overlay.classList.add('active');
            overlay.setAttribute('aria-hidden', 'false');
            input.value = '';
            selectedIndex = -1;
            renderHint();
            lockScroll();
            input.focus();
        }

        function close() {
            if (!overlay.classList.contains('active')) return;
            overlay.classList.remove('active');
            overlay.setAttribute('aria-hidden', 'true');
            unlockScroll();
            if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
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
            try {
                localStorage.setItem(recentKey, JSON.stringify(recent.slice(0, 5)));
            } catch (e) {
                // 隐私模式/配额不足时跳过记录，不阻断搜索跳转
            }
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
            const tags = item.querySelector('.article-tags');
            return {
                title: titleEl ? titleEl.textContent.trim() : '',
                href: titleEl ? titleEl.getAttribute('href') : '#',
                meta: metaEl ? metaEl.textContent.trim() : '',
                excerpt: excerptEl ? excerptEl.textContent.trim() : '',
                tags: tags ? tags.textContent.trim() : ''
            };
            });

            const q = query.toLowerCase();
            const matched = articles.filter(function (a) {
                return a.title.toLowerCase().includes(q) ||
                    a.excerpt.toLowerCase().includes(q) ||
                    a.meta.toLowerCase().includes(q) ||
                    a.tags.toLowerCase().includes(q);
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

        function trapFocus(e) {
            if (e.key !== 'Tab' || !overlay.classList.contains('active')) return;
            const focusable = Array.from(overlay.querySelectorAll('button:not([disabled]), input:not([disabled]), a[href]'))
                .filter(function (el) { return el.offsetParent !== null; });
            if (!focusable.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Tab') {
                trapFocus(e);
                return;
            }
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
        overlay.addEventListener('lm-close', close);
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

        let lastFocused = null;
        let isOpen = false;
        drawer.setAttribute('inert', '');

        function getFocusable() {
            return Array.from(drawer.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])'
            )).filter(function (el) { return el.offsetParent !== null; });
        }

        function open() {
            if (isOpen) return;
            isOpen = true;
            lastFocused = document.activeElement;
            drawer.classList.add('active');
            drawer.setAttribute('aria-hidden', 'false');
            drawer.removeAttribute('inert');
            btn.setAttribute('aria-expanded', 'true');
            if (overlay) {
                overlay.classList.add('active');
            }
            lockScroll();
            if (closeBtn) closeBtn.focus();
        }

        function close() {
            if (!isOpen) return;
            isOpen = false;
            drawer.classList.remove('active');
            drawer.setAttribute('aria-hidden', 'true');
            drawer.setAttribute('inert', '');
            btn.setAttribute('aria-expanded', 'false');
            if (overlay) {
                overlay.classList.remove('active');
            }
            unlockScroll();
            if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
            }
        }

        btn.addEventListener('click', open);
        if (closeBtn) closeBtn.addEventListener('click', close);
        if (overlay) overlay.addEventListener('click', close);
        drawer.addEventListener('lm-close', close);

        drawer.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', close);
        });

        // 焦点陷阱：Tab/Shift+Tab 在抽屉内首尾元素间循环
        drawer.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab') return;
            const focusable = getFocusable();
            if (!focusable.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        });
    }

    // ==================== 文章页增强 ====================
    function initArticleEnhancements() {
        const articleContent = document.querySelector('.article-content');
        const articleCard = document.querySelector('.article-detail-card') || document.querySelector('.article-page-card');
        if (!articleContent) return;

        // 计算阅读时间：与 article.php 的 500 字符/分钟保持一致
        const text = articleContent.textContent || '';
        const wordCount = text.replace(/\s/g, '').length;
        const minutes = Math.max(1, Math.ceil(wordCount / 500));

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

        // 初始化画廊「+N」展开
        initArticleGalleryMore();
    }

    // ==================== 文章画廊「+N」展开 ====================
    // 前 6 张直接展示，其余由 CSS 隐藏；点击 +N 显示全部并把按钮移除，
    // 展开后所有图片仍可点击进入统一灯箱（由 main.js 绑定）。
    function initArticleGalleryMore() {
        const gallery = document.querySelector('.article-gallery');
        if (!gallery) return;

        const moreBtn = gallery.querySelector('.article-gallery-more');
        if (!moreBtn) return;

        const extras = gallery.querySelectorAll('.article-gallery-item--extra');
        if (!extras.length) {
            moreBtn.remove();
            return;
        }

        moreBtn.addEventListener('click', function (e) {
            // 阻止冒泡到父项的图片点击（否则会同时打开灯箱）
            e.stopPropagation();
            extras.forEach(function (item) {
                item.classList.remove('article-gallery-item--extra');
            });
            const firstExtra = extras[0];
            moreBtn.remove();
            if (firstExtra) {
                const img = firstExtra.querySelector('img');
                if (img && typeof img.focus === 'function') {
                    img.setAttribute('tabindex', '-1');
                    img.focus();
                }
            }
            window.showToast('已展开全部图片', 'success');
        });
    }

    function initArticleTocHighlight() {
        const content = document.querySelector('.article-content');
        const toc = document.querySelector('.toc-container');
        const tocList = document.querySelector('.toc-list');
        if (!content || !toc || !tocList) return;

        const headings = Array.from(content.querySelectorAll('h2, h3, h4'));
        if (!headings.length) return;

        // 仅在正文存在标题时生成目录，避免空目录容器占位。
        const usedIds = new Set();
        tocList.innerHTML = '';
        headings.forEach(function (heading, index) {
            let id = heading.id || 'section-' + (index + 1);
            const baseId = id;
            let suffix = 2;
            while (usedIds.has(id) || (document.getElementById(id) && document.getElementById(id) !== heading)) {
                id = baseId + '-' + suffix;
                suffix += 1;
            }
            heading.id = id;
            usedIds.add(id);

            const item = document.createElement('li');
            const link = document.createElement('a');
            link.href = '#' + id;
            link.className = heading.tagName.toLowerCase() === 'h3' || heading.tagName.toLowerCase() === 'h4' ? 'toc-' + heading.tagName.toLowerCase() : '';
            link.textContent = heading.textContent.trim();
            item.appendChild(link);
            tocList.appendChild(item);
        });
        toc.style.display = '';

        const tocLinks = Array.from(tocList.querySelectorAll('a'));
        if (!('IntersectionObserver' in window)) return;

        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    tocLinks.forEach(function (link) {
                        link.classList.remove('active');
                        link.removeAttribute('aria-current');
                    });
                    const activeLink = tocLinks.filter(function (link) {
                        return link.getAttribute('href') === '#' + entry.target.id;
                    })[0];
                    if (activeLink) {
                        activeLink.classList.add('active');
                        activeLink.setAttribute('aria-current', 'location');
                    }
                }
            });
        }, { rootMargin: '-80px 0px -70% 0px' });

        headings.forEach(function (heading) { observer.observe(heading); });
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

        function saveBookmarks(list) {
            try {
                localStorage.setItem(key, JSON.stringify(list));
                return true;
            } catch (e) {
                return false;
            }
        }

        btn.addEventListener('click', function () {
            let bookmarks = getBookmarks();
            const wasBookmarked = isBookmarked();

            if (wasBookmarked) {
                bookmarks = bookmarks.filter(function (b) { return b.url !== url; });
            } else {
                bookmarks.unshift({ url: url, title: title, time: Date.now() });
                bookmarks = bookmarks.slice(0, 100);
            }

            if (!saveBookmarks(bookmarks)) {
                window.showToast('浏览器存储不可用，收藏未保存', 'error');
                return;
            }

            window.showToast(wasBookmarked ? '已取消收藏' : '文章已收藏', wasBookmarked ? 'info' : 'success');
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
        let lastFocused = null;

        function open() {
            if (modal.classList.contains('active')) return;
            lastFocused = document.activeElement;
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
            if (linkInput) linkInput.value = url;
            lockScroll();
            if (closeBtn) closeBtn.focus();
        }

        function close() {
            if (!modal.classList.contains('active')) return;
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
            unlockScroll();
            if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
        }

        if (trigger) trigger.addEventListener('click', open);
        if (closeBtn) closeBtn.addEventListener('click', close);
        if (backdrop) backdrop.addEventListener('click', close);
        modal.addEventListener('lm-close', close);

        modal.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab' || !modal.classList.contains('active')) return;
            const focusable = Array.from(modal.querySelectorAll('button:not([disabled]), input:not([disabled]), a[href]'))
                .filter(function (el) { return el.offsetParent !== null; });
            if (!focusable.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        });

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
                    close();
                    return;
                }
                if (shareUrl) {
                    window.open(shareUrl, '_blank', 'width=600,height=400');
                    close();
                }
            });
        });

        if (copyBtn && linkInput) {
            copyBtn.addEventListener('click', function () {
                copyToClipboard(linkInput.value);
                close();
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
            const tocLinks = toc ? toc.querySelectorAll('.toc-list a').length : 0;
            if (toc && tocLinks) {
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
                // 统一的 Escape 入口：按视觉层级从最上层开始，一次只关一层，
                // 全部通过 lm-close 事件交给各自的 close() 处理，
                // 确保 aria-hidden / 焦点返回 / 滚动锁计数同步。
                const layers = [
                    document.querySelector('.lightbox'),
                    document.getElementById('search-overlay'),
                    document.getElementById('share-modal'),
                    document.querySelector('.wechat-modal'),
                    document.getElementById('mobile-drawer')
                ];

                for (let i = 0; i < layers.length; i++) {
                    const layer = layers[i];
                    if (layer && layer.classList.contains('active')) {
                        layer.dispatchEvent(new CustomEvent('lm-close'));
                        break;
                    }
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
        const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
        // 正在提交标记：拦截双击/连点产生的第二次提交。
        // 注意不能在 click 里立即 disabled 按钮——disabled 会阻止随后的原生
        // submit 事件触发，导致表单根本不发出去。改用 flag 拦截，真正的
        // disabled 放在 submit 事件里（此时表单已被放行）。
        let submitting = false;

        function shouldCommit() {
            if (submitting) return false;
            if (!submitBtn || submitBtn.disabled) return false;
            // 表单未通过 HTML5 校验则不进入加载态，避免 preventDefault 后按钮卡死
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return false;
            }
            return true;
        }

        function commitLoading() {
            submitting = true;
            if (!submitBtn) return;
            if (submitBtn.dataset.originalText === undefined) {
                submitBtn.dataset.originalText = submitBtn.innerHTML;
            }
            submitBtn.classList.add('is-loading');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="visually-hidden">加载中</span>';

            // 安全超时：若为客户端提交（fetch/AJAX）未导航离开，几秒后自动还原，
            // 同时清掉 submitting 标记，避免短暂网络抖动后永久无法再次提交。
            const safetyTimer = setTimeout(function () {
                submitting = false;
                restoreSubmitBtn(submitBtn);
            }, 8000);

            // bfcache 还原：从历史返回时恢复按钮与标记
            window.addEventListener('pageshow', function () {
                clearTimeout(safetyTimer);
                submitting = false;
                restoreSubmitBtn(submitBtn);
            }, { once: true });
        }

        // capture 阶段拦截：双击的第二次点击发生在第一次 submit 之前，
        // flag 能挡掉第二次进入 commitLoading。
        form.addEventListener('submit', function () {
            if (!shouldCommit()) return;
            commitLoading();
        }, true);
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
            if (drawer && drawer.classList.contains('active')) {
                drawer.dispatchEvent(new CustomEvent('lm-close'));
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
