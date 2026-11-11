<?php

namespace App\Http\Middleware;

use App\Services\AuthService;
use Closure;

class AuthenticatesRole
{
    public function handle($request, Closure $next, $role = null)
    {
        $sessionId = config('v2board.web_session_enabled', false) ? $request->cookie(config('v2board.web_session_cookie', 'v2board_sid')) : null;
        if ($sessionId && !in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $origin = rtrim((string)$request->headers->get('Origin'), '/');
            $allowed = (array)config('v2board.web_session_origins', []);
            if ($origin && !in_array($origin, $allowed, true)) {
                abort(403, 'Invalid origin');
            }
        }
        $adminRole = in_array($role, ['admin', 'staff'], true);
        $user = $sessionId ? AuthService::resolveWebSession($sessionId) : false;
        if ($adminRole) {
            // 管理端只接受由管理端入口（密码 + TOTP）签发的 admin 作用域会话，且不再回退到 auth_data
            if (!$user || ($user['web_scope'] ?? 'user') !== 'admin') {
                abort(403, '未登录或登陆已过期');
            }
        } elseif (!$user) {
            $authorization = $request->input('auth_data') ?? $request->header('authorization');
            if (!$authorization) abort(403, '未登录或登陆已过期');
            $user = AuthService::decryptAuthData($authorization);
        }
        if (!$user) abort(403, '未登录或登陆已过期');
        unset($user['web_scope']);

        if (!empty($user['banned'])) {
            abort(403, '账号已被封禁');
        }

        if ($role === 'admin' && !$user['is_admin']) {
            abort(403, '未登录或登陆已过期');
        }
        if ($role === 'staff' && !$user['is_staff']) {
            abort(403, '未登录或登陆已过期');
        }

        $request->attributes->set('auth_user', $user);
        $request->merge([
            'user' => $user
        ]);
        return $next($request);
    }
}
