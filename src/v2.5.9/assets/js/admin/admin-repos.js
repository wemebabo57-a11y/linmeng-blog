/**
 * 后台仓库管理脚本
 */
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-edit-repo-id]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var repo = {
                    id: btn.getAttribute('data-edit-repo-id'),
                    name: btn.getAttribute('data-edit-repo-name'),
                    url: btn.getAttribute('data-edit-repo-url'),
                    description: btn.getAttribute('data-edit-repo-desc'),
                    sort_order: parseInt(btn.getAttribute('data-edit-repo-sort'), 10) || 0,
                    status: parseInt(btn.getAttribute('data-edit-repo-status'), 10)
                };
                fillRepoForm(repo);
            });
        });

        var resetBtn = document.getElementById('reset-repo-form');
        if (resetBtn) {
            resetBtn.addEventListener('click', resetRepoForm);
        }

        document.querySelectorAll('a[data-confirm]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                if (!confirm(link.getAttribute('data-confirm'))) {
                    e.preventDefault();
                }
            });
        });
    });

    function fillRepoForm(r) {
        setVal('repo_id', r.id);
        setVal('repo_name', r.name || '');
        setVal('repo_url', r.url || '');
        setVal('repo_desc', r.description || '');
        setVal('repo_sort', r.sort_order);
        setChecked('repo_status', r.status === 1);

        var submitBtn = document.getElementById('repo_submit_btn');
        if (submitBtn) submitBtn.textContent = '保存修改';

        scrollToFirstCard();
    }

    function resetRepoForm() {
        setVal('repo_id', '0');
        setVal('repo_name', '');
        setVal('repo_url', '');
        setVal('repo_desc', '');
        setVal('repo_sort', '0');
        setChecked('repo_status', true);

        var submitBtn = document.getElementById('repo_submit_btn');
        if (submitBtn) submitBtn.textContent = '添加仓库';
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