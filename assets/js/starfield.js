;(function(){
  'use strict';

  var MOBILE_BREAKPOINT = 768;
  if (window.innerWidth < MOBILE_BREAKPOINT) return;
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  var canvas = document.createElement('canvas');
  canvas.className = 'starfield-canvas';
  document.body.appendChild(canvas);
  var ctx = canvas.getContext('2d');

  var w, h, stars = [], meteors = [];
  var STAR_DENSITY = 0.00008;
  var MAX_STARS = 180;
  var METEOR_CHANCE = 0.003;
  var MAX_METEORS = 3;

  function getTheme() {
    var attr = document.documentElement.getAttribute('data-theme');
    if (attr === 'dark') return 'dark';
    if (attr === 'light') return 'light';
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) return 'dark';
    return 'light';
  }

  function themeColor() {
    return getTheme() === 'dark'
      ? { star: [240, 230, 220], meteor: [255, 255, 245], alpha: 0.85 }
      : { star: [184, 114, 45], meteor: [184, 114, 45], alpha: 0.55 };
  }

  function rand(min, max) { return Math.random() * (max - min) + min; }

  function createStar() {
    return {
      x: rand(0, w),
      y: rand(0, h),
      size: rand(0.6, 2.2),
      baseAlpha: rand(0.15, 0.45),
      phase: rand(0, Math.PI * 2),
      speed: rand(0.0005, 0.002)
    };
  }

  function createMeteor() {
    var fromTop = Math.random() > 0.5;
    return {
      x: fromTop ? rand(w * 0.1, w * 0.9) : -160,
      y: fromTop ? -160 : rand(0, h * 0.5),
      angle: rand(Math.PI / 5, Math.PI / 2.5),
      len: rand(90, 220),
      speed: rand(6, 12),
      life: 1,
      decay: rand(0.008, 0.018)
    };
  }

  function resize() {
    w = window.innerWidth;
    h = window.innerHeight;
    canvas.width = w;
    canvas.height = h;
    var count = Math.min(MAX_STARS, Math.max(60, Math.floor(w * h * STAR_DENSITY)));
    stars = [];
    for (var i = 0; i < count; i++) stars.push(createStar());
  }

  function drawMeteor(m, color) {
    var x2 = m.x - Math.cos(m.angle) * m.len;
    var y2 = m.y - Math.sin(m.angle) * m.len;
    var grad = ctx.createLinearGradient(m.x, m.y, x2, y2);
    grad.addColorStop(0, 'rgba(' + color.join(',') + ',' + (m.life * 0.95) + ')');
    grad.addColorStop(0.4, 'rgba(' + color.join(',') + ',' + (m.life * 0.5) + ')');
    grad.addColorStop(1, 'rgba(' + color.join(',') + ',0)');
    ctx.strokeStyle = grad;
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.beginPath();
    ctx.moveTo(m.x, m.y);
    ctx.lineTo(x2, y2);
    ctx.stroke();
  }

  function loop(timestamp) {
    var theme = themeColor();
    ctx.clearRect(0, 0, w, h);

    // 星星闪烁
    for (var i = 0; i < stars.length; i++) {
      var s = stars[i];
      var twinkle = Math.sin(timestamp * s.speed + s.phase);
      var alpha = Math.max(0, Math.min(theme.alpha, s.baseAlpha + twinkle * 0.12));
      ctx.fillStyle = 'rgba(' + theme.star.join(',') + ',' + alpha + ')';
      ctx.beginPath();
      ctx.arc(s.x, s.y, s.size, 0, Math.PI * 2);
      ctx.fill();
    }

    // 流星
    for (var j = meteors.length - 1; j >= 0; j--) {
      var m = meteors[j];
      m.x += Math.cos(m.angle) * m.speed;
      m.y += Math.sin(m.angle) * m.speed;
      m.life -= m.decay;
      if (m.life <= 0 || m.x > w + 200 || m.y > h + 200) {
        meteors.splice(j, 1);
        continue;
      }
      drawMeteor(m, theme.meteor);
    }
    if (meteors.length < MAX_METEORS && Math.random() < METEOR_CHANCE) {
      meteors.push(createMeteor());
    }

    requestAnimationFrame(loop);
  }

  // 监听主题变化，星星颜色会随主题切换
  var themeObserver = new MutationObserver(function() {
    // themeColor 在每一帧都会读取，无需额外处理
  });
  themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

  window.addEventListener('resize', resize);
  resize();
  requestAnimationFrame(loop);
})();
