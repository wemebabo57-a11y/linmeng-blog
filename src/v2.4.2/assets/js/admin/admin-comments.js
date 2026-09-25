/**
 * 后台评论管理脚本 v2.2
 *
 * v2.1 用 form.submit() + 动态填充 id 的方式做批量操作，在某些环境下会出现
 * “按钮错乱 / 一直提示请选择”的问题。v2.2 彻底放弃 form 提交，所有操作（单行、
 * 批量、清空待审核）统一走 fetch，逻辑线性、无 form、无嵌套，从根上消除冲突。
 *
 * 脚本位于 body 末尾加载，DOM 已就绪，直接执行绑定，不依赖 DOMContentLoaded。
 */
(function() {
    'use strict';

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function getCsrfName() {
        var meta = document.querySelector('meta[name="csrf-token-name"]');
        return meta ? meta.getAttribute('content') : 'lm_csrf_token';
    }

    // 收集选中评论的 id 数组
    function getSelectedIds() {
        var cbs = document.querySelectorAll('.comment-checkbox:checked');
        var ids = [];
        for (var i = 0; i < cbs.length; i++) {
            ids.push(cbs[i].value);
        }
        return ids;
    }

    // 统一 POST 提交：URLSearchParams 编码，后端 $_POST 正常读取。
    // 成功后 reload 看结果；失败时给出提示。
    function postAction(payload, doneMsg) {
        try {
            var body = new URLSearchParams();
            body.append(getCsrfName(), getCsrfToken());
            Object.keys(payload).forEach(function(key) {
                var val = payload[key];
                if (Object.prototype.toString.call(val) === '[object Array]') {
                    for (var i = 0; i < val.length; i++) {
                        body.append(key + '[]', val[i]);
                    }
                } else {
                    body.append(key, val);
                }
            });
            fetch(window.location.pathname + window.location.search, {
                method: 'POST',
                body: body,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function() {
                // 后端对 POST 不返回 JSON（处理完直接重渲染页面），按成功刷新。
                window.location.reload();
            }).catch(function() {
                if (typeof showMessage === 'function') {
                    showMessage('操作失败，请重试', 'error');
                } else {
                    alert('操作失败，请重试');
                }
            });
        } catch (e) {
            console.error('[admin-comments] postAction error:', e);
            alert('提交异常: ' + e.message);
        }
    }

    function bindAll() {
        // ========= 全选 =========
        var selectAll = document.getElementById('select-all');
        var selectAllHeader = document.getElementById('select-all-header');
        var checkboxes = document.querySelectorAll('.comment-checkbox');

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                for (var i = 0; i < checkboxes.length; i++) {
                    checkboxes[i].checked = selectAll.checked;
                }
                if (selectAllHeader) selectAllHeader.checked = selectAll.checked;
            });
        }
        if (selectAllHeader) {
            selectAllHeader.addEventListener('change', function() {
                for (var i = 0; i < checkboxes.length; i++) {
                    checkboxes[i].checked = selectAllHeader.checked;
                }
                if (selectAll) selectAll.checked = selectAllHeader.checked;
            });
        }

        // ========= 批量操作 =========
        var batchMessages = {
            'approve': '确定批量通过选中的评论？',
            'reject': '确定批量拒绝选中的评论？',
            'delete': '确定批量删除选中的评论？此操作不可恢复！'
        };
        var batchBtns = document.querySelectorAll('[data-batch-action]');
        for (var b = 0; b < batchBtns.length; b++) {
            (function(btn) {
                btn.addEventListener('click', function() {
                    var action = btn.getAttribute('data-batch-action');
                    var ids = getSelectedIds();
                    if (ids.length === 0) {
                        alert('请选择要操作的评论');
                        return;
                    }
                    if (!confirm(batchMessages[action] || '确定执行此操作？')) return;
                    postAction({ action: action, ids: ids });
                });
            })(batchBtns[b]);
        }

        // ========= 单行操作 =========
        var singleBtns = document.querySelectorAll('[data-single-action]');
        for (var s = 0; s < singleBtns.length; s++) {
            (function(btn) {
                btn.addEventListener('click', function() {
                    var action = btn.getAttribute('data-single-action');
                    var id = btn.getAttribute('data-comment-id');
                    var confirmMsg = btn.getAttribute('data-confirm');
                    if (confirmMsg && !confirm(confirmMsg)) return;
                    postAction({ action: action, ids: [id] });
                });
            })(singleBtns[s]);
        }

        // ========= 清空待审核 =========
        var clearBtn = document.getElementById('btn-clear-pending');
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                if (!confirm('确定清空所有待审核评论？此操作不可恢复！')) return;
                postAction({ action: 'delete_all_pending' });
            });
        }

        // ========= 回复表单 =========
        var replyBtns = document.querySelectorAll('[data-reply-id]');
        for (var r = 0; r < replyBtns.length; r++) {
            (function(btn) {
                btn.addEventListener('click', function() {
                    showReplyForm(btn.getAttribute('data-reply-id'));
                });
            })(replyBtns[r]);
        }

        var cancelBtns = document.querySelectorAll('[data-cancel-reply]');
        for (var c = 0; c < cancelBtns.length; c++) {
            (function(btn) {
                btn.addEventListener('click', function() {
                    hideReplyForm(btn.getAttribute('data-cancel-reply'));
                });
            })(cancelBtns[c]);
        }

        var submitReplyBtns = document.querySelectorAll('[data-submit-reply]');
        for (var sr = 0; sr < submitReplyBtns.length; sr++) {
            (function(btn) {
                btn.addEventListener('click', function() {
                    submitReply(btn.getAttribute('data-submit-reply'));
                });
            })(submitReplyBtns[sr]);
        }
    }

    function showReplyForm(commentId) {
        var rows = document.querySelectorAll('[id^="reply-form-"]');
        for (var i = 0; i < rows.length; i++) rows[i].style.display = 'none';
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
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
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
            } else {
                alert('回复失败');
            }
        });
    }

    // 脚本在 body 末尾加载，DOM 已就绪，直接绑定。
    // 用 try-catch 包住，任何一处失败都能在控制台看到，不会静默吞掉。
    try {
        bindAll();
    } catch (e) {
        console.error('[admin-comments] bindAll failed:', e);
    }
})();
