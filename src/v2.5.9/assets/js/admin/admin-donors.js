/**
 * 后台捐赠者管理脚本（独立于赞助商）
 */
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-edit-donor-id]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                editDonor({
                    id: btn.getAttribute('data-edit-donor-id'),
                    name: btn.getAttribute('data-edit-donor-name'),
                    url: btn.getAttribute('data-edit-donor-url'),
                    amount: btn.getAttribute('data-edit-donor-amount') || '',
                    sort_order: parseInt(btn.getAttribute('data-edit-donor-sort'), 10) || 0,
                    status: parseInt(btn.getAttribute('data-edit-donor-status'), 10)
                });
            });
        });
        var resetBtn = document.getElementById('reset-donor-form');
        if (resetBtn) resetBtn.addEventListener('click', resetDonorForm);
    });
    function editDonor(donor) {
        var id = document.getElementById('donor_id');
        var name = document.getElementById('donor_name');
        var url = document.getElementById('donor_url');
        var amount = document.getElementById('donor_amount');
        var sort = document.getElementById('donor_sort');
        var status = document.getElementById('donor_status');
        var submitBtn = document.getElementById('donor_submit_btn');
        if (id) id.value = donor.id;
        if (name) name.value = donor.name || '';
        if (url) url.value = donor.url || '';
        if (amount) amount.value = donor.amount || '';
        if (sort) sort.value = donor.sort_order;
        if (status) status.checked = donor.status === 1;
        if (submitBtn) submitBtn.textContent = '保存修改';
        var firstCard = document.querySelector('.card');
        if (firstCard && firstCard.scrollIntoView) firstCard.scrollIntoView({ behavior: 'smooth' });
    }
    function resetDonorForm() {
        var id = document.getElementById('donor_id');
        var name = document.getElementById('donor_name');
        var url = document.getElementById('donor_url');
        var amount = document.getElementById('donor_amount');
        var sort = document.getElementById('donor_sort');
        var status = document.getElementById('donor_status');
        var submitBtn = document.getElementById('donor_submit_btn');
        if (id) id.value = '0';
        if (name) name.value = '';
        if (url) url.value = '';
        if (amount) amount.value = '';
        if (sort) sort.value = '0';
        if (status) status.checked = true;
        if (submitBtn) submitBtn.textContent = '添加捐赠者';
    }
})();
