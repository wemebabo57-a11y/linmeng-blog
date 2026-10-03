/**
 * 后台游戏管理脚本
 */
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-edit-game-id]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var game = {
                    id: btn.getAttribute('data-edit-game-id'),
                    name: btn.getAttribute('data-edit-game-name'),
                    description: btn.getAttribute('data-edit-game-desc'),
                    image_url: btn.getAttribute('data-edit-game-image'),
                    sort_order: parseInt(btn.getAttribute('data-edit-game-sort'), 10) || 0,
                    status: parseInt(btn.getAttribute('data-edit-game-status'), 10)
                };
                fillGameForm(game);
            });
        });

        var resetBtn = document.getElementById('reset-game-form');
        if (resetBtn) {
            resetBtn.addEventListener('click', resetGameForm);
        }

        document.querySelectorAll('a[data-confirm]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                if (!confirm(link.getAttribute('data-confirm'))) {
                    e.preventDefault();
                }
            });
        });
    });

    function fillGameForm(g) {
        setVal('game_id', g.id);
        setVal('game_name', g.name || '');
        setVal('game_desc', g.description || '');
        setVal('game_image_url', g.image_url || '');
        setVal('game_sort', g.sort_order);
        setChecked('game_status', g.status === 1);

        var submitBtn = document.getElementById('game_submit_btn');
        if (submitBtn) submitBtn.textContent = '保存修改';

        scrollToFirstCard();
    }

    function resetGameForm() {
        setVal('game_id', '0');
        setVal('game_name', '');
        setVal('game_desc', '');
        setVal('game_image_url', '');
        setVal('game_sort', '0');
        setChecked('game_status', true);

        var submitBtn = document.getElementById('game_submit_btn');
        if (submitBtn) submitBtn.textContent = '添加游戏';
    }

    function setVal(id, value) {
        var el = document.getElementById(id);
        if (el) el.value = value;
    }

    function setChecked(id, checked) {
        var el = document.getElementById(id);
        if (el) el.checked = checked;
    }

    function scrollToFirstCard() {
        var firstCard = document.querySelector('.card');
        if (firstCard && firstCard.scrollIntoView) {
            firstCard.scrollIntoView({ behavior: 'smooth' });
        }
    }
})();
