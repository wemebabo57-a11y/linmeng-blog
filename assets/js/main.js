/**
 * 林梦博客 v3.0 - 主JavaScript文件
 * 支持深色/浅色主题切换、多图上传、图片灯箱等功能
 * 重点：代码健壮性、错误处理、性能优化
 */

'use strict';

// 全局错误处理
window.addEventListener('error', function(e) {
    console.warn('[全局] 未捕获的错误:', e.error);
});

window.addEventListener('unhandledrejection', function(e) {
    console.warn('[全局] 未处理的 Promise 拒绝:', e.reason);
});

document.addEventListener('DOMContentLoaded', function() {
    // ==================== 页面加载浮现 ====================
    try {
        document.body.classList.add('loaded');
    } catch (e) {
        console.warn('[加载] 添加 loaded 类失败:', e);
    }

    // ==================== 主题切换 ====================
    try {
        initTheme();
    } catch (e) {
        console.warn('[主题] 初始化失败:', e);
    }
    
    // ==================== 增强主题切换动画 ====================
    try {
        initThemeTransition();
    } catch (e) {
        console.warn('[主题动画] 初始化失败:', e);
    }
    
    // ==================== 后台移动端菜单切换 ====================
    try {
        const adminMenuToggle = document.getElementById('admin-menu-toggle');
        const adminSidebar = document.getElementById('admin-sidebar');
        if (adminMenuToggle && adminSidebar) {
            adminMenuToggle.style.display = 'block';
            adminMenuToggle.addEventListener('click', function() {
                adminSidebar.classList.toggle('open');
            });
            document.addEventListener('click', function(e) {
                if (!adminSidebar.contains(e.target) && !adminMenuToggle.contains(e.target)) {
                    adminSidebar.classList.remove('open');
                }
            });
        }
    } catch (e) {
        console.warn('[后台菜单] 初始化失败:', e);
    }
    
    // ==================== 图片懒加载 ====================
    try {
        const lazyImages = document.querySelectorAll('img[data-src]');
        if (lazyImages.length > 0) {
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                            observer.unobserve(img);
                        }
                    });
                }, {
                    rootMargin: '50px 0px',
                    threshold: 0.01
                });
                
                lazyImages.forEach(img => imageObserver.observe(img));
            } else {
                // 降级方案
                lazyImages.forEach(img => {
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                });
            }
        }
    } catch (e) {
        console.warn('[图片懒加载] 初始化失败:', e);
    }
    
    // ==================== 自动消失的消息提示 ====================
    try {
        const alerts = document.querySelectorAll('.alert:not(.alert-float)');
        alerts.forEach(alert => {
            // 跳过初始隐藏的 alert（如工具页错误框，由 JS 控制显示/隐藏，不应被自动移除）
            if (alert.style.display === 'none' || getComputedStyle(alert).display === 'none') return;
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 500);
            }, 5000);
        });
    } catch (e) {
        console.warn('[消息提示] 初始化失败:', e);
    }
    
    // ==================== 二维码弹窗 ====================
    const wechatBtn = document.querySelector('.wechat-btn');
    const wechatModal = document.querySelector('.wechat-modal');

    if (wechatBtn && wechatModal) {
        wechatBtn.addEventListener('click', function(e) {
            e.preventDefault();
            wechatModal.classList.add('active');
        });

        // 点击背景关闭
        wechatModal.addEventListener('click', function(e) {
            if (e.target === wechatModal) {
                wechatModal.classList.remove('active');
            }
        });

        // 关闭按钮（由 sidebar.php 提供 .modal-close，缺失时容错跳过）
        const wechatCloseBtn = wechatModal.querySelector('.modal-close, .wechat-close');
        if (wechatCloseBtn) {
            wechatCloseBtn.addEventListener('click', function(e) {
                e.preventDefault();
                wechatModal.classList.remove('active');
            });
        }

        // Esc 关闭任意已开启的弹窗（wechat-modal / lightbox / share-modal）
        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Escape') return;
            if (wechatModal.classList.contains('active')) {
                wechatModal.classList.remove('active');
                return;
            }
            const lightbox = document.querySelector('.lightbox.active');
            if (lightbox) {
                lightbox.classList.remove('active');
                document.body.style.overflow = '';
                return;
            }
            const shareModal = document.getElementById('share-modal');
            if (shareModal && shareModal.classList.contains('active')) {
                shareModal.classList.remove('active');
            }
        });
    }
    
    // ==================== 评论回复 ====================
    // 已移除：article.php 中不存在 .reply-btn 元素，原绑定属于死代码。

    // ==================== 表单验证 ====================
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let valid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    valid = false;
                    field.style.borderColor = 'var(--danger-color)';
                    
                    let errorHint = field.parentNode.querySelector('.error-hint');
                    if (!errorHint) {
                        errorHint = document.createElement('div');
                        errorHint.className = 'error-hint';
                        errorHint.style.color = 'var(--danger-color)';
                        errorHint.style.fontSize = '0.8rem';
                        errorHint.style.marginTop = '4px';
                        field.parentNode.appendChild(errorHint);
                    }
                    errorHint.textContent = '此字段不能为空';
                } else {
                    field.style.borderColor = '';
                    const errorHint = field.parentNode.querySelector('.error-hint');
                    if (errorHint) errorHint.remove();
                }
            });
            
            if (!valid) {
                e.preventDefault();
            }
        });
    });
    
    // ==================== 密码强度检测 ====================
    const passwordInputs = document.querySelectorAll('input[data-strength]');
    passwordInputs.forEach(input => {
        input.addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.querySelector('#password-strength');
            
            if (!strengthBar) return;
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            const colors = ['#ef4444', '#ef4444', '#f59e0b', '#84cc16', '#10b981'];
            const texts = ['极弱', '弱', '一般', '强', '极强'];
            
            strengthBar.style.width = (strength / 5 * 100) + '%';
            strengthBar.style.backgroundColor = colors[strength - 1] || '#ef4444';
            strengthBar.textContent = texts[strength - 1] || '极弱';
        });
    });
    
    // ==================== 图片预览 ====================
    const imageInputs = document.querySelectorAll('input[type="file"][data-preview]');
    imageInputs.forEach(input => {
        input.addEventListener('change', function() {
            const previewId = this.dataset.preview;
            const preview = document.querySelector('#' + previewId);
            
            if (preview && this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
    
    // ==================== 多图上传预览 ====================
    initMultiUpload();
    
    // ==================== 图片灯箱 ====================
    initLightbox();
    
    // ==================== 自动生成文章目录 ====================
    (function() {
        const articleContent = document.querySelector('.article-content');
        const tocContainer = document.querySelector('.toc-container');
        
        if (articleContent && tocContainer) {
            const headings = articleContent.querySelectorAll('h2, h3');
            if (headings.length > 0) {
                const tocList = tocContainer.querySelector('.toc-list, #toc-list');
                if (tocList) {
                    tocContainer.style.display = 'block';
                    tocList.innerHTML = '';
                    headings.forEach((heading, index) => {
                        const id = 'heading-' + index;
                        heading.id = id;
                        
                        const li = document.createElement('li');
                        const a = document.createElement('a');
                        a.href = '#' + id;
                        a.textContent = heading.textContent;
                        if (heading.tagName === 'H3') {
                            a.classList.add('toc-h3');
                        }
                        a.addEventListener('click', function(e) {
                            e.preventDefault();
                            heading.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        });
                        li.appendChild(a);
                        tocList.appendChild(li);
                    });
                }
            } else {
                tocContainer.style.display = 'none';
            }
        }
    })();
    
    // ==================== 平滑滚动 ====================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (!href || href === '#') return;
            const target = document.querySelector(href);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
    
    // ==================== AJAX表单提交 ====================
    const ajaxForms = document.querySelectorAll('form[data-ajax]');
    ajaxForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn ? submitBtn.textContent : '';
            
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = '提交中...';
            }
            
            const formData = new FormData(form);
            
            fetch(form.action, {
                method: form.method || 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message || '操作成功', 'success');
                    if (data.redirect) {
                        setTimeout(() => window.location.href = data.redirect, 1000);
                    }
                    if (data.reload) {
                        setTimeout(() => window.location.reload(), 1000);
                    }
                } else {
                    showMessage(data.message || '操作失败', 'error');
                }
            })
            .catch(error => {
                showMessage('网络错误，请稍后重试', 'error');
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            });
        });
    });
    
    // ==================== 文章点赞 ====================
    const likeBtns = document.querySelectorAll('.like-btn');
    likeBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const articleId = this.dataset.articleId;
            if (!articleId) return;

            const csrfInput = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfInput ? csrfInput.getAttribute('content') : '';
            const csrfNameMeta = document.querySelector('meta[name="csrf-token-name"]');
            const csrfName = csrfNameMeta ? csrfNameMeta.getAttribute('content') : 'lm_csrf_token';

            fetch('/api/like.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'article_id=' + encodeURIComponent(articleId) + '&' + encodeURIComponent(csrfName) + '=' + encodeURIComponent(csrfToken)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    this.classList.toggle('active');
                    const countEl = this.querySelector('.like-count');
                    if (countEl) countEl.textContent = data.count;
                    showMessage(data.message, 'success');
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(() => showMessage('操作失败', 'error'));
        });
    });

    // ==================== 滚动视差 ====================
    function initParallax() {
        if (window.innerWidth < 768) return;
        var parallaxEls = document.querySelectorAll('[data-parallax]');
        if (!parallaxEls.length) return;
        window.addEventListener('scroll', function() {
            var scrollY = window.pageYOffset;
            parallaxEls.forEach(function(el) {
                var speed = parseFloat(el.dataset.parallax) || 0.3;
                var rect = el.getBoundingClientRect();
                var offset = (rect.top + scrollY) * speed * 0.1;
                el.style.transform = 'translateY(' + (-scrollY * speed * 0.05) + 'px)';
            });
        }, { passive: true });
    }
    initParallax();

    // ==================== 数字滚动动画 ====================
    function initCountUp() {
        var counters = document.querySelectorAll('[data-count]');
        if (!counters.length) return;
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var target = parseInt(entry.target.dataset.count);
                    var startTime = performance.now();
                    var duration = 1200;
                    function update(currentTime) {
                        var elapsed = currentTime - startTime;
                        var progress = Math.min(elapsed / duration, 1);
                        var eased = 1 - Math.pow(1 - progress, 4);
                        entry.target.textContent = Math.floor(target * eased);
                        if (progress < 1) requestAnimationFrame(update);
                        else entry.target.textContent = target;
                    }
                    requestAnimationFrame(update);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });
        counters.forEach(function(c) { observer.observe(c); });
    }
    initCountUp();

    // ==================== 滚动渐入增强 ====================
    // 已移除：原 initScrollReveal 通过 inline style 预隐藏 .article-item/.widget/.card，
    // 若 IntersectionObserver 回调延迟或失败会导致白屏。
    // 现统一由 design-system.css 的 `html.js .article-item/.reveal-item` 类隐藏，
    // 并由 ui-enhancements.js 添加 .is-revealed 类来揭示；
    // .widget/.card 默认可见，不参与预隐藏，避免 JS 失败导致内容不可见。

    // ==================== 鼠标跟随光效 ====================
    function initCardGlow() {
        if (window.innerWidth < 768 || !('ontouchstart' in window === false)) return;
        document.querySelectorAll('.card, .widget').forEach(function(card) {
            var glow = document.createElement('div');
            glow.className = 'card-glow';
            glow.style.cssText = 'position:absolute;top:0;left:0;right:0;bottom:0;pointer-events:none;z-index:0;border-radius:inherit;opacity:0;transition:opacity 0.4s ease;background:radial-gradient(circle 300px at 50% 50%, var(--primary-glow), transparent 70%);';
            card.style.position = 'relative';
            card.style.overflow = 'hidden';
            card.appendChild(glow);

            card.addEventListener('mousemove', function(e) {
                var rect = card.getBoundingClientRect();
                var x = e.clientX - rect.left;
                var y = e.clientY - rect.top;
                glow.style.background = 'radial-gradient(circle 300px at ' + x + 'px ' + y + 'px, var(--primary-glow), transparent 70%)';
                glow.style.opacity = '0.06';
            });
            card.addEventListener('mouseleave', function() {
                glow.style.opacity = '0';
            });
        });
    }
    initCardGlow();

    // ==================== 打字机效果 ====================
    function initTypewriter() {
        var elements = document.querySelectorAll('[data-typewriter]');
        elements.forEach(function(el) {
            var text = el.dataset.typewriter || el.textContent;
            el.textContent = '';
            el.style.borderRight = '2px solid var(--primary-color)';
            var i = 0;
            function type() {
                if (i < text.length) {
                    el.textContent += text.charAt(i);
                    i++;
                    setTimeout(type, 50 + Math.random() * 50);
                } else {
                    setTimeout(function() { el.style.borderRight = 'none'; }, 1000);
                }
            }
            // 延迟启动，等页面渲染完
            setTimeout(type, 500);
        });
    }
    initTypewriter();

    // ==================== TOC滚动高亮 ====================
    function initTocHighlight() {
        var headings = document.querySelectorAll('.article-content h2, .article-content h3, .article-content h4');
        var tocLinks = document.querySelectorAll('.toc-list a');
        if (!headings.length || !tocLinks.length) return;

        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    tocLinks.forEach(function(link) { link.classList.remove('active'); });
                    var activeLink = document.querySelector('.toc-list a[href="#' + entry.target.id + '"]');
                    if (activeLink) activeLink.classList.add('active');
                }
            });
        }, { rootMargin: '-80px 0px -70% 0px' });

        headings.forEach(function(h) {
            if (h.id) observer.observe(h);
        });
    }
    initTocHighlight();

    // ==================== 复制文章链接按钮 ====================
    document.querySelectorAll('.copy-link-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var url = this.dataset.url || window.location.href;
            copyToClipboard(url);
        });
    });

    // ==================== 代码块复制按钮 ====================
    function initCodeCopy() {
        document.querySelectorAll('pre code').forEach(function(codeBlock) {
            var pre = codeBlock.parentElement;
            if (pre.querySelector('.code-copy-btn')) return;
            var btn = document.createElement('button');
            btn.className = 'code-copy-btn';
            btn.textContent = '复制';
            btn.addEventListener('click', function() {
                var text = codeBlock.textContent;
                navigator.clipboard.writeText(text).then(function() {
                    btn.textContent = '已复制';
                    setTimeout(function() { btn.textContent = '复制'; }, 2000);
                }).catch(function() {
                    btn.textContent = '失败';
                    setTimeout(function() { btn.textContent = '复制'; }, 2000);
                });
            });
            pre.style.position = 'relative';
            pre.appendChild(btn);
        });
    }
    initCodeCopy();

    // ==================== 站点访问人数统计 ====================
    (function initVisitorCounter() {
        var storageKey = 'lm_site_visitor_marked';
        var thirtyDays = 30 * 24 * 60 * 60 * 1000;
        var markedAt = localStorage.getItem(storageKey);

        if (markedAt && (Date.now() - parseInt(markedAt, 10)) < thirtyDays) {
            return;
        }

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

        fetch('/api/visit.php', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                localStorage.setItem(storageKey, Date.now().toString());
                document.querySelectorAll('.visitor-count').forEach(function(el) {
                    el.textContent = data.count.toLocaleString();
                    el.setAttribute('data-count', data.count);
                });
            }
        })
        .catch(function() {});
    })();

    // ==================== 全局 data-toggle 切换显示 ====================
    document.querySelectorAll('[data-toggle-target]').forEach(function(el) {
        el.addEventListener('click', function() {
            var target = document.getElementById(el.getAttribute('data-toggle-target'));
            var display = el.getAttribute('data-toggle-display');
            if (target) {
                target.style.display = display || (target.style.display === 'none' ? 'block' : 'none');
            }
        });
    });

    // ==================== 全局 data-confirm 确认框 ====================
    document.querySelectorAll('a[data-confirm], button[data-confirm]').forEach(function(el) {
        var tagName = el.tagName.toLowerCase();
        if (tagName === 'a') {
            el.addEventListener('click', function(e) {
                if (!confirm(el.getAttribute('data-confirm'))) {
                    e.preventDefault();
                }
            });
        } else {
            var form = el.closest('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (!confirm(el.getAttribute('data-confirm'))) {
                        e.preventDefault();
                    }
                });
            }
        }
    });
});

