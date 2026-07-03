/**
 * 服务状态管理 - 后台脚本
 * 编辑回填 + 探测测试按钮
 */
(function () {
    'use strict';

    // ===== 编辑回填 =====
    var editBtns = document.querySelectorAll('[data-edit-id]');
    var nameInput = document.getElementById('service_name');
    var hostInput = document.getElementById('service_host');
    var typeSelect = document.getElementById('service_type');
    var portInput = document.getElementById('service_port');
    var pathInput = document.getElementById('service_path');
    var sortInput = document.getElementById('service_sort');
    var enabledCheckbox = document.getElementById('service_enabled');
    var idInput = document.getElementById('service_id');
    var submitBtn = document.getElementById('service_submit_btn');
    var pathGroup = document.getElementById('path-group');

    function togglePathGroup() {
        if (typeSelect && pathGroup) {
            pathGroup.style.display = typeSelect.value === 'tcp' ? 'none' : '';
        }
    }
    if (typeSelect) {
        typeSelect.addEventListener('change', togglePathGroup);
        togglePathGroup();
    }

    editBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            idInput.value = this.dataset.editId;
            nameInput.value = this.dataset.editName;
            hostInput.value = this.dataset.editHost;
            typeSelect.value = this.dataset.editType;
            portInput.value = this.dataset.editPort;
            pathInput.value = this.dataset.editPath;
            sortInput.value = this.dataset.editSort;
            enabledCheckbox.checked = this.dataset.editEnabled === '1';
            submitBtn.textContent = '保存修改';
            togglePathGroup();
            nameInput.focus();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    // 重置表单
    var resetBtn = document.getElementById('reset-service-form');
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            idInput.value = '0';
            nameInput.value = '';
            hostInput.value = '';
            typeSelect.value = 'http';
            portInput.value = '80';
            pathInput.value = '/';
            sortInput.value = '0';
            enabledCheckbox.checked = true;
            submitBtn.textContent = '添加服务';
            togglePathGroup();
        });
    }

    // ===== 探测测试 =====
    var testBtns = document.querySelectorAll('.service-test-btn');
    testBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = this.dataset.id;
            var originalText = this.textContent;
            this.disabled = true;
            this.textContent = '探测中...';

            var self = this;
            fetch('/api/service-probe.php?id=' + encodeURIComponent(id), { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.success) {
                        var status = data.online ? '在线' : '离线';
                        var latency = data.latency + 'ms';
                        self.textContent = status + ' · ' + latency;
                        self.className = 'btn btn-sm ' + (data.online ? 'btn-success' : 'btn-danger');
                        var row = self.closest('tr');
                        if (row) {
                            var badge = row.querySelector('.badge');
                            if (badge) {
                                badge.className = 'badge ' + (data.online ? 'badge-success' : 'badge-danger');
                                badge.textContent = data.online ? '在线' : '离线';
                            }
                        }
                    } else {
                        self.textContent = '失败';
                        self.className = 'btn btn-sm btn-danger';
                    }
                })
                .catch(function () {
                    self.textContent = '请求失败';
                    self.className = 'btn btn-sm btn-danger';
                })
                .finally(function () {
                    setTimeout(function () {
                        self.disabled = false;
                        self.textContent = originalText;
                        self.className = 'btn btn-sm btn-secondary';
                    }, 3000);
                });
        });
    });
})();
