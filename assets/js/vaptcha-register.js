(function () {
    var root = document.getElementById('vaptcha-register-gate');
    var button = document.getElementById('vaptcha-gate-btn');
    var message = document.getElementById('vaptcha-gate-message');
    var vaptchaObj = null;

    function setMessage(text, isError) {
        if (!message) return;
        message.textContent = text;
        message.style.color = isError ? 'var(--danger-color)' : 'var(--text-light)';
    }

    if (!root || !button) return;

    if (!window.vaptcha) {
        setMessage('VAPTCHA SDK 加载失败，请刷新页面重试', true);
        return;
    }

    window.vaptcha({
        vid: root.dataset.vid || '',
        container: '#vaptcha-container',
        lang: 'zh-CN'
    }).then(function (obj) {
        vaptchaObj = obj;
        setMessage('点击按钮开始验证');
    }).catch(function () {
        setMessage('VAPTCHA 初始化失败，请检查后台 VID 配置', true);
    });

    button.addEventListener('click', function () {
        if (!vaptchaObj) {
            setMessage('验证组件尚未初始化完成', true);
            return;
        }

        button.disabled = true;
        setMessage('正在验证...');

        vaptchaObj.validate().then(function () {
            var result = vaptchaObj.getVerifyResult();
            if (!result || !result.token || !result.knock) {
                throw new Error('请先完成人机验证');
            }

            var body = new URLSearchParams();
            body.set('vaptcha_action', 'verify_register_gate');
            body.set(root.dataset.csrfName || '', root.dataset.csrf || '');
            body.set('vaptcha_token', result.token);
            body.set('vaptcha_knock', result.knock);
            body.set('vaptcha_dfu', result.dfu || '');

            return fetch('/register.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: body.toString(),
                credentials: 'same-origin'
            });
        }).then(function (resp) {
            return resp.json();
        }).then(function (data) {
            if (!data || !data.success) {
                throw new Error(data && data.message ? data.message : '人机验证失败');
            }
            setMessage('验证通过，正在进入申请页面...');
            window.location.reload();
        }).catch(function (err) {
            button.disabled = false;
            setMessage(err.message || '人机验证失败，请重试', true);
        });
    });
})();
