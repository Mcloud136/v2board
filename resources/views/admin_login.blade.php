@php
    $bgRaw = trim((string)config('v2board.frontend_background_url'));
    $bgUrl = (preg_match('#^https://[^\s"\']{1,512}$#i', $bgRaw) === 1) ? $bgRaw : '';
@endphp
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>管理端登录 - {{ config('v2board.app_name', 'V2Board') }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f3f5f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
            color: #1f2937;
        }
@if ($bgUrl !== '')
        body {
            background-image: linear-gradient(rgba(15, 23, 42, 0.18), rgba(15, 23, 42, 0.18)), url('{{ $bgUrl }}');
            background-size: cover, cover;
            background-position: center, center;
            background-repeat: no-repeat, no-repeat;
            background-attachment: fixed, fixed;
        }
@endif
        .login-card {
            width: 100%;
            max-width: 400px;
            margin: 24px;
            padding: 32px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.08);
        }
        h1 { margin: 0 0 8px; font-size: 20px; font-weight: 600; }
        .hint { margin: 0 0 24px; font-size: 13px; color: #6b7280; }
        .error {
            margin-bottom: 16px;
            padding: 10px 12px;
            border-radius: 8px;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 13px;
            border: 1px solid #fecaca;
        }
        .status { margin: 12px 0 0; font-size: 13px; color: #2563eb; min-height: 18px; }
        .back { display: none; margin-bottom: 12px; font-size: 13px; color: #6b7280; }
        label { display: block; margin-bottom: 6px; font-size: 13px; color: #374151; }
        input {
            width: 100%;
            height: 42px;
            margin-bottom: 16px;
            padding: 0 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
        }
        input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12); }
        button {
            width: 100%;
            height: 44px;
            border: 0;
            border-radius: 8px;
            background: #2563eb;
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover { background: #1d4ed8; }
        button:disabled { background: #93a5c8; cursor: default; }
        #btn-next { display: none; background: #eef2f7; color: #1f2937; }
        #btn-next:hover { background: #e2e8f0; }
        html.js #btn-next { display: block; }
        html.js #step-2 { display: none; }
        html.js #step-2.shown { display: block; }
        html.js #step-1.hidden { display: none; }
        html.js .back { display: inline-block; }
    </style>
</head>
<body>
<div class="login-card">
    <h1>{{ config('v2board.app_name', 'V2Board') }} 管理端</h1>
    <p class="hint">仅限管理员与员工登录，登录后 30 分钟无操作需重新验证。</p>
    <div class="error" id="error"@if (!$error) style="display:none"@endif>{{ $error }}</div>
    <form id="login-form" method="post" action="{{ '/' . config('v2board.secure_path') . '/login' }}"
          data-admin-path="{{ '/' . config('v2board.secure_path') }}">
        <input type="hidden" name="csrf_token" value="{{ $csrfToken }}">
        <div id="step-1">
            <label for="email">邮箱</label>
            <input id="email" type="email" name="email" value="{{ $email }}" autocomplete="username" required autofocus>
            <label for="password">密码</label>
            <input id="password" type="password" name="password" autocomplete="current-password" required>
            <button id="btn-next" type="button">下一步</button>
        </div>
        <div id="step-2">
            <p class="hint" style="margin-top:4px">请输入身份验证器上的 6 位动态验证码。</p>
            <label for="totp_code">动态验证码</label>
            <input id="totp_code" type="text" name="totp_code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="6 位数字">
            <a class="back" id="btn-back" href="#">返回修改账号密码</a>
            <button id="btn-submit" type="submit">登录</button>
        </div>
    </form>
    <p class="status" id="status" role="status" aria-live="polite"></p>
</div>
<script nonce="{{ $cspNonce }}">
(function () {
    var html = document.documentElement;
    html.className = html.className ? html.className + ' js' : 'js';

    var form = document.getElementById('login-form');
    var step1 = document.getElementById('step-1');
    var step2 = document.getElementById('step-2');
    var btnNext = document.getElementById('btn-next');
    var btnBack = document.getElementById('btn-back');
    var btnSubmit = document.getElementById('btn-submit');
    var email = document.getElementById('email');
    var password = document.getElementById('password');
    var totp = document.getElementById('totp_code');
    var errBox = document.getElementById('error');
    var statusBar = document.getElementById('status');
    var tokenInput = form.querySelector('input[name="csrf_token"]');

    function showError(msg) {
        if (!errBox) return;
        errBox.textContent = msg || '';
        errBox.style.display = msg ? 'block' : 'none';
    }

    function goStep(n) {
        step1.className = n === 1 ? '' : 'hidden';
        step2.className = n === 2 ? 'shown' : '';
        try {
            if (n === 2) { totp.focus(); } else { email.focus(); }
        } catch (e) {}
    }

    function setBusy(busy, label) {
        btnSubmit.disabled = busy;
        btnNext.disabled = busy;
        btnSubmit.textContent = busy ? (label || '正在验证…') : '登录';
    }

    btnNext.addEventListener('click', function () {
        showError('');
        if (!email.value.trim() || password.value === '') {
            showError('请输入邮箱与密码');
            return;
        }
        goStep(2);
    });

    btnBack.addEventListener('click', function (e) {
        e.preventDefault();
        showError('');
        goStep(1);
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var code = (totp.value || '').trim();
        if (!/^\d{6}$/.test(code)) {
            showError('请输入 6 位动态验证码');
            goStep(2);
            return;
        }
        setBusy(true, '正在验证…');
        fetch(form.action, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: new URLSearchParams(new FormData(form))
        }).then(function (res) {
            return res.json().catch(function () { return null; }).then(function (data) {
                return { data: data };
            });
        }).then(function (r) {
            var data = r.data;
            if (!data || typeof data.code === 'undefined') {
                setBusy(false);
                showError('登录状态异常，请刷新页面后重试');
                return;
            }
            if (data.csrf_token) tokenInput.value = data.csrf_token;
            if (data.code === 0) {
                btnSubmit.textContent = '登录成功，正在进入后台…';
                statusBar.textContent = '正在载入控制台…';
                location.href = data.redirect || form.getAttribute('data-admin-path') || '/';
                return;
            }
            setBusy(false);
            showError(data.msg || '登录失败');
            goStep(data.step === 'totp' ? 2 : 1);
        }).catch(function () {
            setBusy(false);
            showError('网络异常，请重试');
        });
    });

    // 整页回退路径：服务端已回填错误且邮箱有值时，认为账号密码已提交过
    if ((errBox.textContent || '').trim() !== '' && email.value !== '') {
        goStep(2);
    }
})();
</script>
</body>
</html>
