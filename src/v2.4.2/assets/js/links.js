;(function(){
  'use strict';

  document.addEventListener('DOMContentLoaded', function(){
    var search = document.getElementById('links-search');
    var grid   = document.getElementById('links-grid');
    var cards  = grid ? Array.prototype.slice.call(grid.querySelectorAll('.link-card')) : [];
    var count  = document.getElementById('links-count');
    var btn    = document.getElementById('links-search-btn');

    // 搜索过滤
    function doSearch() {
      var kw = search ? search.value.trim().toLowerCase() : '';
      var visible = 0;
      cards.forEach(function(card){
        var text = card.dataset.keywords || '';
        var show = !kw || text.indexOf(kw) !== -1;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
      });
      if (count) count.textContent = visible;
    }

    if (search) {
      search.addEventListener('input', doSearch);
      search.addEventListener('keydown', function(e){
        if (e.key === 'Escape') {
          search.value = '';
          doSearch();
        }
      });
    }
    if (btn) btn.addEventListener('click', doSearch);

    // Ctrl/⌘ + K 聚焦搜索
    document.addEventListener('keydown', function(e){
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        if (search) search.focus();
      }
    });

    // 复制本站信息
    var copyBtn = document.getElementById('copy-site-info');
    if (copyBtn) {
      copyBtn.addEventListener('click', function(){
        var text = copyBtn.dataset.info;
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(function(){
            if (typeof showMessage === 'function') {
              showMessage('已复制本站信息，快去交换友链吧', 'success');
            } else {
              var original = copyBtn.textContent;
              copyBtn.textContent = '已复制';
              setTimeout(function(){ copyBtn.textContent = original; }, 1500);
            }
          }).catch(function(){
            fallbackCopy(text, copyBtn);
          });
        } else {
          fallbackCopy(text, copyBtn);
        }
      });
    }

    function fallbackCopy(text, btnEl) {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      try {
        document.execCommand('copy');
        if (typeof showMessage === 'function') {
          showMessage('已复制本站信息，快去交换友链吧', 'success');
        } else {
          var original = btnEl.textContent;
          btnEl.textContent = '已复制';
          setTimeout(function(){ btnEl.textContent = original; }, 1500);
        }
      } catch (err) {
        // ignore
      }
      document.body.removeChild(ta);
    }

    // 卡片 3D 微倾斜（仅非触屏且未要求减少动画）
    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var isTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

    if (!reducedMotion && !isTouch) {
      cards.forEach(function(card){
        card.addEventListener('mousemove', function(e){
          var rect = card.getBoundingClientRect();
          var dx = (e.clientX - rect.left - rect.width / 2) / (rect.width / 2);
          var dy = (e.clientY - rect.top - rect.height / 2) / (rect.height / 2);
          card.style.transform = 'translateY(-6px) scale(1.01) rotateX(' + (-dy * 4) + 'deg) rotateY(' + (dx * 4) + 'deg)';
        });
        card.addEventListener('mouseleave', function(){
          card.style.transform = '';
        });
      });
    }
  });
})();
