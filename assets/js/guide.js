/**
 * 指引页游戏 3D Coverflow 轮盘
 * 所有封面排在 x 轴上，居中正面朝向用户，两侧绕 Y 轴旋转后退
 * 支持：自动轮播 / 拖拽切换 / 点击居中 / 左右箭头 / 键盘 ←→ / 悬停暂停 / reduced-motion 降级
 */
(function () {
    var stage = document.getElementById('guide-stage');
    var inner = document.getElementById('guide-track-inner');
    var cards = inner ? inner.querySelectorAll('.guide-card') : [];
    if (!stage || !inner || cards.length === 0) return;

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var prevBtn = document.getElementById('guide-prev');
    var nextBtn = document.getElementById('guide-next');
    var dotsWrap = document.getElementById('guide-dots');

    var current = 0;
    var autoTimer = null;
    var AUTO_INTERVAL = 4000;

    // 卡片中心间距（px）—— 两侧卡片往中间靠拢形成 coverflow 重叠
    var SPACING = 150;
    var ANGLE = 52;          // 两侧旋转角度
    var DEPTH = 140;         // 两侧后退深度
    var CENTER_Z = 70;       // 居中卡片前移
    var FAR_COUNT = 2;       // 每侧可见张数（超出则隐藏）

    // 构建指示点
    if (dotsWrap) {
        for (var i = 0; i < cards.length; i++) {
            (function (idx) {
                var dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'guide-dot';
                dot.setAttribute('aria-label', '切换到第 ' + (idx + 1) + ' 个游戏');
                dot.addEventListener('click', function () { goTo(idx); });
                dotsWrap.appendChild(dot);
            })(i);
        }
    }

    function render() {
        for (var i = 0; i < cards.length; i++) {
            var offset = i - current;
            var abs = Math.abs(offset);
            var x = offset * SPACING;
            var angle = offset === 0 ? 0 : (offset > 0 ? -ANGLE : ANGLE);
            var z = offset === 0 ? CENTER_Z : -DEPTH - (abs - 1) * 60;
            var scale = offset === 0 ? 1 : Math.max(0.7, 1 - abs * 0.08);
            var opacity = abs > FAR_COUNT ? 0 : 1 - abs * 0.15;
            var visible = abs <= FAR_COUNT;

            cards[i].style.transform =
                'translateX(' + x + 'px) translateZ(' + z + 'px) rotateY(' + angle + 'deg) scale(' + scale + ')';
            cards[i].style.opacity = visible ? Math.max(0.25, opacity) : 0;
            cards[i].style.zIndex = 100 - abs;
            cards[i].style.pointerEvents = visible ? 'auto' : 'none';
            cards[i].classList.toggle('is-active', offset === 0);
        }
        if (dotsWrap) {
            var dots = dotsWrap.querySelectorAll('.guide-dot');
            for (var d = 0; d < dots.length; d++) {
                dots[d].classList.toggle('active', d === current);
            }
        }
    }

    function goTo(idx) {
        current = ((idx % cards.length) + cards.length) % cards.length;
        render();
        restartAuto();
    }
    function next() { goTo(current + 1); }
    function prev() { goTo(current - 1); }

    function startAuto() {
        if (reduceMotion) return;
        stopAuto();
        autoTimer = setInterval(next, AUTO_INTERVAL);
    }
    function stopAuto() {
        if (autoTimer) { clearInterval(autoTimer); autoTimer = null; }
    }
    function restartAuto() {
        if (reduceMotion) return;
        stopAuto();
        startAuto();
    }

    // 按钮
    if (prevBtn) prevBtn.addEventListener('click', prev);
    if (nextBtn) nextBtn.addEventListener('click', next);

    // 键盘
    stage.setAttribute('tabindex', '0');
    stage.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft') { e.preventDefault(); prev(); }
        else if (e.key === 'ArrowRight') { e.preventDefault(); next(); }
    });

    // 点击卡片居中
    cards.forEach(function (card, idx) {
        card.addEventListener('click', function () {
            if (idx !== current) { goTo(idx); }
        });
    });

    // 悬停暂停
    stage.addEventListener('mouseenter', stopAuto);
    stage.addEventListener('mouseleave', startAuto);

    // 拖拽 / 滑动切换
    var dragging = false;
    var startX = 0;
    var startCurrent = 0;
    var moved = false;

    stage.addEventListener('pointerdown', function (e) {
        if (e.target.closest('button')) return;
        dragging = true;
        moved = false;
        startX = e.clientX;
        startCurrent = current;
        stage.setPointerCapture(e.pointerId);
        stopAuto();
    });
    stage.addEventListener('pointermove', function (e) {
        if (!dragging) return;
        var dx = e.clientX - startX;
        if (Math.abs(dx) > 8) moved = true;
        // 实时预览：每超过半个卡片宽度切一次
        var step = Math.round(-dx / (SPACING * 0.7));
        var target = (startCurrent + step + cards.length * 100) % cards.length;
        if (target !== current) {
            current = target;
            render();
        }
    });
    function endDrag(e) {
        if (!dragging) return;
        dragging = false;
        try { stage.releasePointerCapture(e.pointerId); } catch (err) {}
        startAuto();
    }
    stage.addEventListener('pointerup', endDrag);
    stage.addEventListener('pointercancel', endDrag);

    // 防止拖拽时图片被选中/拖动
    stage.addEventListener('dragstart', function (e) { e.preventDefault(); });

    // 触摸滑动时不要触发卡片点击跳转
    cards.forEach(function (card) {
        card.addEventListener('click', function (e) {
            if (moved) { e.preventDefault(); e.stopPropagation(); }
        }, true);
    });

    // 窗口失焦暂停
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) stopAuto(); else startAuto();
    });

    // 初始化
    render();
    startAuto();

    // 窗口尺寸变化时重绘（卡片间距可能需要调整）
    window.addEventListener('resize', function () {
        if (window.innerWidth < 600) { SPACING = 110; } else { SPACING = 150; }
        render();
    });
    if (window.innerWidth < 600) { SPACING = 110; render(); }
})();
