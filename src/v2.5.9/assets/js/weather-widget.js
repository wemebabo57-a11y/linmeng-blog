/**
 * 博客侧边栏天气组件
 * 纯原生JS，自执行函数
 * 数据源：/api/weather.php（心知天气服务端代理，按访客 IP 定位）
 * 代理不可用（未配置密钥/网络异常）时回退 wttr.in（按后台设置城市）
 */
;(function () {
    'use strict';

    // ========== 配置 ==========
    var CACHE_KEY = 'app_weather_data_v2';           // 与 weather-popup.js 共用同一份缓存；v2：作废旧版可能缓存的兜底城市数据
    var CACHE_EXPIRE = 30 * 60 * 1000; // 缓存有效期：30分钟（毫秒）
    var REFRESH_INTERVAL = 30 * 60 * 1000; // 自动刷新间隔：30分钟

    // ========== 天气图标映射（wttr.in 英文描述） ==========
    var WEATHER_ICONS = {
        'Sunny': '☀️',
        'Clear': '☀️',
        'Cloudy': '☁️',
        'Overcast': '☁️',
        'Partly cloudy': '⛅',
        'Partly Cloudy': '⛅',
        'Rain': '🌧️',
        'Drizzle': '🌧️',
        'Light rain': '🌧️',
        'Heavy rain': '🌧️',
        'Moderate rain': '🌧️',
        'Snow': '❄️',
        'Light snow': '❄️',
        'Heavy snow': '❄️',
        'Thunderstorm': '⛈️',
        'Thundery outbreaks possible': '⛈️',
        'Patchy rain possible': '🌧️',
        'Patchy snow possible': '❄️',
        'Patchy sleet possible': '🌧️',
        'Blowing snow': '❄️',
        'Blizzard': '❄️',
        'Fog': '🌫️',
        'Mist': '🌫️',
        'Freezing fog': '🌫️',
        'Light drizzle': '🌧️',
        'Light rain shower': '🌧️',
        'Moderate rain shower': '🌧️',
        'Heavy rain shower': '🌧️',
        'Light snow shower': '❄️',
        'Moderate snow shower': '❄️',
        'Heavy snow shower': '❄️',
        'Patchy light drizzle': '🌧️',
        'Patchy light rain': '🌧️',
        'Patchy moderate rain': '🌧️',
        'Patchy heavy rain': '🌧️',
        'Patchy light snow': '❄️',
        'Patchy moderate snow': '❄️',
        'Patchy heavy snow': '❄️',
        'Light showers of ice': '❄️',
        'Moderate or heavy showers of ice': '❄️'
    };

    // 默认图标（无法匹配时使用）
    var DEFAULT_ICON = '🌤️';

    /**
     * 根据天气描述获取对应的Unicode图标（支持英文与中文描述）
     * @param {string} description - 天气描述文本
     * @returns {string} 对应的天气图标
     */
    function getWeatherIcon(description) {
        if (!description) return DEFAULT_ICON;
        // 先尝试精确匹配（wttr.in 英文）
        if (WEATHER_ICONS.hasOwnProperty(description)) {
            return WEATHER_ICONS[description];
        }
        // 中文描述（心知天气 zh-Hans）关键词匹配
        if (description.indexOf('雷') !== -1 || description.indexOf('雹') !== -1) return '⛈️';
        if (description.indexOf('雪') !== -1) return '❄️';
        if (description.indexOf('雨') !== -1) return '🌧️';
        if (description.indexOf('雾') !== -1 || description.indexOf('霾') !== -1 || description.indexOf('沙') !== -1 || description.indexOf('尘') !== -1) return '🌫️';
        if (description.indexOf('阴') !== -1) return '☁️';
        if (description.indexOf('云') !== -1) return '⛅';
        if (description.indexOf('晴') !== -1) return '☀️';
        // 英文关键词匹配（wttr.in 兜底）
        var descLower = description.toLowerCase();
        if (descLower.includes('thunder') || descLower.includes('thundery')) return '⛈️';
        if (descLower.includes('snow') || descLower.includes('blizzard') || descLower.includes('sleet')) return '❄️';
        if (descLower.includes('drizzle') || descLower.includes('rain') || descLower.includes('shower')) return '🌧️';
        if (descLower.includes('fog') || descLower.includes('mist')) return '🌫️';
        if (descLower.includes('overcast')) return '☁️';
        if (descLower.includes('cloudy')) return '⛅';
        if (descLower.includes('sunny') || descLower.includes('clear')) return '☀️';
        return DEFAULT_ICON;
    }

    /**
     * 从localStorage读取缓存的天气数据
     * 与 weather-popup.js 共用缓存（其写入字段为 ts）
     * @returns {object|null} 缓存的天气数据，过期或不存在返回null
     */
    function getCache() {
        try {
            var raw = localStorage.getItem(CACHE_KEY);
            if (!raw) return null;
            var data = JSON.parse(raw);
            // 检查是否过期（兼容 timestamp / ts 两种字段）
            var ts = data.timestamp || data.ts || 0;
            if (Date.now() - ts > CACHE_EXPIRE) {
                return null;
            }
            return data;
        } catch (e) {
            return null;
        }
    }

    /**
     * 将天气数据写入localStorage缓存
     * @param {object} data - 天气数据
     */
    function setCache(data) {
        try {
            data.timestamp = Date.now();
            localStorage.setItem(CACHE_KEY, JSON.stringify(data));
        } catch (e) {
            // localStorage不可用时静默失败
        }
    }

    /**
     * 渲染天气信息到DOM元素
     * @param {HTMLElement} el - 天气组件容器元素
     * @param {object} data - 天气数据
     */
    function renderWeather(el, data) {
        el.innerHTML =
            '<div class="weather-icon">' + data.icon + '</div>' +
            '<div class="weather-info">' +
                '<div class="weather-city">' + escapeHtml(data.city) + '</div>' +
                '<div class="weather-temp">' + data.temp + '°C</div>' +
                '<div class="weather-desc">' + escapeHtml(data.desc) + '</div>' +
            '</div>';
    }

    /**
     * 渲染加载中状态
     * @param {HTMLElement} el - 天气组件容器元素
     */
    function renderLoading(el) {
        el.innerHTML = '<div class="weather-loading">加载中...</div>';
    }

    /**
     * 渲染错误状态
     * @param {HTMLElement} el - 天气组件容器元素
     */
    function renderError(el) {
        el.innerHTML = '<div class="weather-error">天气数据获取失败</div>';
    }

    /**
     * HTML转义，防止XSS
     * @param {string} str - 需要转义的字符串
     * @returns {string} 转义后的安全字符串
     */
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /**
     * 数据源1：自家心知天气代理（与弹窗共用缓存，按访客 IP 定位）
     * @returns {Promise<object|null>} 成功返回天气数据，未配置/失败返回null
     */
    function fetchFromProxy() {
        // 时间戳参数：防止某些忽略 Cache-Control: private 的 CDN 把
        // 别人的天气响应缓存在边缘节点返回给当前访客（天气因 IP 而异）
        return fetch('/api/weather.php?_=' + Date.now(), { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) return null;
                return response.json();
            })
            .then(function (json) {
                if (!json || json.success !== true || !json.text) return null;
                return {
                    city: json.city || '未知城市',
                    temp: json.temperature,
                    desc: json.text,
                    code: json.code,
                    icon: getWeatherIcon(json.text)
                };
            })
            .catch(function () {
                return null;
            });
    }

    /**
     * 数据源2：wttr.in 免费API（回退方案，按后台设置城市）
     * @param {string} city - 城市名
     * @returns {Promise<object>} 解析后的天气数据
     */
    function fetchFromWttr(city) {
        return fetch('https://wttr.in/' + encodeURIComponent(city) + '?format=j1')
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (json) {
                var current = json.current_condition[0];
                return {
                    city: city,
                    temp: current.temp_C,
                    desc: current.lang_zh && current.lang_zh[0] ? current.lang_zh[0].value : current.weatherDesc[0].value,
                    icon: getWeatherIcon(current.weatherDesc[0].value)
                };
            });
    }

    /**
     * 获取天气：优先缓存 → 心知代理 → wttr.in
     * @param {string} city - 后台设置的默认城市（wttr.in 回退用）
     * @returns {Promise<object|null>} 天气数据，全部失败返回null
     */
    function fetchWeather(city) {
        var cached = getCache();
        if (cached) {
            return Promise.resolve({
                city: cached.city,
                temp: cached.temperature,
                desc: cached.text,
                icon: getWeatherIcon(cached.text)
            });
        }

        return fetchFromProxy()
            .then(function (data) {
                if (data) {
                    // 与 weather-popup.js 共用缓存结构
                    setCache({
                        city: data.city,
                        text: data.desc,
                        code: data.code,
                        temperature: data.temp
                    });
                    return data;
                }
                // 代理不可用，回退 wttr.in
                return fetchFromWttr(city);
            });
    }

    /**
     * 初始化单个天气组件
     * @param {HTMLElement} el - 天气组件容器元素
     */
    function initWidget(el) {
        var city = el.getAttribute('data-city') || '北京';

        // 先检查缓存
        var cached = getCache();
        if (cached) {
            renderWeather(el, {
                city: cached.city,
                temp: cached.temperature,
                desc: cached.text,
                icon: getWeatherIcon(cached.text)
            });
        } else {
            renderLoading(el);
            fetchWeather(city)
                .then(function (data) {
                    if (data) {
                        renderWeather(el, data);
                    } else {
                        renderError(el);
                    }
                })
                .catch(function () {
                    renderError(el);
                });
        }

        // 每30分钟自动刷新
        setInterval(function () {
            fetchWeather(city)
                .then(function (data) {
                    if (data) {
                        renderWeather(el, data);
                    } else {
                        renderError(el);
                    }
                })
                .catch(function () {
                    renderError(el);
                });
        }, REFRESH_INTERVAL);
    }

    // ========== 入口：DOMContentLoaded时初始化 ==========
    document.addEventListener('DOMContentLoaded', function () {
        var widgets = document.querySelectorAll('.weather-widget');
        widgets.forEach(function (el) {
            initWidget(el);
        });
    });
})();
