/**
 * 前台图库上传、复制链接、查看大图
 */
(function() {
    var uploadArea = document.getElementById('uploadArea');
    var fileInput = document.getElementById('fileInput');
    var uploadStatus = document.getElementById('uploadStatus');
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    if (!uploadArea) return;

    var maxSize = parseInt(uploadArea.dataset.maxSize || '0', 10);
    var maxSizeText = uploadArea.dataset.maxSizeText || '';

    // 点击上传
    uploadArea.addEventListener('click', function(e) {
        if (e.target.closest('.gallery-btn')) return;
        fileInput.click();
    });

    // 文件选择
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                uploadFile(this.files[0]);
            }
        });
    }

    // 拖拽上传
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
    });
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        var files = e.dataTransfer.files;
        if (files.length > 0) {
            uploadFile(files[0]);
        }
    });

    function uploadFile(file) {
        var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            showStatus('不支持的文件类型，请上传图片文件', 'error');
            return;
        }
        if (maxSize > 0 && file.size > maxSize) {
            showStatus('文件大小超过 ' + maxSizeText + 'MB 限制', 'error');
            return;
        }

        var formData = new FormData();
        formData.append('file', file);
        formData.append('lm_csrf_token', csrfToken);

        showStatus('正在上传...', 'info');

        fetch('/api/github-upload.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': csrfToken
            }
        })
        .then(async function(r) {
            var text = await r.text();
            try {
                return JSON.parse(text);
            } catch(e) {
                console.error('API 原始响应:', text.substring(0, 500));
                return { success: false, message: '服务器返回了异常响应，请检查后台日志' };
            }
        })
        .then(function(data) {
            if (data.success) {
                showStatus('上传成功！', 'success');
                setTimeout(function() { location.reload(); }, 800);
            } else {
                showStatus(data.message || '上传失败', 'error');
            }
        })
        .catch(function(err) {
            showStatus('上传出错: ' + err.message, 'error');
        });
    }

    function showStatus(msg, type) {
        if (!uploadStatus) return;
        uploadStatus.style.display = 'block';
        var colors = {
            success: '#10b981',
            error: '#ef4444',
            info: '#3b82f6'
        };
        var color = colors[type] || colors.info;
        uploadStatus.innerHTML = '<div style="padding: 12px 16px; border-radius: var(--radius); background: ' + color + '15; color: ' + color + '; border: 1px solid ' + color + '30;">' + msg + '</div>';
    }

    // 复制链接
    document.querySelectorAll('.gallery-btn-copy').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var url = this.dataset.url;
            if (url && navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function() {
                    var original = this.innerHTML;
                    this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
                    setTimeout(function() { this.innerHTML = original; }.bind(this), 1500);
                }.bind(this));
            }
        });
    });

    // 查看大图（使用灯箱）
    document.querySelectorAll('.gallery-btn-view').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var src = this.dataset.src;
            if (src) {
                var lightbox = document.querySelector('.lightbox');
                var lightboxImg = lightbox ? lightbox.querySelector('img') : null;
                if (lightboxImg) {
                    lightboxImg.src = src;
                    lightbox.classList.add('active');
                }
            }
        });
    });
})();
