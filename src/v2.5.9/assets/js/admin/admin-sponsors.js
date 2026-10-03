/**
 * 后台赞助商管理脚本
 */
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-edit-sponsor-id]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var sponsor = {
                    id: btn.getAttribute('data-edit-sponsor-id'),
                    name: btn.getAttribute('data-edit-sponsor-name'),
                    url: btn.getAttribute('data-edit-sponsor-url'),
                    detail: btn.getAttribute('data-edit-sponsor-detail'),
                    icon: btn.getAttribute('data-edit-sponsor-icon'),
                    sort_order: parseInt(btn.getAttribute('data-edit-sponsor-sort'), 10) || 0,
                    status: parseInt(btn.getAttribute('data-edit-sponsor-status'), 10)
                };
                editSponsor(sponsor);
            });
        });

        var resetBtn = document.getElementById('reset-sponsor-form');
        if (resetBtn) {
            resetBtn.addEventListener('click', resetSponsorForm);
        }

        document.querySelectorAll('a[data-confirm]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                if (!confirm(link.getAttribute('data-confirm'))) {
                    e.preventDefault();
                }
            });
        });
    });

    function editSponsor(sponsor) {
        var sponsorId = document.getElementById('sponsor_id');
        var sponsorName = document.getElementById('sponsor_name');
        var sponsorUrl = document.getElementById('sponsor_url');
        var sponsorDetail = document.getElementById('sponsor_detail');
        var sponsorIconUrl = document.getElementById('sponsor_icon_url');
        var sponsorSort = document.getElementById('sponsor_sort');
        var sponsorStatus = document.getElementById('sponsor_status');
        var sponsorFile = document.getElementById('sponsor_icon_file');
        var submitBtn = document.getElementById('sponsor_submit_btn');

        if (sponsorId) sponsorId.value = sponsor.id;
        if (sponsorName) sponsorName.value = sponsor.name || '';
        if (sponsorUrl) sponsorUrl.value = sponsor.url || '';
        if (sponsorDetail) sponsorDetail.value = sponsor.detail || '';
        if (sponsorIconUrl) sponsorIconUrl.value = sponsor.icon || '';
        if (sponsorSort) sponsorSort.value = sponsor.sort_order;
        if (sponsorStatus) sponsorStatus.checked = sponsor.status === 1;
        if (sponsorFile) sponsorFile.value = '';
        if (submitBtn) submitBtn.textContent = '保存修改';

        var firstCard = document.querySelector('.card');
        if (firstCard && firstCard.scrollIntoView) {
            firstCard.scrollIntoView({ behavior: 'smooth' });
        }
    }

    function resetSponsorForm() {
        var sponsorId = document.getElementById('sponsor_id');
        var sponsorName = document.getElementById('sponsor_name');
        var sponsorUrl = document.getElementById('sponsor_url');
        var sponsorDetail = document.getElementById('sponsor_detail');
        var sponsorIconUrl = document.getElementById('sponsor_icon_url');
        var sponsorSort = document.getElementById('sponsor_sort');
        var sponsorStatus = document.getElementById('sponsor_status');
        var sponsorFile = document.getElementById('sponsor_icon_file');
        var submitBtn = document.getElementById('sponsor_submit_btn');

        if (sponsorId) sponsorId.value = '0';
        if (sponsorName) sponsorName.value = '';
        if (sponsorUrl) sponsorUrl.value = '';
        if (sponsorDetail) sponsorDetail.value = '';
        if (sponsorIconUrl) sponsorIconUrl.value = '';
        if (sponsorSort) sponsorSort.value = '0';
        if (sponsorStatus) sponsorStatus.checked = true;
        if (sponsorFile) sponsorFile.value = '';
        if (submitBtn) submitBtn.textContent = '添加赞助商';
    }
})();
