/**
 * 后台文章编辑脚本
 */
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        var MAX_IMAGES = 10;
        var input = document.getElementById('article-images-input');
        var preview = document.getElementById('new-images-preview');
        var counter = document.getElementById('image-counter');
        var group = document.getElementById('article-images-group');
        var form = document.querySelector('form[data-validate]');

        function countTotal() {
            var existing = 0;
            var existingBox = document.getElementById('existing-images');
            if (existingBox) {
                existingBox.querySelectorAll('.upload-preview-item').forEach(function(el) {
                    var cb = el.querySelector('.delete-image-checkbox');
                    if (!cb || !cb.checked) {
                        existing++;
                    }
                });
            }
            var newOnes = preview ? preview.querySelectorAll('.upload-preview-item').length : 0;
            var urls = parseUrlsFromText();
            return existing + newOnes + urls.length;
        }

        function parseUrlsFromText() {
            var t = document.getElementById('article-image-urls-text');
            if (!t) return [];
            return t.value.split(/\r?\n/).map(function(s){return s.trim();}).filter(Boolean);
        }

        function updateCounter() {
            if (!counter) return;
            counter.textContent = '当前: ' + countTotal() + ' / ' + MAX_IMAGES;
        }

        if (group) {
            group.addEventListener('change', function(e) {
                if (e.target && e.target.classList && e.target.classList.contains('delete-image-checkbox')) {
                    updateCounter();
                }
            });
            var urlText = document.getElementById('article-image-urls-text');
            if (urlText) {
                urlText.addEventListener('input', updateCounter);
                urlText.addEventListener('change', updateCounter);
            }
        }

        // 上传文件预览
        if (input && preview) {
            input.addEventListener('change', function() {
                var files = Array.prototype.slice.call(input.files || []);
                files.forEach(function(file) {
                    if (!file.type.match(/^image\//)) return;
                    var reader = new FileReader();
                    reader.onload = function(ev) {
                        var div = document.createElement('div');
                        div.className = 'upload-preview-item';
                        div.style.cssText = 'position: relative; border: 1px solid var(--border-color); border-radius: var(--radius); padding: 6px; background: var(--bg-color);';
                        div.innerHTML = '<img src="' + ev.target.result + '" style="width:100%;height:100px;object-fit:cover;border-radius:calc(var(--radius) - 2px);" alt=""><div style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,0.7);color:white;padding:3px 8px;border-radius:4px;font-size:0.75rem;">新</div>';
                        preview.appendChild(div);
                        updateCounter();
                    };
                    reader.readAsDataURL(file);
                });
            });
        }

        updateCounter();

        // 前端防重复提交
        if (form) {
            form.addEventListener('submit', function() {
                var btn = document.getElementById('submit-btn');
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = '保存中...';
                }
            });
        }

        // ==================== 内联图片插入（光标位置） ====================
        var contentTextarea = document.getElementById('article-content');
        var storageInputs = document.querySelectorAll('input[name="content_storage"]');
        var storageHint = document.getElementById('content-storage-hint');
        storageInputs.forEach(function(input) {
            input.addEventListener('change', function() {
                document.querySelectorAll('.storage-option').forEach(function(option) {
                    var radio = option.querySelector('input');
                    option.classList.toggle('active', !!radio && radio.checked);
                });
                if (!input.checked || !contentTextarea) return;
                var markdown = input.value === 'markdown';
                contentTextarea.placeholder = markdown ? '使用 Markdown 编写文章' : '支持 HTML 标签';
                if (storageHint) {
                    storageHint.textContent = markdown
                        ? '支持标题、列表、引用、代码块、链接和图片等 Markdown 语法。'
                        : '支持 p、strong、h1-h6、列表、引用、代码、链接、图片等 HTML 标签。';
                }
            });
        });
        var insertBtn = document.getElementById('insert-image-btn');
        var modal = document.getElementById('inline-image-modal');
        var closeBtn = document.getElementById('inline-image-close');
        var cancelBtn = document.getElementById('inline-image-cancel');
        var confirmBtn = document.getElementById('inline-image-confirm');
        var tabUpload = document.getElementById('tab-upload');
        var tabDirect = document.getElementById('tab-direct');
        var panelUpload = document.getElementById('panel-upload');
        var panelDirect = document.getElementById('panel-direct');
        var fileInput = document.getElementById('inline-image-file');
        var urlInput = document.getElementById('inline-image-url');
        var previewUploadBox = document.getElementById('inline-preview-upload');
        var previewUploadImg = document.getElementById('inline-preview-img-upload');
        var previewDirectBox = document.getElementById('inline-preview-direct');
        var previewDirectImg = document.getElementById('inline-preview-img-direct');
        var errorBox = document.getElementById('inline-image-error');

        // 跟踪 textarea 最后光标位置（因为打开模态框会丢失焦点）
        var lastCursorPos = 0;
        if (contentTextarea) {
            var saveCursor = function() {
                if (document.activeElement === contentTextarea) {
                    lastCursorPos = contentTextarea.selectionStart;
                }
            };
            contentTextarea.addEventListener('blur', saveCursor);
            contentTextarea.addEventListener('keyup', saveCursor);
            contentTextarea.addEventListener('click', saveCursor);
            contentTextarea.addEventListener('focus', saveCursor);
        }

        function showError(msg) {
            if (errorBox) {
                errorBox.textContent = msg;
                errorBox.style.display = 'block';
            }
        }
        function clearError() {
            if (errorBox) {
                errorBox.textContent = '';
                errorBox.style.display = 'none';
            }
        }

        function openModal() {
            if (!modal) return;
            clearError();
            // 重置表单
            if (fileInput) fileInput.value = '';
            if (urlInput) urlInput.value = '';
            if (previewUploadBox) previewUploadBox.style.display = 'none';
            if (previewUploadImg) previewUploadImg.src = '';
            if (previewDirectBox) previewDirectBox.style.display = 'none';
            if (previewDirectImg) previewDirectImg.src = '';
            // 默认切到上传 tab
            switchTab('upload');
            modal.style.display = 'flex';
            modal.setAttribute('aria-hidden', 'false');
            // 焦点到模态框
            if (closeBtn) closeBtn.focus();
        }

        function closeModal() {
            if (!modal) return;
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
            // 焦点回到插入按钮
            if (insertBtn) insertBtn.focus();
        }

        function switchTab(which) {
            if (tabUpload && tabDirect && panelUpload && panelDirect) {
                if (which === 'upload') {
                    tabUpload.setAttribute('aria-selected', 'true');
                    tabUpload.classList.add('active');
                    tabUpload.style.color = 'var(--primary-color)';
                    tabUpload.style.borderBottomColor = 'var(--primary-color)';
                    tabDirect.setAttribute('aria-selected', 'false');
                    tabDirect.classList.remove('active');
                    tabDirect.style.color = 'var(--text-secondary)';
                    tabDirect.style.borderBottomColor = 'transparent';
                    panelUpload.style.display = 'block';
                    panelDirect.style.display = 'none';
                } else {
                    tabDirect.setAttribute('aria-selected', 'true');
                    tabDirect.classList.add('active');
                    tabDirect.style.color = 'var(--primary-color)';
                    tabDirect.style.borderBottomColor = 'var(--primary-color)';
                    tabUpload.setAttribute('aria-selected', 'false');
                    tabUpload.classList.remove('active');
                    tabUpload.style.color = 'var(--text-secondary)';
                    tabUpload.style.borderBottomColor = 'transparent';
                    panelDirect.style.display = 'block';
                    panelUpload.style.display = 'none';
                }
                clearError();
            }
        }

        // 在 textarea 当前光标位置插入文本，并把光标移到插入文本之后
        function insertAtCursor(text) {
            if (!contentTextarea) return;
            var start = contentTextarea.selectionStart;
            var end = contentTextarea.selectionEnd;
            // 如果 textarea 当前没有焦点，用上次保存的位置
            if (document.activeElement !== contentTextarea) {
                start = lastCursorPos;
                end = lastCursorPos;
            }
            var before = contentTextarea.value.substring(0, start);
            var after = contentTextarea.value.substring(end);
            contentTextarea.value = before + text + after;
            var newCursor = start + text.length;
            contentTextarea.focus();
            contentTextarea.setSelectionRange(newCursor, newCursor);
            // 触发 input 事件，便于其他监听
            contentTextarea.dispatchEvent(new Event('input', { bubbles: true }));
        }

        function getCsrfToken() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        }

        // 上传图片并插入
        function uploadAndInsert(file) {
            if (!file) {
                showError('请先选择图片文件');
                return;
            }
            // 客户端预检
            if (!file.type.match(/^image\/(jpeg|png|gif|webp)$/i)) {
                showError('仅支持 JPG/PNG/GIF/WEBP 图片');
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                showError('图片大小不能超过 5MB');
                return;
            }
            clearError();
            confirmBtn.disabled = true;
            confirmBtn.textContent = '上传中...';
            var formData = new FormData();
            formData.append('image', file);
            var csrfNameMeta = document.querySelector('meta[name="csrf-token-name"]');
            var csrfName = csrfNameMeta ? csrfNameMeta.getAttribute('content') : 'csrf_token';
            formData.append(csrfName, getCsrfToken());

            fetch('/api/article-inline-image.php', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(resp) {
                // 即使 4xx 也尝试解析 JSON
                return resp.json().then(function(data) {
                    return { ok: resp.ok, data: data };
                });
            })
            .then(function(result) {
                confirmBtn.disabled = false;
                confirmBtn.textContent = '插入图片';
                if (result.ok && result.data && result.data.success && result.data.url) {
                    var markdownMode = document.querySelector('input[name="content_storage"]:checked');
                    var imgTag = markdownMode && markdownMode.value === 'markdown'
                        ? '![](' + result.data.url + ')'
                        : '<img src="' + result.data.url + '" alt="" style="max-width:100%;border-radius:12px;">';
                    insertAtCursor(imgTag);
                    closeModal();
                } else {
                    showError((result.data && result.data.message) || '上传失败，请重试');
                }
            })
            .catch(function(err) {
                confirmBtn.disabled = false;
                confirmBtn.textContent = '插入图片';
                showError('网络错误：' + (err.message || err));
            });
        }

        // 直链插入
        function insertDirectUrl(url) {
            if (!url) {
                showError('请输入图片 URL');
                return;
            }
            if (!/^https?:\/\//i.test(url)) {
                showError('URL 必须以 http:// 或 https:// 开头');
                return;
            }
            clearError();
            var markdownMode = document.querySelector('input[name="content_storage"]:checked');
            var imgTag = markdownMode && markdownMode.value === 'markdown'
                ? '![](' + url + ')'
                : '<img src="' + url + '" alt="" style="max-width:100%;border-radius:12px;">';
            insertAtCursor(imgTag);
            closeModal();
        }

        // 绑定事件
        if (insertBtn) {
            insertBtn.addEventListener('click', openModal);
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }
        if (cancelBtn) {
            cancelBtn.addEventListener('click', closeModal);
        }
        if (tabUpload) {
            tabUpload.addEventListener('click', function() { switchTab('upload'); });
        }
        if (tabDirect) {
            tabDirect.addEventListener('click', function() { switchTab('direct'); });
        }
        // 点击模态框背景关闭
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeModal();
            });
        }
        // ESC 关闭
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal && modal.style.display !== 'none') {
                closeModal();
            }
        });

        // 文件选择预览
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                var file = fileInput.files && fileInput.files[0];
                if (!file) {
                    previewUploadBox.style.display = 'none';
                    return;
                }
                if (!file.type.match(/^image\//i)) {
                    showError('选择的文件不是图片');
                    previewUploadBox.style.display = 'none';
                    return;
                }
                clearError();
                var reader = new FileReader();
                reader.onload = function(ev) {
                    previewUploadImg.src = ev.target.result;
                    previewUploadBox.style.display = 'block';
                };
                reader.readAsDataURL(file);
            });
        }

        // 直链 URL 输入预览（防抖）
        if (urlInput) {
            var urlTimer = null;
            urlInput.addEventListener('input', function() {
                var url = urlInput.value.trim();
                if (urlTimer) clearTimeout(urlTimer);
                if (!url || !/^https?:\/\//i.test(url)) {
                    previewDirectBox.style.display = 'none';
                    return;
                }
                urlTimer = setTimeout(function() {
                    previewDirectImg.onerror = function() {
                        previewDirectBox.style.display = 'none';
                        showError('图片加载失败，请检查 URL');
                    };
                    previewDirectImg.onload = function() {
                        clearError();
                        previewDirectBox.style.display = 'block';
                    };
                    previewDirectImg.src = url;
                }, 400);
            });
        }

        // 确认按钮：根据当前 active tab 执行
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                // 判断当前 tab
                var isUploadActive = tabUpload && tabUpload.classList.contains('active');
                if (isUploadActive) {
                    var file = fileInput.files && fileInput.files[0];
                    if (!file) {
                        showError('请先选择图片文件');
                        return;
                    }
                    uploadAndInsert(file);
                } else {
                    var url = urlInput.value.trim();
                    insertDirectUrl(url);
                }
            });
        }
    });
})();
