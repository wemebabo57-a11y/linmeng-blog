/**
 * 博客侧边栏天气组件
 * 纯原生JS，自执行函数
 * 使用 wttr.in 免费API获取天气数据
 */
;(function () {
    'use strict';

    // ========== 配置 ==========
    const CACHE_EXPIRE = 30 * 60 * 1000; // 缓存有效期：30分钟（毫秒）
    const REFRESH_INTERVAL = 30 * 60 * 1000; // 自动刷新间隔：30分钟

    // ========== 天气图标映射 ==========
    const WEATHER_ICONS = {
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
    const DEFAULT_ICON = '🌤️';

    /**
     * 根据天气描述获取对应的Unicode图标
     * @param {string} description - 天气描述文本
     * @returns {string} 对应的天气图标
     */
    function getWeatherIcon(description) {
        if (!description) return DEFAULT_ICON;
        // 先尝试精确匹配
        if (WEATHER_ICONS.hasOwnProperty(description)) {
            return WEATHER_ICONS[description];
        }
        // 再尝试关键词匹配
        const descLower = description.toLowerCase();
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
     * @param {string} city - 城市名
     * @returns {object|null} 缓存的天气数据，过期或不存在返回null
     */
    function getCache(city) {
        try {
            const key = 'weather_' + city;
            const raw = localStorage.getItem(key);
            if (!raw) return null;
            const data = JSON.parse(raw);
            // 检查是否过期
            if (Date.now() - data.timestamp > CACHE_EXPIRE) {
                localStorage.removeItem(key);
                return null;
            }
            return data;
        } catch (e) {
            return null;
        }
    }

    /**
     * 将天气数据写入localStorage缓存
     * @param {string} city - 城市名
     * @param {object} data - 天气数据
     */
    function setCache(city, data) {
        try {
            const key = 'weather_' + city;
            data.timestamp = Date.now();
            localStorage.setItem(key, JSON.stringify(data));
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
     * 从wttr.in API获取天气数据
     * @param {string} city - 城市名
     * @returns {Promise<object>} 解析后的天气数据
     */
    function fetchWeather(city) {
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
     * 初始化单个天气组件
     * @param {HTMLElement} el - 天气组件容器元素
     */
    function initWidget(el) {
        var city = el.getAttribute('data-city') || '北京';

        // 先检查缓存
        var cached = getCache(city);
        if (cached) {
            renderWeather(el, cached);
        } else {
            renderLoading(el);
            fetchWeather(city)
                .then(function (data) {
                    setCache(city, data);
                    renderWeather(el, data);
                })
                .catch(function () {
                    renderError(el);
                });
        }

        // 每30分钟自动刷新
        setInterval(function () {
            fetchWeather(city)
                .then(function (data) {
                    setCache(city, data);
                    renderWeather(el, data);
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
