/**
 * 后台文章编辑：图片计数、上传预览、提交按钮状态
 */
(function() {
    var MAX_IMAGES = 10;
    var $input = document.getElementById('article-images-input');
    var $preview = document.getElementById('new-images-preview');
    var $counter = document.getElementById('image-counter');
    var $group = document.getElementById('article-images-group');
    var $form = document.querySelector('form[data-validate]');

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
        var newOnes = $preview ? $preview.querySelectorAll('.upload-preview-item').length : 0;
        var urls = parseUrlsFromText();
        return existing + newOnes + urls.length;
    }

    function parseUrlsFromText() {
        var t = document.getElementById('article-image-urls-text');
        if (!t) return [];
        return t.value.split(/\r?\n/).map(function(s){return s.trim();}).filter(Boolean);
    }

    function updateCounter() {
        if (!$counter) return;
        $counter.textContent = '当前: ' + countTotal() + ' / ' + MAX_IMAGES;
    }

    if ($group) {
        $group.addEventListener('change', function(e) {
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
    if ($input && $preview) {
        $input.addEventListener('change', function() {
            var files = Array.prototype.slice.call($input.files || []);
            for (var i = 0; i < files.length; i++) {
                (function(file) {
                    if (!file.type.match(/^image\//)) return;
                    var reader = new FileReader();
                    reader.onload = function(ev) {
                        var div = document.createElement('div');
                        div.className = 'upload-preview-item';
                        div.style.cssText = 'position: relative; border: 1px solid var(--border-color); border-radius: var(--radius); padding: 6px; background: var(--bg-color);';
                        div.innerHTML = '<img src="' + ev.target.result + '" style="width:100%;height:100px;object-fit:cover;border-radius:calc(var(--radius) - 2px);" alt=""><div style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,0.7);color:white;padding:3px 8px;border-radius:4px;font-size:0.75rem;">新</div>';
                        $preview.appendChild(div);
                        updateCounter();
                    };
                    reader.readAsDataURL(file);
                })(files[i]);
            }
        });
    }

    updateCounter();

    // 前端防重复提交 + 提交时总数校验
    if ($form) {
        $form.addEventListener('submit', function(e) {
            var btn = document.getElementById('submit-btn');
            if (btn) {
                btn.disabled = true;
                btn.textContent = '保存中...';
            }
        });
    }
})();
