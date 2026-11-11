<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="/assets/admin/components.chunk.css?v={{$version}}">
    <link rel="stylesheet" href="/assets/admin/umi.css?v={{$version}}">
    <link rel="stylesheet" href="/assets/admin/custom.css?v={{$version}}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,minimum-scale=1,user-scalable=no">
    <title>{{$title}}</title>
    <!-- <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Nunito+Sans:300,400,400i,600,700"> -->
    <script nonce="{{ $cspNonce }}">window.routerBase = "/";</script>
    <script nonce="{{ $cspNonce }}">
        window.settings = {
            title: '{{$title}}',
            theme: {
                sidebar: '{{$theme_sidebar}}',
                header: '{{$theme_header}}',
                color: '{{$theme_color}}',
            },
            version: '{{$version}}',
            background_url: '{{$background_url}}',
            logo: '{{$logo}}',
            authenticated: true,
            secure_path: '{{$secure_path}}'
        }
    </script>
    <script nonce="{{ $cspNonce }}">
        (function () {
            var sp = String((window.settings && window.settings.secure_path) || '').replace(/^\/+|\/+$/g, '');
            if (!sp) { return; }
            var target = '/' + sp + '/login';
            var KEY = 'admin_login_guard_ts';

            function loopGuard() {
                var last = 0;
                try { last = parseInt(sessionStorage.getItem(KEY) || '0', 10); } catch (e) { return true; }
                if (Date.now() - last < 5000) { return true; }
                try { sessionStorage.setItem(KEY, String(Date.now())); } catch (e) {}
                return false;
            }

            function stillSignedIn() {
                try { return !!(localStorage.getItem('authorization') || localStorage.getItem('token')); } catch (e) { return false; }
            }

            function awayFromSpaLogin() {
                if (location.hash.indexOf('#/login') !== 0) { return; }
                if (stillSignedIn()) { return; }
                if (loopGuard()) { return; }
                try {
                    fetch('/api/v1/user/logout', { method: 'POST', credentials: 'include', keepalive: true });
                } catch (e) {}
                location.replace(target);
            }

            window.addEventListener('hashchange', awayFromSpaLogin);
            awayFromSpaLogin();
        })();
    </script>
</head>

<body>
<div id="root"><div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f3f5f9;color:#6b7280;font-size:14px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif">正在载入控制台…</div></div>
<script src="/assets/auth-shim.js?v={{$version}}-ws4"></script>
<script src="/assets/admin/vendors.async.js?v={{$version}}"></script>
<script src="/assets/admin/components.async.js?v={{$version}}"></script>
<script src="/assets/admin/umi.lg1.js?v={{$version}}"></script>
</body>

</html>
