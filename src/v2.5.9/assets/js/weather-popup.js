/**
 * 天气欢迎弹窗 + 实时天气背景
 * 纯原生 JS，自执行函数
 * - 每天首次访问弹出一次："欢迎来自 XX 的朋友，目前天气 XX"
 * - 背景（body[data-weather]）随天气切换色调；雨天/雪天附加轻量粒子动画
 * - 数据来自 /api/weather.php（心知天气代理，服务端 IP 定位）
 */
;(function () {
    'use strict';

    // ========== 配置 ==========
    var CACHE_KEY = 'app_weather_data_v2';        // 天气数据缓存（30 分钟）；v2：旧版缓存可能存有定位失败的兜底城市，换键作废
    var POPUP_DATE_KEY = 'app_weather_popup_date'; // 每日弹窗标记（YYYY-MM-DD）
    var CACHE_TTL = 30 * 60 * 1000;             // 天气数据缓存有效期：30 分钟
    var AUTO_CLOSE_MS = 10000;                  // 弹窗自动关闭：10 秒

    // ========== 样式自注入（CDN 兼容） ==========
    // 不依赖 theme-refresh.css：即使 CDN 返回旧版缓存的 CSS，
    // 弹窗与天气背景的样式也由本脚本完整注入，保证排版始终正常。
    var STYLE_ID = 'weather-popup-styles';

    function injectStyles() {
        if (document.getElementById(STYLE_ID)) return;

        var css = ''
            + '/* ---- 天气弹窗 ---- */'
            + '.weather-popup{position:fixed;inset:0;z-index:1005;display:flex;align-items:center;justify-content:center;padding:24px;background:rgba(24,16,22,0.32);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);opacity:0;transition:opacity .3s ease;}'
            + '.weather-popup.is-visible{opacity:1;}'
            + '.weather-popup-card{position:relative;width:min(340px,90vw);padding:46px 28px 30px;border-radius:20px;background:linear-gradient(145deg,rgba(255,255,255,.44),rgba(255,255,255,.18));border:1px solid rgba(255,255,255,.68);box-shadow:0 24px 70px rgba(12,25,42,.28),inset 0 1px 0 rgba(255,255,255,.72);backdrop-filter:blur(24px) saturate(145%);-webkit-backdrop-filter:blur(24px) saturate(145%);text-align:center;overflow:hidden;transform:translateY(14px) scale(.96);transition:transform .35s cubic-bezier(.22,.61,.36,1);}'
            + '.weather-popup.is-visible .weather-popup-card{transform:translateY(0) scale(1);}'
            + '.weather-popup-deco{position:absolute;inset:0 0 auto 0;height:132px;z-index:0;pointer-events:none;background:radial-gradient(circle at 50% -20%,var(--weather-glow,rgba(228,87,87,.22)),transparent 70%);}'
            + '.weather-popup-card[data-weather="clear"]{--weather-glow:rgba(255,190,80,.22);}'
            + '.weather-popup-card[data-weather="cloudy"]{--weather-glow:rgba(148,173,199,.20);}'
            + '.weather-popup-card[data-weather="overcast"]{--weather-glow:rgba(128,142,158,.22);}'
            + '.weather-popup-card[data-weather="fog"]{--weather-glow:rgba(176,186,196,.24);}'
            + '.weather-popup-card[data-weather="haze"]{--weather-glow:rgba(174,158,128,.22);}'
            + '.weather-popup-card[data-weather="rain"]{--weather-glow:rgba(96,137,178,.22);}'
            + '.weather-popup-card[data-weather="thunder"]{--weather-glow:rgba(106,106,158,.24);}'
            + '.weather-popup-card[data-weather="snow"]{--weather-glow:rgba(168,198,226,.26);}'
            + '.weather-popup-close{position:absolute;top:12px;right:12px;width:32px;height:32px;border:none;border-radius:50%;background:transparent;color:#999;font-size:1.4rem;line-height:1;cursor:pointer;transition:color .2s,background .2s;}'
            + '.weather-popup-close:hover,.weather-popup-close:focus-visible{color:#333;background:rgba(0,0,0,.06);outline:none;}'
            + '.weather-popup-greeting{position:relative;font-size:.98rem;color:#6b6b76;margin-bottom:26px;letter-spacing:.02em;}'
            + '.weather-popup-city{color:#e45757;font-weight:700;}'
            + '.weather-popup-hero{position:relative;display:flex;align-items:center;justify-content:center;gap:14px;margin-bottom:10px;}'
            + '.weather-popup-icon{position:relative;display:grid;place-items:center;width:76px;height:76px;border:1px solid rgba(255,255,255,.48);border-radius:50%;background:rgba(255,255,255,.16);color:#31516f;font-size:1.25rem;font-weight:700;line-height:1;letter-spacing:.08em;filter:drop-shadow(0 6px 14px rgba(0,0,0,.16));animation:weatherIconFloat 3.2s ease-in-out infinite;}'
            + '.weather-popup-temp{font-size:3.4rem;font-weight:200;color:#2a2a33;line-height:1;letter-spacing:-.02em;font-variant-numeric:tabular-nums;}'
            + '.weather-popup-temp i{font-style:normal;font-size:1.5rem;font-weight:300;color:#9a9aa5;margin-left:2px;}'
            + '.weather-popup-desc{position:relative;font-size:1rem;color:#6b6b76;}'
            + '.weather-popup-timer{position:absolute;left:0;right:0;bottom:0;height:3px;background:rgba(0,0,0,.06);}'
            + '.weather-popup-timer span{display:block;height:100%;width:100%;background:linear-gradient(90deg,#e45757,#c9765a);transform-origin:left center;animation:weatherTimerShrink 10s linear forwards;}'
            + '@keyframes weatherIconFloat{0%,100%{transform:translateY(0);}50%{transform:translateY(-6px);}}'
            + '@keyframes weatherTimerShrink{from{transform:scaleX(1);}to{transform:scaleX(0);}}'
            + '@keyframes weatherGlassRain{to{transform:translate3d(28px,145%,0) rotate(14deg);}}'
            + '.weather-popup-glass-shine{position:absolute;inset:0;pointer-events:none;background:linear-gradient(115deg,rgba(255,255,255,.28),transparent 32%,transparent 68%,rgba(255,255,255,.10));mix-blend-mode:screen;}'
            + '.weather-popup-rain{position:absolute;inset:0;z-index:1;pointer-events:none;overflow:hidden;opacity:0;transition:opacity .3s ease;}'
            + '.weather-popup-greeting,.weather-popup-hero,.weather-popup-desc,.weather-popup-close,.weather-popup-timer{z-index:3;}'
            + '.weather-popup-card[data-weather=rain] .weather-popup-rain,.weather-popup-card[data-weather=thunder] .weather-popup-rain{opacity:1;}'
            + '.weather-popup-rain span{position:absolute;top:-18%;width:1px;height:54px;border-radius:99px;background:linear-gradient(180deg,transparent,rgba(224,241,255,.78));transform:rotate(14deg);animation:weatherGlassRain 1.35s linear infinite;filter:blur(.15px);}'
            + '.weather-popup-rain span:nth-child(3n){height:38px;animation-duration:1.05s;opacity:.7;}'
            + '.weather-popup-rain span:nth-child(4n){height:70px;animation-duration:1.7s;opacity:.45;}'
            + '/* 深色主题下的卡片配色 */'
            + 'html[data-theme="dark"] .weather-popup-card{background:linear-gradient(145deg,rgba(38,48,62,.68),rgba(20,28,40,.52));border-color:rgba(255,255,255,.18);box-shadow:0 24px 70px rgba(0,0,0,.38),inset 0 1px 0 rgba(255,255,255,.18);}'
            + 'html[data-theme="dark"] .weather-popup-greeting{color:#a8a8b4;}'
            + 'html[data-theme="dark"] .weather-popup-icon{color:#d9e9f8;border-color:rgba(255,255,255,.22);background:rgba(255,255,255,.10);}'
            + 'html[data-theme="dark"] .weather-popup-temp{color:#f0f0f5;}'
            + 'html[data-theme="dark"] .weather-popup-temp i{color:#7a7a88;}'
            + 'html[data-theme="dark"] .weather-popup-desc{color:#b8b8c4;}'
            + 'html[data-theme="dark"] .weather-popup-close{color:#888;}'
            + 'html[data-theme="dark"] .weather-popup-close:hover{color:#ccc;background:rgba(255,255,255,0.08);}'
            + 'html[data-theme="dark"] .weather-popup-timer{background:rgba(255,255,255,0.08);}'
            + 'html[data-theme="dark"] .weather-popup-city{color:#ff8f8f;}'
            + '/* ---- 实时天气背景（未设置自定义背景图时生效） ---- */'
            + 'body[data-weather]:not(.has-site-background){background-image:var(--weather-bg);background-attachment:fixed;}'
            + 'body[data-weather="clear"]:not(.has-site-background){--weather-bg:linear-gradient(180deg,#bfe0ff 0%,#e3f0fb 34%,var(--bg-color,#f5f5f7) 78%);}'
            + 'body[data-weather="cloudy"]:not(.has-site-background){--weather-bg:linear-gradient(180deg,#cdd9e6 0%,#e4ebf2 34%,var(--bg-color,#f5f5f7) 78%);}'
            + 'body[data-weather="overcast"]:not(.has-site-background){--weather-bg:linear-gradient(180deg,#b8c2cc 0%,#d7dde3 34%,var(--bg-color,#f5f5f7) 78%);}'
            + 'body[data-weather="fog"]:not(.has-site-background){--weather-bg:linear-gradient(180deg,#c9d2d9 0%,#e2e7ea 34%,var(--bg-color,#f5f5f7) 78%);}'
            + 'body[data-weather="haze"]:not(.has-site-background){--weather-bg:linear-gradient(180deg,#cec3a8 0%,#e4ddca 34%,var(--bg-color,#f5f5f7) 78%);}'
            + 'body[data-weather="rain"]:not(.has-site-background){--weather-bg:linear-gradient(180deg,#8fa8c2 0%,#b9cadb 34%,var(--bg-color,#f5f5f7) 78%);}'
            + 'body[data-weather="thunder"]:not(.has-site-background){--weather-bg:linear-gradient(180deg,#8188a8 0%,#adb4c9 34%,var(--bg-color,#f5f5f7) 78%);}'
            + 'body[data-weather="snow"]:not(.has-site-background){--weather-bg:linear-gradient(180deg,#a8c4de 0%,#d3e2ef 34%,var(--bg-color,#f5f5f7) 78%);}'
            + 'html[data-theme="dark"] body[data-weather="clear"]:not(.has-site-background){--weather-bg:linear-gradient(180deg,#1d2b45 0%,#16202f 40%,var(--bg-color,#141420) 82%);}'
            + 'html[data-theme="dark"] body[data-weather="cloudy"]:not(.has-site-background),html[data-theme="dark"] body[data-weather="overcast"]:not(.has-site-background){--weather-bg:linear-gradient(180deg,#232a34 0%,#1a2028 40%,var(--bg-color,#141420) 82%);}'
            + 'html[data-theme="dark"] body[data-weather="fog"]:not(.has-site-background),html[data-theme="dark"] body[data-weather="haze"]:not(.has-site-background){--weather-bg:linear-gradient(180deg,#2b2d31 0%,#202225 40%,var(--bg-color,#141420) 82%);}'
            + 'html[data-theme="dark"] body[data-weather="rain"]:not(.has-site-background),html[data-theme="dark"] body[data-weather="thunder"]:not(.has-site-background){--weather-bg:linear-gradient(180deg,#1b2531 0%,#141c25 40%,var(--bg-color,#141420) 82%);}'
            + 'html[data-theme="dark"] body[data-weather="snow"]:not(.has-site-background){--weather-bg:linear-gradient(180deg,#233246 0%,#1a2432 40%,var(--bg-color,#141420) 82%);}'
            + '/* ---- 雨/雪粒子画布 ---- */'
            + '.weather-fx-canvas{position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:1;overflow:hidden;}'
            + '@media (prefers-reduced-motion:reduce){.weather-popup-icon{animation:none;}.weather-popup-rain span{animation:none !important;display:none;}.weather-fx-canvas{display:none !important;}}';

        var style = document.createElement('style');
        style.id = STYLE_ID;
        style.textContent = css;
        document.head.appendChild(style);
    }

    // ========== 工具 ==========

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str == null ? '' : str)));
        return div.innerHTML;
    }

    function localDateKey() {
        var d = new Date();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + day;
    }

    function prefersReducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    /**
     * 按天气文本/心知 code 归类
     * 文本优先（心知返回简体中文描述），code 兜底
     */
    function categorize(text, code) {
        text = String(text || '');
        code = parseInt(code, 10);

        if (text.indexOf('雪') !== -1) return 'snow';
        if (text.indexOf('雷') !== -1 || text.indexOf('冰雹') !== -1) return 'thunder';
        if (text.indexOf('雨') !== -1) return 'rain';
        if (text.indexOf('雾') !== -1) return 'fog';
        if (text.indexOf('霾') !== -1 || text.indexOf('沙') !== -1 || text.indexOf('尘') !== -1) return 'haze';
        if (text.indexOf('阴') !== -1) return 'overcast';
        if (text.indexOf('云') !== -1) return 'cloudy';
        if (text.indexOf('晴') !== -1) return 'clear';

        // 文本无法判断时按心知 code 兜底
        switch (code) {
            case 0: case 1: case 30: case 31: case 32: case 33: case 34:
                return 'clear';
            case 2: case 35:
                return 'cloudy';
            case 3: case 36:
                return 'overcast';
            case 4:
                return 'haze';
            case 5: case 6: case 7:
                return 'fog';
            case 10: case 11: case 38:
                return 'thunder';
            case 12: case 13: case 14: case 15: case 16: case 17:
            case 18: case 19: case 20: case 21: case 22: case 23: case 24:
            case 8: case 9: case 37:
                return 'rain';
            case 25: case 26: case 27: case 28: case 29:
                return 'snow';
            default:
                return 'cloudy';
        }
    }

    /** 使用单色天气字标，避免平台 Emoji 风格不一致。 */
    function categoryIcon(category) {
        switch (category) {
            case 'clear': return '晴';
            case 'cloudy': return '云';
            case 'overcast': return '阴';
            case 'fog': return '雾';
            case 'haze': return '霾';
            case 'rain': return '雨';
            case 'thunder': return '雷';
            case 'snow': return '雪';
            default: return '天';
        }
    }

    // ========== 数据获取（带缓存） ==========

    function readCache() {
        try {
            var raw = localStorage.getItem(CACHE_KEY);
            if (!raw) return null;
            var data = JSON.parse(raw);
            if (!data || !data.city || !data.text) return null;
            return data;
        } catch (e) {
            return null;
        }
    }

    function writeCache(data) {
        try {
            data.ts = Date.now();
            localStorage.setItem(CACHE_KEY, JSON.stringify(data));
        } catch (e) {
            // localStorage 不可用时静默失败
        }
    }

    function fetchWeather() {
        // 时间戳参数：防止某些忽略 Cache-Control: private 的 CDN 把
        // 别人的天气响应缓存在边缘节点返回给当前访客（天气因 IP 而异）
        return fetch('/api/weather.php?_=' + Date.now(), { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            })
            .then(function (json) {
                if (!json || json.success !== true) throw new Error('bad payload');
                return {
                    city: json.city,
                    text: json.text,
                    code: json.code,
                    temperature: json.temperature
                };
            });
    }

    // ========== 实时天气背景 ==========

    function applyBackground(data) {
        var category = categorize(data.text, data.code);
        document.body.setAttribute('data-weather', category);
        // 雨滴只在天气弹窗的玻璃层内显示，避免遮挡整页内容。
    }

    // ========== 雨 / 雪 粒子（轻量 canvas） ==========

    var particleCanvas = null;
    var particleRafId = 0;

    function startParticles(type) {
        var canvas = document.createElement('canvas');
        canvas.className = 'weather-fx-canvas';
        canvas.setAttribute('aria-hidden', 'true');
        document.body.appendChild(canvas);
        particleCanvas = canvas;

        var ctx = canvas.getContext('2d');
        if (!ctx) return;

        var width = 0;
        var height = 0;
        var dpr = Math.min(window.devicePixelRatio || 1, 2);
        var particles = [];

        function resize() {
            width = window.innerWidth;
            height = window.innerHeight;
            canvas.width = width * dpr;
            canvas.height = height * dpr;
            canvas.style.width = width + 'px';
            canvas.style.height = height + 'px';
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        }

        function build() {
            var base = type === 'rain' ? 70 : 45;
            var count = Math.round(base * (width < 768 ? 0.5 : 1));
            particles = [];
            for (var i = 0; i < count; i++) {
                particles.push({
                    x: Math.random() * width,
                    y: Math.random() * height,
                    len: type === 'rain' ? 10 + Math.random() * 14 : 1.5 + Math.random() * 2.5,
                    speed: type === 'rain' ? 7 + Math.random() * 6 : 0.6 + Math.random() * 1.2,
                    drift: (Math.random() - 0.5) * 0.8,
                    opacity: type === 'rain' ? 0.16 + Math.random() * 0.18 : 0.5 + Math.random() * 0.35
                });
            }
        }

        function draw() {
            ctx.clearRect(0, 0, width, height);
            for (var i = 0; i < particles.length; i++) {
                var p = particles[i];
                if (type === 'rain') {
                    ctx.strokeStyle = 'rgba(120, 150, 185, ' + p.opacity + ')';
                    ctx.lineWidth = 1;
                    ctx.beginPath();
                    ctx.moveTo(p.x, p.y);
                    ctx.lineTo(p.x + p.drift * 2, p.y + p.len);
                    ctx.stroke();
                    p.y += p.speed;
                    p.x += p.drift;
                } else {
                    ctx.fillStyle = 'rgba(255, 255, 255, ' + p.opacity + ')';
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.len, 0, Math.PI * 2);
                    ctx.fill();
                    p.y += p.speed;
                    p.x += p.drift + Math.sin((p.y + i * 30) / 60) * 0.4;
                }
                if (p.y > height + 20) {
                    p.y = -20;
                    p.x = Math.random() * width;
                }
                if (p.x < -20) p.x = width + 20;
                if (p.x > width + 20) p.x = -20;
            }
            particleRafId = requestAnimationFrame(draw);
        }

        resize();
        build();
        particleRafId = requestAnimationFrame(draw);

        window.addEventListener('resize', function () {
            resize();
            build();
        });
    }

    // ========== 欢迎弹窗 ==========

    var popupRoot = null;
    var autoCloseTimer = 0;

    function buildPopup(data) {
        var category = categorize(data.text, data.code);
        var icon = categoryIcon(category);
        var city = escapeHtml(data.city);
        var hasTemp = data.temperature !== '' && data.temperature != null;

        var root = document.createElement('div');
        root.className = 'weather-popup';
        root.setAttribute('role', 'dialog');
        root.setAttribute('aria-modal', 'true');
        root.setAttribute('aria-labelledby', 'weather-popup-title');
        root.innerHTML =
            '<div class="weather-popup-card" data-weather="' + category + '">' +
                '<button type="button" class="weather-popup-close" aria-label="关闭天气弹窗">&times;</button>' +
                '<div class="weather-popup-deco" aria-hidden="true"></div>' +
                '<div class="weather-popup-glass-shine" aria-hidden="true"></div>' +
                '<div class="weather-popup-rain" aria-hidden="true">' + Array.from({length:18}, function (_, i) { return '<span style="left:' + (i * 6 + 2) + '%;animation-delay:-' + (i % 7) * .18 + 's"></span>'; }).join('') + '</div>' +
                '<div class="weather-popup-greeting" id="weather-popup-title">欢迎来自 <span class="weather-popup-city">' + city + '</span> 的朋友</div>' +
                '<div class="weather-popup-hero">' +
                    '<span class="weather-popup-icon weather-popup-icon--' + category + '" aria-hidden="true">' + icon + '</span>' +
                    (hasTemp ? '<span class="weather-popup-temp">' + escapeHtml(data.temperature) + '<i>°C</i></span>' : '') +
                '</div>' +
                '<div class="weather-popup-desc">' + escapeHtml(data.text) + '</div>' +
                '<div class="weather-popup-timer" aria-hidden="true"><span></span></div>' +
            '</div>';
        return root;
    }

    function showPopup(data) {
        if (popupRoot) return;

        popupRoot = buildPopup(data);
        document.body.appendChild(popupRoot);

        // 等下一帧再加 class，保证过渡动画生效
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                popupRoot.classList.add('is-visible');
            });
        });

        // 每日只显示一次：标记今天
        try {
            localStorage.setItem(POPUP_DATE_KEY, localDateKey());
        } catch (e) { /* 忽略 */ }

        var closeBtn = popupRoot.querySelector('.weather-popup-close');
        if (closeBtn) closeBtn.focus();

        function closePopup() {
            if (!popupRoot) return;
            window.clearTimeout(autoCloseTimer);
            document.removeEventListener('keydown', onKeydown);
            var el = popupRoot;
            popupRoot = null;
            el.classList.remove('is-visible');
            window.setTimeout(function () {
                el.remove();
            }, 300);
        }

        function onKeydown(e) {
            if (e.key === 'Escape' || e.key === 'Esc') {
                closePopup();
            }
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', closePopup);
        }
        popupRoot.addEventListener('click', function (e) {
            if (e.target === popupRoot) closePopup();
        });
        document.addEventListener('keydown', onKeydown);

        autoCloseTimer = window.setTimeout(closePopup, AUTO_CLOSE_MS);
    }

    // ========== 主流程 ==========

    function shownToday() {
        try {
            return localStorage.getItem(POPUP_DATE_KEY) === localDateKey();
        } catch (e) {
            return false;
        }
    }

    function init() {
        if (document.body.classList.contains('admin-page')) return;

        // 先注入样式再干活：无论站点 CSS 是否被 CDN 缓存成旧版，弹窗排版都有保障
        injectStyles();

        var cached = readCache();
        var cacheFresh = cached && (Date.now() - (cached.ts || cached.timestamp || 0) < CACHE_TTL);

        if (cacheFresh) {
            // 缓存可用：直接应用背景；弹窗未显示过则显示
            applyBackground(cached);
            if (!shownToday()) showPopup(cached);
            return;
        }

        fetchWeather()
            .then(function (data) {
                writeCache(data);
                applyBackground(data);
                if (!shownToday()) showPopup(data);
            })
            .catch(function () {
                // 请求失败：若有旧缓存则继续用于背景（当天弹窗已显示过就不打扰）
                if (cached) {
                    applyBackground(cached);
                    if (!shownToday()) showPopup(cached);
                }
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            // 稍作延迟，避免与首屏渲染抢占
            window.setTimeout(init, 600);
        });
    } else {
        window.setTimeout(init, 600);
    }
})();
