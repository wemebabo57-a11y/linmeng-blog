/**
 * 后台图库管理脚本
 */
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-copy-url]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var url = btn.getAttribute('data-copy-url');
                copyUrl(url);
            });
        });
    });

    function copyUrl(url) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function() {
                alert('链接已复制到剪贴板');
            });
        } else {
            var ta = document.createElement('textarea');
            ta.value = url;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            alert('链接已复制到剪贴板');
        }
    }
})();
