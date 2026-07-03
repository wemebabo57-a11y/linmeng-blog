/**
 * 工具页交互脚本 v1.1
 * - 蓝奏云直链解析表单提交（AJAX）
 * - 复制直链到剪贴板
 * - 健壮的错误处理（兼容非 JSON 响应）
 */
;(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    initLanzouForm();
  });

  // ===== 蓝奏云解析表单 =====
  function initLanzouForm() {
    var form = document.getElementById('lanzou-form');
    if (!form) return;

    var submitBtn = document.getElementById('lanzou-submit');
    var btnText = submitBtn ? submitBtn.querySelector('.btn-text') : null;
    var btnLoading = submitBtn ? submitBtn.querySelector('.btn-loading') : null;
    var resultBox = document.getElementById('lanzou-result');
    var resultBody = document.getElementById('lanzou-result-body');
    var resultTime = document.getElementById('lanzou-result-time');
    var errorBox = document.getElementById('lanzou-error');
    var typeSelect = document.getElementById('lanzou-type');
    var urlInput = document.getElementById('lanzou-url');
    var pwdInput = document.getElementById('lanzou-pwd');

    // CSRF Token：从 meta 标签读取（与 gallery.js / main.js 一致）
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfNameMeta = document.querySelector('meta[name="csrf-token-name"]');
    var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
    var csrfTokenName = csrfNameMeta ? csrfNameMeta.getAttribute('content') : 'lm_csrf_token';

    var apiPath = form.getAttribute('data-api') || '/api/lanzou-parse.php';

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      hideError();
      hideResult();

      var url = urlInput ? urlInput.value.trim() : '';
      var pwd = pwdInput ? pwdInput.value.trim() : '';
      var type = typeSelect ? typeSelect.value : '';

      if (!url) {
        showError('请填写文件链接');
        return;
      }

      setLoading(true);

      // 构建 FormData，同时通过 header 发送 CSRF token（双重保障）
      var formData = new FormData();
      formData.append('url', url);
      if (pwd) formData.append('pwd', pwd);
      if (type) formData.append('type', type);
      formData.append(csrfTokenName, csrfToken);

      fetch(apiPath, {
        method: 'POST',
        body: formData,
        headers: {
          'X-CSRF-Token': csrfToken,
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      })
        .then(function (r) {
          // 先读原始文本，再尝试 JSON 解析（兼容 PHP 错误页面等非 JSON 响应）
          return r.text().then(function (text) {
            if (!text || text.trim() === '') {
              return { success: false, message: '服务器返回空响应，请检查 PHP 错误日志' };
            }
            try {
              return JSON.parse(text);
            } catch (e) {
              console.error('[tools] 非JSON响应:', text.substring(0, 500));
              return { success: false, message: '服务器返回了异常响应，请检查后台日志或 PHP 扩展（cURL）' };
            }
          });
        })
        .then(function (data) {
          setLoading(false);
          if (data && data.success && data.downurl) {
            showResult(data);
            // down 模式：自动打开下载直链
            if (type === 'down') {
              window.open(data.downurl, '_blank', 'noopener');
            }
          } else {
            showError((data && data.message) || '解析失败，请稍后重试');
          }
        })
        .catch(function (err) {
          setLoading(false);
          console.error('[tools] 请求异常:', err);
          showError('网络请求失败：' + (err && err.message ? err.message : '未知错误'));
        });
    });

    function setLoading(loading) {
      if (!submitBtn) return;
      submitBtn.disabled = loading;
      if (btnText && btnLoading) {
        btnText.style.display = loading ? 'none' : '';
        btnLoading.style.display = loading ? 'inline-flex' : 'none';
      }
    }

    function showError(msg) {
      if (!errorBox) return;
      errorBox.textContent = msg;
      errorBox.style.display = 'block';
      errorBox.style.cssText = 'display:block; margin-top:16px;';
    }

    function hideError() {
      if (!errorBox) return;
      errorBox.style.display = 'none';
      errorBox.textContent = '';
    }

    function hideResult() {
      if (!resultBox) return;
      resultBox.style.display = 'none';
      if (resultBody) resultBody.innerHTML = '';
    }

    function showResult(data) {
      if (!resultBox || !resultBody) return;

      var html = '';
      html += '<div class="result-row">';
      html += '<span class="result-label">文件名称</span>';
      html += '<span class="result-value">' + escapeHtml(data.name || '—') + '</span>';
      html += '</div>';

      html += '<div class="result-row">';
      html += '<span class="result-label">文件大小</span>';
      html += '<span class="result-value">' + escapeHtml(data.filesize || '—') + '</span>';
      html += '</div>';

      html += '<div class="result-row result-row-downurl">';
      html += '<span class="result-label">下载直链</span>';
      html += '<div class="result-downurl-wrap">';
      html += '<input type="text" class="result-downurl-input" value="' + escapeHtml(data.downurl || '') + '" readonly>';
      html += '<button type="button" class="btn btn-primary btn-sm result-copy-btn" data-url="' + escapeHtml(data.downurl || '') + '">复制</button>';
      html += '</div>';
      html += '</div>';

      html += '<div class="result-actions">';
      html += '<a href="' + escapeHtml(data.downurl || '#') + '" class="btn btn-primary" target="_blank" rel="noopener">';
      html += '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
      html += ' 下载文件</a>';
      html += '</div>';

      resultBody.innerHTML = html;
      if (resultTime) resultTime.textContent = data.time || '';

      resultBox.style.display = 'block';
      resultBox.style.cssText = 'display:block;';
      try {
        resultBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      } catch (e) {}

      // 绑定复制按钮
      var copyBtn = resultBody.querySelector('.result-copy-btn');
      if (copyBtn) {
        copyBtn.addEventListener('click', function () {
          var url = copyBtn.getAttribute('data-url') || '';
          copyToClipboard(url).then(function () {
            var originalText = copyBtn.textContent;
            copyBtn.textContent = '已复制';
            copyBtn.classList.add('copied');
            setTimeout(function () {
              copyBtn.textContent = originalText;
              copyBtn.classList.remove('copied');
            }, 1500);
          }).catch(function () {
            var input = resultBody.querySelector('.result-downurl-input');
            if (input) {
              input.select();
              try { document.execCommand('copy'); } catch (e) {}
            }
          });
        });
      }
    }
  }

  // ===== 工具函数 =====
  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function (resolve, reject) {
      try {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        resolve();
      } catch (e) {
        reject(e);
      }
    });
  }
})();
