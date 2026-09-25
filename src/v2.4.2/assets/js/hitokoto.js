/**
 * 一言组件
 */
(function() {
    var hitokotoText = document.getElementById('hitokoto-text');
    var hitokotoFrom = document.getElementById('hitokoto-from');
    if (!hitokotoText) return;

    function fetchHitokoto() {
        fetch('/api/hitokoto.php')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                hitokotoText.style.opacity = '0';
                setTimeout(function() {
                    hitokotoText.textContent = data.hitokoto || '';
                    hitokotoFrom.textContent = data.from ? '—— ' + data.from : '';
                    hitokotoText.style.opacity = '1';
                }, 300);
            })
            .catch(function() {
                hitokotoText.textContent = '生活不止眼前的代码，还有远方的Bug。';
                hitokotoFrom.textContent = '—— 林梦博客';
            });
    }

    hitokotoText.style.transition = 'opacity 0.3s ease';
    fetchHitokoto();
    setInterval(fetchHitokoto, 30000);
})();
