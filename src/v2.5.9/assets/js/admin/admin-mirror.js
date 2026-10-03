/**
 * 后台镜像软件管理脚本
 * 处理：编辑按钮回填表单、重置表单、删除确认
 */
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        // ====== 分区：编辑回填 ======
        document.querySelectorAll('[data-edit-mcat-id]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var cat = {
                    id: btn.getAttribute('data-edit-mcat-id'),
                    name: btn.getAttribute('data-edit-mcat-name'),
                    description: btn.getAttribute('data-edit-mcat-desc'),
                    icon: btn.getAttribute('data-edit-mcat-icon'),
                    sort_order: parseInt(btn.getAttribute('data-edit-mcat-sort'), 10) || 0,
                    status: parseInt(btn.getAttribute('data-edit-mcat-status'), 10)
                };
                fillCategoryForm(cat);
            });
        });

        var resetCatBtn = document.getElementById('reset-mcat-form');
        if (resetCatBtn) {
            resetCatBtn.addEventListener('click', resetCategoryForm);
        }

        // ====== 软件：编辑回填 ======
        document.querySelectorAll('[data-edit-msw-id]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var sw = {
                    id: btn.getAttribute('data-edit-msw-id'),
                    name: btn.getAttribute('data-edit-msw-name'),
                    category_id: parseInt(btn.getAttribute('data-edit-msw-cat'), 10) || 0,
                    description: btn.getAttribute('data-edit-msw-desc'),
                    icon_url: btn.getAttribute('data-edit-msw-icon'),
                    download_url: btn.getAttribute('data-edit-msw-dl'),
                    official_url: btn.getAttribute('data-edit-msw-official'),
                    version: btn.getAttribute('data-edit-msw-version'),
                    sort_order: parseInt(btn.getAttribute('data-edit-msw-sort'), 10) || 0,
                    status: parseInt(btn.getAttribute('data-edit-msw-status'), 10)
                };
                fillSoftwareForm(sw);
            });
        });

        var resetSwBtn = document.getElementById('reset-msw-form');
        if (resetSwBtn) {
            resetSwBtn.addEventListener('click', resetSoftwareForm);
        }

        // ====== 删除确认 ======
        document.querySelectorAll('a[data-confirm]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                if (!confirm(link.getAttribute('data-confirm'))) {
                    e.preventDefault();
                }
            });
        });
    });

    function fillCategoryForm(c) {
        setVal('mcat_id', c.id);
        setVal('mcat_name', c.name || '');
        setVal('mcat_desc', c.description || '');
        setVal('mcat_icon', c.icon || '');
        setVal('mcat_sort', c.sort_order);
        setChecked('mcat_status', c.status === 1);
        var btn = document.getElementById('mcat_submit_btn');
        if (btn) btn.textContent = '保存修改';
        scrollToFirstCard();
    }

    function resetCategoryForm() {
        setVal('mcat_id', '0');
        setVal('mcat_name', '');
        setVal('mcat_desc', '');
        setVal('mcat_icon', '');
        setVal('mcat_sort', '0');
        setChecked('mcat_status', true);
        var btn = document.getElementById('mcat_submit_btn');
        if (btn) btn.textContent = '添加分区';
    }

    function fillSoftwareForm(s) {
        setVal('msw_id', s.id);
        setVal('msw_name', s.name || '');
        setVal('msw_cat', s.category_id);
        setVal('msw_desc', s.description || '');
        setVal('msw_icon_url', s.icon_url || '');
        setVal('msw_dl', s.download_url || '');
        setVal('msw_official', s.official_url || '');
        setVal('msw_version', s.version || '');
        setVal('msw_sort', s.sort_order);
        setChecked('msw_status', s.status === 1);
        var file = document.getElementById('msw_icon_file');
        if (file) file.value = '';
        var btn = document.getElementById('msw_submit_btn');
        if (btn) btn.textContent = '保存修改';
        scrollToFirstCard();
    }

    function resetSoftwareForm() {
        setVal('msw_id', '0');
        setVal('msw_name', '');
        setVal('msw_cat', '0');
        setVal('msw_desc', '');
        setVal('msw_icon_url', '');
        setVal('msw_dl', '');
        setVal('msw_official', '');
        setVal('msw_version', '');
        setVal('msw_sort', '0');
        setChecked('msw_status', true);
        var file = document.getElementById('msw_icon_file');
        if (file) file.value = '';
        var btn = document.getElementById('msw_submit_btn');
        if (btn) btn.textContent = '发布软件';
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