// ==================== 增强主题切换动画 ====================
function initThemeTransition() {
    // 添加主题切换时的闪烁动画
    document.documentElement.style.transition = 'background-color 0.3s ease, color 0.3s ease';
    
    // 为所有支持过渡的元素添加过渡
    const transitionElements = document.querySelectorAll('.card, .widget, .header, .footer, .article-item, .btn');
    transitionElements.forEach(function(el) {
        el.style.transition = 'background-color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease';
    });
}

// ==================== 主题切换功能 ====================
function initTheme() {
    const themeToggle = document.querySelector('.theme-toggle');
    const html = document.documentElement;
    
    // 读取保存的主题或默认跟随系统
    const savedTheme = localStorage.getItem('theme') || 'auto';
    
    function applyTheme(theme) {
        if (theme === 'dark') {
            html.setAttribute('data-theme', 'dark');
        } else if (theme === 'light') {
            html.setAttribute('data-theme', 'light');
        } else {
            html.removeAttribute('data-theme');
        }
        updateThemeIcon(theme);
    }
    
    function updateThemeIcon(theme) {
        if (!themeToggle) return;
        if (theme === 'dark') {
            // 深色模式：显示太阳（点击切换到浅色）
            themeToggle.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>';
            themeToggle.title = '切换到浅色';
        } else if (theme === 'light') {
            // 浅色模式：显示月亮（点击切换到深色）
            themeToggle.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>';
            themeToggle.title = '切换到深色';
        } else {
            // 自动模式：显示显示器图标（区别于浅色的月亮，代表跟随系统）
            themeToggle.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>';
            themeToggle.title = '跟随系统';
        }
    }
    
    applyTheme(savedTheme);
    
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            const current = localStorage.getItem('theme') || 'auto';
            let next;
            if (current === 'auto') next = 'light';
            else if (current === 'light') next = 'dark';
            else next = 'auto';
            
            localStorage.setItem('theme', next);
            applyTheme(next);
        });
    }
    
    // 监听系统主题变化
    if (window.matchMedia) {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        mediaQuery.addEventListener('change', function(e) {
            const saved = localStorage.getItem('theme') || 'auto';
            if (saved === 'auto') {
                applyTheme('auto');
            }
        });
    }

    // ==================== 标签 hover 增强 ====================
    document.querySelectorAll('.tag').forEach(function(tag) {
        tag.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-1px) scale(1.03)';
        });
        tag.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });
}

