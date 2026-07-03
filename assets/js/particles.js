;(function () {
  'use strict';

  var MOBILE_BREAKPOINT = 768;
  var BLOB_COUNT = 4;
  var animId;
  var canvas, ctx;
  var blobs = [];
  var w, h;

  var themes = {
    light: [
      { r: 194, g: 122, b: 58 },
      { r: 45,  g: 138, b: 110 },
      { r: 212, g: 148, b: 78 },
      { r: 194, g: 122, b: 58 }
    ],
    dark: [
      { r: 160, g: 90,  b: 40 },
      { r: 30,  g: 100, b: 80 },
      { r: 180, g: 110, b: 50 },
      { r: 160, g: 90,  b: 40 }
    ]
  };

  function getTheme() {
    var attr = document.documentElement.getAttribute('data-theme');
    if (attr === 'dark' || attr === 'light') return attr;
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) return 'dark';
    return 'light';
  }

  function rand(min, max) {
    return Math.random() * (max - min) + min;
  }

  function createBlob(index) {
    var palette = themes[getTheme()];
    var color = palette[index % palette.length];
    var baseSize = Math.min(w, h) * rand(0.35, 0.55);

    return {
      x: rand(0, w),
      y: rand(0, h),
      vx: rand(-0.15, 0.15),
      vy: rand(-0.15, 0.15),
      baseSize: baseSize,
      sizeVar: rand(0.8, 1.2),
      phase: rand(0, Math.PI * 2),
      speed: rand(0.0003, 0.0008),
      color: color,
      alpha: rand(0.03, 0.06)
    };
  }

  function resize() {
    w = window.innerWidth;
    h = window.innerHeight;
    canvas.width = w;
    canvas.height = h;
  }

  function draw() {
    ctx.clearRect(0, 0, w, h);

    var now = Date.now();
    var theme = getTheme();
    var palette = themes[theme];

    for (var i = 0; i < blobs.length; i++) {
      var b = blobs[i];

      b.x += b.vx;
      b.y += b.vy;

      if (b.x < -b.baseSize) b.x = w + b.baseSize;
      if (b.x > w + b.baseSize) b.x = -b.baseSize;
      if (b.y < -b.baseSize) b.y = h + b.baseSize;
      if (b.y > h + b.baseSize) b.y = -b.baseSize;

      var breathe = Math.sin(now * b.speed + b.phase);
      var radius = b.baseSize * (b.sizeVar + breathe * 0.15);

      var color = palette[i % palette.length];
      b.color = color;

      var gradient = ctx.createRadialGradient(b.x, b.y, 0, b.x, b.y, radius);
      var alpha = b.alpha;
      gradient.addColorStop(0, 'rgba(' + color.r + ',' + color.g + ',' + color.b + ',' + alpha + ')');
      gradient.addColorStop(0.5, 'rgba(' + color.r + ',' + color.g + ',' + color.b + ',' + (alpha * 0.5) + ')');
      gradient.addColorStop(1, 'rgba(' + color.r + ',' + color.g + ',' + color.b + ',0)');

      ctx.fillStyle = gradient;
      ctx.beginPath();
      ctx.arc(b.x, b.y, radius, 0, Math.PI * 2);
      ctx.fill();
    }

    animId = requestAnimationFrame(draw);
  }

  function init() {
    if (window.innerWidth < MOBILE_BREAKPOINT) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    if (animId) return;

    canvas = document.createElement('canvas');
    canvas.className = 'particles-canvas';
    canvas.style.cssText = 'position:fixed;top:0;left:0;z-index:0;pointer-events:none;';
    document.body.appendChild(canvas);
    ctx = canvas.getContext('2d');

    resize();

    blobs = [];
    for (var i = 0; i < BLOB_COUNT; i++) {
      blobs.push(createBlob(i));
    }

    draw();
  }

  function destroy() {
    if (animId) {
      cancelAnimationFrame(animId);
      animId = null;
    }
    if (canvas && canvas.parentNode) {
      canvas.parentNode.removeChild(canvas);
      canvas = null;
      ctx = null;
    }
    blobs = [];
  }

  window.addEventListener('resize', function () {
    if (!canvas) return;
    if (window.innerWidth < MOBILE_BREAKPOINT) {
      destroy();
    } else {
      resize();
    }
  });

  window.initParticles = init;

  document.addEventListener('DOMContentLoaded', init);
})();
