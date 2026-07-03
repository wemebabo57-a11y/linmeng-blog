/**
 * 文章页 AI 总结交互
 * 通过服务端代理请求总结，不暴露 API Key
 * 支持流式输出（SSE），逐字显示总结
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var generateBtn = document.getElementById('ai-generate-btn');
        var select = document.getElementById('ai-provider-select');
        var contentBox = document.getElementById('ai-summary-content');
        var loadingBox = document.getElementById('ai-summary-loading');
        var errorBox = document.getElementById('ai-summary-error');

        if (!generateBtn) return;

        // 浏览器流式读取能力检测：fetch + ReadableStream + TextDecoder
        var streamSupported = (typeof fetch !== 'undefined')
            && (typeof ReadableStream !== 'undefined')
            && (typeof TextDecoder !== 'undefined');

        function getCsrfToken() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        }

        function getCsrfName() {
            var meta = document.querySelector('meta[name="csrf-token-name"]');
            return meta ? meta.getAttribute('content') : 'lm_csrf_token';
        }

        function setVisible(el, visible) {
            if (el) el.style.display = visible ? 'block' : 'none';
        }

        function showError(msg) {
            setVisible(loadingBox, false);
            setVisible(contentBox, false);
            setVisible(errorBox, true);
            errorBox.textContent = msg;
            generateBtn.disabled = false;
        }

        function finishOk() {
            setVisible(loadingBox, false);
            generateBtn.disabled = false;
        }

        generateBtn.addEventListener('click', function() {
            var articleId = this.getAttribute('data-article-id');
            var providerId = select ? select.value : '0';
            var csrfName = getCsrfName();
            var token = getCsrfToken();

            if (!articleId) {
                showError('文章信息异常');
                return;
            }

            setVisible(contentBox, false);
            setVisible(errorBox, false);
            setVisible(loadingBox, true);
            contentBox.textContent = '';
            generateBtn.disabled = true;

            var bodyParts = [
                'article_id=' + encodeURIComponent(articleId),
                'provider_id=' + encodeURIComponent(providerId),
                encodeURIComponent(csrfName) + '=' + encodeURIComponent(token)
            ];
            // 默认走流式；不支持流式的旧浏览器回退普通 JSON
            if (streamSupported) {
                bodyParts.push('stream=1');
            }
            var body = bodyParts.join('&');

            if (!streamSupported) {
                // 旧浏览器：普通 JSON 一次性返回
                fetch('/api/ai-summary.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: body
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data && data.success) {
                        contentBox.textContent = data.summary || '';
                        setVisible(contentBox, true);
                        finishOk();
                    } else {
                        showError(data && data.message ? data.message : '生成失败，请稍后重试');
                    }
                })
                .catch(function() {
                    showError('网络错误，请稍后重试');
                });
                return;
            }

            // 流式读取
            fetch('/api/ai-summary.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body
            }).then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                // 失败时后端可能返回 JSON（错误）
                var ct = response.headers.get('Content-Type') || '';
                if (ct.indexOf('text/event-stream') === -1) {
                    // 非 SSE：当作 JSON 处理
                    return response.json().then(function(data) {
                        throw new Error(data && data.message ? data.message : '生成失败');
                    });
                }
                var reader = response.body.getReader();
                var decoder = new TextDecoder('utf-8');
                var buffer = '';
                var received = '';
                var firstChunk = true;

                function processBuffer() {
                    // 按 SSE 协议解析：以 \n\n 分隔事件
                    var idx;
                    while ((idx = buffer.indexOf('\n\n')) !== -1) {
                        var rawEvent = buffer.slice(0, idx);
                        buffer = buffer.slice(idx + 2);
                        var dataLines = [];
                        rawEvent.split(/\r\n|\n|\r/).forEach(function(line) {
                            if (line.indexOf('data:') === 0 || line.indexOf('data: ') === 0) {
                                dataLines.push(line.replace(/^data:\s?/, ''));
                            }
                        });
                        if (dataLines.length === 0) continue;
                        var payload;
                        try {
                            payload = JSON.parse(dataLines.join(''));
                        } catch (e) {
                            continue;
                        }
                        handleSsePayload(payload, function(delta) {
                            if (firstChunk) {
                                firstChunk = false;
                                setVisible(loadingBox, false);
                                setVisible(contentBox, true);
                            }
                            received += delta;
                            contentBox.textContent = received;
                        });
                        if (payload.done) {
                            finishOk();
                            return true;
                        }
                        if (payload.success === false) {
                            showError(payload.message || '生成失败');
                            return true;
                        }
                    }
                    return false;
                }

                function handleSsePayload(p, onDelta) {
                    if (p.delta) {
                        onDelta(p.delta);
                    } else if (p.success === false) {
                        showError(p.message || '生成失败');
                    }
                }

                function pump() {
                    return reader.read().then(function(res) {
                        if (res.done) {
                            // 流结束：处理剩余 buffer
                            if (buffer.trim() !== '') {
                                processBuffer();
                            }
                            if (firstChunk) {
                                // 整个流读完都没收到 delta
                                showError('生成失败，未收到内容');
                            }
                            return;
                        }
                        buffer += decoder.decode(res.value, { stream: true });
                        if (processBuffer()) {
                            // 已经结束或出错，停止读取
                            return;
                        }
                        return pump();
                    });
                }

                return pump();
            }).catch(function(err) {
                showError(err && err.message ? err.message : '网络错误，请稍后重试');
            });
        });
    });
})();
