/**
 * 后台评论管理脚本
 */
(function() {
    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function getCsrfName() {
        var meta = document.querySelector('meta[name="csrf-token-name"]');
        return meta ? meta.getAttribute('content') : 'lm_csrf_token';
    }

    document.addEventListener('DOMContentLoaded', function() {
        var selectAll = document.getElementById('select-all');
        var selectAllHeader = document.getElementById('select-all-header');
        var checkboxes = document.querySelectorAll('.comment-checkbox');

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(function(cb) {
                    cb.checked = selectAll.checked;
                });
                if (selectAllHeader) selectAllHeader.checked = selectAll.checked;
            });
        }

        if (selectAllHeader) {
            selectAllHeader.addEventListener('change', function() {
                checkboxes.forEach(function(cb) {
                    cb.checked = selectAllHeader.checked;
                });
                if (selectAll) selectAll.checked = selectAllHeader.checked;
            });
        }

        var batchMessages = {
            'approve': '确定批量通过选中的评论？',
            'reject': '确定批量拒绝选中的评论？',
            'delete': '确定批量删除选中的评论？此操作不可恢复！'
        };

        document.querySelectorAll('[data-batch-action]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var action = btn.getAttribute('data-batch-action');
                var checked = document.querySelectorAll('.comment-checkbox:checked');
                if (checked.length === 0) {
                    alert('请选择要操作的评论');
                    return;
                }
                if (confirm(batchMessages[action])) {
                    var actionInput = document.getElementById('batch-action');
                    var form = document.getElementById('batch-form');
                    if (actionInput && form) {
                        actionInput.value = action;
                        form.submit();
                    }
                }
            });
        });

        document.querySelectorAll('[data-reply-id]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var commentId = btn.getAttribute('data-reply-id');
                showReplyForm(commentId);
            });
        });

        document.querySelectorAll('[data-cancel-reply]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var commentId = btn.getAttribute('data-cancel-reply');
                hideReplyForm(commentId);
            });
        });

        document.querySelectorAll('[data-submit-reply]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var commentId = btn.getAttribute('data-submit-reply');
                submitReply(commentId);
            });
        });

        document.querySelectorAll('form').forEach(function(form) {
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

    function showReplyForm(commentId) {
        document.querySelectorAll('[id^="reply-form-"]').forEach(function(row) {
            row.style.display = 'none';
        });
        var row = document.getElementById('reply-form-' + commentId);
        var textarea = document.getElementById('reply-content-' + commentId);
        if (row) {
            row.style.display = 'table-row';
            if (textarea) textarea.focus();
        }
    }

    function hideReplyForm(commentId) {
        var row = document.getElementById('reply-form-' + commentId);
        if (row) row.style.display = 'none';
    }

    function submitReply(commentId) {
        var textarea = document.getElementById('reply-content-' + commentId);
        if (!textarea) return;
        var content = textarea.value.trim();
        if (!content) {
            alert('请输入回复内容');
            return;
        }

        var formData = new FormData();
        formData.append(getCsrfName(), getCsrfToken());
        formData.append('comment_id', commentId);
        formData.append('content', content);

        fetch('/api/comment-reply.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (typeof showMessage === 'function') {
                showMessage(data.message, data.success ? 'success' : 'error');
            }
            if (data.success) {
                setTimeout(function() { window.location.reload(); }, 800);
            }
        })
        .catch(function() {
            if (typeof showMessage === 'function') {
                showMessage('回复失败', 'error');
            }
        });
    }
})();
