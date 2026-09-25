/**
 * 后台用户管理脚本
 */
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-edit-user-id]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var user = {
                    id: btn.getAttribute('data-edit-user-id'),
                    username: btn.getAttribute('data-edit-user-username'),
                    email: btn.getAttribute('data-edit-user-email'),
                    nickname: btn.getAttribute('data-edit-user-nickname'),
                    role: btn.getAttribute('data-edit-user-role'),
                    status: parseInt(btn.getAttribute('data-edit-user-status'), 10)
                };
                editUser(user);
            });
        });

        var resetBtn = document.getElementById('reset-user-form');
        if (resetBtn) {
            resetBtn.addEventListener('click', resetForm);
        }

        document.querySelectorAll('a[data-confirm]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                if (!confirm(link.getAttribute('data-confirm'))) {
                    e.preventDefault();
                }
            });
        });
    });

    function editUser(user) {
        var userId = document.getElementById('user_id');
        var username = document.getElementById('username');
        var email = document.getElementById('email');
        var nickname = document.getElementById('nickname');
        var role = document.getElementById('role');
        var status = document.getElementById('status');
        var submitBtn = document.getElementById('submit-btn');

        if (userId) userId.value = user.id;
        if (username) username.value = user.username || '';
        if (email) email.value = user.email || '';
        if (nickname) nickname.value = user.nickname || '';
        if (role) role.value = user.role || 'user';
        if (status) status.checked = user.status === 1;
        if (submitBtn) submitBtn.textContent = '保存修改';

        var firstCard = document.querySelector('.card');
        if (firstCard && firstCard.scrollIntoView) {
            firstCard.scrollIntoView({ behavior: 'smooth' });
        }
    }

    function resetForm() {
        var userId = document.getElementById('user_id');
        var username = document.getElementById('username');
        var email = document.getElementById('email');
        var nickname = document.getElementById('nickname');
        var role = document.getElementById('role');
        var status = document.getElementById('status');
        var submitBtn = document.getElementById('submit-btn');

        if (userId) userId.value = '0';
        if (username) username.value = '';
        if (email) email.value = '';
        if (nickname) nickname.value = '';
        if (role) role.value = 'user';
        if (status) status.checked = true;
        if (submitBtn) submitBtn.textContent = '添加用户';
    }
})();
