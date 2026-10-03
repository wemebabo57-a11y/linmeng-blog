/**
 * 侧边栏创作日历组件
 * 纯原生JS，自执行函数
 * 有发布文章的日子加方框标记，可左右翻月（纯客户端计算，不发请求）
 */
;(function () {
    'use strict';

    var WEEK_START = 1; // 周一起始

    /**
     * 解析 YYYY-MM 为 {y, m}，m 为 1-12
     * @param {string} str - 形如 2026-08 的月份字符串
     * @returns {object|null} 解析结果，非法输入返回null
     */
    function parseMonth(str) {
        var match = /^(\d{4})-(\d{2})$/.exec(String(str || ''));
        if (!match) return null;
        var y = parseInt(match[1], 10);
        var m = parseInt(match[2], 10);
        if (m < 1 || m > 12) return null;
        return { y: y, m: m };
    }

    /**
     * 将 {y, m} 格式化为 YYYY-MM
     * @param {object} month - 月份对象
     * @returns {string} 形如 2026-08 的字符串
     */
    function formatMonth(month) {
        return month.y + '-' + (month.m < 10 ? '0' : '') + month.m;
    }

    /**
     * 月份偏移，自动处理跨年
     * @param {object} month - 月份对象
     * @param {number} delta - 偏移月数，可为负
     * @returns {object} 偏移后的月份对象
     */
    function shiftMonth(month, delta) {
        var total = month.y * 12 + (month.m - 1) + delta;
        return { y: Math.floor(total / 12), m: (total % 12) + 1 };
    }

    /**
     * 比较两个 YYYY-MM 字符串的先后，可直接用字典序
     * @param {string} a - 月份字符串
     * @param {string} b - 月份字符串
     * @returns {number} a<b返回负数，相等返回0，a>b返回正数
     */
    function compareMonth(a, b) {
        return a < b ? -1 : (a > b ? 1 : 0);
    }

    /**
     * 读取 data-days 中的日期→篇数映射
     * @param {HTMLElement} el - 日历容器元素
     * @returns {object} 形如 {'2026-08-09': 2} 的映射
     */
    function readDays(el) {
        try {
            var raw = el.getAttribute('data-days');
            if (!raw) return {};
            var parsed = JSON.parse(raw);
            return (parsed && typeof parsed === 'object') ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    /**
     * 渲染指定月份的日期格
     * @param {object} state - 组件状态
     * @param {object} month - 要渲染的月份对象
     */
    function render(state, month) {
        var monthKey = formatMonth(month);
        var first = new Date(month.y, month.m - 1, 1);
        var daysInMonth = new Date(month.y, month.m, 0).getDate();
        // JS 的 getDay() 周日为0，换算成周一起始的前置空格数
        var lead = (first.getDay() - WEEK_START + 7) % 7;
        var cells = Math.ceil((lead + daysInMonth) / 7) * 7;

        var html = '';
        for (var i = 0; i < cells; i++) {
            var dayNum = i - lead + 1;
            if (dayNum < 1 || dayNum > daysInMonth) {
                html += '<span class="calendar-day is-empty"></span>';
                continue;
            }
            var date = monthKey + '-' + (dayNum < 10 ? '0' : '') + dayNum;
            var count = state.days[date] || 0;
            var cls = 'calendar-day';
            if (count > 0) cls += ' has-post';
            if (date === state.today) cls += ' is-today';
            html += '<time class="' + cls + '" datetime="' + date + '"' +
                (count > 0 ? ' title="' + count + ' 篇"' : '') +
                '>' + dayNum + '</time>';
        }

        state.grid.innerHTML = html;
        state.title.textContent = month.y + '年' + month.m + '月';
        state.current = month;

        state.prevBtn.disabled = compareMonth(monthKey, state.minMonth) <= 0;
        state.nextBtn.disabled = compareMonth(monthKey, state.maxMonth) >= 0;
    }

    /**
     * 初始化单个日历组件
     * @param {HTMLElement} el - 日历容器元素
     */
    function initCalendar(el) {
        var grid = el.querySelector('[data-calendar-grid]');
        var title = el.querySelector('[data-calendar-title]');
        var prevBtn = el.querySelector('[data-calendar-prev]');
        var nextBtn = el.querySelector('[data-calendar-next]');
        if (!grid || !title || !prevBtn || !nextBtn) return;

        var today = el.getAttribute('data-today') || '';
        var maxMonth = el.getAttribute('data-month') || today.slice(0, 7);
        var current = parseMonth(maxMonth);
        if (!current) return;

        var minMonth = el.getAttribute('data-min-month') || maxMonth;
        if (!parseMonth(minMonth)) minMonth = maxMonth;
        // 最早发文月晚于当月时（数据异常），退回当月避免边界反转
        if (compareMonth(minMonth, maxMonth) > 0) minMonth = maxMonth;

        var state = {
            days: readDays(el),
            today: today,
            minMonth: minMonth,
            maxMonth: maxMonth,
            grid: grid,
            title: title,
            prevBtn: prevBtn,
            nextBtn: nextBtn,
            current: current
        };

        prevBtn.addEventListener('click', function () {
            render(state, shiftMonth(state.current, -1));
        });
        nextBtn.addEventListener('click', function () {
            render(state, shiftMonth(state.current, 1));
        });

        // 首次渲染交给 JS，保证按钮禁用状态与实际边界一致
        render(state, current);
    }

    // ========== 入口：DOMContentLoaded时初始化 ==========
    document.addEventListener('DOMContentLoaded', function () {
        var calendars = document.querySelectorAll('.creation-calendar');
        calendars.forEach(function (el) {
            initCalendar(el);
        });
    });
})();
