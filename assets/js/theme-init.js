/**
 * 主题初始化（在页面渲染前执行，避免闪烁）
 */
(function() {
    var theme = localStorage.getItem('theme') || 'auto';
    if (theme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
    } else if (theme === 'light') {
        document.documentElement.setAttribute('data-theme', 'light');
    }
})();