// ==================== 多图上传 ====================
function initMultiUpload() {
    const uploadAreas = document.querySelectorAll('.upload-area');
    
    uploadAreas.forEach(area => {
        const input = area.querySelector('input[type="file"]');
        const preview = area.querySelector('.upload-preview');
        
        if (!input || !preview) return;
        
        area.addEventListener('click', () => input.click());
        area.addEventListener('dragover', (e) => {
            e.preventDefault();
            area.style.borderColor = 'var(--primary-color)';
        });
        area.addEventListener('dragleave', () => {
            area.style.borderColor = '';
        });
        area.addEventListener('drop', (e) => {
            e.preventDefault();
            area.style.borderColor = '';
            if (e.dataTransfer.files) {
                handleFiles(e.dataTransfer.files, preview, input);
            }
        });
        
        input.addEventListener('change', function() {
            if (this.files) {
                handleFiles(this.files, preview, input);
            }
        });
    });
}

function handleFiles(files, previewContainer, input) {
    Array.from(files).forEach(file => {
        if (!file.type.startsWith('image/')) return;
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const item = document.createElement('div');
            item.className = 'upload-preview-item';
            item.innerHTML = `
                <img src="${e.target.result}" alt="">
                <button type="button" class="remove-btn">&times;</button>
            `;
            
            item.querySelector('.remove-btn').addEventListener('click', function() {
                item.remove();
            });
            
            previewContainer.appendChild(item);
        };
        reader.readAsDataURL(file);
    });
}

