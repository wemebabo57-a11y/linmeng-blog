/**
 * 后台网站设置脚本
 */
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        var setStartNowBtn = document.getElementById('btn-set-start-now');
        var startDateInput = document.getElementById('site_start_date');
        if (setStartNowBtn && startDateInput) {
            setStartNowBtn.addEventListener('click', function() {
                var now = new Date();
                var year = now.getFullYear();
                var month = String(now.getMonth() + 1).padStart(2, '0');
                var day = String(now.getDate()).padStart(2, '0');
                var hours = String(now.getHours()).padStart(2, '0');
                var minutes = String(now.getMinutes()).padStart(2, '0');
                var seconds = String(now.getSeconds()).padStart(2, '0');
                startDateInput.value = year + '-' + month + '-' + day + 'T' + hours + ':' + minutes + ':' + seconds;
            });
        }

        var calibrateBtn = document.getElementById('btn-calibrate-time');
        var calibrateTimeInput = document.getElementById('calibrate_time');
        var settingsForm = document.querySelector('form[method="POST"]');
        if (calibrateBtn && calibrateTimeInput && settingsForm) {
            calibrateBtn.addEventListener('click', function() {
                if (!confirm('确定要将网站时间校准为当前电脑时间吗？这会覆盖当前偏移量。')) {
                    return;
                }
                calibrateTimeInput.value = Date.now();
                settingsForm.submit();
            });
        }

        // 背景图位置 9 宫格选择器
        var bgPosGrid = document.querySelector('.bg-position-grid');
        if (bgPosGrid) {
            bgPosGrid.addEventListener('click', function(e) {
                var cell = e.target.closest('.bg-pos-cell');
                if (!cell) return;
                bgPosGrid.querySelectorAll('.bg-pos-cell').forEach(function(c) {
                    c.classList.remove('is-active');
                });
                cell.classList.add('is-active');
                var radio = cell.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
            });
        }
    });
})();
