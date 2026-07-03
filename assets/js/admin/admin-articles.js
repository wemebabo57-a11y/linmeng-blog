/**
 * 后台文章列表脚本
 */
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        var selectAll = document.getElementById('select-all');
        var selectAllHeader = document.getElementById('select-all-header');
        var checkboxes = document.querySelectorAll('.article-checkbox');

        function updateAllCheckboxes(checked) {
            checkboxes.forEach(function(cb) {
                cb.checked = checked;
            });
        }

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                updateAllCheckboxes(selectAll.checked);
                if (selectAllHeader) selectAllHeader.checked = selectAll.checked;
            });
        }

        if (selectAllHeader) {
            selectAllHeader.addEventListener('change', function() {
                updateAllCheckboxes(selectAllHeader.checked);
                if (selectAll) selectAll.checked = selectAllHeader.checked;
            });
        }

        var messages = {
            'publish': '确定批量发布选中的文章？',
            'draft': '确定批量设为草稿？',
            'top': '确定批量置顶选中的文章？',
            'untop': '确定批量取消置顶？',
            'delete': '确定批量删除选中的文章？此操作不可恢复！'
        };

        document.querySelectorAll('[data-batch-action]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var action = btn.getAttribute('data-batch-action');
                var checked = document.querySelectorAll('.article-checkbox:checked');
                if (checked.length === 0) {
                    alert('请选择要操作的文章');
                    return;
                }
                if (confirm(messages[action])) {
                    var actionInput = document.getElementById('batch-action');
                    var form = document.getElementById('batch-form');
                    if (actionInput && form) {
                        actionInput.value = action;
                        form.submit();
                    }
                }
            });
        });

        document.querySelectorAll('.form-delete-article').forEach(function(form) {
            var confirmBtn = form.querySelector('[data-confirm]');
            if (confirmBtn) {
                form.addEventListener('submit', function(e) {
                    if (!confirm(confirmBtn.getAttribute('data-confirm'))) {
                        e.preventDefault();
                    }
                });
            }
        });
    });
})();
