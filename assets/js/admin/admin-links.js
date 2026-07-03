/**
 * 后台友链管理脚本
 */
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-edit-link-id]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var link = {
                    id: btn.getAttribute('data-edit-link-id'),
                    name: btn.getAttribute('data-edit-link-name'),
                    url: btn.getAttribute('data-edit-link-url'),
                    description: btn.getAttribute('data-edit-link-description'),
                    logo: btn.getAttribute('data-edit-link-logo'),
                    sort_order: parseInt(btn.getAttribute('data-edit-link-sort'), 10) || 0,
                    status: parseInt(btn.getAttribute('data-edit-link-status'), 10)
                };
                editLink(link);
            });
        });

        var resetBtn = document.getElementById('reset-link-form');
        if (resetBtn) {
            resetBtn.addEventListener('click', resetLinkForm);
        }

        document.querySelectorAll('a[data-confirm]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                if (!confirm(link.getAttribute('data-confirm'))) {
                    e.preventDefault();
                }
            });
        });
    });

    function editLink(link) {
        var linkId = document.getElementById('link_id');
        var linkName = document.getElementById('link_name');
        var linkUrl = document.getElementById('link_url');
        var linkDescription = document.getElementById('link_description');
        var linkLogo = document.getElementById('link_logo');
        var linkSort = document.getElementById('link_sort');
        var linkStatus = document.getElementById('link_status');
        var submitBtn = document.getElementById('link_submit_btn');

        if (linkId) linkId.value = link.id;
        if (linkName) linkName.value = link.name || '';
        if (linkUrl) linkUrl.value = link.url || '';
        if (linkDescription) linkDescription.value = link.description || '';
        if (linkLogo) linkLogo.value = link.logo || '';
        if (linkSort) linkSort.value = link.sort_order;
        if (linkStatus) linkStatus.checked = link.status === 1;
        if (submitBtn) submitBtn.textContent = '保存修改';

        var firstCard = document.querySelector('.card');
        if (firstCard && firstCard.scrollIntoView) {
            firstCard.scrollIntoView({ behavior: 'smooth' });
        }
    }

    function resetLinkForm() {
        var linkId = document.getElementById('link_id');
        var linkName = document.getElementById('link_name');
        var linkUrl = document.getElementById('link_url');
        var linkDescription = document.getElementById('link_description');
        var linkLogo = document.getElementById('link_logo');
        var linkSort = document.getElementById('link_sort');
        var linkStatus = document.getElementById('link_status');
        var submitBtn = document.getElementById('link_submit_btn');

        if (linkId) linkId.value = '0';
        if (linkName) linkName.value = '';
        if (linkUrl) linkUrl.value = '';
        if (linkDescription) linkDescription.value = '';
        if (linkLogo) linkLogo.value = '';
        if (linkSort) linkSort.value = '0';
        if (linkStatus) linkStatus.checked = true;
        if (submitBtn) submitBtn.textContent = '添加友链';
    }
})();