// ==================== 图片灯箱 ====================
function initLightbox() {
    const lightbox = document.querySelector('.lightbox');
    if (!lightbox) return;
    
    const lightboxImg = lightbox.querySelector('img');
    const lightboxClose = lightbox.querySelector('.lightbox-close');
    
    // 文章画廊图片点击
    document.querySelectorAll('.article-gallery-item img, .article-content img').forEach(img => {
        img.style.cursor = 'zoom-in';
        img.addEventListener('click', function() {
            lightboxImg.src = this.src;
            lightbox.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    });
    
    // 关闭灯箱
    if (lightboxClose) {
        lightboxClose.addEventListener('click', closeLightbox);
    }
    lightbox.addEventListener('click', function(e) {
        if (e.target === lightbox) closeLightbox();
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeLightbox();
    });
    
    function closeLightbox() {
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// ==================== 显示消息提示 ====================
function showMessage(message, type = 'success') {
    const existingAlert = document.querySelector('.alert-float');
    if (existingAlert) existingAlert.remove();
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-float`;
    alert.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,0.15);';
    alert.textContent = message;
    
    document.body.appendChild(alert);
    
    setTimeout(() => {
        alert.style.opacity = '0';
        alert.style.transition = 'opacity 0.5s';
        setTimeout(() => alert.remove(), 500);
    }, 3000);
}

// ==================== 确认删除 ====================
function confirmDelete(message) {
    return confirm(message || '确定要删除吗？此操作不可恢复！');
}

// ==================== 复制到剪贴板 ====================
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showMessage('复制成功', 'success');
        });
    } else {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showMessage('复制成功', 'success');
    }
}

// ==================== 返回顶部 ====================
// 注：返回顶部按钮与阅读进度条由 template/header.php 静态输出，
// 滚动显隐与进度计算由 ui-enhancements.js 统一处理，此处不再重复创建。
function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ==================== 初始化 Lucide 图标 ====================
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}