(function () {
    var root = document.getElementById('geetest-register-gate');
    var button = document.getElementById('geetest-gate-btn');
    var message = document.getElementById('geetest-gate-message');
    var captchaObj = null;

    function setMessage(text, isError) {
        if (!message) return;
        message.textContent = text;
        message.style.color = isError ? 'var(--danger-color)' : 'var(--text-light)';
    }

    if (!root || !button) return;

    if (!window.initGeetest4) {
        setMessage('极验 SDK 未加载。请检查浏览器是否拦截 static.geetest.com，或刷新页面重试', true);
        return;
    }

    function formatGeetestError(err) {
        if (!err) return '极验验证加载失败，请检查验证 ID 是否正确';
        if (typeof err === 'string') return err;
        if (err.msg) return err.msg;
        if (err.message) return err.message;
        if (err.error_code) return '极验验证加载失败：' + err.error_code;
        return '极验验证加载失败，请检查验证 ID 是否正确';
    }

    window.initGeetest4({
        captchaId: root.dataset.captchaId || '',
        product: 'bind',
        language: 'zho'
    }, function (obj) {
        captchaObj = obj;
        captchaObj.onReady(function () {
            setMessage('点击按钮开始验证');
        }).onSuccess(function () {
            var result = captchaObj.getValidate();
            if (!result || !result.lot_number || !result.captcha_output || !result.pass_token || !result.gen_time) {
                setMessage('请先完成极验验证', true);
                button.disabled = false;
                return;
            }

            var body = new URLSearchParams();
            body.set('geetest_action', 'verify_register_gate');
            body.set(root.dataset.csrfName || '', root.dataset.csrf || '');
            body.set('lot_number', result.lot_number);
            body.set('captcha_output', result.captcha_output);
            body.set('pass_token', result.pass_token);
            body.set('gen_time', result.gen_time);

            fetch('/register.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: body.toString(),
                credentials: 'same-origin'
            }).then(function (resp) {
                return resp.json();
            }).then(function (data) {
                if (!data || !data.success) {
                    throw new Error(data && data.message ? data.message : '人机验证失败');
                }
                setMessage('验证通过，正在进入申请页面...');
                // 跳转到带一次性 gate token 的 URL，token 绑定 session
                // 复制此 URL 给他人会因 session 不同而重新要求验证
                window.location.href = data.redirect || '/register.php';
            }).catch(function (err) {
                button.disabled = false;
                setMessage(err.message || '人机验证失败，请重试', true);
            });
        }).onError(function (err) {
            button.disabled = false;
            setMessage(formatGeetestError(err), true);
        }).onClose(function () {
            button.disabled = false;
            setMessage('验证已取消，请重新开始');
        });
    });

    button.addEventListener('click', function () {
        if (!captchaObj) {
            setMessage('验证组件尚未初始化完成', true);
            return;
        }
        button.disabled = true;
        setMessage('正在打开验证...');
        captchaObj.showCaptcha();
    });
})();
